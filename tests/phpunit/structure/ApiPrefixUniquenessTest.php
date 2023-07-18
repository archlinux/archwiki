<?php

use MediaWiki\Request\FauxRequest;

/**
 * Checks that all API query modules, core and extensions, have unique prefixes.
 *
 * @group API
 * @coversNothing
 */
class ApiPrefixUniquenessTest extends MediaWikiIntegrationTestCase {

	public function testPrefixes() {
		$main = new ApiMain( new FauxRequest() );
		$query = $main->getModuleManager()->getModule( 'query' );
		$moduleManager = $query->getModuleManager();

		$modules = $moduleManager->getNames();
		$prefixes = [];

		foreach ( $modules as $name ) {
			$module = $moduleManager->getModule( $name );
			$class = get_class( $module );

			$prefix = $module->getModulePrefix();
			if ( $prefix === '' /* HACK: T196962 */ || $prefix === 'wbeu' ) {
				continue;
			}

			if ( isset( $prefixes[$prefix] ) ) {
				$this->fail(
					"Module prefix '{$prefix}' is shared between {$class} and {$prefixes[$prefix]}"
				);
			}
			$prefixes[$module->getModulePrefix()] = $class;

			if ( $module instanceof ApiQueryGeneratorBase ) {
				// namespace with 'g', a generator can share a prefix with a module
				$prefix = 'g' . $prefix;
				if ( isset( $prefixes[$prefix] ) ) {
					$this->fail(
						"Module prefix '{$prefix}' is shared between {$class} and {$prefixes[$prefix]}" .
							" (as a generator)"
					);
				}
				$prefixes[$module->getModulePrefix()] = $class;
			}
		}
		$this->assertTrue( true ); // dummy call to make this test non-incomplete
	}
}
