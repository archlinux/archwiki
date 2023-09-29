<?php
/**
 * Copyright © 2015 Wikimedia Foundation and contributors
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Content\Renderer\ContentRenderer;
use MediaWiki\Content\Transform\ContentTransformer;
use MediaWiki\MainConfigNames;
use MediaWiki\ParamValidator\TypeDef\UserDef;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\Title\Title;
use MediaWiki\User\ActorMigration;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Query module to enumerate all revisions.
 *
 * @ingroup API
 * @since 1.27
 */
class ApiQueryAllRevisions extends ApiQueryRevisionsBase {

	/** @var RevisionStore */
	private $revisionStore;

	/** @var ActorMigration */
	private $actorMigration;

	/** @var NamespaceInfo */
	private $namespaceInfo;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param RevisionStore $revisionStore
	 * @param IContentHandlerFactory $contentHandlerFactory
	 * @param ParserFactory $parserFactory
	 * @param SlotRoleRegistry $slotRoleRegistry
	 * @param ActorMigration $actorMigration
	 * @param NamespaceInfo $namespaceInfo
	 * @param ContentRenderer $contentRenderer
	 * @param ContentTransformer $contentTransformer
	 * @param CommentFormatter $commentFormatter
	 */
	public function __construct(
		ApiQuery $query,
		$moduleName,
		RevisionStore $revisionStore,
		IContentHandlerFactory $contentHandlerFactory,
		ParserFactory $parserFactory,
		SlotRoleRegistry $slotRoleRegistry,
		ActorMigration $actorMigration,
		NamespaceInfo $namespaceInfo,
		ContentRenderer $contentRenderer,
		ContentTransformer $contentTransformer,
		CommentFormatter $commentFormatter
	) {
		parent::__construct(
			$query,
			$moduleName,
			'arv',
			$revisionStore,
			$contentHandlerFactory,
			$parserFactory,
			$slotRoleRegistry,
			$contentRenderer,
			$contentTransformer,
			$commentFormatter
		);
		$this->revisionStore = $revisionStore;
		$this->actorMigration = $actorMigration;
		$this->namespaceInfo = $namespaceInfo;
	}

	/**
	 * @param ApiPageSet|null $resultPageSet
	 * @return void
	 */
	protected function run( ApiPageSet $resultPageSet = null ) {
		$db = $this->getDB();
		$params = $this->extractRequestParams( false );

		$result = $this->getResult();

		$this->requireMaxOneParameter( $params, 'user', 'excludeuser' );

		$tsField = 'rev_timestamp';
		$idField = 'rev_id';
		$pageField = 'rev_page';

		// Namespace check is likely to be desired, but can't be done
		// efficiently in SQL.
		$miser_ns = null;
		$needPageTable = false;
		if ( $params['namespace'] !== null ) {
			$params['namespace'] = array_unique( $params['namespace'] );
			sort( $params['namespace'] );
			if ( $params['namespace'] != $this->namespaceInfo->getValidNamespaces() ) {
				$needPageTable = true;
				if ( $this->getConfig()->get( MainConfigNames::MiserMode ) ) {
					$miser_ns = $params['namespace'];
				} else {
					$this->addWhere( [ 'page_namespace' => $params['namespace'] ] );
				}
			}
		}

		if ( $resultPageSet === null ) {
			$this->parseParameters( $params );
			$revQuery = $this->revisionStore->getQueryInfo( [ 'page' ] );
		} else {
			$this->limit = $this->getParameter( 'limit' ) ?: 10;
			$revQuery = [
				'tables' => [ 'revision' ],
				'fields' => [ 'rev_timestamp', 'rev_id' ],
				'joins' => [],
			];

			if ( $params['generatetitles'] ) {
				$revQuery['fields'][] = 'rev_page';
			}

			if ( $params['user'] !== null || $params['excludeuser'] !== null ) {
				$actorQuery = $this->actorMigration->getJoin( 'rev_user' );
				$revQuery['tables'] += $actorQuery['tables'];
				$revQuery['joins'] += $actorQuery['joins'];
			}

			if ( $needPageTable ) {
				$revQuery['tables'][] = 'page';
				$revQuery['joins']['page'] = [ 'JOIN', [ "$pageField = page_id" ] ];
				if ( (bool)$miser_ns ) {
					$revQuery['fields'][] = 'page_namespace';
				}
			}
		}

		$this->addTables( $revQuery['tables'] );
		$this->addFields( $revQuery['fields'] );
		$this->addJoinConds( $revQuery['joins'] );

		// Seems to be needed to avoid a planner bug (T113901)
		$this->addOption( 'STRAIGHT_JOIN' );

		$dir = $params['dir'];
		$this->addTimestampWhereRange( $tsField, $dir, $params['start'], $params['end'] );

		if ( $this->fld_tags ) {
			$this->addFields( [ 'ts_tags' => ChangeTags::makeTagSummarySubquery( 'revision' ) ] );
		}

		if ( $params['user'] !== null ) {
			$actorQuery = $this->actorMigration->getWhere( $db, 'rev_user', $params['user'] );
			$this->addWhere( $actorQuery['conds'] );
		} elseif ( $params['excludeuser'] !== null ) {
			$actorQuery = $this->actorMigration->getWhere( $db, 'rev_user', $params['excludeuser'] );
			$this->addWhere( 'NOT(' . $actorQuery['conds'] . ')' );
		}

		if ( $params['user'] !== null || $params['excludeuser'] !== null ) {
			// Paranoia: avoid brute force searches (T19342)
			if ( !$this->getAuthority()->isAllowed( 'deletedhistory' ) ) {
				$bitmask = RevisionRecord::DELETED_USER;
			} elseif ( !$this->getAuthority()->isAllowedAny( 'suppressrevision', 'viewsuppressed' )
			) {
				$bitmask = RevisionRecord::DELETED_USER | RevisionRecord::DELETED_RESTRICTED;
			} else {
				$bitmask = 0;
			}
			if ( $bitmask ) {
				$this->addWhere( $db->bitAnd( 'rev_deleted', $bitmask ) . " != $bitmask" );
			}
		}

		if ( $params['continue'] !== null ) {
			$op = ( $dir == 'newer' ? '>=' : '<=' );
			$cont = $this->parseContinueParamOrDie( $params['continue'], [ 'timestamp', 'int' ] );
			$this->addWhere( $db->buildComparison( $op, [
				$tsField => $db->timestamp( $cont[0] ),
				$idField => $cont[1],
			] ) );
		}

		$this->addOption( 'LIMIT', $this->limit + 1 );

		$sort = ( $dir == 'newer' ? '' : ' DESC' );
		$orderby = [];
		// Targeting index rev_timestamp, user_timestamp, usertext_timestamp, or actor_timestamp.
		// But 'user' is always constant for the latter three, so it doesn't matter here.
		$orderby[] = "rev_timestamp $sort";
		$orderby[] = "rev_id $sort";
		$this->addOption( 'ORDER BY', $orderby );

		$hookData = [];
		$res = $this->select( __METHOD__, [], $hookData );

		if ( $resultPageSet === null ) {
			$this->executeGenderCacheFromResultWrapper( $res, __METHOD__ );
		}

		$pageMap = []; // Maps rev_page to array index
		$count = 0;
		$nextIndex = 0;
		$generated = [];
		foreach ( $res as $row ) {
			if ( $count === 0 && $resultPageSet !== null ) {
				// Set the non-continue since the list of all revisions is
				// prone to having entries added at the start frequently.
				$this->getContinuationManager()->addGeneratorNonContinueParam(
					$this, 'continue', "$row->rev_timestamp|$row->rev_id"
				);
			}
			if ( ++$count > $this->limit ) {
				// We've had enough
				$this->setContinueEnumParameter( 'continue', "$row->rev_timestamp|$row->rev_id" );
				break;
			}

			// Miser mode namespace check
			if ( $miser_ns !== null && !in_array( $row->page_namespace, $miser_ns ) ) {
				continue;
			}

			if ( $resultPageSet !== null ) {
				if ( $params['generatetitles'] ) {
					$generated[$row->rev_page] = $row->rev_page;
				} else {
					$generated[] = $row->rev_id;
				}
			} else {
				$revision = $this->revisionStore->newRevisionFromRow( $row, 0, Title::newFromRow( $row ) );
				$rev = $this->extractRevisionInfo( $revision, $row );

				if ( !isset( $pageMap[$row->rev_page] ) ) {
					$index = $nextIndex++;
					$pageMap[$row->rev_page] = $index;
					$title = Title::newFromLinkTarget( $revision->getPageAsLinkTarget() );
					$a = [
						'pageid' => $title->getArticleID(),
						'revisions' => [ $rev ],
					];
					ApiResult::setIndexedTagName( $a['revisions'], 'rev' );
					ApiQueryBase::addTitleInfo( $a, $title );
					$fit = $this->processRow( $row, $a['revisions'][0], $hookData ) &&
						$result->addValue( [ 'query', $this->getModuleName() ], $index, $a );
				} else {
					$index = $pageMap[$row->rev_page];
					$fit = $this->processRow( $row, $rev, $hookData ) &&
						$result->addValue( [ 'query', $this->getModuleName(), $index, 'revisions' ], null, $rev );
				}
				if ( !$fit ) {
					$this->setContinueEnumParameter( 'continue', "$row->rev_timestamp|$row->rev_id" );
					break;
				}
			}
		}

		if ( $resultPageSet !== null ) {
			if ( $params['generatetitles'] ) {
				$resultPageSet->populateFromPageIDs( $generated );
			} else {
				$resultPageSet->populateFromRevisionIDs( $generated );
			}
		} else {
			$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'page' );
		}
	}

	public function getAllowedParams() {
		$ret = parent::getAllowedParams() + [
			'user' => [
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name', 'ip', 'id', 'interwiki' ],
				UserDef::PARAM_RETURN_OBJECT => true,
			],
			'namespace' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => 'namespace',
				ParamValidator::PARAM_DEFAULT => null,
			],
			'start' => [
				ParamValidator::PARAM_TYPE => 'timestamp',
			],
			'end' => [
				ParamValidator::PARAM_TYPE => 'timestamp',
			],
			'dir' => [
				ParamValidator::PARAM_TYPE => [
					'newer',
					'older'
				],
				ParamValidator::PARAM_DEFAULT => 'older',
				ApiBase::PARAM_HELP_MSG => 'api-help-param-direction',
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [
					'newer' => 'api-help-paramvalue-direction-newer',
					'older' => 'api-help-paramvalue-direction-older',
				],
			],
			'excludeuser' => [
				ParamValidator::PARAM_TYPE => 'user',
				UserDef::PARAM_ALLOWED_USER_TYPES => [ 'name', 'ip', 'id', 'interwiki' ],
				UserDef::PARAM_RETURN_OBJECT => true,
			],
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
			'generatetitles' => [
				ParamValidator::PARAM_DEFAULT => false,
			],
		];

		if ( $this->getConfig()->get( MainConfigNames::MiserMode ) ) {
			$ret['namespace'][ApiBase::PARAM_HELP_MSG_APPEND] = [
				'api-help-param-limited-in-miser-mode',
			];
		}

		return $ret;
	}

	protected function getExamplesMessages() {
		return [
			'action=query&list=allrevisions&arvuser=Example&arvlimit=50'
				=> 'apihelp-query+allrevisions-example-user',
			'action=query&list=allrevisions&arvdir=newer&arvlimit=50'
				=> 'apihelp-query+allrevisions-example-ns-any',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Allrevisions';
	}
}
