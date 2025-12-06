<?php

namespace MediaWiki\Extension\Scribunto\Engines\LuaCommon;

use MediaWiki\Html\Html;
use MediaWiki\Parser\Sanitizer;

class SvgLibrary extends LibraryBase {

	/** @inheritDoc */
	public function register() {
		$lib = [
			'createImgTag' => [ $this, 'createImgTag' ],
		];

		return $this->getEngine()->registerInterface( 'mw.svg.lua', $lib );
	}

	/**
	 * Creates an img element with data URI set to the given SVG content.
	 * @param string $svgString
	 * @param array $attributes
	 * @return array
	 */
	public function createImgTag( string $svgString, array $attributes ) {
		$dataUrl = 'data:image/svg+xml;base64,' . base64_encode( $svgString );

		$attributes = Sanitizer::validateAttributes( $attributes,
			array_fill_keys( [ 'width', 'height', 'class', 'id', 'alt', 'title', 'style' ], true ) );
		$attributes['src'] = $dataUrl;

		$output = Html::rawElement( 'img', $attributes );

		$parser = $this->getEngine()->getParser();
		return [ $parser->insertStripItem( $output ) ];
	}
}
