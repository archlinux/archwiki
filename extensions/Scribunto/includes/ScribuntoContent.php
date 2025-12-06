<?php
/**
 * Scribunto Content Model
 *
 * @file
 * @ingroup Extensions
 * @ingroup Scribunto
 *
 * @author Brad Jorsch <bjorsch@wikimedia.org>
 */

namespace MediaWiki\Extension\Scribunto;

use MediaWiki\Content\TextContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

/**
 * Represents the content of a Scribunto script page
 */
class ScribuntoContent extends TextContent {

	/**
	 * @param string $text
	 */
	public function __construct( $text ) {
		parent::__construct( $text, CONTENT_MODEL_SCRIBUNTO );
	}

	/**
	 * @inheritDoc
	 */
	public function updateRedirect( Title $target ) {
		return $this->getEngineFactory()->getDefaultEngine()->updateRedirect( $this, $target );
	}

	/**
	 * @inheritDoc
	 */
	public function getRedirectTarget() {
		return $this->getEngineFactory()->getDefaultEngine()->getRedirectTarget( $this );
	}

	private function getEngineFactory(): EngineFactory {
		return MediaWikiServices::getInstance()->getService( 'Scribunto.EngineFactory' );
	}
}
