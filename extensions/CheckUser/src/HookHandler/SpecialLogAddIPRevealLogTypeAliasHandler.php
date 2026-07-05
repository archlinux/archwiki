<?php

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Hook\SpecialLogGetSubpagesForPrefixSearchHook;
use MediaWiki\MainConfigNames;
use MediaWiki\Specials\Hook\SpecialLogResolveLogTypeHook;

/**
 * 'ipreveal' is an alias for the checkuser-temporary-account log type used
 * to make it easier to type a URL. This class implements hooks for:
 *
 * - T381875: Resolve the log type to the canonical name "checkuser-temporary-account"
 *   when it is provided in URLs as "ipreveal" so the page displays the logs the user
 *   is looking for
 *
 * - T398293: List the alias "ipreveal" as a valid log subpage when the user is
 *   types Special:Log in the search box, so as it may identify the alias better than
 *   the long name.
 */
class SpecialLogAddIPRevealLogTypeAliasHandler implements
	SpecialLogResolveLogTypeHook,
	SpecialLogGetSubpagesForPrefixSearchHook
{
	private const ALIAS_NAME = 'ipreveal';

	/** @inheritDoc */
	public function onSpecialLogResolveLogType(
		array $params,
		string &$type
	): void {
		if ( $type === self::ALIAS_NAME ) {
			$type = TemporaryAccountLogger::LOG_TYPE;
		}
	}

	/** @inheritDoc */
	public function onSpecialLogGetSubpagesForPrefixSearch(
		IContextSource $context,
		array &$subpages
	): void {
		if ( $this->canAccessIPRevealLog( $context ) ) {
			$subpages[] = self::ALIAS_NAME;
		}
	}

	private function canAccessIPRevealLog( IContextSource $context ): bool {
		$performer = $context->getAuthority();
		$logRestrictions = $context->getConfig()->get(
			MainConfigNames::LogRestrictions
		);

		if ( !isset( $logRestrictions[ TemporaryAccountLogger::LOG_TYPE ] ) ) {
			return true;
		}

		return $performer->isAllowed(
			$logRestrictions[ TemporaryAccountLogger::LOG_TYPE ]
		);
	}
}
