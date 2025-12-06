<?php

declare( strict_types = 1 );

namespace Cite\Tests\Integration\Config;

use ExtensionRegistry;
use MediaWiki\Extension\CommunityConfiguration\Tests\SchemaProviderTestCase;
use MediaWikiIntegrationTestCase;

// TODO: Rewrite CommunityConfiguration helper to allow composition instead of inheritence.
// Thus supporting regular markTestSkippedIfExtensionNotLoaded() from setUp().
// phpcs:disable Generic.Classes.DuplicateClassName.Found
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
if ( !ExtensionRegistry::getInstance()->isLoaded( 'CommunityConfiguration' ) ) {
	class CiteSchemaProviderTest extends MediaWikiIntegrationTestCase {
		public static function setUpBeforeClass(): void {
			self::markTestSkipped( "Extension CommunityConfiguration is required for this test" );
		}
	}
	return;
}

/**
 * @coversNothing
 * @license GPL-2.0-or-later
 */
class CiteSchemaProviderTest extends SchemaProviderTestCase {
	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( 'CiteBacklinkCommunityConfiguration', true );
	}

	protected function getExtensionName(): string {
		return 'Cite';
	}

	protected function getProviderId(): string {
		return 'Cite';
	}

}
