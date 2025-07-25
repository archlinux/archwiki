<?php
namespace MediaWiki\Extension\Thanks;

use MediaWiki\Title\TitleFactory;
use MediaWiki\User\ActorNormalization;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\DBAccessObjectUtils;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Query module
 */
class ThanksQueryHelper {
	private TitleFactory $titleFactory;
	private IConnectionProvider $dbProvider;
	private ActorNormalization $actorNormalization;

	public function __construct(
		TitleFactory $titleFactory,
		IConnectionProvider $dbProvider,
		ActorNormalization $actorNormalization
	) {
		$this->titleFactory = $titleFactory;
		$this->dbProvider = $dbProvider;
		$this->actorNormalization = $actorNormalization;
	}

	/**
	 * Counts number of thanks a user has received. The query is not cached.
	 *
	 * @param UserIdentity $userIdentity
	 * @param int $limit cap the value of counts queried for performance
	 * @param int $flags database options. If calling in a POST context where a user is being thanked, the
	 *  return value will be incorrect if returned from replica. This allows you to query primary if the
	 *  exact number is important.
	 * @return int Number of thanks received for the user ID
	 */
	public function getThanksReceivedCount(
		UserIdentity $userIdentity,
		int $limit = 1000,
		int $flags = IDBAccessObject::READ_NORMAL
	): int {
		$db = DBAccessObjectUtils::getDBFromRecency( $this->dbProvider, $flags );
		$userPage = $this->titleFactory->newFromText( $userIdentity->getName(), NS_USER );
		$logTitle = $userPage->getDBkey();
		return $db->newSelectQueryBuilder()
			->table( 'logging' )
			->field( '1' )
			// There is no type + target index, but there's a target index (log_page_time)
			// and it's unlikely the user's page has many other log events than thanks,
			// so the query should be okay.
			->conds( [
				'log_type' => 'thanks',
				'log_action' => 'thank',
				'log_namespace' => NS_USER,
				'log_title' => $logTitle,
			] )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchRowCount();
	}

	/**
	 * Return the number of thanks a user has given. The query is not cached.
	 *
	 * @param UserIdentity $userIdentity
	 * @param int $limit cap the value of counts queried for performance
	 * @param int $flags database options. If calling in a POST context where a user is giving thanks, the
	 *  return value will be incorrect if returned from replica. This allows you to query primary if the
	 *  exact number is important.
	 * @return int Number of thanks given for the user ID
	 */
	public function getThanksGivenCount(
		UserIdentity $userIdentity,
		int $limit = 1000,
		int $flags = IDBAccessObject::READ_NORMAL
	): int {
		$db = DBAccessObjectUtils::getDBFromRecency( $this->dbProvider, $flags );
		$actorId = $this->actorNormalization->findActorId( $userIdentity, $db );
		if ( !$actorId ) {
			return 0;
		}
		return $db->newSelectQueryBuilder()
			->table( 'logging' )
			->field( '1' )
			->conds( [
				'log_type' => 'thanks',
				// Omit the log_action and log_namespace, as there's only one action
				// ('thank') and namespace (NS_USER) involved; this speeds up the query
				// because we can use the `log_actor_type_time` index
				'log_actor' => $actorId
			] )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchRowCount();
	}
}
