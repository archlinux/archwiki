<?php

use MediaWiki\Extension\Math\InputCheck\InputCheckFactory;
use MediaWiki\Extension\Math\InputCheck\MathoidChecker;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;

/**
 * @method InputCheckFactory newServiceInstance( string $serviceClass, array $parameterOverrides )
 * @covers \MediaWiki\Extension\Math\InputCheck\InputCheckFactory
 */
class InputCheckFactoryTest extends MediaWikiUnitTestCase {
	use MockServiceDependenciesTrait;

	public function testNewMathoidChecker() {
		$checker = $this->newServiceInstance( InputCheckFactory::class, [] )
			->newMathoidChecker( 'FORMULA', 'TYPE' );
		$this->assertInstanceOf( MathoidChecker::class, $checker );
	}
}
