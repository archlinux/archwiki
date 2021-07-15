<?php
/**
 * SkinTemplate class for the Timeless skin
 *
 * @ingroup Skins
 */
class SkinTimeless extends SkinTemplate {
	/**
	 * Provide support for < 1.36. Can be deleted when no longer supported.
	 * @inheritDoc
	 */
	public function __construct(
		array $options = []
	) {
		global $wgVersion;
		$out = $this->getOutput();

		// This code block can be removed when 1.35 is no longer supported.
		if ( version_compare( $wgVersion, '1.36', '<' ) ) {
			// Add external links - this is replaced by `content-links` feature in 1.36
			$out->addModuleStyles( [
				'mediawiki.skinning.content.externallinks'
			] );
			// Make responsive - this is replaced by `responsive` option in 1.36
			$out->addMeta( 'viewport',
				'width=device-width, initial-scale=1.0, ' .
				'user-scalable=yes, minimum-scale=0.25, maximum-scale=5.0'
			);
			// Associate template - this is replaced by `template` option in 1.36
			$this->template = 'TimelessTemplate';
		}
		// Basic IE support without flexbox
		$out->addStyle( $this->stylename . '/resources/IE9fixes.css', 'screen', 'IE' );

		parent::__construct( $options );
	}
}
