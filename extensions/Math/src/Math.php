<?php

namespace MediaWiki\Extension\Math;

use MediaWiki\Extension\Math\InputCheck\InputCheckFactory;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\VisitorFactory;
use MediaWiki\MediaWikiServices;
use Psr\Container\ContainerInterface;

/**
 * Top level factory for the Math extension.
 *
 * @license GPL-2.0-or-later
 */
final class Math {
	/**
	 * @codeCoverageIgnore
	 */
	private function __construct() {
		// should not be instantiated
	}

	public static function getMathConfig( ?ContainerInterface $services = null ): MathConfig {
		return self::getService( 'Math.Config', $services );
	}

	public static function getCheckerFactory( ?ContainerInterface $services = null ): InputCheckFactory {
		return self::getService( 'Math.CheckerFactory', $services );
	}

	public static function getVisitorFactory( ?ContainerInterface $services = null ): VisitorFactory {
		return self::getService( 'Math.MathMLTreeVisitor', $services );
	}

	/**
	 * Retrieves a service instance from the specified or default container.
	 * @param string $serviceName Service identifier to retrieve
	 * @param ContainerInterface|null $services Optional container override for unit tests
	 * @return mixed Service instance
	 */
	private static function getService( string $serviceName, ?ContainerInterface $services = null ) {
		return ( $services ?? MediaWikiServices::getInstance() )->getService( $serviceName );
	}
}
