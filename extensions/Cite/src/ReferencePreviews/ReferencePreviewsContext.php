<?php

namespace Cite\ReferencePreviews;

use MediaWiki\Config\Config;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use Skin;

/**
 * @license GPL-2.0-or-later
 */
class ReferencePreviewsContext {

	private Config $config;
	private ReferencePreviewsGadgetsIntegration $gadgetsIntegration;
	private UserOptionsLookup $userOptionsLookup;

	public function __construct(
		Config $config,
		ReferencePreviewsGadgetsIntegration $gadgetsIntegration,
		UserOptionsLookup $userOptionsLookup
	) {
		$this->config = $config;
		$this->gadgetsIntegration = $gadgetsIntegration;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * User preference key to enable/disable Reference Previews. Named
	 * "mwe-popups-referencePreviews-enabled" in localStorage for anonymous users.
	 */
	public const REFERENCE_PREVIEWS_PREFERENCE_NAME = 'popups-reference-previews';

	/**
	 * If the client-side code for Reference Previews should continue loading
	 * (see isReferencePreviewsEnabled.js), incorporating decisions we can only make after the
	 * ResourceLoader module was registered via {@see CiteHooks::onResourceLoaderRegisterModules}.
	 */
	public function isReferencePreviewsEnabled( User $user, Skin $skin ): bool {
		if (
			// T243822: Temporarily disabled in the mobile skin
			$skin->getSkinName() === 'minerva' ||
			// The feature flag is also checked in the ResourceLoaderRegisterModules hook handler
			// and technically redundant here, but it's cheap; better safe than sorry
			!$this->config->get( 'CiteReferencePreviews' ) ||
			$this->gadgetsIntegration->isRefToolTipsGadgetEnabled( $user ) ||
			$this->gadgetsIntegration->isNavPopupsGadgetEnabled( $user )
		) {
			return false;
		}

		// Anonymous users can (de)activate the feature via a cookie at runtime, hence it must load
		return !$user->isNamed() || $this->userOptionsLookup->getBoolOption(
			$user, self::REFERENCE_PREVIEWS_PREFERENCE_NAME
		);
	}
}
