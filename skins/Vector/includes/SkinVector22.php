<?php

namespace Vector;

/**
 * @ingroup Skins
 * @package Vector
 * @internal
 */
class SkinVector22 extends SkinVector {
	/**
	 * @inheritDoc
	 * Updates the constructor to conditionally disable table of contents in article body.
	 */
	public function __construct( $options = [] ) {
		$options += [
			'template' => self::getTemplateOption(),
			'scripts' => self::getScriptsOption(),
			'styles' => self::getStylesOption(),
		];

		$options['toc'] = !$this->isTableOfContentsVisibleInSidebar();
		parent::__construct( $options );
	}

	/**
	 * Determines if the Table of Contents should be visible.
	 * TOC is visible on main namespaces except for the Main Page
	 * when the feature flag is on.
	 *
	 * @return bool
	 */
	private function isTableOfContentsVisibleInSidebar(): bool {
		$featureManager = VectorServices::getFeatureManager();
		$title = $this->getTitle();
		$isMainNS = $title ? $title->inNamespaces( 0 ) : false;
		$isMainPage = $title ? $title->isMainPage() : false;
		return $featureManager->isFeatureEnabled( Constants::FEATURE_TABLE_OF_CONTENTS ) && $isMainNS && !$isMainPage;
	}

	/**
	 * Temporary static function while we deprecate SkinVector class.
	 *
	 * @return string
	 */
	public static function getTemplateOption() {
		return 'skin';
	}

	/**
	 * Temporary static function while we deprecate SkinVector class.
	 *
	 * @return array
	 */
	public static function getScriptsOption() {
		return [
			'skins.vector.user',
			'skins.vector.js',
			'skins.vector.es6',
		];
	}

	/**
	 * Temporary static function while we deprecate SkinVector class.
	 *
	 * @return array
	 */
	public static function getStylesOption() {
		return [
			'mediawiki.ui.button',
			'skins.vector.styles',
			'skins.vector.user.styles',
			'skins.vector.icons',
			'mediawiki.ui.icon',
		];
	}

	/**
	 * @return array
	 */
	public function getTemplateData(): array {
		$data = parent::getTemplateData();
		if ( !$this->isTableOfContentsVisibleInSidebar() ) {
			unset( $data['data-toc'] );
		}
		return $data;
	}
}
