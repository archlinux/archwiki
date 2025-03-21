<?php

namespace MediaWiki\Extension\Notifications\Push;

use MediaWiki\Extension\Notifications\Mapper\AbstractMapper;
use MediaWiki\Storage\NameTableStore;
use Wikimedia\Rdbms\DBError;
use Wikimedia\Rdbms\IDatabase;

class SubscriptionManager extends AbstractMapper {

	/** @var IDatabase */
	private $dbw;

	/** @var IDatabase */
	private $dbr;

	/** @var NameTableStore */
	private $pushProviderStore;

	/** @var NameTableStore */
	private $pushTopicStore;

	/** @var int */
	private $maxSubscriptionsPerUser;

	/**
	 * @param IDatabase $dbw primary DB connection (for writes)
	 * @param IDatabase $dbr replica DB connection (for reads)
	 * @param NameTableStore $pushProviderStore
	 * @param NameTableStore $pushTopicStore
	 * @param int $maxSubscriptionsPerUser
	 */
	public function __construct(
		IDatabase $dbw,
		IDatabase $dbr,
		NameTableStore $pushProviderStore,
		NameTableStore $pushTopicStore,
		int $maxSubscriptionsPerUser
	) {
		parent::__construct();
		$this->dbw = $dbw;
		$this->dbr = $dbr;
		$this->pushProviderStore = $pushProviderStore;
		$this->pushTopicStore = $pushTopicStore;
		$this->maxSubscriptionsPerUser = $maxSubscriptionsPerUser;
	}

	/**
	 * Store push subscription information for a central user ID.
	 * @param string $provider Provider name string (validated by presence in the PARAM_TYPE array)
	 * @param string $token Subscriber token provided by the push provider
	 * @param int $centralId
	 * @param string|null $topic APNS topic string
	 * @return bool true if the subscription was created; false if the token already exists
	 */
	public function create( string $provider, string $token, int $centralId, ?string $topic = null ): bool {
		$subscriptions = $this->getSubscriptionsForUser( $centralId );
		if ( count( $subscriptions ) >= $this->maxSubscriptionsPerUser ) {
			// If we exceed the number of subscriptions for this user, then delete the oldest subscription
			// before inserting the new one, making it behave like a circular buffer.
			// (Find the oldest subscription by iterating, since their order in the DB is not guaranteed.)
			$oldest = $subscriptions[0];
			foreach ( $subscriptions as $subscription ) {
				if ( $subscription->getUpdated() < $oldest->getUpdated() ) {
					$oldest = $subscription;
				}
			}
			$this->delete( [ $oldest->getToken() ], $centralId );
		}
		$topicId = $topic ? $this->pushTopicStore->acquireId( $topic ) : null;
		$this->dbw->newInsertQueryBuilder()
			->insertInto( 'echo_push_subscription' )
			->ignore()
			->row( [
				'eps_user' => $centralId,
				'eps_provider' => $this->pushProviderStore->acquireId( $provider ),
				'eps_token' => $token,
				'eps_token_sha256' => hash( 'sha256', $token ),
				'eps_data' => null,
				'eps_topic' => $topicId,
				'eps_updated' => $this->dbw->timestamp()
			] )
			->caller( __METHOD__ )
			->execute();
		return (bool)$this->dbw->affectedRows();
	}

	/**
	 * Get full data for all registered subscriptions for a user (by central ID).
	 * @param int $centralId
	 * @return Subscription[]
	 */
	public function getSubscriptionsForUser( int $centralId ): array {
		$res = $this->dbr->newSelectQueryBuilder()
			->select( '*' )
			->from( 'echo_push_subscription' )
			->join( 'echo_push_provider', null, 'eps_provider = epp_id' )
			->leftJoin( 'echo_push_topic', null, 'eps_topic = ept_id' )
			->where( [ 'eps_user' => $centralId ] )
			->caller( __METHOD__ )
			->fetchResultSet();
		$result = [];
		foreach ( $res as $row ) {
			$result[] = Subscription::newFromRow( $row );
		}
		return $result;
	}

	/**
	 * Delete one or more push subscriptions by token. Unless the current user is a push
	 * subscription manager, the query will also include the current central user ID as a condition.
	 * @param array $tokens Delete the subscription with these tokens
	 * @param int|null $centralId
	 * @return int number of rows deleted
	 * @throws DBError
	 */
	public function delete( array $tokens, ?int $centralId = null ): int {
		$cond = [ 'eps_token' => $tokens ];
		if ( $centralId ) {
			$cond['eps_user'] = $centralId;
		}
		$this->dbw->newDeleteQueryBuilder()
			->deleteFrom( 'echo_push_subscription' )
			->where( $cond )
			->caller( __METHOD__ )
			->execute();
		return $this->dbw->affectedRows();
	}

}
