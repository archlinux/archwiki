<?php

namespace MediaWiki\Extension\Notifications\Test\Structure;

/**
 * @group Echo
 */
class EchoBundleSizeTest extends \MediaWiki\Tests\Structure\BundleSizeTestBase {

	/** @inheritDoc */
	public function getBundleSizeConfig(): string {
		return dirname( __DIR__, 3 ) . '/bundlesize.config.json';
	}
}
