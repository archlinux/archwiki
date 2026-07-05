<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Navigation;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Navigation\SuggestedInvestigationsPagerNavigationBuilder;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Navigation\SuggestedInvestigationsPagerNavigationBuilder
 */
class SuggestedInvestigationsPagerNavigationBuilderTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->setUserLang( 'qqx' );
	}

	public function testGetFilterButtonWithNoFiltersApplied() {
		$navBuilder = $this->initializeNavBuilder( 0 );
		$actualFiltersButtonHtml = $navBuilder->getFilterButton();

		$this->assertStringContainsString(
			'mw-checkuser-suggestedinvestigations-filter-button',
			$actualFiltersButtonHtml,
			'Missing CSS class for the filter button'
		);
		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-filter-button)',
			$actualFiltersButtonHtml,
			'Button label was not as expected'
		);
		$this->assertStringNotContainsString(
			'mw-checkuser-suggestedinvestigations-filter-button-filters-applied-chip',
			$actualFiltersButtonHtml,
			'The info chip indicating how many filters were applied should not be present'
		);
	}

	public function testGetFilterButtonWithFiltersApplied() {
		$navBuilder = $this->initializeNavBuilder( 2 );
		$actualFiltersButtonHtml = $navBuilder->getFilterButton();

		$this->assertStringContainsString(
			'mw-checkuser-suggestedinvestigations-filter-button',
			$actualFiltersButtonHtml,
			'Missing CSS class for the filter button'
		);
		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-filter-button)',
			$actualFiltersButtonHtml,
			'Button label was not as expected'
		);
		$this->assertStringContainsString(
			'mw-checkuser-suggestedinvestigations-filter-button-filters-applied-chip',
			$actualFiltersButtonHtml,
			'The info chip indicating how many filters were applied was not present'
		);
	}

	public function testGetLimitForm() {
		$navBuilder = $this->initializeNavBuilder( 0 );
		$actualLimitFormHtml = $navBuilder->getLimitForm();

		$this->assertStringContainsString(
			'mw-checkuser-suggestedinvestigations-filter-button',
			$actualLimitFormHtml,
			'Limit form should have the filter button present in the HTML'
		);
	}

	private function initializeNavBuilder(
		int $numberOfFiltersApplied
	): SuggestedInvestigationsPagerNavigationBuilder {
		return new SuggestedInvestigationsPagerNavigationBuilder(
			RequestContext::getMain(),
			[],
			$numberOfFiltersApplied
		);
	}
}
