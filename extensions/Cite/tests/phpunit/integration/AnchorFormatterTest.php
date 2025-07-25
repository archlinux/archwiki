<?php

namespace Cite\Tests\Integration;

use Cite\AnchorFormatter;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\Sanitizer;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Cite\AnchorFormatter
 * @license GPL-2.0-or-later
 */
class AnchorFormatterTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( MainConfigNames::FragmentMode, [ 'html5' ] );
	}

	public function testBackLink() {
		$formatter = new AnchorFormatter();

		$this->assertSame(
			'cite_ref-1',
			$formatter->backLink( null, 1, 0 ) );
		$this->assertSame(
			'cite_ref-name_2-0',
			$formatter->backLink( 'name', 2, 1 ) );
	}

	public function testJumpLink() {
		$formatter = new AnchorFormatter();

		$this->assertSame(
			'cite_note-name-1',
			$formatter->jumpLink( 'name', 1 ) );
	}

	/**
	 * @dataProvider provideFragmentIdentifierNormalizations
	 */
	public function testFragmentIdentifierNormalization( string $id, string $expected ) {
		/** @var AnchorFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new AnchorFormatter() );
		$normalized = $formatter->normalizeFragmentIdentifier( $id );
		$encoded = Sanitizer::safeEncodeAttribute( Sanitizer::escapeIdForLink( $normalized ) );
		$this->assertSame( $expected, $encoded );
	}

	public static function provideFragmentIdentifierNormalizations() {
		return [
			[ 'a b', 'a_b' ],
			[ 'a  __  b', 'a_b' ],
			[ ':', ':' ],
			[ "\t\n", '_' ],
			[ "'", '&#039;' ],
			[ "''", '&#039;&#039;' ],
			[ '"%&/<>?[]{|}', '&quot;%&amp;/&lt;&gt;?&#91;&#93;&#123;&#124;&#125;' ],
			[ 'ISBN', '&#73;SBN' ],

			[ 'nature%20phylo', 'nature%2520phylo' ],
			[ 'Mininova%2E26%2E11%2E2009', 'Mininova%252E26%252E11%252E2009' ],
			[ '%c8%98tiri_2019', '%25c8%2598tiri_2019' ],
			[ 'play%21', 'play%2521' ],
			[ 'Terry+O%26rsquo%3BN…</ref', 'Terry+O%2526rsquo%253BN…&lt;/ref' ],
			[ '9&nbsp;pm', '9&amp;nbsp;pm' ],
			[ 'n%25%32%30n', 'n%2525%2532%2530n' ],
			[ 'a_ %20a', 'a_%2520a' ],
		];
	}

}
