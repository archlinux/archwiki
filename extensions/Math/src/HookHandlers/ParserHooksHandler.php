<?php

namespace MediaWiki\Extension\Math\HookHandlers;

use FatalError;
use Hooks as MWHooks;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathMathML;
use MediaWiki\Extension\Math\MathMathMLCli;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Hook\ParserAfterTidyHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Hook\ParserOptionsRegisterHook;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserOptionsLookup;
use MWException;
use Parser;
use ParserOptions;

/**
 * Hook handler for Parser hooks
 */
class ParserHooksHandler implements
	ParserFirstCallInitHook,
	ParserAfterTidyHook,
	ParserOptionsRegisterHook
{

	/** @var int */
	private $mathTagCounter = 1;

	/** @var array[] renders delayed to be done as a batch [ MathRenderer, Parser ] */
	private $mathLazyRenderBatch = [];

	/** @var RendererFactory */
	private $rendererFactory;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/**
	 * @param RendererFactory $rendererFactory
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		RendererFactory $rendererFactory,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->rendererFactory = $rendererFactory;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * Register the <math> tag with the Parser.
	 *
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'math', [ $this, 'mathTagHook' ] );
		// @deprecated the ce tag is deprecated in favour of chem cf. T153606
		$parser->setHook( 'ce', [ $this, 'chemTagHook' ] );
		$parser->setHook( 'chem', [ $this, 'chemTagHook' ] );
	}

	/**
	 * Callback function for the <math> parser hook.
	 *
	 * @param ?string $content (the LaTeX input)
	 * @param array $attributes
	 * @param Parser $parser
	 * @return array|string
	 */
	public function mathTagHook( ?string $content, array $attributes, Parser $parser ) {
		$mode = $parser->getOptions()->getOption( 'math' );
		$renderer = $this->rendererFactory->getRenderer( $content ?? '', $attributes, $mode );

		$parser->getOutput()->addModuleStyles( [ 'ext.math.styles' ] );
		if ( $mode == MathConfig::MODE_MATHML ) {
			$parser->getOutput()->addModules( [ 'ext.math.scripts' ] );
			$marker = Parser::MARKER_PREFIX .
				'-postMath-' . sprintf( '%08X', $this->mathTagCounter++ ) .
				Parser::MARKER_SUFFIX;
			$this->mathLazyRenderBatch[$marker] = [ $renderer, $parser ];
			return $marker;
		}
		return [ $this->mathPostTagHook( $renderer, $parser ), 'markerType' => 'nowiki' ];
	}

	/**
	 * Callback function for the <ce> parser hook.
	 *
	 * @param ?string $content (the LaTeX input)
	 * @param array $attributes
	 * @param Parser $parser
	 * @return array|string
	 */
	public function chemTagHook( ?string $content, array $attributes, Parser $parser ) {
		$attributes['chem'] = true;
		return $this->mathTagHook( '\ce{' . $content . '}', $attributes, $parser );
	}

	/**
	 * Callback function for the <math> parser hook.
	 *
	 * @param MathRenderer $renderer
	 * @param Parser $parser
	 * @return string
	 * @throws FatalError
	 * @throws MWException
	 */
	private function mathPostTagHook( MathRenderer $renderer, Parser $parser ) {
		$checkResult = $renderer->checkTeX();

		if ( $checkResult !== true ) {
			$renderer->addTrackingCategories( $parser );
			return $renderer->getLastError();
		}

		if ( $renderer->render() ) {
			LoggerFactory::getInstance( 'Math' )->debug( "Rendering successful. Writing output" );
			$renderedMath = $renderer->getHtmlOutput();
			$renderer->addTrackingCategories( $parser );
		} else {
			LoggerFactory::getInstance( 'Math' )->warning(
				"Rendering failed. Printing error message." );
			// Set a short parser cache time (10 minutes) after encountering
			// render issues, but not syntax issues.
			$parser->getOutput()->updateCacheExpiry( 600 );
			$renderer->addTrackingCategories( $parser );
			return $renderer->getLastError();
		}
		// TODO: Convert to a new style hook system and inject HookContainer
		MWHooks::run( 'MathFormulaPostRender',
			[ $parser, $renderer, &$renderedMath ]
		); // Enables indexing of math formula

		// Writes cache if rendering was successful
		$renderer->writeCache();

		return $renderedMath;
	}

	/**
	 * @param Parser $parser
	 * @param string &$text
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		global $wgMathoidCli;
		$renderers = array_map( static function ( $tag ) {
			return $tag[0];
		}, $this->mathLazyRenderBatch );
		if ( $wgMathoidCli ) {
			MathMathMLCli::batchEvaluate( $renderers );
		} else {
			MathMathML::batchEvaluate( $renderers );
		}
		foreach ( $this->mathLazyRenderBatch as $key => [ $renderer, $renderParser ] ) {
			$value = $this->mathPostTagHook( $renderer, $renderParser );
			// Workaround for https://phabricator.wikimedia.org/T103269
			$text = preg_replace(
				'/(<mw:editsection[^>]*>.*?)' . preg_quote( $key ) . '(.*?)<\/mw:editsection>/',
				'\1 $' . htmlspecialchars( $renderer->getTex() ) . '\2</mw:editsection>',
				$text
			);
			$count = 0;
			$text = str_replace( $key, $value, $text, $count );
			if ( $count ) {
				// This hook might be called multiple times. However once the tag is rendered the job is done.
				unset( $this->mathLazyRenderBatch[ $key ] );
			}
		}
	}

	public function onParserOptionsRegister( &$defaults, &$inCacheKey, &$lazyLoad ) {
		$defaults['math'] = $this->userOptionsLookup->getDefaultOption( 'math' );
		$inCacheKey['math'] = true;
		$lazyLoad['math'] = function ( ParserOptions $options ) {
			return MathConfig::normalizeRenderingMode(
				$this->userOptionsLookup->getOption( $options->getUserIdentity(), 'math' )
			);
		};
	}
}
