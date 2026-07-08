<?php

namespace MediaWiki\Extension\Scribunto\Engines\LuaCommon;

use MediaWiki\Html\Html;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Xml\Xml;

class SvgLibrary extends LibraryBase {

	private const ALLOWED_IMG_ATTRIBUTES = [
		'width' => true,
		'height' => true,
		'class' => true,
		'id' => true,
		'alt' => true,
		'title' => true,
		'style' => true,
	];

	/** @inheritDoc */
	public function register() {
		$lib = [
			'createSvgString' => $this->createSvgString( ... ),
			'createImgTag' => $this->createImgTag( ... ),
		];

		return $this->getEngine()->registerInterface( 'mw.svg.lua', $lib, [
			'ALLOWED_IMG_ATTRIBUTES' => self::ALLOWED_IMG_ATTRIBUTES,
		] );
	}

	private function stringifySvg( string $content, array $attributes ): string {
		$attributes['xmlns'] = 'http://www.w3.org/2000/svg';
		return Xml::tags( 'svg', $attributes, $content );
	}

	/**
	 * Generates the full SVG tag string.
	 * @param string $content
	 * @param array $attributes
	 * @return array
	 */
	private function createSvgString( $content, $attributes ): array {
		$this->checkType( 'toString', 1, $content, 'string' );
		$this->checkType( 'toString', 2, $attributes, 'table' );

		return [ $this->stringifySvg( $content, $attributes ) ];
	}

	/**
	 * Creates an img element with data URI set to the given SVG content.
	 * @param string $content
	 * @param array $attributes
	 * @param array $imgAttributes
	 * @return array
	 */
	private function createImgTag( $content, $attributes, $imgAttributes ): array {
		$this->checkType( 'toImage', 1, $content, 'string' );
		$this->checkType( 'toImage', 2, $attributes, 'table' );
		$this->checkType( 'toImage', 3, $imgAttributes, 'table' );

		$svgString = $this->stringifySvg( $content, $attributes );
		$dataUrl = 'data:image/svg+xml;base64,' . base64_encode( $svgString );

		$imgAttributes = Sanitizer::validateAttributes( $imgAttributes, self::ALLOWED_IMG_ATTRIBUTES );
		$imgAttributes['src'] = $dataUrl;

		$output = Html::rawElement( 'img', $imgAttributes );

		$parser = $this->getEngine()->getParser();
		return [ $parser->insertStripItem( $output ) ];
	}
}
