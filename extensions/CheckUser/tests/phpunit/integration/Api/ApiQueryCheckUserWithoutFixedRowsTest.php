<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Api;

use MediaWiki\Api\ApiQueryTokens;
use MediaWiki\Context\RequestContext;
use MediaWiki\Logging\LogPage;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Permissions\Authority;
use MediaWiki\Tests\Api\ApiTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for the CheckUser API query module that does not use fixed rows shared over many tests.
 * Where possible add tests to ApiQueryCheckUserTest instead to improve test speed
 *
 * @group API
 * @group medium
 * @group Database
 *
 * @covers \MediaWiki\Extension\CheckUser\Api\ApiQueryCheckUser
 * @covers \MediaWiki\Extension\CheckUser\Api\CheckUser\ApiQueryCheckUserAbstractResponse
 * @covers \MediaWiki\Extension\CheckUser\Api\CheckUser\ApiQueryCheckUserActionsResponse
 * @covers \MediaWiki\Extension\CheckUser\Api\CheckUser\ApiQueryCheckUserIpUsersResponse
 * @covers \MediaWiki\Extension\CheckUser\Api\CheckUser\ApiQueryCheckUserUserIpsResponse
 * @covers \MediaWiki\Extension\CheckUser\Services\ApiQueryCheckUserResponseFactory
 */
class ApiQueryCheckUserWithoutFixedRowsTest extends ApiTestCase {

	private const INITIAL_API_PARAMS = [
		'action' => 'query',
		'list' => 'checkuser',
	];

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( 'CheckUserDisableCheckUserAPI', false );
	}

	private function doCheckUserApiRequest( array $params, Authority $performer ): array {
		return $this->doApiRequestWithToken( self::INITIAL_API_PARAMS + $params, performer: $performer );
	}

	public function testGetActionsWhenLogEntryActionHidden(): void {
		$request = RequestContext::getMain()->getRequest();
		$request->setIP( '127.0.0.1' );
		$request->setHeader( 'User-Agent', 'user-agent-for-logs' );

		// Add a hidden log entry which will be added to cu_log_event and should be found by the checkuser API
		$logPerformer = $this->getTestUser()->getUserIdentity();
		ConvertibleTimestamp::setFakeTime( '20230405060707' );
		$logEntry = new ManualLogEntry( 'phpunit', 'test' );
		$logEntry->setPerformer( $logPerformer );
		$logEntry->setTarget( $this->getServiceContainer()->getTitleFactory()->makeTitle( NS_MAIN, 'Hidden target' ) );
		$logEntry->setComment( 'A very good reason' );
		$logEntry->setDeleted( LogPage::DELETED_ACTION );
		$logId = $logEntry->insert();
		$logEntry->publish( $logId );

		$checkPerformer = $this->getTestUser( 'checkuser' )->getUser();
		$result = $this->doCheckUserApiRequest(
			[
				'curequest' => 'actions',
				'cutarget' => $logPerformer->getName(),
				'cutoken' => ApiQueryTokens::getToken(
					$checkPerformer,
					$this->getServiceContainer()->getSessionManager()->getEmptySession(),
					ApiQueryTokens::getTokenTypeSalts()['csrf']
				)->toString(),
			],
			$checkPerformer
		);
		$this->assertArrayHasKey( 'query', $result[0] );
		$this->assertArrayHasKey( 'checkuser', $result[0]['query'] );
		$this->assertArrayHasKey( 'edits', $result[0]['query']['checkuser'] );
		$this->assertArrayEquals(
			[
				[
					'timestamp' => '2023-04-05T06:07:07Z',
					'ns' => null,
					'title' => wfMessage( 'rev-deleted-event' )->text(),
					'user' => $logPerformer->getName(),
					'ip' => '127.0.0.1',
					'agent' => 'user-agent-for-logs',
					'summary' => $logPerformer->getName() . ' ' . wfMessage( 'rev-deleted-event' )->text() .
						' (A very good reason)',
				],
			],
			$result[0]['query']['checkuser']['edits'],
			false,
			true,
			'The log entry target should not be visible in the response when hidden from the viewing user'
		);
	}
}
