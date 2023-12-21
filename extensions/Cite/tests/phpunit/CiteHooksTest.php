<?php

namespace Cite\Tests;

use ApiQuerySiteinfo;
use Cite\Hooks\CiteHooks;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\StaticUserOptionsLookup;

/**
 * @coversDefaultClass \Cite\Hooks\CiteHooks
 *
 * @license GPL-2.0-or-later
 */
class CiteHooksTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @covers ::onResourceLoaderGetConfigVars
	 */
	public function testOnResourceLoaderGetConfigVars() {
		$vars = [];

		$config = MediaWikiServices::getInstance()->getMainConfig();

		$citeHooks = new CiteHooks( new StaticUserOptionsLookup( [] ) );
		$citeHooks->onResourceLoaderGetConfigVars( $vars, 'vector', $config );

		$this->assertArrayHasKey( 'wgCiteVisualEditorOtherGroup', $vars );
		$this->assertArrayHasKey( 'wgCiteResponsiveReferences', $vars );
	}

	/**
	 * @covers ::onAPIQuerySiteInfoGeneralInfo
	 */
	public function testOnAPIQuerySiteInfoGeneralInfo() {
		$api = $this->createMock( ApiQuerySiteinfo::class );
		$api->expects( $this->once() )
			->method( 'getConfig' )
			->willReturn( MediaWikiServices::getInstance()->getMainConfig() );

		$data = [];

		$citeHooks = new CiteHooks( new StaticUserOptionsLookup( [] ) );
		$citeHooks->onAPIQuerySiteInfoGeneralInfo( $api, $data );

		$this->assertArrayHasKey( 'citeresponsivereferences', $data );
	}

}
