<?php
/**
 * Query the list of contributors to a page
 *
 * Copyright © 2013 Wikimedia Foundation and contributors
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
 * @since 1.23
 */

use MediaWiki\Permissions\GroupPermissionsLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\User\ActorMigration;
use MediaWiki\User\UserGroupManager;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

/**
 * A query module to show contributors to a page
 *
 * @ingroup API
 * @since 1.23
 */
class ApiQueryContributors extends ApiQueryBase {
	/** We don't want to process too many pages at once (it hits cold
	 * database pages too heavily), so only do the first MAX_PAGES input pages
	 * in each API call (leaving the rest for continuation).
	 */
	private const MAX_PAGES = 100;

	/** @var RevisionStore */
	private $revisionStore;

	/** @var ActorMigration */
	private $actorMigration;

	/** @var UserGroupManager */
	private $userGroupManager;

	/** @var GroupPermissionsLookup */
	private $groupPermissionsLookup;

	/**
	 * @param ApiQuery $query
	 * @param string $moduleName
	 * @param RevisionStore $revisionStore
	 * @param ActorMigration $actorMigration
	 * @param UserGroupManager $userGroupManager
	 * @param GroupPermissionsLookup $groupPermissionsLookup
	 */
	public function __construct(
		ApiQuery $query,
		$moduleName,
		RevisionStore $revisionStore,
		ActorMigration $actorMigration,
		UserGroupManager $userGroupManager,
		GroupPermissionsLookup $groupPermissionsLookup
	) {
		// "pc" is short for "page contributors", "co" was already taken by the
		// GeoData extension's prop=coordinates.
		parent::__construct( $query, $moduleName, 'pc' );
		$this->revisionStore = $revisionStore;
		$this->actorMigration = $actorMigration;
		$this->userGroupManager = $userGroupManager;
		$this->groupPermissionsLookup = $groupPermissionsLookup;
	}

	public function execute() {
		$db = $this->getDB();
		$params = $this->extractRequestParams();
		$this->requireMaxOneParameter( $params, 'group', 'excludegroup', 'rights', 'excluderights' );

		// Only operate on existing pages
		$pages = array_keys( $this->getPageSet()->getGoodPages() );

		// Filter out already-processed pages
		if ( $params['continue'] !== null ) {
			$cont = $this->parseContinueParamOrDie( $params['continue'], [ 'int', 'int' ] );
			$cont_page = (int)$cont[0];
			$pages = array_filter( $pages, static function ( $v ) use ( $cont_page ) {
				return $v >= $cont_page;
			} );
		}
		if ( $pages === [] ) {
			// Nothing to do
			return;
		}

		// Apply MAX_PAGES, leaving any over the limit for a continue.
		sort( $pages );
		$continuePages = null;
		if ( count( $pages ) > self::MAX_PAGES ) {
			$continuePages = $pages[self::MAX_PAGES] . '|0';
			$pages = array_slice( $pages, 0, self::MAX_PAGES );
		}

		$result = $this->getResult();
		$revQuery = $this->revisionStore->getQueryInfo();
		$pageField = 'rev_page';
		$idField = 'rev_actor';
		$countField = 'rev_actor';

		// First, count anons
		$this->addTables( $revQuery['tables'] );
		$this->addJoinConds( $revQuery['joins'] );
		$this->addFields( [
			'page' => $pageField,
			'anons' => "COUNT(DISTINCT $countField)",
		] );
		$this->addWhereFld( $pageField, $pages );
		$this->addWhere( $this->actorMigration->isAnon( $revQuery['fields']['rev_user'] ) );
		$this->addWhere( $db->bitAnd( 'rev_deleted', RevisionRecord::DELETED_USER ) . ' = 0' );
		$this->addOption( 'GROUP BY', $pageField );
		$res = $this->select( __METHOD__ );
		foreach ( $res as $row ) {
			$fit = $result->addValue( [ 'query', 'pages', $row->page ],
				'anoncontributors', (int)$row->anons
			);
			if ( !$fit ) {
				// This not fitting isn't reasonable, so it probably means that
				// some other module used up all the space. Just set a dummy
				// continue and hope it works next time.
				$this->setContinueEnumParameter( 'continue',
					$params['continue'] ?? '0|0'
				);

				return;
			}
		}

		// Next, add logged-in users
		$this->resetQueryParams();
		$this->addTables( $revQuery['tables'] );
		$this->addJoinConds( $revQuery['joins'] );
		$this->addFields( [
			'page' => $pageField,
			'id' => $idField,
			// Non-MySQL databases don't like partial group-by
			'userid' => 'MAX(' . $revQuery['fields']['rev_user'] . ')',
			'username' => 'MAX(' . $revQuery['fields']['rev_user_text'] . ')',
		] );
		$this->addWhereFld( $pageField, $pages );
		$this->addWhere( $this->actorMigration->isNotAnon( $revQuery['fields']['rev_user'] ) );
		$this->addWhere( $db->bitAnd( 'rev_deleted', RevisionRecord::DELETED_USER ) . ' = 0' );
		$this->addOption( 'GROUP BY', [ $pageField, $idField ] );
		$this->addOption( 'LIMIT', $params['limit'] + 1 );

		// Force a sort order to ensure that properties are grouped by page
		// But only if rev_page is not constant in the WHERE clause.
		if ( count( $pages ) > 1 ) {
			$this->addOption( 'ORDER BY', [ 'page', 'id' ] );
		} else {
			$this->addOption( 'ORDER BY', 'id' );
		}

		$limitGroups = [];
		if ( $params['group'] ) {
			$excludeGroups = false;
			$limitGroups = $params['group'];
		} elseif ( $params['excludegroup'] ) {
			$excludeGroups = true;
			$limitGroups = $params['excludegroup'];
		} elseif ( $params['rights'] ) {
			$excludeGroups = false;
			foreach ( $params['rights'] as $r ) {
				$limitGroups = array_merge( $limitGroups,
					$this->groupPermissionsLookup->getGroupsWithPermission( $r ) );
			}

			// If no group has the rights requested, no need to query
			if ( !$limitGroups ) {
				if ( $continuePages !== null ) {
					// But we still need to continue for the next page's worth
					// of anoncontributors
					$this->setContinueEnumParameter( 'continue', $continuePages );
				}

				return;
			}
		} elseif ( $params['excluderights'] ) {
			$excludeGroups = true;
			foreach ( $params['excluderights'] as $r ) {
				$limitGroups = array_merge( $limitGroups,
					$this->groupPermissionsLookup->getGroupsWithPermission( $r ) );
			}
		}

		if ( $limitGroups ) {
			$limitGroups = array_unique( $limitGroups );
			$this->addTables( 'user_groups' );
			$this->addJoinConds( [ 'user_groups' => [
				// @phan-suppress-next-line PhanPossiblyUndeclaredVariable excludeGroups declared when limitGroups set
				$excludeGroups ? 'LEFT JOIN' : 'JOIN',
				[
					'ug_user=' . $revQuery['fields']['rev_user'],
					'ug_group' => $limitGroups,
					'ug_expiry IS NULL OR ug_expiry >= ' . $db->addQuotes( $db->timestamp() )
				]
			] ] );
			// @phan-suppress-next-next-line PhanTypeMismatchArgumentNullable,PhanPossiblyUndeclaredVariable
			// excludeGroups declared when limitGroups set
			$this->addWhereIf( 'ug_user IS NULL', $excludeGroups );
		}

		if ( $params['continue'] !== null ) {
			$cont = $this->parseContinueParamOrDie( $params['continue'], [ 'int', 'int' ] );
			$this->addWhere( $db->buildComparison( '>=', [
				$pageField => $cont[0],
				$idField => $cont[1],
			] ) );
		}

		$res = $this->select( __METHOD__ );
		$count = 0;
		foreach ( $res as $row ) {
			if ( ++$count > $params['limit'] ) {
				// We've reached the one extra which shows that
				// there are additional pages to be had. Stop here...
				$this->setContinueEnumParameter( 'continue', $row->page . '|' . $row->id );
				return;
			}

			$fit = $this->addPageSubItem( $row->page,
				[ 'userid' => (int)$row->userid, 'name' => $row->username ],
				'user'
			);
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'continue', $row->page . '|' . $row->id );
				return;
			}
		}

		if ( $continuePages !== null ) {
			$this->setContinueEnumParameter( 'continue', $continuePages );
		}
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function getAllowedParams( $flags = 0 ) {
		$userGroups = $this->userGroupManager->listAllGroups();
		$userRights = $this->getPermissionManager()->getAllPermissions();

		if ( $flags & ApiBase::GET_VALUES_FOR_HELP ) {
			sort( $userGroups );
		}

		return [
			'group' => [
				ParamValidator::PARAM_TYPE => $userGroups,
				ParamValidator::PARAM_ISMULTI => true,
			],
			'excludegroup' => [
				ParamValidator::PARAM_TYPE => $userGroups,
				ParamValidator::PARAM_ISMULTI => true,
			],
			'rights' => [
				ParamValidator::PARAM_TYPE => $userRights,
				ParamValidator::PARAM_ISMULTI => true,
			],
			'excluderights' => [
				ParamValidator::PARAM_TYPE => $userRights,
				ParamValidator::PARAM_ISMULTI => true,
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	protected function getExamplesMessages() {
		return [
			'action=query&prop=contributors&titles=Main_Page'
				=> 'apihelp-query+contributors-example-simple',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Contributors';
	}
}
