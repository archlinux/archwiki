<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CheckUser\Maintenance\PopulateSicUrlIdentifier;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Rdbms\IMaintainableDatabase;
use Wikimedia\Services\NoSuchServiceException;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Maintenance\PopulateSicUrlIdentifier
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService
 */
class PopulateSicUrlIdentifierTest extends MaintenanceBaseTestCase {
	use SuggestedInvestigationsTestTrait;

	public function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
	}

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateSicUrlIdentifier::class;
	}

	public function testWhenSuggestedInvestigationsIsDisabled() {
		$this->disableSuggestedInvestigations();

		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Populating sic_url_identifier in cusi_case...', $actualOutputString );
		$this->assertStringContainsString(
			'Nothing to do as CheckUser Suggested Investigations is not enabled',
			$actualOutputString
		);
	}

	public function testWhenSuggestedInvestigationsCaseTableIsEmpty() {
		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Populating sic_url_identifier in cusi_case...', $actualOutputString );
		$this->assertStringContainsString(
			'Done. Populated 0 rows',
			$actualOutputString
		);
	}

	public function testWhenSuggestedInvestigationsCaseTableHasNoRowsToPopulate() {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		$firstCaseId = $caseManager->createCase(
			[ new UserIdentityValue( 1, 'TestUser' ) ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ) ]
		);
		$urlIdentifier = $this->newSelectQueryBuilder()
			->select( 'sic_url_identifier' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $firstCaseId ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertGreaterThan( 0, (int)$urlIdentifier, 'URL identifier should be set before population' );

		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Populating sic_url_identifier in cusi_case...', $actualOutputString );
		$this->assertStringContainsString(
			'Done. Populated 0 rows',
			$actualOutputString
		);

		// Check that the URL identifier has not changed through the script being run
		$this->newSelectQueryBuilder()
			->select( 'sic_url_identifier' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $firstCaseId ] )
			->caller( __METHOD__ )
			->assertFieldValue( $urlIdentifier );
	}

	public function testWhenSuggestedInvestigationsCaseTableHasRowsToPopulate() {
		// Schema changes are needed for this test, so skip this on postgres and sqlite
		// where the tests don't work.
		$this->markTestSkippedIfDbType( 'postgres' );
		$this->markTestSkippedIfDbType( 'sqlite' );

		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );

		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );
		$firstCaseId = $caseManager->createCase(
			[ UserIdentityValue::newRegistered( 1, 'Test user 1' ) ],
			[ $signal ]
		);
		$secondCaseId = $caseManager->createCase(
			[ UserIdentityValue::newRegistered( 2, 'Test user 2' ) ],
			[ $signal ]
		);
		$thirdCaseId = $caseManager->createCase(
			[ UserIdentityValue::newRegistered( 3, 'Test user 3' ) ],
			[ $signal ]
		);
		$fourthCaseId = $caseManager->createCase(
			[ UserIdentityValue::newRegistered( 4, 'Test user 4' ) ],
			[ $signal ]
		);

		$fourthCaseUrlIdentifier = $this->newSelectQueryBuilder()
			->select( 'sic_url_identifier' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $fourthCaseId ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertGreaterThan( 0, (int)$fourthCaseUrlIdentifier );

		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cusi_case' )
			->set( [ 'sic_url_identifier' => 0 ] )
			->where( [ 'sic_id' => [ $firstCaseId, $secondCaseId, $thirdCaseId ] ] )
			->caller( __METHOD__ )
			->execute();

		$this->maintenance->loadWithArgv( [ '--batch-size', 2 ] );
		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Populating sic_url_identifier in cusi_case...', $actualOutputString );
		$this->assertStringContainsString(
			'Done. Populated 3 rows',
			$actualOutputString
		);

		// Check that the fourth case URL identifier has not changed through the script being run
		$this->newSelectQueryBuilder()
			->select( 'sic_url_identifier' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $fourthCaseId ] )
			->caller( __METHOD__ )
			->assertFieldValue( $fourthCaseUrlIdentifier );

		// No cases should exist that are missing a URL identifier after the script has run
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cusi_case' )
			->where( [ 'sic_url_identifier' => 0 ] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	public function testWhenSuggestedInvestigationsCaseTableHasRowsToPopulateWithoutSiteConfigAvailable() {
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );

		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );
		$caseManager->createCase( [ UserIdentityValue::newRegistered( 1, 'Test user 1' ) ], [ $signal ] );

		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cusi_case' )
			->set( [ 'sic_url_identifier' => 0 ] )
			->where( [ '1=1' ] )
			->caller( __METHOD__ )
			->execute();

		// Mock that site config is not yet defined and that the CheckUserSuggestedInvestigationsCaseManager service
		// is not yet defined in MediaWikiServices. This occurs when running the script via update.php
		$this->setService(
			'CheckUserSuggestedInvestigationsCaseManager',
			static fn () => throw new NoSuchServiceException( 'CheckUserSuggestedInvestigationsCaseManager' )
		);
		$this->maintenance->setConfig( new HashConfig() );

		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Populating sic_url_identifier in cusi_case...', $actualOutputString );
		$this->assertStringContainsString(
			'Done. Populated 1 rows',
			$actualOutputString
		);

		// No cases should exist that are missing a URL identifier after the script has run
		$this->newSelectQueryBuilder()
			->select( '1' )
			->from( 'cusi_case' )
			->where( [ 'sic_url_identifier' => 0 ] )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	/**
	 * Makes sic_url_identifier non-unique during the execution of this test class.
	 *
	 * @inheritDoc
	 */
	protected function getSchemaOverrides( IMaintainableDatabase $db ) {
		// The schema changes don't work on postgres and require temporary tables on sqlite (which are fragile),
		// so the tests are skipped (and therefore no need to attempt to make the schema changes either).
		if ( $db->getType() !== 'mysql' ) {
			return [];
		}

		return [
			'scripts' => [
				__DIR__ . '/patches/' . $db->getType() . '/patch-cusi_case-sic_url_identifier-non_unique.sql',
			],
			'drop' => [ 'cusi_case' ],
		];
	}
}
