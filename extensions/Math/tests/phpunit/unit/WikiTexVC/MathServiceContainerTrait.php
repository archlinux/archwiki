<?php

namespace MediaWiki\Extension\Math\Tests\WikiTexVC;

use MediaWiki\Extension\Math\InputCheck\InputCheckFactory;
use MediaWiki\Extension\Math\Math;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLDomVisitor;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\VisitorFactory;
use Psr\Container\ContainerInterface;

trait MathServiceContainerTrait {
	/**
	 * Sets up mocked services for Math classes.
	 */
	protected function setUpMathServiceContainer(): void {
		$visitorFactory = $this->createMock( VisitorFactory::class );
		$visitorFactory->method( 'createVisitor' )
			->willReturnCallback( static function () {
				return new MMLDomVisitor();
			} );
		$mockContainer = $this->createMock( ContainerInterface::class );
		$mockContainer->method( 'get' )->willReturnMap(
			$this->getMockMathServices( $visitorFactory )
		);

		Math::setServiceContainer( $mockContainer );
	}

	/**
	 * Define mocked services. Override to add more services.
	 * @param VisitorFactory $visitorFactory
	 * @return array[] Return value for willReturnMap()
	 */
	protected function getMockMathServices( VisitorFactory $visitorFactory ): array {
		return [
			[ 'Math.MathMLTreeVisitor', $visitorFactory ],
			[ 'Math.Config', $this->createMock( MathConfig::class ) ],
			[ 'Math.CheckerFactory', $this->createMock( InputCheckFactory::class ) ]
		];
	}
}
