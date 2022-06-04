<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use Generator;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWikiIntegrationTestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * @group Test
 * @group AbuseFilter
 */
class AbuseFilterServicesTest extends MediaWikiIntegrationTestCase {
	/**
	 * @covers \MediaWiki\Extension\AbuseFilter\AbuseFilterServices
	 * @param string $getter
	 * @dataProvider provideGetters
	 */
	public function testServiceGetters( string $getter ) {
		// Methods are typehinted, so no need to assert
		AbuseFilterServices::$getter();
		$this->addToAssertionCount( 1 );
	}

	/**
	 * @return Generator
	 */
	public function provideGetters(): Generator {
		$clazz = new ReflectionClass( AbuseFilterServices::class );
		foreach ( $clazz->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
			$name = $method->getName();
			if ( strpos( $name, 'get' ) === 0 ) {
				yield $name => [ $name ];
			}
		}
	}
}
