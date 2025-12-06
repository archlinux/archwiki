<?php

namespace MediaWiki\Extension\Scribunto;

use MediaWiki\Config\ConfigException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaInterpreterBadVersionError;
use MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaInterpreterNotFoundError;
use MediaWiki\Extension\Scribunto\Engines\LuaSandbox\LuaSandboxInterpreter;
use MediaWiki\Parser\Parser;
use WeakMap;

/**
 * Factory class to create a new lua engine
 */
class EngineFactory {
	/** @internal For use by ServiceWiring */
	public const CONSTRUCTOR_OPTIONS = [
		'ScribuntoDefaultEngine',
		'ScribuntoEngineConf',
	];

	private readonly ?string $defaultEngine;
	/** @var array<string,array> */
	private readonly array $engineConf;
	/** @var WeakMap<Parser,ScribuntoEngineBase> */
	private WeakMap $engineForParser;

	private ?ScribuntoEngineBase $cachedDefaultEngine = null;

	/** @internal For use by ServiceWiring */
	public function __construct(
		ServiceOptions $options,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->defaultEngine = $options->get( 'ScribuntoDefaultEngine' );
		$this->engineConf = $options->get( 'ScribuntoEngineConf' );
		$this->engineForParser = new WeakMap();
	}

	/**
	 * Create a new engine object with specified parameters.
	 */
	public function newEngine( array $options = [] ): ScribuntoEngineBase {
		if ( isset( $options['factory'] ) ) {
			return $options['factory']( $options );
		}
		if ( isset( $options['implementation'] ) ) {
			$implementation = $options['implementation'];
			unset( $options['implementation'] );
			switch ( $implementation ) {
				case 'autodetect':
					return $this->newAutodetectEngine( $options );
				default:
					throw new ConfigException( "Unknown implementation" );
			}
		}

		$class = $options['class'];
		return new $class( $options );
	}

	/**
	 * Create a new engine object with default parameters
	 *
	 * @param array $extraOptions Extra options to pass to the constructor,
	 *  in addition to the configured options
	 */
	public function getDefaultEngine( array $extraOptions = [] ): ScribuntoEngineBase {
		if ( $extraOptions === [] && $this->cachedDefaultEngine !== null ) {
			return $this->cachedDefaultEngine;
		}

		if ( !$this->defaultEngine ) {
			throw new ConfigException(
				'Scribunto extension is enabled but $wgScribuntoDefaultEngine is not set'
			);
		}

		// @phan-suppress-next-line PhanAccessReadOnlyProperty False positive, related to phan#5062
		if ( !isset( $this->engineConf[$this->defaultEngine] ) ) {
			throw new ConfigException( 'Invalid scripting engine is specified in $wgScribuntoDefaultEngine' );
		}
		$options = $extraOptions + $this->engineConf[$this->defaultEngine];
		$defaultEngine = $this->newEngine( $options );

		if ( $extraOptions === [] && $this->cachedDefaultEngine === null ) {
			$this->cachedDefaultEngine = $defaultEngine;
		}

		return $defaultEngine;
	}

	/**
	 * If luasandbox is installed and usable then use it,
	 * otherwise
	 */
	private function newAutodetectEngine( array $options ): ScribuntoEngineBase {
		$engine = 'luastandalone';
		try {
			LuaSandboxInterpreter::checkLuaSandboxVersion();
			$engine = 'luasandbox';
		} catch ( LuaInterpreterNotFoundError | LuaInterpreterBadVersionError ) {
			// pass
		}

		return $this->newEngine( $options + $this->engineConf[$engine] );
	}

	/**
	 * Get an engine instance for the given parser, and cache it
	 * so that subsequent calls to this function for the same parser will return
	 * the same engine.
	 */
	public function getEngineForParser( Parser $parser ): ScribuntoEngineBase {
		$this->engineForParser[$parser] ??= $this->getDefaultEngine( [
			'parser' => $parser,
			'title' => $parser->getTitle(),
		] );

		return $this->engineForParser[$parser];
	}

	public function peekEngineForParser( Parser $parser ): ?ScribuntoEngineBase {
		return $this->engineForParser[$parser] ?? null;
	}

	public function destroyEngineForParser( Parser $parser ): void {
		unset( $this->engineForParser[$parser] );
	}
}
