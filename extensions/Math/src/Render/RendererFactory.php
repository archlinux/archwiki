<?php

namespace MediaWiki\Extension\Math\Render;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathLaTeXML;
use MediaWiki\Extension\Math\MathMathML;
use MediaWiki\Extension\Math\MathMathMLCli;
use MediaWiki\Extension\Math\MathPng;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Extension\Math\MathSource;
use MediaWiki\User\UserOptionsLookup;
use Psr\Log\LoggerInterface;

class RendererFactory {

	/** @var string[] */
	public const CONSTRUCTOR_OPTIONS = [
		'MathoidCli',
		'MathEnableExperimentalInputFormats',
		'MathValidModes',
	];

	/** @var ServiceOptions */
	private $options;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var MathConfig */
	private $mathConfig;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param ServiceOptions $serviceOptions
	 * @param MathConfig $mathConfig
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		ServiceOptions $serviceOptions,
		MathConfig $mathConfig,
		UserOptionsLookup $userOptionsLookup,
		LoggerInterface $logger
	) {
		$serviceOptions->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $serviceOptions;
		$this->mathConfig = $mathConfig;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->logger = $logger;
	}

	/**
	 * Factory method for getting a renderer based on mode
	 *
	 * @param string $tex LaTeX markup
	 * @param array $params HTML attributes
	 * @param string $mode indicating rendering mode, one of MathConfig::MODE_*
	 * @return MathRenderer appropriate renderer for mode
	 */
	public function getRenderer(
		string $tex,
		array $params = [],
		string $mode = MathConfig::MODE_PNG
	): MathRenderer {
		if ( isset( $params['forcemathmode'] ) ) {
			$mode = $params['forcemathmode'];
		}
		if ( !in_array( $mode, $this->mathConfig->getValidRenderingModes() ) ) {
			$mode = $this->userOptionsLookup->getDefaultOption( 'math' );
		}
		if ( $this->options->get( 'MathEnableExperimentalInputFormats' ) === true &&
			$mode == MathConfig::MODE_MATHML &&
			isset( $params['type'] )
		) {
			// Support of MathML input (experimental)
			// Currently support for mode 'mathml' only
			if ( !in_array( $params['type'], [ 'pmml', 'ascii' ] ) ) {
				unset( $params['type'] );
			}
		}
		if ( isset( $params['chem'] ) ) {
			$mode = MathConfig::MODE_MATHML;
			$params['type'] = 'chem';
		}
		switch ( $mode ) {
			case MathConfig::MODE_SOURCE:
				$renderer = new MathSource( $tex, $params );
				break;
			case MathConfig::MODE_PNG:
				$renderer = new MathPng( $tex, $params );
				break;
			case MathConfig::MODE_LATEXML:
				$renderer = new MathLaTeXML( $tex, $params );
				break;
			case MathConfig::MODE_MATHML:
			default:
				if ( $this->options->get( 'MathoidCli' ) ) {
					$renderer = new MathMathMLCli( $tex, $params );
				} else {
					$renderer = new MathMathML( $tex, $params );
				}
		}
		$this->logger->debug(
			'Start rendering "{tex}" in mode {mode}',
			[
				'tex' => $tex,
				'mode' => $mode
			]
		);
		return $renderer;
	}
}
