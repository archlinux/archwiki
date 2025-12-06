<?php

namespace Cite\Tests\Unit;

use Cite\AlphabetsProvider;
use Cite\Cite;
use Cite\CiteFactory;
use MediaWiki\Config\Config;
use MediaWiki\Parser\Parser;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Cite\CiteFactory
 * @license GPL-2.0-or-later
 */
class CiteFactoryTest extends \MediaWikiUnitTestCase {

	private function newCiteFactory(): CiteFactory {
		return new CiteFactory(
			$this->createNoOpMock( Config::class ),
			$this->createNoOpMock( AlphabetsProvider::class ),
			null
		);
	}

	public function testGet() {
		$parser = $this->createNoOpMock( Parser::class );
		$cite = $this->createNoOpMock( Cite::class );
		$citeFactory = TestingAccessWrapper::newFromObject( $this->newCiteFactory() );
		$citeFactory->citeForParser[$parser] = $cite;

		$this->assertSame( $cite, $citeFactory->getCiteForParser( $parser ) );
	}

	public function testPeek() {
		$parser = $this->createNoOpMock( Parser::class );
		$cite = $this->createNoOpMock( Cite::class );
		$citeFactory = TestingAccessWrapper::newFromObject( $this->newCiteFactory() );

		$this->assertNull( $citeFactory->peekCiteForParser( $parser ) );
		$citeFactory->citeForParser[$parser] = $cite;
		$this->assertNotNull( $citeFactory->peekCiteForParser( $parser ) );
	}

	public function testDestroy() {
		$parser = $this->createNoOpMock( Parser::class );
		$cite = $this->createNoOpMock( Cite::class );
		$citeFactory = TestingAccessWrapper::newFromObject( $this->newCiteFactory() );
		$citeFactory->citeForParser[$parser] = $cite;

		$citeFactory->destroyCiteForParser( $parser );
		$this->assertFalse( isset( $citeFactory->citeForParser[$parser] ) );
	}

}
