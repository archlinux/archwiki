<?php

namespace Cite\Tests;

use Cite\ReferenceMessageLocalizer;

/**
 * @covers \Cite\ReferenceMessageLocalizer
 * @license GPL-2.0-or-later
 */
class ReferenceMessageLocalizerTest extends \MediaWikiIntegrationTestCase {

	public function testMsg() {
		$lang = $this->getServiceContainer()->getLanguageFactory()->getLanguage( 'qqx' );
		$localizer = new ReferenceMessageLocalizer( $lang );
		$this->assertSame(
			'(cite-desc)',
			$localizer->msg( 'cite-desc' )->plain() );
	}

}
