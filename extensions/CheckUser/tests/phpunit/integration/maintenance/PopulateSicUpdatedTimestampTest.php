<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\Extension\CheckUser\Maintenance\PopulateSicUpdatedTimestamp;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseManagerService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\Rdbms\IMaintainableDatabase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\Maintenance\PopulateSicUpdatedTimestamp
 */
class PopulateSicUpdatedTimestampTest extends MaintenanceBaseTestCase {
	use SuggestedInvestigationsTestTrait;

	public function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
	}

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateSicUpdatedTimestamp::class;
	}

	public function testWhenSuggestedInvestigationsIsDisabled() {
		$this->disableSuggestedInvestigations();

		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Populating sic_updated_timestamp in cusi_case...',
			$actualOutputString
		);
		$this->assertStringContainsString(
			'Nothing to do as CheckUser Suggested Investigations is not enabled',
			$actualOutputString
		);
	}

	public function testWhenSuggestedInvestigationsCaseTableIsEmpty() {
		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Populating sic_updated_timestamp in cusi_case...',
			$actualOutputString
		);
		$this->assertStringContainsString(
			'Done. Populated 0 rows',
			$actualOutputString
		);
	}

	public function testWhenSuggestedInvestigationsCaseTableHasNoRowsToPopulate() {
		ConvertibleTimestamp::setFakeTime( '20260504030201' );
		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );
		$firstCaseId = $caseManager->createCase(
			[ new UserIdentityValue( 1, 'TestUser' ) ],
			[ SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false ) ]
		);

		// Cause the sic_updated_timestamp column to be different to sic_created_timestamp
		// so we can check that the script does not overwrite any currently set values
		// for sic_updated_timestamp
		ConvertibleTimestamp::setFakeTime( '20260504030202' );
		$caseManager->updateCase( $firstCaseId, [ new UserIdentityValue( 2, 'TestUser2' ) ], [] );

		$updatedTimestamp = $this->newSelectQueryBuilder()
			->select( 'sic_updated_timestamp' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $firstCaseId ] )
			->caller( __METHOD__ )
			->fetchField();
		$this->assertSame(
			$this->getDb()->timestamp( '20260504030202' ),
			$updatedTimestamp,
			'Updated timestamp not as expected for the test'
		);

		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Populating sic_updated_timestamp in cusi_case...',
			$actualOutputString
		);
		$this->assertStringContainsString(
			'Done. Populated 0 rows',
			$actualOutputString
		);

		// Check that the URL identifier has not changed through the script being run
		$this->newSelectQueryBuilder()
			->select( 'sic_updated_timestamp' )
			->from( 'cusi_case' )
			->where( [ 'sic_id' => $firstCaseId ] )
			->caller( __METHOD__ )
			->assertFieldValue( $updatedTimestamp );
	}

	public function testWhenSuggestedInvestigationsCaseTableHasRowsToPopulate() {
		$this->markTestSkippedIfDbType( 'sqlite' );

		/** @var SuggestedInvestigationsCaseManagerService $caseManager */
		$caseManager = $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsCaseManager' );

		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'Lorem', 'ipsum', false );
		ConvertibleTimestamp::setFakeTime( '20260504030201' );
		$firstCaseId = $caseManager->createCase(
			[ UserIdentityValue::newRegistered( 1, 'Test user 1' ) ],
			[ $signal ]
		);
		ConvertibleTimestamp::setFakeTime( '20260504030202' );
		$secondCaseId = $caseManager->createCase(
			[ UserIdentityValue::newRegistered( 2, 'Test user 2' ) ],
			[ $signal ]
		);
		ConvertibleTimestamp::setFakeTime( '20260504030203' );
		$thirdCaseId = $caseManager->createCase(
			[ UserIdentityValue::newRegistered( 3, 'Test user 3' ) ],
			[ $signal ]
		);
		ConvertibleTimestamp::setFakeTime( '20260504030204' );
		$fourthCaseId = $caseManager->createCase(
			[ UserIdentityValue::newRegistered( 4, 'Test user 4' ) ],
			[ $signal ]
		);

		// Set two rows to use a value of null as sic_updated_timestamp
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'cusi_case' )
			->set( [ 'sic_updated_timestamp' => null ] )
			->where( [ 'sic_id' => [ $firstCaseId, $secondCaseId ] ] )
			->caller( __METHOD__ )
			->execute();

		// On some non-release branches of 1.46, the sic_updated_timestamp could have
		// a default of an empty string (T415348).
		// We still set one row to an empty string if the column is nullable so that
		// we can test both null and empty string checks. We don't do this for postgres
		// as the column never supported an empty string in the column.
		if ( $this->getDb()->getType() === 'postgres' ) {
			$this->getDb()->newUpdateQueryBuilder()
				->update( 'cusi_case' )
				->set( [ 'sic_updated_timestamp' => null ] )
				->where( [ 'sic_id' => $thirdCaseId ] )
				->caller( __METHOD__ )
				->execute();
		} else {
			$this->getDb()->newUpdateQueryBuilder()
				->update( 'cusi_case' )
				->set( [ 'sic_updated_timestamp' => '' ] )
				->where( [ 'sic_id' => $thirdCaseId ] )
				->caller( __METHOD__ )
				->execute();
		}

		$this->maintenance->loadWithArgv( [ '--batch-size', 2 ] );
		$this->maintenance->execute();

		$actualOutputString = $this->getActualOutputForAssertion();
		$this->assertStringContainsString(
			'Populating sic_updated_timestamp in cusi_case...',
			$actualOutputString
		);
		$this->assertStringContainsString(
			'Done. Populated 3 rows',
			$actualOutputString
		);

		// Check that the sic_updated_timestamp has been populated
		// by copying values from sic_created_timestamp
		$this->newSelectQueryBuilder()
			->select( [ 'sic_id', 'sic_updated_timestamp' ] )
			->from( 'cusi_case' )
			->caller( __METHOD__ )
			->assertResultSet( [
				[ $firstCaseId, $this->getDb()->timestamp( '20260504030201' ) ],
				[ $secondCaseId, $this->getDb()->timestamp( '20260504030202' ) ],
				[ $thirdCaseId, $this->getDb()->timestamp( '20260504030203' ) ],
				[ $fourthCaseId, $this->getDb()->timestamp( '20260504030204' ) ],
			] );
	}

	/** @inheritDoc */
	protected function getSchemaOverrides( IMaintainableDatabase $db ): array {
		// The schema changes require temporary tables on sqlite (which are fragile), so the tests
		// are skipped for sqlite (including the need to make any schema modifications for the test)
		if ( $db->getType() === 'sqlite' ) {
			return [];
		}

		return [
			'scripts' => [
				__DIR__ . '/patches/' . $db->getType() .
					'/patch-cusi_case-modify-sic_updated_timestamp-nullable.sql',
			],
			'drop' => [ 'cusi_case' ],
		];
	}
}
