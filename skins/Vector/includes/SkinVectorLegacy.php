<?php

namespace Vector;

/**
 * @ingroup Skins
 * @package Vector
 * @internal
 */
class SkinVectorLegacy extends SkinVector {
	/**
	 * @inheritDoc
	 */
	public function __construct( $options = [] ) {
		$options += [
			'template' => self::getTemplateOption(),
			'scripts' => self::getScriptsOption(),
			'styles' => self::getStylesOption(),
		];
		parent::__construct( $options );
	}

	/**
	 * Temporary static function while we deprecate SkinVector class.
	 *
	 * @return string
	 */
	public static function getTemplateOption() {
		return 'skin-legacy';
	}

	/**
	 * Temporary static function while we deprecate SkinVector class.
	 *
	 * @return array
	 */
	public static function getScriptsOption() {
		return [
			'skins.vector.legacy.js',
		];
	}

	/**
	 * Temporary static function while we deprecate SkinVector class.
	 *
	 * @return array
	 */
	public static function getStylesOption() {
		return [
			'skins.vector.styles.legacy',
		];
	}
}
