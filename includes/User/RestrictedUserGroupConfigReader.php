<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\User;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use MediaWiki\WikiMap\WikiMap;

/**
 * A helper class to read the restricted user groups configuration for a given wiki.
 *
 * @since 1.46
 */
class RestrictedUserGroupConfigReader {

	/** @internal */
	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::RestrictedGroups,
	];

	/**
	 * Scope value representing user groups as supported by MediaWiki core.
	 * Other scopes may be added by extensions.
	 */
	public const SCOPE_LOCAL = 'local';

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly UserRequirementsConditionValidator $userRequirementsConditionValidator,
	) {
	}

	/**
	 * Reads the restricted group configuration for the specified wiki, either from the ServiceOptions provided to
	 * the constructor (if the wiki is the local wiki) or from the global $wgConf variable (otherwise).
	 * Only restrictions relevant for the given scope are returned: restrictions without a `scope` key are always
	 * included, while restrictions with a `scope` key are only included if the requested scope is listed.
	 * @param false|string $wiki The wiki ID for which to read the configuration. `false` means the current wiki.
	 * @param string $scope The scope for which to read the configuration. Defaults to {@see self::SCOPE_LOCAL}.
	 * @return array<string, UserGroupRestrictions> An array mapping group names to their restrictions.
	 */
	public function getConfig( false|string $wiki = false, string $scope = self::SCOPE_LOCAL ): array {
		$isLocal = $wiki === false || $wiki === WikiMap::getCurrentWikiId();
		if ( $isLocal ) {
			$rawConfig = $this->getConfigForLocalWiki();
		} else {
			$rawConfig = $this->getConfigForRemoteWiki( $wiki );
		}

		$result = [];
		foreach ( $rawConfig as $groupName => $spec ) {
			if ( isset( $spec['scope'] ) && !in_array( $scope, $spec['scope'], true ) ) {
				continue;
			}
			$result[$groupName] = UserGroupRestrictions::newFromSpecValidated(
				$spec,
				$this->userRequirementsConditionValidator
			);
		}
		return $result;
	}

	private function getConfigForLocalWiki(): array {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		return $this->options->get( MainConfigNames::RestrictedGroups );
	}

	private function getConfigForRemoteWiki( string $wiki ): array {
		global $wgConf;
		'@phan-var \MediaWiki\Config\SiteConfiguration $wgConf';

		return $wgConf->get( 'wgRestrictedGroups', $wiki ) ?? [];
	}
}
