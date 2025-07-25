<?php

use MediaWiki\Request\FauxRequest;

/**
 * @group Database
 * @license GPL-2.0-or-later
 */
class TemplateDataSpecialPageTest extends SpecialPageTestBase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUserLang( 'qqx' );
	}

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'TemplateSearch' );
	}

	/**
	 * Get the full HTML of the special page
	 *
	 * @return array
	 */
	private function getSpecialPageHtml() {
		return $this->executeSpecialPage( '', new FauxRequest(), null, $this->getTestUser()->getUser(), true );
	}

	/**
	 * Test that the special page loads when the feature is enabled
	 *
	 * @covers \MediaWiki\Extension\TemplateData\Special\SpecialTemplateDiscovery::execute
	 */
	public function testSpecialPageWhenFeatureEnabled() {
		$this->overrideConfigValue( 'TemplateDataEnableDiscovery', true );
		[ $html ] = $this->getSpecialPageHtml();
		$this->assertStringNotContainsString( '(templatedata-template-discovery-disabled', $html );
		$this->assertStringContainsString( 'id="ext-TemplateData-SpecialTemplateSearch-widget"', $html );
	}

	/**
	 * Test that the special page does not load when the feature is disabled
	 *
	 * @covers \MediaWiki\Extension\TemplateData\Special\SpecialTemplateDiscovery::execute
	 */
	public function testSpecialPageWhenFeatureDisabled() {
		$this->overrideConfigValue( 'TemplateDataEnableDiscovery', false );
		[ $html ] = $this->getSpecialPageHtml();
		$this->assertStringContainsString( '(templatedata-template-discovery-disabled', $html );
	}

}
