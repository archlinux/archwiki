<?php
/**
 * MediaWiki math extension
 *
 * @copyright 2002-2012 Tomasz Wegrzanowski, Brion Vibber, Moritz Schubotz,
 * and other MediaWiki contributors
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\Math;

use DeferredUpdates;
use MediaWiki\Extension\Math\InputCheck\RestbaseChecker;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Message;
use MWException;
use Parser;
use Psr\Log\LoggerInterface;
use RequestContext;
use Sanitizer;
use stdClass;
use StringUtils;

/**
 * Abstract base class with static methods for rendering the <math> tags using
 * different technologies. These static methods create a new instance of the
 * extending classes and render the math tags based on the mode setting of the user.
 * Furthermore this class handles the caching of the rendered output.
 *
 * @author Tomasz Wegrzanowski
 * @author Brion Vibber
 * @author Moritz Schubotz
 */
abstract class MathRenderer {

	// REPRESENTATIONS OF THE MATHEMATICAL CONTENT
	/** @var string tex representation */
	protected $tex = '';
	/** @var string MathML content and presentation */
	protected $mathml = '';
	/** @var string SVG layout only (no semantics) */
	protected $svg = '';
	/** @var string PNG  image only (no semantics) */
	protected $png = '';
	/** @var string the original user input string (which was used to calculate the inputhash) */
	protected $userInputTex = '';
	// FURTHER PROPERTIES OF THE MATHEMATICAL CONTENT
	/** @var string ('inlineDisplaystyle'|'display'|'inline'|'linebreak') the rendering style */
	protected $mathStyle = 'inlineDisplaystyle';
	/** @var array with userdefined parameters passed to the extension (not used) */
	protected $params = [];
	/** @var string a userdefined identifier to link to the equation. */
	protected $id = '';

	// STATE OF THE CLASS INSTANCE
	/** @var bool has variable tex been security-checked */
	protected $texSecure = false;
	/** @var bool has the mathematical content changed */
	protected $changed = false;
	/** @var bool is there a database entry for the mathematical content */
	protected $storedInDatabase = null;
	/** @var bool is there a request to purge the existing mathematical content */
	protected $purge = false;
	/** @var string with last occurred error */
	protected $lastError = '';
	/** @var string md5 value from userInputTex */
	protected $md5 = '';
	/** @var string binary packed inputhash */
	protected $inputHash = '';
	/** @var string rendering mode */
	protected $mode = MathConfig::MODE_PNG;
	/** @var string input type */
	protected $inputType = 'tex';
	/** @var MathRestbaseInterface used for checking */
	protected $rbi;
	/** @var array with rendering warnings */
	protected $warnings;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * Constructs a base MathRenderer
	 *
	 * @param string $tex (optional) LaTeX markup
	 * @param array $params (optional) HTML attributes
	 */
	public function __construct( $tex = '', $params = [] ) {
		$this->params = $params;
		if ( isset( $params['id'] ) ) {
			$this->id = $params['id'];
		}
		if ( isset( $params['display'] ) ) {
			$layoutMode = $params['display'];
			if ( $layoutMode == 'block' ) {
				$this->mathStyle = 'display';
				$tex = '{\displaystyle ' . $tex . '}';
				$this->inputType = 'tex';
			} elseif ( $layoutMode == 'inline' ) {
				$this->mathStyle = 'inline';
				$this->inputType = 'inline-tex';
				$tex = '{\textstyle ' . $tex . '}';
			} elseif ( $layoutMode == 'linebreak' ) {
				$this->mathStyle = 'linebreak';
				$tex = '\[ ' . $tex . ' \]';
			}
		}
		// TODO: Implement caching for attributes of the math tag
		// Currently the key for the database entry relating to an equation
		// is md5($tex) the new option to determine if the tex input
		// is rendered in displaystyle or textstyle would require a database
		// layout change to use a composite key e.g. (md5($tex),$mathStyle).
		// As a workaround we use the prefix \displaystyle so that the key becomes
		// md5((\{\\displaystyle|\{\\textstyle)?\s?$tex\}?)
		// The new value of $tex string describes now how the rendering should look like.
		// The variable MathRenderer::mathStyle determines if the rendered equation should
		// be centered in a new line, or just in be displayed in the current line.
		$this->userInputTex = $tex;
		$this->tex = $tex;
		$this->logger = LoggerFactory::getInstance( 'Math' );
	}

	/**
	 * Static method for rendering math tag
	 *
	 * @param string $tex LaTeX markup
	 * @param array $params HTML attributes
	 * @param string $mode constant indicating rendering mode
	 * @return string HTML for math tag
	 */
	public static function renderMath( $tex, $params = [], $mode = MathConfig::MODE_PNG ) {
		$renderer = MediaWikiServices::getInstance()
			->get( 'Math.RendererFactory' )
			->getRenderer( $tex, $params, $mode );
		if ( $renderer->render() ) {
			return $renderer->getHtmlOutput();
		} else {
			return $renderer->getLastError();
		}
	}

	/**
	 * @param string $md5
	 * @return self the MathRenderer generated from md5
	 */
	public static function newFromMd5( $md5 ) {
		// @phan-suppress-next-line PhanTypeInstantiateAbstractStatic
		$instance = new static();
		$instance->setMd5( $md5 );
		$instance->readFromDatabase();
		return $instance;
	}

	/**
	 * Static factory method for getting a renderer based on mode
	 *
	 * @deprecated since 3.0.0. Use Math.RendererFactory service instead.
	 * @param string $tex LaTeX markup
	 * @param array $params HTML attributes
	 * @param string $mode indicating rendering mode
	 * @return self appropriate renderer for mode
	 */
	public static function getRenderer( $tex, $params = [], $mode = MathConfig::MODE_PNG ) {
		return MediaWikiServices::getInstance()
			->get( 'Math.RendererFactory' )
			->getRenderer( $tex, $params, $mode );
	}

	/**
	 * Performs the rendering
	 *
	 * @return bool if rendering was successful.
	 */
	abstract public function render();

	/**
	 * @return string Html output that is embedded in the page
	 */
	abstract public function getHtmlOutput();

	/**
	 * texvc error messages
	 * TODO: update to MathML
	 * Returns an internationalized HTML error string
	 *
	 * @param string $msg message key for specific error
	 * @param string ...$parameters zero or more message
	 *  parameters for specific error
	 *
	 * @return string HTML error string
	 */
	public function getError( $msg, ...$parameters ) {
		$mf = wfMessage( 'math_failure' )->inContentLanguage()->escaped();
		$errmsg = wfMessage( $msg, $parameters )->inContentLanguage()->escaped();
		$source = htmlspecialchars( str_replace( "\n", ' ', $this->tex ) );
		return "<strong class=\"error texerror\">$mf ($errmsg): $source</strong>\n";
	}

	/**
	 * Return hash of input
	 *
	 * @return string hash
	 */
	public function getMd5() {
		if ( !$this->md5 ) {
			$this->md5 = md5( $this->userInputTex );
		}
		return $this->md5;
	}

	/**
	 * Set the input hash (if user input tex is not available)
	 * @param string $md5
	 */
	public function setMd5( $md5 ) {
		$this->md5 = $md5;
	}

	/**
	 * Return hash of input
	 *
	 * @return string hash
	 */
	public function getInputHash() {
		// TODO: What happens if $tex is empty?
		if ( !$this->inputHash ) {
			$dbr = wfGetDB( DB_REPLICA );
			return $dbr->encodeBlob( pack( "H32", $this->getMd5() ) ); # Binary packed, not hex
		}
		return $this->inputHash;
	}

	/**
	 * Decode binary packed hash from the database to md5 of input_tex
	 * @param string $hash (binary)
	 * @return string md5
	 */
	private static function dbHash2md5( $hash ) {
		$dbr = wfGetDB( DB_REPLICA );
		$xhash = unpack( 'H32md5', $dbr->decodeBlob( $hash ) . "                " );
		return $xhash['md5'];
	}

	/**
	 * Reads rendering data from database
	 *
	 * @return bool true if read successfully, false otherwise
	 */
	public function readFromDatabase() {
		$dbr = wfGetDB( DB_REPLICA );
		$rpage = $dbr->selectRow( $this->getMathTableName(),
			$this->dbInArray(),
			[ 'math_inputhash' => $this->getInputHash() ],
			__METHOD__ );
		if ( $rpage !== false ) {
			$this->initializeFromDatabaseRow( $rpage );
			$this->storedInDatabase = true;
				return true;
		} else {
			# Missing from the database and/or the render cache
			$this->storedInDatabase = false;
			return false;
		}
	}

	/**
	 * @return array with the database column names
	 */
	protected function dbInArray() {
		$in = [ 'math_inputhash',
			'math_mathml',
			'math_inputtex',
			'math_tex',
			'math_svg'
		];
		return $in;
	}

	/**
	 * Reads the values from the database but does not overwrite set values with empty values
	 * @param stdClass $rpage (a database row)
	 */
	protected function initializeFromDatabaseRow( $rpage ) {
		$this->inputHash = $rpage->math_inputhash; // MUST NOT BE NULL
		$this->md5 = self::dbHash2md5( $this->inputHash );
		if ( !empty( $rpage->math_mathml ) ) {
			$this->mathml = utf8_decode( $rpage->math_mathml );
		}
		if ( !empty( $rpage->math_inputtex ) ) {
			// in the current database the field is probably not set.
			$this->userInputTex = $rpage->math_inputtex;
		}
		if ( !empty( $rpage->math_tex ) ) {
			$this->tex = $rpage->math_tex;
		}
		if ( !empty( $rpage->math_svg ) ) {
			$this->svg = $rpage->math_svg;
		}
		$this->changed = false;
	}

	/**
	 * Writes rendering entry to database.
	 *
	 * WARNING: Use writeCache() instead of this method to be sure that all
	 * renderer specific (such as squid caching) are taken into account.
	 * This function stores the values that are currently present in the class
	 * to the database even if they are empty.
	 *
	 * This function can be seen as protected function.
	 * @param \Wikimedia\Rdbms\IDatabase|null $dbw
	 */
	public function writeToDatabase( $dbw = null ) {
		# Now save it back to the DB:
		if ( MediaWikiServices::getInstance()->getReadOnlyMode()->isReadOnly() ) {
			return;
		}
		$outArray = $this->dbOutArray();
		$mathTableName = $this->getMathTableName();
		$fname = __METHOD__;
		if ( $this->isInDatabase() ) {
			$this->debug( 'Update database entry' );
			$inputHash = $this->getInputHash();
			DeferredUpdates::addCallableUpdate( function () use (
				$dbw, $outArray, $inputHash, $mathTableName, $fname
			) {
				$dbw = $dbw ?: wfGetDB( DB_PRIMARY );

				$dbw->update( $mathTableName, $outArray,
					[ 'math_inputhash' => $inputHash ], $fname );
				$this->logger->debug(
					'Row updated after db transaction was idle: ' .
					var_export( $outArray, true ) . " to database" );
			} );
		} else {
			$this->storedInDatabase = true;
			$this->debug( 'Store new entry in database' );
			DeferredUpdates::addCallableUpdate( function () use (
				$dbw, $outArray, $mathTableName, $fname
			) {
				$dbw = $dbw ?: wfGetDB( DB_PRIMARY );

				$dbw->insert( $mathTableName, $outArray, $fname, [ 'IGNORE' ] );
				LoggerFactory::getInstance( 'Math' )->debug(
					'Row inserted after db transaction was idle {out}.',
					[
						'out' => var_export( $outArray, true ),
					]
				);
				if ( $dbw->affectedRows() == 0 ) {
					// That's the price for the delayed update.
					$this->logger->warning(
						'Entry could not be written. Might be changed in between.' );
				}
			} );
		}
	}

	/**
	 * Gets an array that matches the variables of the class to the database columns
	 * @return array
	 */
	protected function dbOutArray() {
		$out = [
			'math_inputhash' => $this->getInputHash(),
			'math_mathml' => utf8_encode( $this->mathml ),
			'math_inputtex' => $this->userInputTex,
			'math_tex' => $this->tex,
			'math_svg' => $this->svg
		];
		return $out;
	}

	/**
	 * @param MathRestbaseInterface $param
	 */
	public function setRestbaseInterface( $param ) {
		$this->rbi = $param;
		$this->rbi->setPurge( $this->isPurge() );
	}

	public function hasWarnings() {
		if ( is_array( $this->warnings ) && count( $this->warnings ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Adds tracking categories to the parser
	 *
	 * @param Parser $parser
	 */
	public function addTrackingCategories( $parser ) {
		if ( !$this->checkTeX() ) {
			$parser->addTrackingCategory( 'math-tracking-category-error' );
		}
		if ( $this->lastError ) {
			// Add a tracking category specialized on render errors.
			$parser->addTrackingCategory( 'math-tracking-category-render-error' );
		}
	}

	/**
	 * Returns sanitized attributes
	 *
	 * @param string $tag element name
	 * @param array $defaults default attributes
	 * @param array $overrides attributes to override defaults
	 * @return array HTML attributes
	 */
	protected function getAttributes( $tag, $defaults = [], $overrides = [] ) {
		$attribs = Sanitizer::validateTagAttributes( $this->params, $tag );
		$attribs = Sanitizer::mergeAttributes( $defaults, $attribs );
		return Sanitizer::mergeAttributes( $attribs, $overrides );
	}

	/**
	 * Writes cache. Writes the database entry if values were changed
	 * @return bool
	 */
	public function writeCache() {
		$this->debug( 'Writing of cache requested' );
		if ( $this->isChanged() ) {
			$this->debug( 'Change detected. Perform writing' );
			$this->writeToDatabase();
			return true;
		} else {
			$this->debug( "Nothing was changed. Don't write to database" );
			return false;
		}
	}

	/**
	 * Gets TeX markup
	 *
	 * @return string TeX markup
	 */
	public function getTex() {
		return $this->tex;
	}

	/**
	 * Gets the rendering mode
	 *
	 * @return string
	 */
	public function getMode() {
		return $this->mode;
	}

	/**
	 * Sets the rendering mode
	 * @param string $newMode element of the array $wgMathValidModes
	 * @return bool
	 */
	public function setMode( $newMode ) {
		if ( MediaWikiServices::getInstance()->get( 'Math.Config' )->isValidRenderingMode( $newMode ) ) {
			$this->mode = $newMode;
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Sets the TeX code
	 *
	 * @param string $tex
	 */
	public function setTex( $tex ) {
		if ( $this->tex != $tex ) {
			$this->changed = true;
			$this->tex = $tex;
		}
	}

	/**
	 * Gets the MathML XML element
	 * @return string in UTF-8 encoding
	 */
	public function getMathml() {
		if ( !StringUtils::isUtf8( $this->mathml ) ) {
			$this->setMathml( '' );
		}
		return $this->mathml;
	}

	/**
	 * @param string $mathml use UTF-8 encoding
	 */
	public function setMathml( $mathml ) {
		$this->changed = true;
		$this->mathml = $mathml;
	}

	/**
	 * Get the attributes of the math tag
	 *
	 * @return array
	 */
	public function getParams() {
		return $this->params;
	}

	/**
	 * @param array $params
	 */
	public function setParams( $params ) {
		// $changed is not set to true here, because the attributes do not affect
		// the rendering in the current implementation.
		// If this behavior will change in the future $this->tex is no longer a
		// primary key and the input hash cannot be calculate form $this->tex
		// only. See the discussion 'Tag extensions in Block mode' on wikitech-l.
		$this->params = $params;
	}

	/**
	 * Checks if the instance was modified i.e., because math was rendered
	 *
	 * @return bool true if something was changed false otherwise
	 */
	public function isChanged() {
		return $this->changed;
	}

	/**
	 * Checks if there is an explicit user request to rerender the math-tag.
	 * @return bool
	 */
	public function isPurge() {
		if ( $this->purge ) {
			return true;
		}
		$refererHeader = RequestContext::getMain()->getRequest()->getHeader( 'REFERER' );
		if ( $refererHeader ) {
			$url = parse_url( $refererHeader, PHP_URL_QUERY );
			if ( !is_string( $url ) ) {
				return false;
			}
			parse_str( $url, $refererParam );
			if ( isset( $refererParam['action'] ) && $refererParam['action'] === 'purge' ) {
				$this->logger->debug( 'Re-Rendering on user request' );
				return true;
			}
		}
		return false;
	}

	/**
	 * Sets purge. If set to true the render is forced to rerender and must not
	 * use a cached version.
	 * @param bool $purge
	 */
	public function setPurge( $purge = true ) {
		$this->changed = true;
		$this->purge = $purge;
	}

	public function getLastError() {
		return $this->lastError;
	}

	/**
	 * @param string $mathStyle ('inlineDisplaystyle'|'display'|'inline')
	 */
	public function setMathStyle( $mathStyle = 'display' ) {
		if ( $this->mathStyle !== $mathStyle ) {
			$this->changed = true;
		}
		$this->mathStyle = $mathStyle;
		if ( $mathStyle == 'inline' ) {
			$this->inputType = 'inline-tex';
		} else {
			$this->inputType = 'tex';
		}
	}

	/**
	 * Returns the value of the DisplayStyle attribute
	 * @return string ('inlineDisplaystyle'|'display'|'inline'|'linebreak') the DisplayStyle
	 */
	public function getMathStyle() {
		return $this->mathStyle;
	}

	/**
	 * Get if the input tex was marked as secure
	 * @return bool
	 */
	public function isTexSecure() {
		return $this->texSecure;
	}

	/**
	 * @return bool
	 */
	public function checkTeX() {
		if ( $this->texSecure ) {
			// equation was already checked
			return true;
		}
		$texCheckDisabled = MediaWikiServices::getInstance()
			->get( 'Math.Config' )
			->texCheckDisabled();
		if ( $texCheckDisabled === MathConfig::ALWAYS ) {
			// checking is disabled
			$this->debug( 'Skip TeX check ' );
			return true;
		} else {
			if ( $texCheckDisabled === MathConfig::NEW && $this->mode != MathConfig::MODE_SOURCE ) {
				if ( $this->readFromDatabase() ) {
					$this->debug( 'Skip TeX check' );
					$this->texSecure = true;
					return true;
				}
			}
			$this->debug( 'Perform TeX check' );
			return $this->doCheck();
		}
	}

	public function isInDatabase() {
		if ( $this->storedInDatabase === null ) {
			$this->readFromDatabase();
		}
		return $this->storedInDatabase;
	}

	/**
	 * @return string TeX the original tex string specified by the user
	 */
	public function getUserInputTex() {
		return $this->userInputTex;
	}

	/**
	 * @return string user defined ID
	 */
	public function getID() {
		return $this->id;
	}

	/**
	 * @param string $id user defined ID
	 */
	public function setID( $id ) {
		// Changes in the ID affect the container for the math element on the current page
		// only. Therefore an id change does not affect the $this->changed variable, which
		// indicates if database relevant fields have been changed.
		$this->id = $id;
	}

	/**
	 * @param string $svg
	 */
	public function setSvg( $svg ) {
		$this->changed = true;
		$this->svg = trim( $svg );
	}

	/**
	 * Gets the SVG image
	 *
	 * @param string $render if set to 'render' (default) and no SVG image exists, the function
	 *                       tries to generate it on the fly.
	 *                       Otherwise, if set to 'cached', and there is no SVG in the database
	 *                       cache, an empty string is returned.
	 *
	 * @return string XML-Document of the rendered SVG
	 */
	public function getSvg( /** @noinspection PhpUnusedParameterInspection */ $render = 'render' ) {
		// Spaces will prevent the image from being displayed correctly in the browser
		if ( !$this->svg && $this->rbi ) {
			$this->svg = $this->rbi->getSvg();
		}
		return trim( $this->svg );
	}

	/**
	 * @return string
	 */
	abstract protected function getMathTableName();

	protected function getModeName(): Message {
		return MediaWikiServices::getInstance()
			->get( 'Math.Config' )
			->getRenderingModeName( $this->getMode() );
	}

	/**
	 * @param string $inputType
	 */
	public function setInputType( $inputType ) {
		$this->inputType = $inputType;
	}

	/**
	 * @return string
	 */
	public function getInputType() {
		return $this->inputType;
	}

	/**
	 * @return bool
	 */
	protected function doCheck() {
		$checker = new RestbaseChecker( $this->tex, $this->getInputType(), $this->rbi );
		try {
			if ( $checker->isValid() ) {
				$this->setTex( $checker->getValidTex() );
				$this->texSecure = true;
				return true;
			}
		}
		catch ( MWException $e ) {
		}
		$checkerError = $checker->getError();
		$this->lastError = $this->getError( $checkerError->getKey(), ...$checkerError->getParams() );
		return false;
	}

	/**
	 * @return string
	 */
	public function getPng() {
		return $this->png;
	}

	protected function debug( $msg ) {
		$this->logger->debug( "$msg for \"{tex}\".", [ 'tex' => $this->userInputTex ] );
	}
}
