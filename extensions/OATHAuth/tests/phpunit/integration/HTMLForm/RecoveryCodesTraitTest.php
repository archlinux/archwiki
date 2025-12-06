<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration;

use LogicException;
use MediaWiki\Config\MultiConfig;
use MediaWiki\Extension\OATHAuth\HTMLForm\RecoveryCodesTrait;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\HTMLForm\RecoveryCodesTrait
 */
class RecoveryCodesTraitTest extends MediaWikiIntegrationTestCase {
	use RecoveryCodesTrait;

	public function getOutput() {
		throw new LogicException( 'Should not be called' );
	}

	public function getLanguage() {
		throw new LogicException( 'Should not be called' );
	}

	public function msg( $key, ...$params ) {
		throw new LogicException( 'Should not be called' );
	}

	public function getConfig(): MultiConfig {
		return OATHAuthServices::getInstance(
			$this->getServiceContainer()
		)->getConfig();
	}

	public function testGetRecoveryCodesForDisplay(): void {
		$this->setMwGlobals( 'wgOATHRecoveryCodesCount', 1 );

		$recCodeKeys = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [] ] );
		$recCodeKeys->regenerateRecoveryCodeKeys();

		$formatted1 = array_map( [ $this, 'tokenFormatterFunction' ], $recCodeKeys->getRecoveryCodeKeys() );
		$formatted2 = $this->getRecoveryCodesForDisplay( $recCodeKeys );
		$this->assertSame( $formatted1, $formatted2 );
	}

	public function provideTokenFormatterData(): array {
		return [
			[ 'ABCDEFGHIJKLMNOP', 'ABCD EFGH IJKL MNOP' ],
			[ '1234567891011121', '1234 5678 9101 1121' ],
			[ 'ABCD', 'ABCD' ],
			[ '1', '1' ]
		];
	}

	/**
	 * @dataProvider provideTokenFormatterData
	 */
	public function testTokenFormatterFunction( $token1, $token2 ): void {
		$this->assertSame( $this->tokenFormatterFunction( $token1 ), $token2 );
	}

	public function provideCreateTextListData(): array {
		return [
			[ [ 'Hey this is a test' ], "Hey this is a test" ],
			[ [ 'token1', 'token2', 'token3' ], "* token1\n* token2\n* token3" ]
		];
	}

	/**
	 * @dataProvider provideCreateTextListData
	 */
	public function testCreateTextList( $array, $string ): void {
		$this->assertSame( $this->createTextList( $array ), $string );
	}
}
