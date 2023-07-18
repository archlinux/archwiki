<?php

namespace MediaWiki\Rest\Handler;

use ChangeTags;
use IDBAccessObject;
use MediaWiki\Page\ExistingPageRecord;
use MediaWiki\Page\PageLookup;
use MediaWiki\Permissions\GroupPermissionsLookup;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Storage\NameTableAccessException;
use MediaWiki\Storage\NameTableStore;
use MediaWiki\Storage\NameTableStoreFactory;
use TitleFormatter;
use Wikimedia\Message\MessageValue;
use Wikimedia\Message\ParamType;
use Wikimedia\Message\ScalarParam;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Handler class for Core REST API endpoints that perform operations on revisions
 */
class PageHistoryHandler extends SimpleHandler {
	use PageRedirectHandlerTrait;

	private const REVISIONS_RETURN_LIMIT = 20;
	private const ALLOWED_FILTER_TYPES = [ 'anonymous', 'bot', 'reverted', 'minor' ];

	/** @var RevisionStore */
	private $revisionStore;

	/** @var NameTableStore */
	private $changeTagDefStore;

	/** @var GroupPermissionsLookup */
	private $groupPermissionsLookup;

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var PageLookup */
	private $pageLookup;

	/** @var TitleFormatter */
	private $titleFormatter;

	/**
	 * @var ExistingPageRecord|false|null
	 */
	private $page = false;

	/**
	 * RevisionStore $revisionStore
	 *
	 * @param RevisionStore $revisionStore
	 * @param NameTableStoreFactory $nameTableStoreFactory
	 * @param GroupPermissionsLookup $groupPermissionsLookup
	 * @param ILoadBalancer $loadBalancer
	 * @param PageLookup $pageLookup
	 * @param TitleFormatter $titleFormatter
	 */
	public function __construct(
		RevisionStore $revisionStore,
		NameTableStoreFactory $nameTableStoreFactory,
		GroupPermissionsLookup $groupPermissionsLookup,
		ILoadBalancer $loadBalancer,
		PageLookup $pageLookup,
		TitleFormatter $titleFormatter
	) {
		$this->revisionStore = $revisionStore;
		$this->changeTagDefStore = $nameTableStoreFactory->getChangeTagDef();
		$this->groupPermissionsLookup = $groupPermissionsLookup;
		$this->loadBalancer = $loadBalancer;
		$this->pageLookup = $pageLookup;
		$this->titleFormatter = $titleFormatter;
	}

	/**
	 * @return ExistingPageRecord|null
	 */
	private function getPage(): ?ExistingPageRecord {
		if ( $this->page === false ) {
			$this->page = $this->pageLookup->getExistingPageByText(
					$this->getValidatedParams()['title']
				);
		}
		return $this->page;
	}

	/**
	 * At most one of older_than and newer_than may be specified. Keep in mind that revision ids
	 * are not monotonically increasing, so a revision may be older than another but have a
	 * higher revision id.
	 *
	 * @param string $title
	 * @return Response
	 * @throws LocalizedHttpException
	 */
	public function run( $title ) {
		$params = $this->getValidatedParams();
		if ( $params['older_than'] !== null && $params['newer_than'] !== null ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-pagehistory-incompatible-params' ), 400 );
		}

		if ( ( $params['older_than'] !== null && $params['older_than'] < 1 ) ||
			( $params['newer_than'] !== null && $params['newer_than'] < 1 )
		) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-pagehistory-param-range-error' ), 400 );
		}

		$tagIds = [];
		if ( $params['filter'] === 'reverted' ) {
			foreach ( ChangeTags::REVERT_TAGS as $tagName ) {
				try {
					$tagIds[] = $this->changeTagDefStore->getId( $tagName );
				} catch ( NameTableAccessException $exception ) {
					// If no revisions are tagged with a name, no tag id will be present
				}
			}
		}

		$page = $this->getPage();

		if ( !$page ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-title',
					[ new ScalarParam( ParamType::PLAINTEXT, $title ) ]
				),
				404
			);
		}
		if ( !$this->getAuthority()->authorizeRead( 'read', $page ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-permission-denied-title',
					[ new ScalarParam( ParamType::PLAINTEXT, $title ) ] ),
				403
			);
		}

		'@phan-var \MediaWiki\Page\ExistingPageRecord $page';
		$redirectResponse = $this->createNormalizationRedirectResponseIfNeeded(
			$page,
			$params['title'] ?? null,
			$this->titleFormatter
		);

		if ( $redirectResponse !== null ) {
			return $redirectResponse;
		}

		$relativeRevId = $params['older_than'] ?? $params['newer_than'] ?? 0;
		if ( $relativeRevId ) {
			// Confirm the relative revision exists for this page. If so, get its timestamp.
			$rev = $this->revisionStore->getRevisionByPageId(
				$page->getId(),
				$relativeRevId
			);
			if ( !$rev ) {
				throw new LocalizedHttpException(
					new MessageValue( 'rest-nonexistent-title-revision',
						[ $relativeRevId, new ScalarParam( ParamType::PLAINTEXT, $title ) ]
					),
					404
				);
			}
			$ts = $rev->getTimestamp();
			if ( $ts === null ) {
				throw new LocalizedHttpException(
					new MessageValue( 'rest-pagehistory-timestamp-error',
						[ $relativeRevId ]
					),
					500
				);
			}
		} else {
			$ts = 0;
		}

		$res = $this->getDbResults( $page, $params, $relativeRevId, $ts, $tagIds );
		$response = $this->processDbResults( $res, $page, $params );
		return $this->getResponseFactory()->createJson( $response );
	}

	/**
	 * @param ExistingPageRecord $page object identifying the page to load history for
	 * @param array $params request parameters
	 * @param int $relativeRevId relative revision id for paging, or zero if none
	 * @param int $ts timestamp for paging, or zero if none
	 * @param array $tagIds validated tags ids, or empty array if not needed for this query
	 * @return IResultWrapper|bool the results, or false if no query was executed
	 */
	private function getDbResults( ExistingPageRecord $page, array $params, $relativeRevId, $ts, $tagIds ) {
		$dbr = $this->loadBalancer->getConnectionRef( DB_REPLICA );
		$revQuery = $this->revisionStore->getQueryInfo();
		$cond = [
			'rev_page' => $page->getId()
		];

		if ( $params['filter'] ) {
			// The validator ensures this value, if present, is one of the expected values
			switch ( $params['filter'] ) {
				case 'bot':
					$cond[] = 'EXISTS(' . $dbr->selectSQLText(
							'user_groups',
							'1',
							[
								'actor_rev_user.actor_user = ug_user',
								'ug_group' => $this->groupPermissionsLookup->getGroupsWithPermission( 'bot' ),
								'ug_expiry IS NULL OR ug_expiry >= ' . $dbr->addQuotes( $dbr->timestamp() )
							],
							__METHOD__
						) . ')';
					$bitmask = $this->getBitmask();
					if ( $bitmask ) {
						$cond[] = $dbr->bitAnd( 'rev_deleted', $bitmask ) . " != $bitmask";
					}
					break;

				case 'anonymous':
					$cond[] = "actor_user IS NULL";
					$bitmask = $this->getBitmask();
					if ( $bitmask ) {
						$cond[] = $dbr->bitAnd( 'rev_deleted', $bitmask ) . " != $bitmask";
					}
					break;

				case 'reverted':
					if ( !$tagIds ) {
						return false;
					}
					$cond[] = 'EXISTS(' . $dbr->selectSQLText(
							'change_tag',
							'1',
							[ 'ct_rev_id = rev_id', 'ct_tag_id' => $tagIds ],
							__METHOD__
						) . ')';
					break;

				case 'minor':
					$cond[] = 'rev_minor_edit != 0';
					break;
			}
		}

		if ( $relativeRevId ) {
			$op = $params['older_than'] ? '<' : '>';
			$sort = $params['older_than'] ? 'DESC' : 'ASC';
			$cond[] = $dbr->buildComparison( $op, [
				'rev_timestamp' => $dbr->timestamp( $ts ),
				'rev_id' => $relativeRevId,
			] );
			$orderBy = "rev_timestamp $sort, rev_id $sort";
		} else {
			$orderBy = "rev_timestamp DESC, rev_id DESC";
		}

		// Select one more than the return limit, to learn if there are additional revisions.
		$limit = self::REVISIONS_RETURN_LIMIT + 1;

		$res = $dbr->select(
			$revQuery['tables'],
			$revQuery['fields'],
			$cond,
			__METHOD__,
			[
				'ORDER BY' => $orderBy,
				'LIMIT' => $limit,
			],
			$revQuery['joins']
		);

		return $res;
	}

	/**
	 * Helper function for rev_deleted/user rights query conditions
	 *
	 * @todo Factor out rev_deleted logic per T233222
	 *
	 * @return int
	 */
	private function getBitmask() {
		if ( !$this->getAuthority()->isAllowed( 'deletedhistory' ) ) {
			$bitmask = RevisionRecord::DELETED_USER;
		} elseif ( !$this->getAuthority()->isAllowedAny( 'suppressrevision', 'viewsuppressed' ) ) {
			$bitmask = RevisionRecord::DELETED_USER | RevisionRecord::DELETED_RESTRICTED;
		} else {
			$bitmask = 0;
		}
		return $bitmask;
	}

	/**
	 * @param IResultWrapper|bool $res database results, or false if no query was executed
	 * @param ExistingPageRecord $page object identifying the page to load history for
	 * @param array $params request parameters
	 * @return array response data
	 */
	private function processDbResults( $res, $page, $params ) {
		$revisions = [];

		if ( $res ) {
			$sizes = [];
			foreach ( $res as $row ) {
				$rev = $this->revisionStore->newRevisionFromRow(
					$row,
					IDBAccessObject::READ_NORMAL,
					$page
				);
				if ( !$revisions ) {
					$firstRevId = $row->rev_id;
				}
				$lastRevId = $row->rev_id;

				$revision = [
					'id' => $rev->getId(),
					'timestamp' => wfTimestamp( TS_ISO_8601, $rev->getTimestamp() ),
					'minor' => $rev->isMinor(),
					'size' => $rev->getSize()
				];

				// Remember revision sizes and parent ids for calculating deltas. If a revision's
				// parent id is unknown, we will be unable to supply the delta for that revision.
				$sizes[$rev->getId()] = $rev->getSize();
				$parentId = $rev->getParentId();
				if ( $parentId ) {
					$revision['parent_id'] = $parentId;
				}

				$comment = $rev->getComment( RevisionRecord::FOR_THIS_USER, $this->getAuthority() );
				$revision['comment'] = $comment ? $comment->text : null;

				$revUser = $rev->getUser( RevisionRecord::FOR_THIS_USER, $this->getAuthority() );
				if ( $revUser ) {
					$revision['user'] = [
						'id' => $revUser->isRegistered() ? $revUser->getId() : null,
						'name' => $revUser->getName()
					];
				} else {
					$revision['user'] = null;
				}

				$revisions[] = $revision;

				// Break manually at the return limit. We may have more results than we can return.
				if ( count( $revisions ) == self::REVISIONS_RETURN_LIMIT ) {
					break;
				}
			}

			// Request any parent sizes that we do not already know, then calculate deltas
			$unknownSizes = [];
			foreach ( $revisions as $revision ) {
				if ( isset( $revision['parent_id'] ) && !isset( $sizes[$revision['parent_id']] ) ) {
					$unknownSizes[] = $revision['parent_id'];
				}
			}
			if ( $unknownSizes ) {
				$sizes += $this->revisionStore->getRevisionSizes( $unknownSizes );
			}
			foreach ( $revisions as &$revision ) {
				$revision['delta'] = null;
				if ( isset( $revision['parent_id'] ) ) {
					if ( isset( $sizes[$revision['parent_id']] ) ) {
						$revision['delta'] = $revision['size'] - $sizes[$revision['parent_id']];
					}

					// We only remembered this for delta calculations. We do not want to return it.
					unset( $revision['parent_id'] );
				}
			}

			if ( $revisions && $params['newer_than'] ) {
				$revisions = array_reverse( $revisions );
				// @phan-suppress-next-next-line PhanPossiblyUndeclaredVariable
				// $lastRevId is declared because $res has one element
				$temp = $lastRevId;
				// @phan-suppress-next-next-line PhanPossiblyUndeclaredVariable
				// $firstRevId is declared because $res has one element
				$lastRevId = $firstRevId;
				$firstRevId = $temp;
			}
		}

		$response = [
			'revisions' => $revisions
		];

		// Omit newer/older if there are no additional corresponding revisions.
		// This facilitates clients doing "paging" style api operations.
		if ( $revisions ) {
			if ( $params['newer_than'] || $res->numRows() > self::REVISIONS_RETURN_LIMIT ) {
				// @phan-suppress-next-next-line PhanPossiblyUndeclaredVariable
				// $lastRevId is declared because $res has one element
				$older = $lastRevId;
			}
			if ( $params['older_than'] ||
				( $params['newer_than'] && $res->numRows() > self::REVISIONS_RETURN_LIMIT )
			) {
				// @phan-suppress-next-next-line PhanPossiblyUndeclaredVariable
				// $firstRevId is declared because $res has one element
				$newer = $firstRevId;
			}
		}

		$queryParts = [];

		if ( isset( $params['filter'] ) ) {
			$queryParts['filter'] = $params['filter'];
		}

		$pathParams = [ 'title' => $this->titleFormatter->getPrefixedDBkey( $page ) ];

		$response['latest'] = $this->getRouteUrl( $pathParams, $queryParts );

		if ( isset( $older ) ) {
			$response['older'] =
				$this->getRouteUrl( $pathParams, $queryParts + [ 'older_than' => $older ] );
		}
		if ( isset( $newer ) ) {
			$response['newer'] =
				$this->getRouteUrl( $pathParams, $queryParts + [ 'newer_than' => $newer ] );
		}

		return $response;
	}

	public function needsWriteAccess() {
		return false;
	}

	public function getParamSettings() {
		return [
			'title' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'older_than' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'newer_than' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'filter' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => self::ALLOWED_FILTER_TYPES,
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}

	/**
	 * Returns an ETag representing a page's latest revision.
	 *
	 * @return string|null
	 */
	protected function getETag(): ?string {
		$page = $this->getPage();
		if ( !$page ) {
			return null;
		}

		return '"' . $page->getLatest() . '"';
	}

	/**
	 * Returns the time of the last change to the page.
	 *
	 * @return string|null
	 */
	protected function getLastModified(): ?string {
		$page = $this->getPage();
		if ( !$page ) {
			return null;
		}

		$rev = $this->revisionStore->getKnownCurrentRevision( $page );
		return $rev->getTimestamp();
	}

	/**
	 * @return bool
	 */
	protected function hasRepresentation() {
		return (bool)$this->getPage();
	}
}
