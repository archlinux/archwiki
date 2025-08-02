<?php

namespace MediaWiki\CheckUser\CheckUser\Widgets;

use MediaWiki\HTMLForm\CollapsibleFieldsetLayout;
use MediaWiki\Output\OutputPage;
use OOUI\Element;
use OOUI\FieldsetLayout;
use OOUI\LabelWidget;
use OOUI\MultilineTextInputWidget;
use OOUI\PanelLayout;
use OOUI\Widget;

class CIDRCalculator {

	private bool $mCollapsible;

	/**
	 * Text to be shown as the legend for
	 * the calculator.
	 * Similar to HTMLForm's $mWrapperLegend.
	 *
	 * @var string|bool
	 */
	private $mWrapperLegend;

	private array $mWrapperAttributes;

	private bool $mCollapsed;

	private OutputPage $out;

	/**
	 * @param OutputPage $out
	 * @param array $config an array with any of the following keys:
	 *   * collapsable - whether to allow the CIDR calculator wrapper fieldset
	 *      to be collapsable (boolean with default of false)
	 *   * wrapperLegend - the text to use as the title for the CIDR calculator
	 *      (default is the message checkuser-cidr-label). Use false for no legend.
	 *   * wrapperAttributes - any attributes to apply to the wrapper fieldset (an array)
	 *   * collapsed - whether to have the wrapper fieldset be collapsed by default
	 *      (boolean with default of false)
	 */
	public function __construct( OutputPage $out, array $config = [] ) {
		$this->out = $out;

		// Just in case the modules were not loaded
		$out->addModules( [ 'ext.checkUser', 'ext.checkUser.styles' ] );
		$this->mCollapsible = $config['collapsable'] ?? false;
		$this->mWrapperLegend = $config['wrapperLegend'] ?? $out->msg( 'checkuser-cidr-label' )->text();
		$this->mWrapperAttributes = $config['wrapperAttributes'] ?? [];
		$this->mCollapsed = $config['collapsed'] ?? false;
	}

	/**
	 * Get the string (HTML) representation of the calculator
	 *
	 * @return string
	 */
	public function toString(): string {
		return $this->getHtml();
	}

	/**
	 * Get the HTML for the calculator.
	 *
	 * @return string
	 */
	public function getHtml(): string {
		$items = [];
		$items[] = new MultilineTextInputWidget( [
			'classes' => [ 'mw-checkuser-cidr-iplist' ],
			'rows' => 5,
			'dir' => 'ltr',
		] );
		$input = new CIDRCalculatorResultBox( [
			'size' => 35,
			'classes' => [ 'mw-checkuser-cidr-res' ],
			'name' => 'mw-checkuser-cidr-res',
		] );
		$items[] = new LabelWidget( [
			'input' => $input,
			'classes' => [ 'mw-checkuser-cidr-res-label' ],
			'label' => $this->out->msg( 'checkuser-cidr-res' )->text(),
		] );
		$items[] = $input;
		$items[] = new LabelWidget( [
			'classes' => [ 'mw-checkuser-cidr-tool-links' ]
		] );
		$items[] = new LabelWidget( [
			'classes' => [ 'mw-checkuser-cidr-ipnote' ]
		] );
		// From OOUIForm but modified.
		if ( is_string( $this->mWrapperLegend ) ) {
			$attributes = [
					'label' => $this->mWrapperLegend,
					'collapsed' => $this->mCollapsed,
					'items' => $items,
				] + Element::configFromHtmlAttributes( $this->mWrapperAttributes );
			if ( $this->mCollapsible ) {
				$content = new CollapsibleFieldsetLayout( $attributes );
			} else {
				$content = new FieldsetLayout( $attributes );
			}
		} else {
			$content = new Widget( [
				'content' => $items
			] );
		}
		return ( new PanelLayout( [
			'classes' => [ 'mw-checkuser-cidrform mw-checkuser-cidr-calculator-hidden' ],
			'id' => 'mw-checkuser-cidrform',
			'expanded' => false,
			'padded' => $this->mWrapperLegend !== false,
			'framed' => $this->mWrapperLegend !== false,
			'content' => $content,
		] ) )->toString();
	}

	/**
	 * Magic method implementation.
	 *
	 * Copied from OOUI\Tag
	 *
	 * @return string
	 */
	public function __toString() {
		try {
			return $this->toString();
		} catch ( \Exception $ex ) {
			trigger_error( (string)$ex, E_USER_ERROR );
		}
	}
}
