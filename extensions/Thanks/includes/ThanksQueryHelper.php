<?php
namespace MediaWiki\Extension\Thanks;

use DBAccessObjectUtils;
use IDBAccessObject;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Query module
 */
class ThanksQueryHelper {
	/** @var TitleFactory */
	private TitleFactory $titleFactory;
	/** @var ILoadBalancer */
	private ILoadBalancer $loadBalancer;

	public function __construct( TitleFactory $titleFactory, ILoadBalancer $loadBalancer ) {
		$this->titleFactory = $titleFactory;
		$this->loadBalancer = $loadBalancer;
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
		$loadBalancer = $this->loadBalancer;
		list( $index, $options ) = DBAccessObjectUtils::getDBOptions( $flags );
		$db = $loadBalancer->getConnection( $index );
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
			->fetchRowCount();
	}
}
