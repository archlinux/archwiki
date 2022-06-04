<?php

namespace MediaWiki\Extension\Math;

use MediaWiki\Extension\Math\Render\RendererFactory;
use SpecialPage;

/**
 * Description of SpecialMathShowSVG
 *
 * @author Moritz Schubotz (Physikerwelt)
 */
class SpecialMathShowImage extends SpecialPage {
	/** @var bool */
	private $noRender = false;
	/** @var MathRenderer|null */
	private $renderer = null;
	/** @var string */
	private $mode = MathConfig::MODE_MATHML;

	/** @var MathConfig */
	private $mathConfig;

	/** @var RendererFactory */
	private $rendererFactory;

	/**
	 * @param MathConfig $mathConfig
	 * @param RendererFactory $rendererFactory
	 */
	public function __construct(
		MathConfig $mathConfig,
		RendererFactory $rendererFactory
	) {
		parent::__construct(
			'MathShowImage',
			'', // Don't restrict
			false // Don't show on Special:SpecialPages - it's not useful interactively
		);
		$this->mathConfig = $mathConfig;
		$this->rendererFactory = $rendererFactory;
	}

	/**
	 * Sets headers - this should be called from the execute() method of all derived classes!
	 * @param bool $success
	 */
	public function setHeaders( $success = true ) {
		$out = $this->getOutput();
		$request = $this->getRequest();
		$out->setArticleBodyOnly( true );
		$out->setArticleRelated( false );
		$out->setRobotPolicy( "noindex,nofollow" );
		$out->disable();
		if ( $success && $this->mode == MathConfig::MODE_PNG ) {
			$request->response()->header( "Content-type: image/png;" );
		} else {
			$request->response()->header( "Content-type: image/svg+xml; charset=utf-8" );
		}
		if ( $success && !( $this->noRender ) ) {
			$request->response()->header(
				'Cache-Control: public, s-maxage=604800, max-age=3600'
			); // 1 week (server) 1 hour (client)
			$request->response()->header( 'Vary: User-Agent' );
		}
	}

	public function execute( $par ) {
		global $wgMathEnableExperimentalInputFormats, $wgMathoidCli;
		$request = $this->getRequest();
		$hash = $request->getText( 'hash', '' );
		$tex = $request->getText( 'tex', '' );
		if ( $wgMathEnableExperimentalInputFormats ) {
			$asciimath = $request->getText( 'asciimath', '' );
		} else {
			$asciimath = '';
		}
		$this->mode = MathConfig::normalizeRenderingMode( $request->getText( 'mode' ) );
		if ( !$this->mathConfig->isValidRenderingMode( $this->mode ) ) {
			// Fallback to the default if an invalid mode was specified
			$this->mode = MathConfig::MODE_MATHML;
		}
		if ( $hash === '' && $tex === '' && $asciimath === '' ) {
			$this->setHeaders( false );
			echo $this->printSvgError( 'No Inputhash specified' );
		} else {
			if ( $tex === '' && $asciimath === '' ) {
				if ( $wgMathoidCli && $this->mode === MathConfig::MODE_PNG ) {
					$this->renderer = $this->rendererFactory->getRenderer( '', [], MathConfig::MODE_MATHML );
				} else {
					$this->renderer = $this->rendererFactory->getRenderer( '', [], $this->mode );
				}
				$this->renderer->setMd5( $hash );
				$this->noRender = $request->getBool( 'noRender', false );
				$isInDatabase = $this->renderer->readFromDatabase();
				if ( $isInDatabase || $this->noRender ) {
					$success = $isInDatabase;
				} else {
					if ( $this->mode == MathConfig::MODE_PNG && !$wgMathoidCli ) {
						// get the texvc input from the mathoid database table
						// and render the conventional way
						$mmlRenderer = MathMathML::newFromMd5( $hash );
						$mmlRenderer->readFromDatabase();
						$this->renderer = $this->rendererFactory->getRenderer(
							$mmlRenderer->getUserInputTex(), [], MathConfig::MODE_PNG
						);
						$this->renderer->setMathStyle( $mmlRenderer->getMathStyle() );
					}
					$success = $this->renderer->render();
				}
			} elseif ( $asciimath === '' ) {
				$this->renderer = $this->rendererFactory->getRenderer( $tex, [], $this->mode );
				$success = $this->renderer->render();
			} else {
				$this->renderer = $this->rendererFactory->getRenderer(
					$asciimath, [ 'type' => 'ascii' ], $this->mode
				);
				$success = $this->renderer->render();
			}
			if ( $success ) {
				if ( $this->mode == MathConfig::MODE_PNG ) {
					$output = $this->renderer->getPng();
				} else {
					$output = $this->renderer->getSvg();
				}
			} else {
				// Error message in PNG not supported
				$output = $this->printSvgError( $this->renderer->getLastError() );
			}
			if ( $output == "" ) {
				$output = $this->printSvgError( 'No Output produced' );
				$success = false;
			}
			$this->setHeaders( $success );
			echo $output;
			if ( $success ) {
				$this->renderer->writeCache();
			}
		}
	}

	/**
	 * Prints the specified error message as svg.
	 * @param string $msg error message, HTML escaped
	 * @return string xml svg image with the error message
	 */
	private function printSvgError( $msg ) {
		global $wgDebugComments;
		$result = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 4" preserveAspectRatio="xMidYMid meet" >
<text text-anchor="start" fill="red" y="2">
$msg
</text>
</svg>
SVG;
		if ( $wgDebugComments ) {
			$result .= '<!--' . var_export( $this->renderer, true ) . '-->';
		}
		return $result;
	}

	protected function getGroupName() {
		return 'other';
	}
}
