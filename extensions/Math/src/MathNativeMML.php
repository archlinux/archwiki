<?php
/**
 * MediaWiki math extension
 *
 * @copyright 2002-2023 various MediaWiki contributors
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\Math;

use DOMDocument;
use DOMXPath;
use MediaWiki\Config\Config;
use MediaWiki\Extension\Math\InputCheck\LocalChecker;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmath;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MediaWikiServices;
use StatusValue;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * Converts LaTeX to MathML using PHP
 */
class MathNativeMML extends MathMathML {
	private LocalChecker $checker;
	private Config $mainConfig;
	private HookContainer $hookContainer;

	/** @inheritDoc */
	public function __construct( $tex = '', $params = [], $cache = null, $mathConfig = null ) {
		parent::__construct( $tex, $params, $cache, $mathConfig );
		$this->setMode( MathConfig::MODE_NATIVE_MML );
	}

	public static function renderReferenceEntry(
		array &$entry,
		?MathConfig $mathConfig = null,
		?HookContainer $hookContainer = null,
		?Config $config = null ): bool {
		$mathConfig ??= Math::getMathConfig();
		$hookContainer ??= MediaWikiServices::getInstance()->getHookContainer();
		$config ??= MediaWikiServices::getInstance()->getMainConfig();
		$renderer = new MathNativeMML( $entry['input'], $entry['params'], WANObjectCache::newEmpty(), $mathConfig );
		$renderer->setRawError( true );
		$renderer->setHookContainer( $hookContainer );
		$renderer->setMainConfig( $config );
		$renderer->setChecker( new LocalChecker( WANObjectCache::newEmpty(), $entry['input'], 'tex' ) );
		$result = $renderer->render();
		$entry['output'] = $renderer->getMathml();
		if ( !$result ) {
			$entry['skipped'] = true;
			$entry['error'] = $renderer->getLastError();
		}
		return $result;
	}

	/**
	 * Adds hyperlinks to MathML elements
	 * @param string $qid Identifier for symbol mapping
	 * @param string $mathml Input MathML HTML content
	 * @return string Modified MathML with either anchor tags or hrefs
	 */
	private function addLinksToMathML( string $qid, string $mathml ): string {
		$services = MediaWikiServices::getInstance();
		$connector = $services->getService( 'Math.WikibaseConnector' );
		$language = $services->getContentLanguageCode()->toString();
		$qmap = $connector->getUrlFromSymbol( $qid, $language );
		$dom = new DOMDocument();
		$dom->loadXML( $mathml );
		$xpath = new DOMXPath( $dom );
		$xpath->registerNamespace( 'mathml', 'http://www.w3.org/1998/Math/MathML' );
		$linkableElements = $xpath->query( '//mathml:mi | //mathml:mo | //mathml:mtext' );
		foreach ( $linkableElements as $linkableElement ) {
			$textValue = $linkableElement->nodeValue;
			if ( empty( $qmap[$textValue]['url'] ) ) {
				continue;
			}
			$a = $dom->createElement( 'a' );
			$a->setAttribute( 'href', $qmap[$textValue]['url'] );
			$a->setAttribute( 'title', $qmap[$textValue]['title'] );
			$a->nodeValue = $linkableElement->nodeValue;
			$linkableElement->nodeValue = "";
			$linkableElement->appendChild( $a );
		}
		return $dom->saveXML();
	}

	public function getMainConfig(): Config {
		$this->mainConfig ??= MediaWikiServices::getInstance()->getMainConfig();
		return $this->mainConfig;
	}

	public function getHookContainer(): HookContainer {
		$this->hookContainer ??= MediaWikiServices::getInstance()->getHookContainer();
		return $this->hookContainer;
	}

	protected function doRender(): StatusValue {
		$checker = $this->getChecker();
		$checker->setContext( $this );
		$checker->setHookContainer( $this->getHookContainer() );
		$presentation = $checker->getPresentationMathMLFragment();
		$config = $this->getMainConfig();
		$attributes = [ 'class' => 'mwe-math-element' ];
		if ( $this->getID() !== '' ) {
			$attributes['id'] = $this->getID();
		}
		if ( $this->getMathStyle() == 'display' ) {
			$attributes['display'] = 'block';
		}
		$root = new MMLmath( "", $attributes );
		$mathElement = $root->encapsulateRaw( $presentation ?? '' );
		if ( isset( $this->params['qid'] ) &&
			preg_match( '/Q\d+/', $this->params['qid'] ) &&
			$config->get( "MathEnableFormulaLinks" ) ) {
			$this->setMathml( $this->addLinksToMathML(
				$this->params['qid'],
				$mathElement ) );
		} else {
			$this->setMathml( $mathElement );
		}
		return StatusValue::newGood();
	}

	protected function getChecker(): LocalChecker {
		$this->checker ??= Math::getCheckerFactory()
			->newLocalChecker( $this->tex, $this->getInputType(), $this->isPurge() );
		return $this->checker;
	}

	/**
	 * @inheritDoc
	 */
	public function getHtmlOutput( bool $svg = true ): string {
		return $this->getMathml();
	}

	public function readFromCache(): bool {
		return false;
	}

	/** @inheritDoc */
	public function writeCache() {
		return true;
	}

	private function setHookContainer( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	private function setMainConfig( Config $config ) {
		$this->mainConfig = $config;
	}

	private function setChecker( LocalChecker $checker ) {
		$this->checker = $checker;
	}
}
