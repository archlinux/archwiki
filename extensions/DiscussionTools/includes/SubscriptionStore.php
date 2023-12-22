<?php

namespace MediaWiki\Extension\DiscussionTools;

use Config;
use ConfigFactory;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityUtils;
use ReadOnlyMode;
use stdClass;
use TitleValue;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;

class SubscriptionStore {

	/**
	 * Constants for the values of the sub_state field.
	 */
	public const STATE_UNSUBSCRIBED = 0;
	public const STATE_SUBSCRIBED = 1;
	public const STATE_AUTOSUBSCRIBED = 2;

	private Config $config;
	private IConnectionProvider $dbProvider;
	private ReadOnlyMode $readOnlyMode;
	private UserFactory $userFactory;
	private UserIdentityUtils $userIdentityUtils;

	public function __construct(
		ConfigFactory $configFactory,
		IConnectionProvider $dbProvider,
		ReadOnlyMode $readOnlyMode,
		UserFactory $userFactory,
		UserIdentityUtils $userIdentityUtils
	) {
		$this->config = $configFactory->makeConfig( 'discussiontools' );
		$this->dbProvider = $dbProvider;
		$this->readOnlyMode = $readOnlyMode;
		$this->userFactory = $userFactory;
		$this->userIdentityUtils = $userIdentityUtils;
	}

	/**
	 * @param IReadableDatabase $db
	 * @param UserIdentity|null $user
	 * @param array|null $itemNames
	 * @param int|int[]|null $state One of (or an array of) SubscriptionStore::STATE_* constants
	 * @return IResultWrapper|false
	 */
	private function fetchSubscriptions(
		IReadableDatabase $db,
		?UserIdentity $user = null,
		?array $itemNames = null,
		$state = null
	) {
		$conditions = [];

		if ( $user ) {
			$conditions[ 'sub_user' ] = $user->getId();
		}

		if ( $itemNames !== null ) {
			if ( !count( $itemNames ) ) {
				// We are not allowed to construct a filter with an empty array.
				// Any empty array should result in no items being returned.
				return new FakeResultWrapper( [] );
			}
			$conditions[ 'sub_item' ] = $itemNames;
		}

		if ( $state !== null ) {
			$conditions[ 'sub_state' ] = $state;
		}

		return $db->newSelectQueryBuilder()
			->from( 'discussiontools_subscription' )
			->fields( [
				'sub_user', 'sub_item', 'sub_namespace', 'sub_title', 'sub_section', 'sub_state',
				'sub_created', 'sub_notified'
			] )
			->where( $conditions )
			->caller( __METHOD__ )
			->fetchResultSet();
	}

	/**
	 * @param UserIdentity $user
	 * @param array|null $itemNames
	 * @param int[]|null $state Array of SubscriptionStore::STATE_* constants
	 * @param array $options
	 * @return SubscriptionItem[]
	 */
	public function getSubscriptionItemsForUser(
		UserIdentity $user,
		?array $itemNames = null,
		?array $state = null,
		array $options = []
	): array {
		// Only a registered user can be subscribed
		if ( !$user->isRegistered() || $this->userIdentityUtils->isTemp( $user ) ) {
			return [];
		}

		$options += [ 'forWrite' => false ];
		if ( $options['forWrite'] ) {
			$db = $this->dbProvider->getPrimaryDatabase();
		} else {
			$db = $this->dbProvider->getReplicaDatabase();
		}

		$rows = $this->fetchSubscriptions(
			$db,
			$user,
			$itemNames,
			$state
		);

		if ( !$rows ) {
			return [];
		}

		$items = [];
		foreach ( $rows as $row ) {
			$target = new TitleValue( (int)$row->sub_namespace, $row->sub_title, $row->sub_section );
			$items[] = $this->getSubscriptionItemFromRow( $user, $target, $row );
		}

		return $items;
	}

	/**
	 * @param string $itemName
	 * @param int[]|null $state An array of SubscriptionStore::STATE_* constants
	 * @param array $options
	 * @return array
	 */
	public function getSubscriptionItemsForTopic(
		string $itemName,
		?array $state = null,
		array $options = []
	): array {
		$options += [ 'forWrite' => false ];
		if ( $options['forWrite'] ) {
			$db = $this->dbProvider->getPrimaryDatabase();
		} else {
			$db = $this->dbProvider->getReplicaDatabase();
		}

		$rows = $this->fetchSubscriptions(
			$db,
			null,
			[ $itemName ],
			$state
		);

		if ( !$rows ) {
			return [];
		}

		$items = [];
		foreach ( $rows as $row ) {
			$target = new TitleValue( (int)$row->sub_namespace, $row->sub_title, $row->sub_section );
			$user = $this->userFactory->newFromId( $row->sub_user );
			$items[] = $this->getSubscriptionItemFromRow( $user, $target, $row );
		}

		return $items;
	}

	/**
	 * @param UserIdentity $user
	 * @param LinkTarget $target
	 * @param stdClass $row
	 * @return SubscriptionItem
	 */
	private function getSubscriptionItemFromRow(
		UserIdentity $user,
		LinkTarget $target,
		stdClass $row
	): SubscriptionItem {
		return new SubscriptionItem(
			$user,
			$row->sub_item,
			$target,
			$row->sub_state,
			$row->sub_created,
			$row->sub_notified
		);
	}

	/**
	 * @param UserIdentity $user
	 * @param LinkTarget $target
	 * @param string $itemName
	 * @param int $state
	 * @return bool
	 */
	public function addSubscriptionForUser(
		UserIdentity $user,
		LinkTarget $target,
		string $itemName,
		// Can not use static:: in compile-time constants
		int $state = self::STATE_SUBSCRIBED
	): bool {
		if ( $this->readOnlyMode->isReadOnly() ) {
			return false;
		}
		// Only a registered user can subscribe
		if ( !$user->isRegistered() || $this->userIdentityUtils->isTemp( $user ) ) {
			return false;
		}
		$dbw = $this->dbProvider->getPrimaryDatabase();
		$dbw->upsert(
			'discussiontools_subscription',
			[
				'sub_user' => $user->getId(),
				'sub_namespace' => $target->getNamespace(),
				'sub_title' => $target->getDBkey(),
				'sub_section' => $target->getFragment(),
				'sub_item' => $itemName,
				'sub_state' => $state,
				'sub_created' => $dbw->timestamp(),
			],
			[ [ 'sub_user', 'sub_item' ] ],
			[
				'sub_state' => $state,
			],
			__METHOD__
		);
		return (bool)$dbw->affectedRows();
	}

	/**
	 * @param UserIdentity $user
	 * @param string $itemName
	 * @return bool
	 */
	public function removeSubscriptionForUser(
		UserIdentity $user,
		string $itemName
	): bool {
		if ( $this->readOnlyMode->isReadOnly() ) {
			return false;
		}
		// Only a registered user can subscribe
		if ( !$user->isRegistered() || $this->userIdentityUtils->isTemp( $user ) ) {
			return false;
		}
		$dbw = $this->dbProvider->getPrimaryDatabase();
		$dbw->newUpdateQueryBuilder()
			->table( 'discussiontools_subscription' )
			->set( [ 'sub_state' => static::STATE_UNSUBSCRIBED ] )
			->where( [
				'sub_user' => $user->getId(),
				'sub_item' => $itemName,
			] )
			->caller( __METHOD__ )
			->execute();
		return (bool)$dbw->affectedRows();
	}

	/**
	 * @param UserIdentity $user
	 * @param LinkTarget $target
	 * @param string $itemName
	 * @return bool
	 */
	public function addAutoSubscriptionForUser(
		UserIdentity $user,
		LinkTarget $target,
		string $itemName
	): bool {
		// Check for existing subscriptions.
		$subscriptionItems = $this->getSubscriptionItemsForUser(
			$user,
			[ $itemName ],
			[ static::STATE_SUBSCRIBED, static::STATE_AUTOSUBSCRIBED ],
			[ 'forWrite' => true ]
		);
		if ( $subscriptionItems ) {
			return false;
		}

		return $this->addSubscriptionForUser(
			$user,
			$target,
			$itemName,
			static::STATE_AUTOSUBSCRIBED
		);
	}

	/**
	 * @param string $field Timestamp field name
	 * @param UserIdentity|null $user
	 * @param string $itemName
	 * @return bool
	 */
	private function updateSubscriptionTimestamp(
		string $field,
		?UserIdentity $user,
		string $itemName
	): bool {
		if ( $this->readOnlyMode->isReadOnly() ) {
			return false;
		}
		$dbw = $this->dbProvider->getPrimaryDatabase();

		$conditions = [
			'sub_item' => $itemName,
		];

		if ( $user ) {
			$conditions[ 'sub_user' ] = $user->getId();
		}

		$dbw->newUpdateQueryBuilder()
			->table( 'discussiontools_subscription' )
			->set( [ $field => $dbw->timestamp() ] )
			->where( $conditions )
			->caller( __METHOD__ )
			->execute();
		return (bool)$dbw->affectedRows();
	}

	/**
	 * Update the notified timestamp on a subscription
	 *
	 * This field could be used in future to cleanup notifications
	 * that are no longer needed (e.g. because the conversation has
	 * been archived), so should be set for muted notifications too.
	 *
	 * @param UserIdentity|null $user
	 * @param string $itemName
	 * @return bool
	 */
	public function updateSubscriptionNotifiedTimestamp(
		?UserIdentity $user,
		string $itemName
	): bool {
		return $this->updateSubscriptionTimestamp(
			'sub_notified',
			$user,
			$itemName
		);
	}
}
