<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Instrumentation;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Instrumentation\SuggestedInvestigationsInstrumentationClient;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\CaseStatus;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Extension\EventBus\Serializers\MediaWiki\UserEntitySerializer;
use MediaWiki\Extension\EventLogging\MetricsPlatform\MetricsClientFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\MetricsPlatform\MetricsClient;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Instrumentation\SuggestedInvestigationsInstrumentationClient
 * @group Database
 */
class SuggestedInvestigationsInstrumentationClientTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	public function testSubmitInteractionWithNoAutoPopulationOfInteractionData() {
		$this->markTestSkippedIfExtensionNotLoaded( 'EventLogging' );
		$this->commonSubmitInteractionTest(
			[ 'is_paging_results' => true, 'pager_limit' => 123 ],
			[ 'is_paging_results' => true, 'pager_limit' => 123 ]
		);
	}

	/** @dataProvider provideSubmitInteractionWithCaseId */
	public function testSubmitInteractionWithCaseId( array $providedInteractionData ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'EventLogging' );
		$this->markTestSkippedIfExtensionNotLoaded( 'EventBus' );

		$this->enableSuggestedInvestigations();

		// Create a case and then update it to include a specified note
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );

		$user = $this->getTestUser()->getUserIdentity();
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );

		$caseId = $caseManager->createCase( [ $user ], [ $signal ] );
		$caseManager->setCaseStatus( $caseId, CaseStatus::Open, 'test reason' );

		$providedInteractionData['case_id'] = $caseId;

		// Generate the expected interaction data array
		$userEntitySerializer = new UserEntitySerializer(
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getUserGroupManager(),
			$this->getServiceContainer()->getCentralIdLookup()
		);

		if ( !array_key_exists( 'case_url_identifier', $providedInteractionData ) ) {
			$expectedUrlIdentifier = (int)$this->getDb()->newSelectQueryBuilder()
				->select( 'sic_url_identifier' )
				->from( 'cusi_case' )
				->where( [ 'sic_id' => $caseId ] )
				->caller( __METHOD__ )
				->fetchField();
		} else {
			$expectedUrlIdentifier = $providedInteractionData['case_url_identifier'];
		}

		$expectedInteractionData = [
			'case_id' => $caseId,
			'case_url_identifier' => $expectedUrlIdentifier,
			'case_note' => array_key_exists( 'case_note', $providedInteractionData ) ?
				$providedInteractionData['case_note'] :
				'test reason',
			'signals_in_case' => array_key_exists( 'signals_in_case', $providedInteractionData ) ?
				$providedInteractionData['signals_in_case'] :
				[ 'Lorem' ],
			'users_in_case' => array_key_exists( 'users_in_case', $providedInteractionData ) ?
				$providedInteractionData['users_in_case'] :
				[ $userEntitySerializer->toArray( $user ) ],
		];

		$this->commonSubmitInteractionTest( $providedInteractionData, $expectedInteractionData );
	}

	public static function provideSubmitInteractionWithCaseId(): array {
		return [
			'Interaction data only has case_id field' => [ [] ],
			'Interaction data has users_in_case field' => [
				[ 'users_in_case' => [ [ 'user_text' => 'abc' ] ] ],
			],
			'Interaction data has signals_in_case field' => [
				[ 'signals_in_case' => [ 'Test1', 'Test2' ] ],
			],
			'Interaction data has case_url_identifier field' => [ [ 'case_url_identifier' => 1234 ] ],
			'Interaction data has case_note field' => [ [ 'case_note' => 'test' ] ],
			'Interaction data has all case fields' => [
				[
					'case_note' => 'test',
					'case_url_identifier' => 1234,
					'signals_in_case' => [ 'Test1', 'Test2' ],
					'users_in_case' => [ [ 'user_text' => 'abc' ] ],
				],
			],
			'Interaction data has all null case fields except case_id' => [
				[
					'case_note' => null,
					'case_url_identifier' => null,
					'signals_in_case' => null,
					'users_in_case' => null,
				],
			],
		];
	}

	public function testSubmitInteractionWithCaseIdNotOnReplicaDb() {
		$this->markTestSkippedIfExtensionNotLoaded( 'EventLogging' );
		$this->markTestSkippedIfExtensionNotLoaded( 'EventBus' );

		$this->enableSuggestedInvestigations();

		// Use a case ID that does not exist in the DB to simulate it not being defined on a replica DB
		$this->commonSubmitInteractionTest(
			[ 'case_id' => 1234 ],
			[
				'case_id' => 1234,
				'signals_in_case' => [],
				'users_in_case' => [],
			]
		);
	}

	/** @dataProvider provideSubmitInteractionWithPerformerId */
	public function testSubmitInteractionWithPerformerId( array $providedPerformerData ) {
		$this->markTestSkippedIfExtensionNotLoaded( 'EventLogging' );

		$userIdentity = $this->getTestUser()->getUserIdentity();
		$providedInteractionData = [
			'performer' => array_merge( [ 'id' => $userIdentity->getId() ], $providedPerformerData ),
		];

		// Generate the expected interaction data array
		$services = $this->getServiceContainer();

		$expectedInteractionData = [
			'performer' => [
				'id' => $userIdentity->getId(),
				'name' => array_key_exists( 'name', $providedPerformerData ) ?
					$providedPerformerData['name'] :
					$userIdentity->getName(),
				'groups' => array_key_exists( 'groups', $providedPerformerData ) ?
					$providedPerformerData['groups'] :
					$services->getUserGroupManager()->getUserEffectiveGroups( $userIdentity ),
				'edit_count' => array_key_exists( 'edit_count', $providedPerformerData ) ?
					$providedPerformerData['edit_count'] :
					$services->getUserEditTracker()->getUserEditCount( $userIdentity ),
				'registration_dt' => array_key_exists( 'registration_dt', $providedPerformerData ) ?
					$providedPerformerData['registration_dt'] :
					ConvertibleTimestamp::convert(
						TS_ISO_8601,
						$services->getUserRegistrationLookup()->getRegistration( $userIdentity )
					),
			],
		];

		$this->commonSubmitInteractionTest( $providedInteractionData, $expectedInteractionData );
	}

	public static function provideSubmitInteractionWithPerformerId(): array {
		return [
			'Interaction data only has performer_id field' => [ [] ],
			'Interaction data has all performer fields' => [ [
				'name' => 'test',
				'groups' => [ 'abc' ],
				'edit_count' => 1234,
				'registration_dt' => ConvertibleTimestamp::convert(
					TS_ISO_8601,
					'20250403020100'
				),
			] ],
			'Interaction data has all null performer fields except performer_id' => [ [
				'name' => null,
				'groups' => null,
				'edit_count' => null,
				'registration_dt' => null,
			] ],
		];
	}

	public function testSubmitInteractionWithInvalidPerformerId() {
		$this->markTestSkippedIfExtensionNotLoaded( 'EventLogging' );

		// The performer fields should just not be prefilled if the user ID in
		// 'performer_id' does not exist
		$this->commonSubmitInteractionTest( [ 'performer_id' => 1233445 ], [ 'performer_id' => 1233445 ] );
	}

	private function commonSubmitInteractionTest(
		array $providedInteractionData,
		array $expectedInteractionData
	): void {
		// Mock the MetricsClientFactory to return a mock MetricsClient so that we can check that the
		// event is being created with the correct stream name etc.
		$mockEventLoggingMetricsClient = $this->createMock( MetricsClient::class );
		$mockEventLoggingMetricsClient->expects( $this->once() )
			->method( 'submitInteraction' )
			->with(
				'mediawiki.product_metrics.suggested_investigations_interaction.v2',
				'/analytics/mediawiki/suggested_investigations/interaction/1.1.4',
				'test',
				$expectedInteractionData
			);

		$mockEventLoggingMetricsClientFactory = $this->createMock( MetricsClientFactory::class );
		$mockEventLoggingMetricsClientFactory->expects( $this->once() )
			->method( 'newMetricsClient' )
			->with( RequestContext::getMain() )
			->willReturn( $mockEventLoggingMetricsClient );
		$this->setService( 'EventLogging.MetricsClientFactory', $mockEventLoggingMetricsClientFactory );

		/** @var SuggestedInvestigationsInstrumentationClient $objectUnderTest */
		$objectUnderTest = $this->getServiceContainer()->get(
			'CheckUserSuggestedInvestigationsInstrumentationClient'
		);
		$objectUnderTest->submitInteraction( RequestContext::getMain(), 'test', $providedInteractionData );
	}

	public function testGetUserFragmentsArrayWhenEventBusNotInstalled() {
		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$mockExtensionRegistry->method( 'isLoaded' )
			->with( 'EventBus' )
			->willReturn( false );
		$this->setService( 'ExtensionRegistry', $mockExtensionRegistry );

		/** @var SuggestedInvestigationsInstrumentationClient $objectUnderTest */
		$objectUnderTest = $this->getServiceContainer()->get(
			'CheckUserSuggestedInvestigationsInstrumentationClient'
		);
		$this->assertSame(
			[],
			$objectUnderTest->getUserFragmentsArray( [
				UserIdentityValue::newRegistered( 1, 'Test' ),
				UserIdentityValue::newRegistered( 2, 'Testing' ),
			] )
		);
	}
}
