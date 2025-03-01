<?php
/**
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
 * @ingroup Pager
 */

namespace MediaWiki\Pager;

use MediaWiki\Block\HideUserUtils;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Html\FormOptions;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\Subquery;

/**
 * This class is used to get a list of active users. The ones with specials
 * rights (sysop, bureaucrat, developer) will have them displayed
 * next to their names.
 *
 * @ingroup Pager
 */
class ActiveUsersPager extends UsersPager {
	/**
	 * @var FormOptions
	 */
	protected $opts;

	/**
	 * @var string[]
	 */
	protected $groups;

	/**
	 * @var array
	 */
	private $blockStatusByUid;

	/** @var int */
	private $RCMaxAge;

	/** @var string[] */
	private $excludegroups;

	/**
	 * @param IContextSource $context
	 * @param HookContainer $hookContainer
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param IConnectionProvider $dbProvider
	 * @param UserGroupManager $userGroupManager
	 * @param UserIdentityLookup $userIdentityLookup
	 * @param HideUserUtils $hideUserUtils
	 * @param FormOptions $opts
	 */
	public function __construct(
		IContextSource $context,
		HookContainer $hookContainer,
		LinkBatchFactory $linkBatchFactory,
		IConnectionProvider $dbProvider,
		UserGroupManager $userGroupManager,
		UserIdentityLookup $userIdentityLookup,
		HideUserUtils $hideUserUtils,
		FormOptions $opts
	) {
		parent::__construct(
			$context,
			$hookContainer,
			$linkBatchFactory,
			$dbProvider,
			$userGroupManager,
			$userIdentityLookup,
			$hideUserUtils,
			null,
			null
		);

		$this->RCMaxAge = $this->getConfig()->get( MainConfigNames::ActiveUserDays );
		$this->requestedUser = '';

		$un = $opts->getValue( 'username' );
		if ( $un != '' ) {
			$username = Title::makeTitleSafe( NS_USER, $un );
			if ( $username !== null ) {
				$this->requestedUser = $username->getText();
			}
		}

		$this->groups = $opts->getValue( 'groups' );
		$this->excludegroups = $opts->getValue( 'excludegroups' );
		// Backwards-compatibility with old URLs
		if ( $opts->getValue( 'hidebots' ) ) {
			$this->excludegroups[] = 'bot';
		}
		if ( $opts->getValue( 'hidesysops' ) ) {
			$this->excludegroups[] = 'sysop';
		}
	}

	public function getIndexField() {
		return 'qcc_title';
	}

	public function getQueryInfo( $data = null ) {
		$dbr = $this->getDatabase();

		$activeUserSeconds = $this->getConfig()->get( MainConfigNames::ActiveUserDays ) * 86400;
		$timestamp = $dbr->timestamp( (int)wfTimestamp( TS_UNIX ) - $activeUserSeconds );
		$fname = __METHOD__ . ' (' . $this->getSqlComment() . ')';

		// Inner subselect to pull the active users out of querycachetwo
		$subquery = $dbr->newSelectQueryBuilder()
			->select( [ 'qcc_title', 'user_id', 'actor_id' ] )
			->from( 'querycachetwo' )
			->join( 'user', null, 'user_name = qcc_title' )
			->join( 'actor', null, 'actor_user = user_id' )
			->where( [
				'qcc_type' => 'activeusers',
				'qcc_namespace' => NS_USER,
			] )
			->caller( $fname );
		if ( $data !== null ) {
			$subquery
				->orderBy( 'qcc_title', $data['order'] )
				->limit( $data['limit'] )
				->andWhere( $data['conds'] );
		}
		if ( $this->requestedUser != '' ) {
			$subquery->andWhere( $dbr->expr( 'qcc_title', '>=', $this->requestedUser ) );
		}
		if ( $this->groups !== [] ) {
			$subquery
				->join( 'user_groups', 'ug1', 'ug1.ug_user = user_id' )
				->andWhere( [
					'ug1.ug_group' => $this->groups,
					$dbr->expr( 'ug1.ug_expiry', '=', null )->or( 'ug1.ug_expiry', '>=', $dbr->timestamp() ),
				] );
		}
		if ( $this->excludegroups !== [] ) {
			$subquery
				->leftJoin( 'user_groups', 'ug2', [
					'ug2.ug_user = user_id',
					'ug2.ug_group' => $this->excludegroups,
					$dbr->expr( 'ug2.ug_expiry', '=', null )->or( 'ug2.ug_expiry', '>=', $dbr->timestamp() ),
				] )
				->andWhere( [ 'ug2.ug_user' => null ] );
		}
		if ( !$this->canSeeHideuser() ) {
			$subquery->andWhere( $this->hideUserUtils->getExpression( $dbr ) );
		}

		// Outer query to select the recent edit counts for the selected active users
		return [
			'tables' => [ 'qcc_users' => new Subquery( $subquery->getSQL() ), 'recentchanges' ],
			'fields' => [
				'qcc_title',
				'user_name' => 'qcc_title',
				'user_id' => 'user_id',
				'recentedits' => 'COUNT(DISTINCT rc_id)'
			],
			'options' => [ 'GROUP BY' => [ 'qcc_title', 'user_id' ] ],
			'conds' => [],
			'join_conds' => [ 'recentchanges' => [ 'LEFT JOIN', [
				'rc_actor = actor_id',
				$dbr->expr( 'rc_type', '!=', RC_EXTERNAL ), // Don't count wikidata.
				$dbr->expr( 'rc_type', '!=', RC_CATEGORIZE ), // Don't count categorization changes.
				$dbr->expr( 'rc_log_type', '=', null )->or( 'rc_log_type', '!=', 'newusers' ),
				$dbr->expr( 'rc_timestamp', '>=', $timestamp ),
			] ] ],
		];
	}

	protected function buildQueryInfo( $offset, $limit, $order ) {
		$fname = __METHOD__ . ' (' . $this->getSqlComment() . ')';

		$sortColumns = array_merge( [ $this->mIndexField ], $this->mExtraSortFields );
		if ( $order === self::QUERY_ASCENDING ) {
			$dir = 'ASC';
			$orderBy = $sortColumns;
			$operator = $this->mIncludeOffset ? '>=' : '>';
		} else {
			$dir = 'DESC';
			$orderBy = [];
			foreach ( $sortColumns as $col ) {
				$orderBy[] = $col . ' DESC';
			}
			$operator = $this->mIncludeOffset ? '<=' : '<';
		}
		$info = $this->getQueryInfo( [
			'limit' => intval( $limit ),
			'order' => $dir,
			'conds' =>
				$offset != '' ? [ $this->getDatabase()->expr( $this->mIndexField, $operator, $offset ) ] : [],
		] );

		$tables = $info['tables'];
		$fields = $info['fields'];
		$conds = $info['conds'];
		$options = $info['options'];
		$join_conds = $info['join_conds'];
		$options['ORDER BY'] = $orderBy;
		return [ $tables, $fields, $conds, $fname, $options, $join_conds ];
	}

	protected function doBatchLookups() {
		parent::doBatchLookups();

		$uids = [];
		foreach ( $this->mResult as $row ) {
			$uids[] = (int)$row->user_id;
		}
		// Fetch the block status of the user for showing "(blocked)" text and for
		// striking out names of suppressed users when privileged user views the list.
		// Although the first query already hits the block table for un-privileged, this
		// is done in two queries to avoid huge quicksorts and to make COUNT(*) correct.
		$dbr = $this->getDatabase();
		$res = $dbr->newSelectQueryBuilder()
			->select( [
				'bt_user',
				'deleted' => 'MAX(bl_deleted)',
				'sitewide' => 'MAX(bl_sitewide)'
			] )
			->from( 'block_target' )
			->join( 'block', null, 'bl_target=bt_id' )
			->where( [ 'bt_user' => $uids ] )
			->groupBy( [ 'bt_user' ] )
			->caller( __METHOD__ )->fetchResultSet();
		$this->blockStatusByUid = [];
		foreach ( $res as $row ) {
			$this->blockStatusByUid[$row->bt_user] = [
				'deleted' => $row->deleted,
				'sitewide' => $row->sitewide,
			];
		}
		$this->mResult->seek( 0 );
	}

	public function formatRow( $row ) {
		$userName = $row->user_name;

		$ulinks = Linker::userLink( $row->user_id, $userName );
		$ulinks .= Linker::userToolLinks(
			$row->user_id,
			$userName,
			// Should the contributions link be red if the user has no edits (using default)
			false,
			// Customisation flags (using default 0)
			0,
			// User edit count (using default)
			null,
			// do not wrap the message in parentheses (CSS will provide these)
			false
		);

		$lang = $this->getLanguage();

		$list = [];

		$userIdentity = new UserIdentityValue( intval( $row->user_id ), $userName );
		$ugms = $this->getGroupMemberships( $userIdentity );
		foreach ( $ugms as $ugm ) {
			$list[] = $this->buildGroupLink( $ugm, $userName );
		}

		$groups = $lang->commaList( $list );

		$item = $lang->specialList( $ulinks, $groups );

		// If there is a block, 'deleted' and 'sitewide' are both set on
		// $this->blockStatusByUid[$row->user_id].
		$blocked = '';
		$isBlocked = isset( $this->blockStatusByUid[$row->user_id] );
		if ( $isBlocked ) {
			if ( $this->blockStatusByUid[$row->user_id]['deleted'] == 1 ) {
				$item = "<span class=\"deleted\">$item</span>";
			}
			if ( $this->blockStatusByUid[$row->user_id]['sitewide'] == 1 ) {
				$blocked = ' ' . $this->msg( 'listusers-blocked', $userName )->escaped();
			}
		}
		$count = $this->msg( 'activeusers-count' )->numParams( $row->recentedits )
			->params( $userName )->numParams( $this->RCMaxAge )->escaped();

		return Html::rawElement( 'li', [], "{$item} [{$count}]{$blocked}" );
	}

}

/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.41
 */
class_alias( ActiveUsersPager::class, 'ActiveUsersPager' );
