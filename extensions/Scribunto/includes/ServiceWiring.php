<?php

namespace MediaWiki\Extension\Scribunto;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MediaWikiServices;

// PHP unit does not understand code coverage for this file
// as the @covers annotation cannot cover a specific file
// This is fully tested in ScribuntoServiceWiringTest.php
// @codeCoverageIgnoreStart

/**
 * Scribunto wiring for MediaWiki services.
 */
return [
	'Scribunto.EngineFactory' => static function ( MediaWikiServices $services ): EngineFactory {
		return new EngineFactory(
			new ServiceOptions(
				EngineFactory::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig()
			),
		);
	},
];
// @codeCoverageIgnoreEnd
