<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Api\Rest\Handler;

use MediaWiki\Extension\CheckUser\Api\Rest\Handler\SuggestedInvestigations\UpdateCaseHandler;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Tests\Rest\Handler\SessionHelperTestTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use RuntimeException;
use Wikimedia\Message\MessageValue;

/**
 * @covers \MediaWiki\Extension\CheckUser\Api\Rest\Handler\SuggestedInvestigations\UpdateCaseHandler
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
			$services->get( 'CheckUserSuggestedInvestigationsCaseManager' ),
			$services->get( 'CheckUserStatusReasonFormatter' )
		);
	}

	private function getRequestData( mixed $caseId, array $postParams ): RequestData {
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
			new MessageValue( 'checkuser-suggestedinvestigations-case-update-feature-not-enabled' ),
			404
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
			[],
			[],
			[],
			[],
			$this->mockRegisteredNullAuthority()
		);
	}

	public function testWhenProvidedTokenIsInvalid() {
		$this->expectExceptionObject( new LocalizedHttpException( new MessageValue( 'rest-badtoken' ), 403 ) );
		$this->executeHandler(
			$this->getObjectUnderTest(),
			$this->getRequestData( 1, [ 'status' => 'open', 'reason' => 'test', 'token' => 'invalid' ] ),
			[],
			[],
			[],
			[],
			$this->mockRegisteredUltimateAuthority(),
			$this->getSession( false )
		);
	}

	/** @dataProvider provideInvalidRequestData */
	public function testExecuteWhenRequestDataIsInvalid(
		mixed $caseId,
		array $postParams,
		string $expectedErrorMessageKey
	) {
		$this->expectExceptionObject( new LocalizedHttpException(
			new MessageValue( $expectedErrorMessageKey ),
			400
		) );
		$this->executeHandler(
			$this->getObjectUnderTest(),
			$this->getRequestData( $caseId, $postParams ),
			[],
			[],
			[],
			[],
			$this->mockRegisteredUltimateAuthority()
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
			$this->getObjectUnderTest(),
			new RequestData( [] ),
			[],
			[],
			[ 'caseId' => 1 ],
			// Simulate a status type that is supported but unhandled
			[ 'status' => 'unhandled-status-abc', 'reason' => 'test' ],
			$this->mockRegisteredUltimateAuthority()
		);
	}

	/** @dataProvider provideCaseStatusChanges */
	public function testExecuteForSuccessfulUpdate(
		CaseStatus $originalStatus,
		CaseStatus $newStatus,
		string $newStatusAsString,
		?string $reason,
		string $expectedReason,
		bool $expectPerformerLink
	): void {
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
			$this->getRequestData( $caseId, $postParams ),
			[],
			[],
			[],
			[],
			$this->mockRegisteredUltimateAuthority()
		);

		$arrayKeys = array_keys( $actualResponseJson );
		sort( $arrayKeys );
		$this->assertArrayEquals(
			[ 'caseId', 'formattedReason', 'reason', 'status' ],
			$arrayKeys,
		);

		$this->assertSame( $caseId, $actualResponseJson['caseId'] );
		$this->assertSame( $newStatusAsString, $actualResponseJson['status'] );
		$this->assertSame( $expectedReason, $actualResponseJson['reason'] );

		$formattedReason = $actualResponseJson['formattedReason'];
		if ( $expectPerformerLink ) {
			$this->assertStringContainsString( 'User_talk:', $formattedReason );
		} else {
			$this->assertStringNotContainsString( 'User_talk:', $formattedReason );
		}
		if ( $expectPerformerLink && ( $expectedReason !== '' || $newStatus === CaseStatus::Invalid ) ) {
			$this->assertStringContainsString( '<br', $formattedReason );
		} else {
			$this->assertStringNotContainsString( '<br', $formattedReason );
		}

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
				'expectPerformerLink' => true,
			],
			'Setting status to resolved with an associated reason' => [
				'originalStatus' => CaseStatus::Open, 'newStatus' => CaseStatus::Resolved,
				'newStatusAsString' => 'resolved', 'reason' => ' test ', 'expectedReason' => 'test',
				'expectPerformerLink' => true,
			],
			'Setting status to invalid' => [
				'originalStatus' => CaseStatus::Open, 'newStatus' => CaseStatus::Invalid,
				'newStatusAsString' => 'invalid', 'reason' => '', 'expectedReason' => '',
				'expectPerformerLink' => true,
			],
			'Setting status to invalid with an associated reason' => [
				'originalStatus' => CaseStatus::Open, 'newStatus' => CaseStatus::Invalid,
				'newStatusAsString' => 'invalid', 'reason' => 'test', 'expectedReason' => 'test',
				'expectPerformerLink' => true,
			],
			'Setting status to open from resolved' => [
				'originalStatus' => CaseStatus::Resolved, 'newStatus' => CaseStatus::Open,
				'newStatusAsString' => 'open', 'reason' => '', 'expectedReason' => '',
				'expectPerformerLink' => false,
			],
			'Setting status to open from resolved when reason is truncated' => [
				'originalStatus' => CaseStatus::Resolved, 'newStatus' => CaseStatus::Open,
				'newStatusAsString' => 'open', 'reason' => str_repeat( 'a', 300 ),
				'expectedReason' => str_repeat( 'a', 252 ) . '...',
				'expectPerformerLink' => false,
			],
			'No status change with reason that has wikitext' => [
				'originalStatus' => CaseStatus::Open, 'newStatus' => CaseStatus::Open,
				'newStatusAsString' => 'open', 'reason' => '[[Test]]',
				'expectedReason' => '[[Test]]',
				'expectPerformerLink' => false,
			],
		];
	}

	public function testFormattedReasonIncludesDefaultInvalidMessage(): void {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		$caseId = $caseManager->createCase(
			[ UserIdentityValue::newRegistered( 1, 'TestUser' ) ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'foo', 'bar', false ) ]
		);

		$actualResponseJson = $this->executeHandlerAndGetBodyData(
			$this->getObjectUnderTest(),
			$this->getRequestData( $caseId, [ 'status' => 'invalid', 'reason' => '' ] ),
			[],
			[],
			[],
			[],
			$this->mockRegisteredUltimateAuthority()
		);

		$this->assertStringContainsString(
			'False positive',
			$actualResponseJson['formattedReason'],
			'formattedReason should include the default invalid message text'
		);
	}
}
