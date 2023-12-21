<?php

namespace MediaWiki\Extension\DiscussionTools\Tests;

use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;

abstract class IntegrationTestCase extends MediaWikiIntegrationTestCase {

	use TestUtils;

	/**
	 * Setup the MW environment
	 *
	 * @param array $config
	 * @param array $data
	 */
	protected function setupEnv( array $config, array $data ): void {
		$this->overrideConfigValues( [
				MainConfigNames::NamespaceAliases => $config['wgNamespaceIds'],
				MainConfigNames::MetaNamespace => strtr( $config['wgFormattedNamespaces'][NS_PROJECT], ' ', '_' ),
				MainConfigNames::MetaNamespaceTalk =>
					strtr( $config['wgFormattedNamespaces'][NS_PROJECT_TALK], ' ', '_' ),
				// TODO: Move this to $config
				MainConfigNames::Localtimezone => $data['localTimezone'],
				// Data used for the tests assumes there are no variants for English.
				// Language variants are tested using other languages.
				MainConfigNames::UsePigLatinVariant => false,
				// Consistent defaults for generating canonical URLs
				MainConfigNames::Server => 'https://example.org',
				MainConfigNames::CanonicalServer => 'https://example.org',
			] + $config );
		$this->setUserLang( $config['wgContentLanguage'] );
		$this->setContentLang( $config['wgContentLanguage'] );
	}
}
