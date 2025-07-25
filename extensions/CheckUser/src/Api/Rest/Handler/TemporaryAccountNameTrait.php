<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\Block\BlockManager;
use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\User\ActorStore;
use MediaWiki\User\UserNameUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

trait TemporaryAccountNameTrait {

	/**
	 * Finds the actor id associated with a temporary account name.
	 *
	 * @param string $name
	 * @return int the actor ID for the temporary account
	 * @throws LocalizedHttpException
	 */
	protected function getTemporaryAccountActorId( string $name ): int {
		if ( !$this->getUserNameUtils()->isTemp( $name ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-invalid-user', [ $name ] ),
				404
			);
		}

		$dbr = $this->getConnectionProvider()->getReplicaDatabase();
		$actorId = $this->getActorStore()->findActorIdByName( $name, $dbr );
		$userIdentity = $this->getActorStore()->getUserIdentityByName( $name );
		if ( $actorId === null || $userIdentity === null ) {
			throw new LocalizedHttpException(
				new MessageValue( 'rest-nonexistent-user', [ $name ] ),
				404
			);
		}

		$blockOnTempAccount = $this->getBlockManager()->getBlock( $userIdentity, null );
		if (
			$blockOnTempAccount &&
			$blockOnTempAccount->getHideName() &&
			!$this->getPermissionManager()->userHasRight( $this->getAuthority()->getUser(), 'viewsuppressed' )
		) {
			if ( $this->getPermissionManager()->userHasRight( $this->getAuthority()->getUser(), 'hideuser' ) ) {
				// The user knows that this user exists, because they have the 'hideuser' right. Instead of pretending
				// the user does not exist, we instead should inform the user that they don't have the
				// permission to view this information.
				throw new LocalizedHttpException(
					new MessageValue( 'checkuser-rest-access-denied' ),
					403
				);
			} else {
				// Pretend the username does not exist if the temporary account is hidden and the user does not have the
				// rights to see suppressed information or blocks with 'hideuser' set.
				throw new LocalizedHttpException(
					new MessageValue( 'rest-nonexistent-user', [ $name ] ),
					404
				);
			}
		}

		return $actorId;
	}

	/**
	 * Query the CheckUser tables for the IP addresses most recently associated with an actor.
	 *
	 * @param int $actorId
	 * @param int $limit
	 * @param IReadableDatabase $dbr
	 * @return string[]
	 */
	protected function getActorIps( int $actorId, int $limit, IReadableDatabase $dbr ): array {
		$resultRows = [];

		foreach ( CheckUserQueryInterface::RESULT_TABLES as $table ) {
			$prefix = CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$table];
			$queryBuilder = $dbr->newSelectQueryBuilder()
				->select( [ 'ip' => "{$prefix}ip", 'timestamp' => 'MAX(' . $prefix . 'timestamp)' ] )
				->from( $table )
				->where( [ "{$prefix}actor" => $actorId ] )
				->groupBy( "{$prefix}ip" )
				->orderBy( 'timestamp', SelectQueryBuilder::SORT_DESC )
				->limit( $limit )
				->caller( __METHOD__ );
			$resultRows = array_merge( $resultRows, iterator_to_array( $queryBuilder->fetchResultSet() ) );
		}

		// Order the results by the timestamp column descending.
		usort( $resultRows, static function ( $a, $b ) {
			return $b->timestamp <=> $a->timestamp;
		} );

		// Get the IP addresses from $resultRows in the order applied by usort.
		$result = array_column( $resultRows, 'ip' );

		// Remove duplicated IPs (if any)
		$result = array_unique( $result );

		// Apply the limit to the IPs list and then return them.
		return array_slice( $result, 0, $limit );
	}

	abstract protected function getAuthority(): Authority;

	abstract protected function getUserNameUtils(): UserNameUtils;

	abstract protected function getConnectionProvider(): IConnectionProvider;

	abstract protected function getActorStore(): ActorStore;

	abstract protected function getBlockManager(): BlockManager;

	abstract protected function getPermissionManager(): PermissionManager;
}
