<?php

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Navigation;

use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Navigation\CodexPagerNavigationBuilder;
use Wikimedia\Codex\Utility\Codex;

class SuggestedInvestigationsPagerNavigationBuilder extends CodexPagerNavigationBuilder {
	public function __construct(
		private readonly IContextSource $context,
		array $queryValues,
		private readonly int $numberOfFiltersApplied,
	) {
		parent::__construct( $this->context, $queryValues );
	}

	/**
	 * Returns the limit form for the CodexTablePager.
	 *
	 * This is modified to add a filters button which is hidden
	 * unless the user is using a mobile device, so that the limit and filters buttons
	 * can be on the same line on mobile devices.
	 *
	 * @inheritDoc
	 */
	public function getLimitForm(): string {
		return parent::getLimitForm() . $this->getFilterButton() . "\n";
	}

	/**
	 * Returns a Codex button that can be used to open the filter dialog
	 * on the Special:SuggestedInvestigations page
	 */
	public function getFilterButton(): string {
		$buttonLabelHtml = Html::element(
			'span',
			[
				'aria-hidden' => 'true',
				'class' => 'mw-checkuser-suggestedinvestigations-icon--filter cdx-button__icon',
			]
		);
		$buttonLabelHtml .= $this->msg( 'checkuser-suggestedinvestigations-filter-button' )->escaped();
		if ( $this->numberOfFiltersApplied !== 0 ) {
			$codex = new Codex();
			$buttonLabelHtml .= $codex->infoChip()
				->setIcon( null )
				->setAttributes( [
					'class' => 'mw-checkuser-suggestedinvestigations-filter-button-filters-applied-chip',
				] )
				->setText( $this->context->getLanguage()->formatNum( $this->numberOfFiltersApplied ) )
				->build()
				->getHtml();
		}

		return Html::rawElement(
			'button',
			[
				'type' => 'button',
				'aria-label' => $this->msg(
					'checkuser-suggestedinvestigations-filter-button'
				)->text(),
				'aria-haspopover' => 'dialog',
				'class' => 'mw-checkuser-suggestedinvestigations-filter-button cdx-button',
			],
			$buttonLabelHtml
		);
	}
}
