<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use LogicException;
use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\HookHandler\IPInfoHandler;
use MediaWiki\Context\RequestContext;
use MediaWiki\IPInfo\Rest\Handler\NoRevisionHandler;
use MediaWiki\IPInfo\Rest\Handler\RevisionHandler;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\IPInfoHandler
 * @group CheckUser
 * @group Database
 */
class IPInfoHandlerTest extends MediaWikiIntegrationTestCase {
	use HandlerTestTrait;
	use MockAuthorityTrait;
	use MockHttpTrait;
	use TempUserTestTrait;

	private static Authority $tempUser;
	private static Authority $tempUserNoEdits;
	private static RevisionRecord $revRecordByTempUser;

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$this->markTestSkippedIfExtensionNotLoaded( 'IPInfo' );

		$this->installMockHttp(
			$this->makeFakeHttpRequest( '', 404 )
		);
	}

	public function addDBDataOnce() {
		$request = new FauxRequest();
		$request->setIP( '1.2.3.4' );
		RequestContext::getMain()->setRequest( $request );

		self::$tempUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, $request )
			->getUser();
		$pageUpdateStatus = $this->editPage(
			$this->getNonexistingTestPage(),
			'test',
			'',
			NS_MAIN,
			self::$tempUser
		);
		self::$revRecordByTempUser = $pageUpdateStatus->getNewRevision();

		self::$tempUserNoEdits = $this->getServiceContainer()->getTempUserCreator()
			->create( null, $request )
			->getUser();
	}

	/**
	 * @param int|string $identifier Either a target username or a target revision id
	 *
	 * @return ResponseInterface response from the handler call
	 */
	private function getEndpointResponse( $identifier ) {
		$services = $this->getServiceContainer();

		// User should have permissions to see IPInfo data
		$userOptionsManager = $services->getUserOptionsManager();
		$user = $this->getTestUser()->getUserIdentity();
		$userOptionsManager->setOption( $user, 'ipinfo-use-agreement', 1 );
		$userOptionsManager->setOption( $user, 'ipinfo-beta-feature-enable', 1 );

		$performer = new SimpleAuthority( $user, [ 'read', 'ipinfo' ] );

		$requestParams = [
			'method' => 'POST',
			'pathParams' => [],
			'queryParams' => [
				'dataContext' => 'infobox',
				'language' => 'en',
			],
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => json_encode( [ 'token' => 'valid-csrf-token' ] ),
		];

		$idType = gettype( $identifier );
		if ( $idType === 'integer' ) {
			// Revision ID passed through, return the response from RevisionHandler
			$revisionHandler = RevisionHandler::factory(
				$services->getService( 'IPInfoInfoManager' ),
				$services->getRevisionLookup(),
				$services->getPermissionManager(),
				$services->getUserFactory(),
				$services->getJobQueueGroup(),
				$services->getLanguageFallback(),
				$services->getUserIdentityUtils(),
				$services->get( 'IPInfoTempUserIPLookup' ),
				$services->get( 'IPInfoPermissionManager' ),
				$services->getReadOnlyMode(),
				$services->get( 'IPInfoHookRunner' )
			);

			// Get the request data
			$requestParams[ 'pathParams' ][ 'id' ] = $identifier;
			$requestData = new RequestData( $requestParams );
		} elseif ( $idType === 'string' ) {
			// Otherwise a username was passed through, return the response from NoRevisionHandler
			$revisionHandler = NoRevisionHandler::factory(
				$services->getService( 'IPInfoInfoManager' ),
				$services->getPermissionManager(),
				$services->getUserFactory(),
				$services->getJobQueueGroup(),
				$services->getLanguageFallback(),
				$services->getUserIdentityUtils(),
				$services->get( 'IPInfoTempUserIPLookup' ),
				$services->get( 'IPInfoPermissionManager' ),
				$services->getReadOnlyMode(),
				$services->get( 'IPInfoAnonymousUserIPLookup' ),
				$services->get( 'IPInfoHookRunner' )
			);

			// Get the request data
			$requestParams[ 'pathParams' ][ 'username' ] = $identifier;
			$requestData = new RequestData( $requestParams );
		}

		// Return the request response
		return $this->executeHandler(
			$revisionHandler,
			$requestData,
			[],
			[],
			[],
			[],
			$performer
		);
	}

	public function testGlobalContributionsCountNotNullFromHookWhenNoEdits() {
		// Run the endpoint
		$response = $this->getEndpointResponse( self::$tempUserNoEdits->getName() );

		// Assert a value is still returned if no global contributions are found
		$body = json_decode( $response->getBody()->getContents(), true );
		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertArrayHasKey( 'ipinfo-source-checkuser', $body['info'][0]['data'] );
		$checkUserData = $body['info'][0]['data']['ipinfo-source-checkuser'];
		$this->assertSame( 0, $checkUserData['globalContributionsCount'] );
	}

	public function testGlobalContributionsCountAddedFromHook() {
		// Run the endpoint
		$response = $this->getEndpointResponse( self::$revRecordByTempUser->getId() );

		$body = json_decode( $response->getBody()->getContents(), true );
		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertArrayHasKey( 'ipinfo-source-checkuser', $body['info'][0]['data'] );
		$checkUserData = $body['info'][0]['data']['ipinfo-source-checkuser'];
		$this->assertSame( 1, $checkUserData['globalContributionsCount'] );
	}

	public function testGlobalContributionsCountThrowsError() {
		$lookup = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$lookup->method( 'getGlobalContributionsCount' )
			->willThrowException( new LogicException() );
		$this->setService( 'CheckUserGlobalContributionsLookup', $lookup );

		// Run the endpoint
		$response = $this->getEndpointResponse( self::$revRecordByTempUser->getId() );

		// Assert that the failure didn't affect the main return and that no data was added
		$body = json_decode( $response->getBody()->getContents(), true );
		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertArrayNotHasKey( 'ipinfo-source-checkuser', $body['info'][0]['data'] );
	}

	/** @dataProvider provideTestOnIPInfoHandlerRun */
	public function testOnIPInfoHandlerRun( $targetProvider, $authorityProvider, string $context, array $expected ) {
		// Test that the isolated hook operates as expected when the handler is run
		$ipInfoData = [];
		$target = $targetProvider();
		$authority = $authorityProvider();
		$handler = new IPInfoHandler(
			$this->getServiceContainer()->get( 'CheckUserGlobalContributionsLookup' )
		);
		$handler->onIPInfoHandlerRun(
			$target,
			$authority,
			$context,
			$ipInfoData
		);
		$this->assertArrayEquals( $expected, $ipInfoData );
	}

	public static function provideTestOnIPInfoHandlerRun() {
		return [
			'infobox data context should append data' => [
				'target' => static fn () => self::$tempUserNoEdits->getName(),
				'authority' => static fn () => self::$tempUserNoEdits,
				'context' => 'infobox',
				'expectedOutput' => [
					'ipinfo-source-checkuser' => [
						'globalContributionsCount' => 0,
					],
				],
			],
			'popup data context shouldn\'t append data' => [
				'target' => static fn () => self::$tempUserNoEdits->getName(),
				'authority' => static fn () => self::$tempUserNoEdits,
				'context' => 'popup',
				'expectedOutput' => [],
			],
		];
	}
}
