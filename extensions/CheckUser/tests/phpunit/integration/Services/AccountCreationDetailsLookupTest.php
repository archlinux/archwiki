<?php
namespace MediaWiki\CheckUser\Tests\Integration\Services;

use MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler;
use MediaWiki\CheckUser\Services\AccountCreationDetailsLookup;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;

/**
 * @covers \MediaWiki\CheckUser\Services\AccountCreationDetailsLookup
 * @group Database
 */
class AccountCreationDetailsLookupTest extends MediaWikiIntegrationTestCase {

	use CheckUserTempUserTestTrait;

	private function getCheckUserPrivateEventsHandler() {
		return new CheckUserPrivateEventsHandler(
			$this->getServiceContainer()->get( 'CheckUserInsert' ),
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getReadOnlyMode(),
			$this->getServiceContainer()->get( 'UserAgentClientHintsManager' ),
			$this->getServiceContainer()->getJobQueueGroup(),
			$this->getServiceContainer()->getConnectionProvider()
		);
	}

	public function testGetIPAndUserAgentFromDBForPrivateLog() {
		// Force the account creation event to be logged to the private table
		// instead of the public one
		$this->overrideConfigValue( MainConfigNames::NewUserLog, false );

		$user = $this->getTestUser()->getUser();

		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'Fake User Agent' );
		$privateEventHandler = $this->getCheckUserPrivateEventsHandler();
		$privateEventHandler->onLocalUserCreated( $user, false );

		$lookup = new AccountCreationDetailsLookup( new NullLogger(),
			new ServiceOptions(
				AccountCreationDetailsLookup::CONSTRUCTOR_OPTIONS,
				$this->getServiceContainer()->getMainConfig()
			)
		);

		$results = $lookup->getIPAndUserAgentFromDB( $user->getName(), $this->getDb() );
		$this->assertSame( 1, $results->numRows(), "Should have found one row and didn't" );
		foreach ( $results as $row ) {
			$this->assertEquals( '7F000001', $row->cupe_ip_hex, 'Bad ip hex value' );
			$this->assertSame( 'Fake User Agent', $row->cupe_agent, 'Bad user agent string' );
		}
	}

	public function testGetIPAndUserAgentFromDBForPublicLogAndTemporaryAccount() {
		$this->overrideConfigValue( MainConfigNames::NewUserLog, true );

		$this->enableAutoCreateTempUser();
		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'Fake User Agent' );
		$user = $this->getServiceContainer()->getTempUserCreator()
			->create( null, RequestContext::getMain()->getRequest() )->getUser();
		$this->disableAutoCreateTempUser();

		$lookup = new AccountCreationDetailsLookup( new NullLogger(),
			new ServiceOptions(
				AccountCreationDetailsLookup::CONSTRUCTOR_OPTIONS,
				$this->getServiceContainer()->getMainConfig()
			)
		);

		$results = $lookup->getIPAndUserAgentFromDB( $user->getName(), $this->getDb() );
		$this->assertSame( 1, $results->numRows(), "Should have found one row and didn't" );
		foreach ( $results as $row ) {
			$this->assertEquals( '7F000001', $row->cupe_ip_hex, 'Bad ip hex value' );
			$this->assertSame( 'Fake User Agent', $row->cupe_agent, 'Bad user agent string' );
		}
	}
}
