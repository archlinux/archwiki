<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Parser;

use MediaWiki\Extension\AbuseFilter\Parser\Exception\UserVisibleException;
use MediaWiki\Extension\AbuseFilter\Parser\Exception\UserVisibleWarning;
use MediaWiki\Extension\AbuseFilter\Parser\ParserStatus;
use MediaWikiUnitTestCase;

/**
 * @group Test
 * @group AbuseFilter
 * @group AbuseFilterParser
 *
 * @covers \MediaWiki\Extension\AbuseFilter\Parser\ParserStatus
 */
class ParserStatusTest extends MediaWikiUnitTestCase {

	public function testGetters() {
		$exc = $this->createMock( UserVisibleException::class );
		$warnings = [ new UserVisibleWarning( 'foo', 1, [] ) ];
		$condsUsed = 42;
		$status = new ParserStatus( $exc, $warnings, $condsUsed );
		$this->assertSame( $exc, $status->getException() );
		$this->assertSame( $warnings, $status->getWarnings() );
		$this->assertSame( $condsUsed, $status->getCondsUsed() );
	}

	public function testIsValid_true() {
		$status = new ParserStatus( null, [], 42 );
		$this->assertTrue( $status->isValid() );
	}

	public function testIsValid_false() {
		$status = new ParserStatus( $this->createMock( UserVisibleException::class ), [], 42 );
		$this->assertFalse( $status->isValid() );
	}

}
