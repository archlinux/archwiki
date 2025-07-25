<?php

namespace MediaWiki\CheckUser\CheckUser\Widgets;

use MediaWiki\HTMLForm\CollapsibleFieldsetLayout;
use MediaWiki\HTMLForm\OOUIHTMLForm;
use OOUI\Element;
use OOUI\FieldsetLayout;
use OOUI\HtmlSnippet;
use OOUI\PanelLayout;
use OOUI\Widget;

class HTMLFieldsetCheckUser extends OOUIHTMLForm {

	/** @var string a custom CSS class to apply to the fieldset layout */
	public $outerClass = '';

	/**
	 * This returns the html but not wrapped in a form
	 * element, so that it can be optionally added by SpecialCheckUser.
	 *
	 * @inheritDoc
	 */
	public function wrapForm( $html ) {
		if ( is_string( $this->mWrapperLegend ) ) {
			$phpClass = $this->mCollapsible ? CollapsibleFieldsetLayout::class : FieldsetLayout::class;
			$content = new $phpClass( [
				'label' => $this->mWrapperLegend,
				'collapsed' => $this->mCollapsed,
				'items' => [
					new Widget( [
						'content' => new HtmlSnippet( $html )
					] ),
				],
			] + Element::configFromHtmlAttributes( $this->mWrapperAttributes ) );
		} else {
			$content = new HtmlSnippet( $html );
		}

		// Include a wrapper for style, if requested.
		return new PanelLayout( [
			'classes' => [ 'mw-htmlform-ooui-wrapper', $this->outerClass ],
			'expanded' => false,
			'padded' => $this->mWrapperLegend !== false,
			'framed' => $this->mWrapperLegend !== false,
			'content' => $content,
		] );
	}
}
