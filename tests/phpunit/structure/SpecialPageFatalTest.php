<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\User\UserIdentityValue;

/**
 * Test that runs against all registered special pages to make sure that regular
 * execution of the special page does not cause a fatal error.
 *
 * UTSysop is used to run as much of the special page code as possible without
 * actually knowing the details of the special page.
 *
 * @since 1.32
 * @author Addshore
 * @coversNothing
 */
class SpecialPageFatalTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Deprecations don't matter for what this test cares about. This made browser tests fail
		// on many occasions already. (T236809)
		$this->filterDeprecated( '//' );
	}

	public function testSpecialPageDoesNotFatal() {
		$spf = MediaWikiServices::getInstance()->getSpecialPageFactory();
		foreach ( $spf->getNames() as $name ) {

			$page = $spf->getPage( $name );
			if ( !$page ) {
				$this->markTestSkipped( "Could not create special page $name" );
			}

			$executor = new SpecialPageExecutor();
			$authority = new UltimateAuthority( new UserIdentityValue( 0, 'UTSysop' ) );

			try {
				$executor->executeSpecialPage( $page, '', null, 'qqx', $authority );
			} catch ( \PHPUnit\Framework\Error\Error $error ) {
				// Let phpunit settings working:
				// - convertDeprecationsToExceptions="true"
				// - convertErrorsToExceptions="true"
				// - convertNoticesToExceptions="true"
				// - convertWarningsToExceptions="true"
				throw $error;
			} catch ( Exception $e ) {
				// Other exceptions are allowed
			}

			// If the page fataled phpunit will have already died
			$this->addToAssertionCount( 1 );
		}
	}

}
