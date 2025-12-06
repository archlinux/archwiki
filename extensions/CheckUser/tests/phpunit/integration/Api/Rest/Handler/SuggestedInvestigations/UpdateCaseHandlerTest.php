<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\CheckUser\Api\Rest\Handler\SuggestedInvestigations\UpdateCaseHandler;
use MediaWiki\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Rest\Handler\SessionHelperTestTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use RuntimeException;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\CheckUser\Api\Rest\Handler\SuggestedInvestigations\UpdateCaseHandler
 * @group Database
 */
class UpdateCaseHandlerTest extends MediaWikiIntegrationTestCase {

	use SuggestedInvestigationsTestTrait;
	use HandlerTestTrait;
	use SessionHelperTestTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
	}

	private function getObjectUnderTest(): UpdateCaseHandler {
		$services = $this->getServiceContainer();
		return new UpdateCaseHandler(
			$services->getMainConfig(),
			$services->getContentLanguage(),
			$services->get( 'CheckUserSuggestedInvestigationsCaseManager' )
		);
	}

	private static function getRequestData( mixed $caseId, array $postParams ): RequestData {
		return new RequestData( [
			'method' => 'POST',
			'pathParams' => [ 'caseId' => $caseId ],
			'headers' => [ 'Content-Type' => 'application/json' ],
			'bodyContents' => json_encode( $postParams ),
		] );
	}

	public function testWhenFeatureIsNotEnabled() {
		$this->disableSuggestedInvestigations();
		$this->expectExceptionObject( new LocalizedHttpException(
			new MessageValue( 'checkuser-suggestedinvestigations-case-update-feature-not-enabled' ), 404
		) );
		$this->executeHandler(
			$this->getObjectUnderTest(),
			$this->getRequestData( 1, [ 'status' => 'open', 'reason' => 'test' ] )
		);
	}

	public function testWhenUserLacksCheckUserRight() {
		$this->expectExceptionObject( new LocalizedHttpException( new MessageValue( 'rest-permission-error' ), 403 ) );
		$this->executeHandler(
			$this->getObjectUnderTest(),
			$this->getRequestData( 1, [ 'status' => 'open', 'reason' => 'test' ] ),
			[], [], [], [], $this->mockRegisteredNullAuthority()
		);
	}

	public function testWhenProvidedTokenIsInvalid() {
		$this->expectExceptionObject( new LocalizedHttpException( new MessageValue( 'rest-badtoken' ), 403 ) );
		$this->executeHandler(
			$this->getObjectUnderTest(),
			$this->getRequestData( 1, [ 'status' => 'open', 'reason' => 'test', 'token' => 'invalid' ] ),
			[], [], [], [], $this->mockRegisteredUltimateAuthority(), $this->getSession( false )
		);
	}

	/** @dataProvider provideInvalidRequestData */
	public function testExecuteWhenRequestDataIsInvalid(
		mixed $caseId, array $postParams, string $expectedErrorMessageKey
	) {
		$this->expectExceptionObject( new LocalizedHttpException(
			new MessageValue( $expectedErrorMessageKey ), 400
		) );
		$this->executeHandler(
			$this->getObjectUnderTest(),
			$this->getRequestData( $caseId, $postParams ),
			[], [], [], [], $this->mockRegisteredUltimateAuthority()
		);
	}

	public static function provideInvalidRequestData(): array {
		return [
			'Status is not recognised' => [
				1, [ 'status' => 'invalid-status-abc', 'reason' => 'abc' ], 'rest-body-validation-error',
			],
			'Case ID is a string' => [
				'abc', [ 'status' => 'resolved', 'reason' => 'abc' ], 'paramvalidator-badinteger',
			],
			'Case ID is 0' => [
				0, [ 'status' => 'resolved', 'reason' => 'abc' ], 'paramvalidator-outofrange-min',
			],
			'Case ID does not exist' => [
				1, [ 'status' => 'resolved', 'reason' => 'abc' ],
				'checkuser-suggestedinvestigations-case-update-case-not-found',
			],
		];
	}

	public function testWhenCaseStatusIsUnhandled() {
		$this->expectException( RuntimeException::class );
		$this->executeHandler(
			$this->getObjectUnderTest(), new RequestData( [] ), [], [],
			[ 'caseId' => 1 ],
			// Simulate a status type that is supported but unhandled
			[ 'status' => 'unhandled-status-abc', 'reason' => 'test' ],
			$this->mockRegisteredUltimateAuthority()
		);
	}

	/** @dataProvider provideCaseStatusChanges */
	public function testExecuteForSuccessfulUpdate(
		CaseStatus $originalStatus, CaseStatus $newStatus, string $newStatusAsString, ?string $reason,
		string $expectedReason
	) {
		// Generate a pre-existing suggested investigations case we can update
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		$caseId = $caseManager->createCase(
			[ UserIdentityValue::newRegistered( 1, 'TestUser' ) ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'foo', 'bar', false ) ]
		);
		if ( $originalStatus !== CaseStatus::Open ) {
			$caseManager->setCaseStatus( $caseId, $originalStatus );
		}

		$postParams = [ 'status' => $newStatusAsString ];
		if ( $reason !== null ) {
			$postParams['reason'] = $reason;
		}
		$actualResponseJson = $this->executeHandlerAndGetBodyData(
			$this->getObjectUnderTest(),
			$this->getRequestData( 1, $postParams ),
			[], [], [], [], $this->mockRegisteredUltimateAuthority()
		);
		$this->assertArrayEquals(
			[ 'caseId' => $caseId, 'reason' => $expectedReason, 'status' => $newStatusAsString ],
			$actualResponseJson
		);

		// Assert that only one case exists and also that that the one case was successfully updated by the API call
		$this->newSelectQueryBuilder()
			->select( [ 'sic_id', 'sic_status', 'sic_status_reason' ] )
			->from( 'cusi_case' )
			->caller( __METHOD__ )
			->assertRowValue( [ $caseId, $newStatus->value, $expectedReason ] );
	}

	public static function provideCaseStatusChanges(): array {
		return [
			'Setting status to resolved with no provided reason' => [
				'originalStatus' => CaseStatus::Open, 'newStatus' => CaseStatus::Resolved,
				'newStatusAsString' => 'resolved', 'reason' => null, 'expectedReason' => '',
			],
			'Setting status to resolved with an associated reason' => [
				'originalStatus' => CaseStatus::Open, 'newStatus' => CaseStatus::Resolved,
				'newStatusAsString' => 'resolved', 'reason' => ' test ', 'expectedReason' => 'test',
			],
			'Setting status to invalid' => [
				'originalStatus' => CaseStatus::Open, 'newStatus' => CaseStatus::Invalid,
				'newStatusAsString' => 'invalid', 'reason' => '', 'expectedReason' => '',
			],
			'Setting status to invalid with an associated reason' => [
				'originalStatus' => CaseStatus::Open, 'newStatus' => CaseStatus::Invalid,
				'newStatusAsString' => 'invalid', 'reason' => 'test', 'expectedReason' => 'test',
			],
			'Setting status to open from resolved' => [
				'originalStatus' => CaseStatus::Resolved, 'newStatus' => CaseStatus::Open,
				'newStatusAsString' => 'open', 'reason' => '', 'expectedReason' => '',
			],
			'Setting status to open from resolved when reason is truncated' => [
				'originalStatus' => CaseStatus::Resolved, 'newStatus' => CaseStatus::Open,
				'newStatusAsString' => 'open', 'reason' => str_repeat( 'a', 300 ),
				'expectedReason' => str_repeat( 'a', 252 ) . '...',
			],
		];
	}
}
