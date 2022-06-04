<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use AbuseFilterConsequencesTest;
use MediaWiki\Hook\UnitTestsAfterDatabaseSetupHook;
use MediaWiki\Hook\UnitTestsBeforeDatabaseTeardownHook;
use Wikimedia\Rdbms\IMaintainableDatabase;

/**
 * @codeCoverageIgnore This is test code
 */
class TestsHandler implements UnitTestsAfterDatabaseSetupHook, UnitTestsBeforeDatabaseTeardownHook {
	/**
	 * Setup tables to emulate global filters, used in AbuseFilterConsequencesTest.
	 *
	 * @param IMaintainableDatabase $db
	 * @param string $prefix The prefix used in unit tests
	 * @suppress PhanUndeclaredClassConstant AbuseFilterConsequencesTest is in AutoloadClasses
	 * @suppress PhanUndeclaredClassStaticProperty AbuseFilterConsequencesTest is in AutoloadClasses
	 */
	public function onUnitTestsAfterDatabaseSetup( $db, $prefix ) {
		$externalPrefix = AbuseFilterConsequencesTest::DB_EXTERNAL_PREFIX;
		if ( $db->tableExists( $externalPrefix . AbuseFilterConsequencesTest::$externalTables[0], __METHOD__ ) ) {
			// Check a random table to avoid unnecessary table creations. See T155147.
			return;
		}

		foreach ( AbuseFilterConsequencesTest::$externalTables as $table ) {
			// Don't create them as temporary, as we'll access the DB via another connection
			if ( $db->getType() === 'sqlite' ) {
				// SQLite definitions don't have the prefix, ref T251967
				$db->duplicateTableStructure(
					$table,
					"$prefix$externalPrefix$table",
					true,
					__METHOD__
				);
				$db->query( "INSERT INTO $prefix$externalPrefix$table SELECT * FROM $prefix$table" );
			} else {
				$db->duplicateTableStructure(
					"$prefix$table",
					"$prefix$externalPrefix$table",
					false,
					__METHOD__
				);
			}
		}
	}

	/**
	 * Drop tables used for global filters in AbuseFilterConsequencesTest.
	 *   Note: this has the same problem as T201290.
	 *
	 * @suppress PhanUndeclaredClassConstant AbuseFilterConsequencesTest is in AutoloadClasses
	 * @suppress PhanUndeclaredClassStaticProperty AbuseFilterConsequencesTest is in AutoloadClasses
	 */
	public function onUnitTestsBeforeDatabaseTeardown() {
		$db = wfGetDB( DB_PRIMARY );
		foreach ( AbuseFilterConsequencesTest::$externalTables as $table ) {
			$db->dropTable( AbuseFilterConsequencesTest::DB_EXTERNAL_PREFIX . $table );
		}
	}
}
