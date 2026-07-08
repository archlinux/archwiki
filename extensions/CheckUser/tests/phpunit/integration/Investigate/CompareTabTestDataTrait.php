<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Investigate;

use MediaWiki\Extension\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\IPUtils;
use Wikimedia\Timestamp\ConvertibleTimestamp;

trait CompareTabTestDataTrait {

	use CheckUserTempUserTestTrait;

	/**
	 * Adds testing data to the DB for tests that cover the Compare tab. This is used to de-duplicate the code that
	 * adds the testing data for ComparePagerTest and CompareServiceTest.
	 *
	 * @return void
	 */
	public function addTestingDataToDB(): void {
		// Pin time to avoid failure when next second starts - T317411
		ConvertibleTimestamp::setFakeTime( '20220904094043' );
		$timestampForDb = $this->getDb()->timestamp();

		// Automatic temp user creation cannot be enabled
		// if actor IDs are being created for IPs.
		$this->disableAutoCreateTempUser();
		$actorStore = $this->getServiceContainer()->getActorStore();

		$testActorData = [
			'User1' => [
				'actor_id'   => 0,
				'actor_user' => 11111,
			],
			'User2' => [
				'actor_id'   => 0,
				'actor_user' => 22222,
			],
			'1.2.3.4' => [
				'actor_id'   => 0,
				'actor_user' => 0,
			],
			'1.2.3.5' => [
				'actor_id'   => 0,
				'actor_user' => 0,
			],
		];

		foreach ( $testActorData as $name => $actor ) {
			$testActorData[$name]['actor_id'] = $actorStore->acquireActorId(
				new UserIdentityValue( $actor['actor_user'], $name ),
				$this->getDb()
			);
		}

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_useragent' )
			->row( [ 'cuua_text' => 'foo user agent' ] )
			->caller( __METHOD__ )
			->execute();
		$fooUserAgentId = $this->getDb()->insertId();

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_useragent' )
			->row( [ 'cuua_text' => 'bar user agent' ] )
			->caller( __METHOD__ )
			->execute();
		$barUserAgentId = $this->getDb()->insertId();

		// Add testing data to cu_changes
		$testDataForCuChanges = [
			[
				'cuc_actor'      => $testActorData['1.2.3.4']['actor_id'],
				'cuc_type'       => RC_NEW,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent_id'   => $fooUserAgentId,
			], [
				'cuc_actor'      => $testActorData['1.2.3.4']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent_id'   => $fooUserAgentId,
			], [
				'cuc_actor'      => $testActorData['1.2.3.4']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent_id'   => $barUserAgentId,
			], [
				'cuc_actor'      => $testActorData['1.2.3.5']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_agent_id'   => $barUserAgentId,
			], [
				'cuc_actor'      => $testActorData['1.2.3.5']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_agent_id'   => $fooUserAgentId,
			], [
				'cuc_actor'      => $testActorData['User1']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent_id'   => $fooUserAgentId,
			], [
				'cuc_actor'      => $testActorData['User2']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent_id'   => $fooUserAgentId,
			], [
				'cuc_actor'      => $testActorData['User1']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_agent_id'   => $fooUserAgentId,
			],
		];

		$testDataForCuChanges = array_map( static fn ( $row ) => [
			'cuc_namespace'  => NS_MAIN,
			'cuc_title'      => 'Foo_Page',
			'cuc_minor'      => 0,
			'cuc_page_id'    => 1,
			'cuc_timestamp'  => $timestampForDb,
			'cuc_xff'        => 0,
			'cuc_xff_hex'    => null,
			'cuc_comment_id' => 0,
			'cuc_this_oldid' => 0,
			'cuc_last_oldid' => 0,
			...$row,
		], $testDataForCuChanges );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_changes' )
			->rows( $testDataForCuChanges )
			->execute();

		// Add testing data to cu_log_event
		$testDataForCuLogEvent = [
			[
				'cule_actor'      => $testActorData['1.2.3.4']['actor_id'],
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cule_agent_id'   => $fooUserAgentId,
			], [
				'cule_actor'      => $testActorData['1.2.3.4']['actor_id'],
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cule_agent_id'   => $barUserAgentId,
			], [
				'cule_actor'      => $testActorData['1.2.3.5']['actor_id'],
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cule_agent_id'   => $barUserAgentId,
			], [
				'cule_actor'      => $testActorData['User1']['actor_id'],
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cule_agent_id'   => $fooUserAgentId,
			],
		];

		$testDataForCuLogEvent = array_map( static fn ( $row ) => [
			'cule_log_id'     => 0,
			'cule_timestamp'  => $timestampForDb,
			'cule_xff'        => 0,
			'cule_xff_hex'    => null,
			...$row,
		], $testDataForCuLogEvent );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_log_event' )
			->rows( $testDataForCuLogEvent )
			->execute();

		// Add testing data to cu_private_event
		$testDataForCuPrivateEvent = [
			[
				'cupe_actor'      => $testActorData['1.2.3.4']['actor_id'],
				'cupe_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cupe_agent_id'   => $fooUserAgentId,
			], [
				'cupe_actor'      => $testActorData['User1']['actor_id'],
				'cupe_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cupe_agent_id'   => $fooUserAgentId,
			], [
				'cupe_actor'      => $testActorData['User2']['actor_id'],
				'cupe_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cupe_agent_id'   => $fooUserAgentId,
			],
		];

		$testDataForCuPrivateEvent = array_map( static fn ( $row ) => [
			'cupe_namespace'  => NS_MAIN,
			'cupe_title'      => 'Foo_Page',
			'cupe_timestamp'  => $timestampForDb,
			'cupe_xff'        => 0,
			'cupe_xff_hex'    => null,
			'cupe_log_action' => 'foo',
			'cupe_log_type'   => 'bar',
			'cupe_params' => '',
			'cupe_comment_id' => 0,
			...$row,
		], $testDataForCuPrivateEvent );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_private_event' )
			->rows( $testDataForCuPrivateEvent )
			->execute();
	}
}
