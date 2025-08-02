<?php

namespace MediaWiki\CheckUser\Investigate\Services;

use MediaWiki\Block\DatabaseBlockStoreFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\User;
use MediaWiki\User\UserGroupManagerFactory;
use MediaWiki\User\UserIdentityValue;
use stdClass;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IResultWrapper;

class PreliminaryCheckService {
	private IConnectionProvider $dbProvider;
	private UserGroupManagerFactory $userGroupManagerFactory;
	private ExtensionRegistry $extensionRegistry;
	private DatabaseBlockStoreFactory $blockStoreFactory;

	/** @var string */
	private $localWikiId;

	public function __construct(
		IConnectionProvider $dbProvider,
		ExtensionRegistry $extensionRegistry,
		UserGroupManagerFactory $userGroupManagerFactory,
		DatabaseBlockStoreFactory $blockStoreFactory,
		string $localWikiId
	) {
		$this->dbProvider = $dbProvider;
		$this->extensionRegistry = $extensionRegistry;
		$this->userGroupManagerFactory = $userGroupManagerFactory;
		$this->blockStoreFactory = $blockStoreFactory;
		$this->localWikiId = $localWikiId;
	}

	/**
	 * Get the information needed to build a query for the preliminary check. The
	 * query will be different depending on whether CentralAuth is available. Any
	 * information for paginating is handled in the PreliminaryCheckPager.
	 *
	 * @param User[] $users
	 * @return array
	 */
	public function getQueryInfo( array $users ): array {
		if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
			$info = $this->getGlobalQueryInfo( $users );
		} else {
			$info = $this->getLocalQueryInfo( $users );
		}
		return $info;
	}

	/**
	 * Get the information for building a query if CentralAuth is available.
	 *
	 * @param User[] $users
	 * @return array
	 */
	protected function getGlobalQueryInfo( array $users ): array {
		return [
			'tables' => 'localuser',
			'fields' => [
				'lu_name',
				'lu_wiki',
			],
			'conds' => $this->buildUserConds( $users, 'lu_name' ),
		];
	}

	/**
	 * Get the information for building a query if CentralAuth is unavailable.
	 *
	 * @param User[]|string[] $users
	 * @return array
	 */
	protected function getLocalQueryInfo( array $users ): array {
		return [
			'tables' => 'user',
			'fields' => [
				'user_name',
				'user_id',
				'user_editcount',
				'user_registration',
			],
			'conds' => $this->buildUserConds( $users, 'user_name' ),
		];
	}

	/**
	 * @param User[] $users
	 * @param string $field
	 * @return array
	 */
	protected function buildUserConds( array $users, string $field ): array {
		if ( !$users ) {
			return [ 0 ];
		}
		return [ $field => array_map( 'strval', $users ) ];
	}

	/**
	 * Perform additional queries to get the required data that is not returned
	 * by the pager's query. (The pager performs the query that is used for
	 * pagination.)
	 *
	 * @param IResultWrapper $rows
	 * @return array[]
	 */
	public function preprocessResults( IResultWrapper $rows ): array {
		$data = [];
		foreach ( $rows as $row ) {
			if ( $this->extensionRegistry->isLoaded( 'CentralAuth' ) ) {
				$localRow = $this->getLocalUserData( $row->lu_name, $row->lu_wiki );
				$data[] = $this->getAdditionalLocalData( $localRow, $row->lu_wiki );
			} else {
				$data[] = $this->getAdditionalLocalData( $row, $this->localWikiId );
			}
		}
		return $data;
	}

	/**
	 * Get basic user information for a given user's account on a given wiki.
	 *
	 * @param string $username
	 * @param string $wikiId
	 * @return stdClass|bool
	 */
	public function getLocalUserData( string $username, string $wikiId ) {
		$dbr = $this->dbProvider->getReplicaDatabase( $wikiId );
		$queryInfo = $this->getLocalQueryInfo( [ $username ] );
		return $dbr->newSelectQueryBuilder()
			->select( $queryInfo['fields'] )
			->from( $queryInfo['tables'] )
			->where( $queryInfo['conds'] )
			->caller( __METHOD__ )
			->fetchRow();
	}

	/**
	 * Get blocked status and user groups for a given user's account on a
	 * given wiki.
	 *
	 * @param stdClass|bool $row
	 * @param string $wikiId
	 * @return array
	 */
	protected function getAdditionalLocalData( $row, string $wikiId ): array {
		if ( $wikiId === $this->localWikiId ) {
			$userIdentity = new UserIdentityValue( (int)$row->user_id, $row->user_name );
		} else {
			$userIdentity = new UserIdentityValue( (int)$row->user_id, $row->user_name, $wikiId );
		}

		return [
			'id' => $row->user_id,
			'name' => $row->user_name,
			'registration' => $row->user_registration,
			'editcount' => $row->user_editcount,
			'blocked' => $this->isUserBlocked( $row->user_id, $wikiId ),
			'groups' => $this->userGroupManagerFactory
				->getUserGroupManager( $wikiId )
				->getUserGroups( $userIdentity ),
			'wiki' => $wikiId,
		];
	}

	/**
	 * @param int $userId
	 * @param string $wikiId
	 * @return bool
	 */
	protected function isUserBlocked( int $userId, string $wikiId ): bool {
		return (bool)$this->blockStoreFactory
			->getDatabaseBlockStore( $wikiId )
			->newListFromConds( [ 'bt_user' => $userId ] );
	}
}
