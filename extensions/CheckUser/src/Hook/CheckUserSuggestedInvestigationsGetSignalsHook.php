<?php

namespace MediaWiki\Extension\CheckUser\Hook;

interface CheckUserSuggestedInvestigationsGetSignalsHook {
	/**
	 * This hook is run when the list of defined Suggested investigation signals is being fetched.
	 *
	 * Any signals defined using private code should also hook into this hook to define their signals.
	 * It is used to generate a list of signals for UI elements and filters where we want the full list
	 * of signals as opposed to the signals we see in open suggested investigations.
	 *
	 * NOTE: Private code handles this hook, so updating it's signature may break code not visible
	 * in codesearch.
	 *
	 * @since 1.45
	 *
	 * @param string[]|array[] &$signals The list of signals that are defined, either represented as:
	 *   * A string representing the name of the signal as used in the database
	 *   * An array of properties about the signal with the following keys:
	 *     * name - The name of the signal as used in the database
	 *     * displayName - The text that represents the name of the signal, used instead of an
	 *         i18n message key constructed from the 'name'
	 *     * description - The text that gives a short description about the signal including the
	 *         name of the signal, used instead of an i18n message key constructed from the 'name'.
	 *         Used in the signals popover.
	 *     * urlName - A string used to identify the signal in the URL, which should be stable
	 *         but ideally a random string to avoid exposing the name of the signal in the URL
	 *
	 *   The name of the signal as used in the database should match up with the
	 *   names for the signals as returned by {@link SuggestedInvestigationsSignalMatchResult::getName}.
	 *   Handlers should add signals to this array.
	 */
	public function onCheckUserSuggestedInvestigationsGetSignals( array &$signals ): void;
}

// @codeCoverageIgnoreStart
/**
 * @deprecated since 1.46
 */
class_alias(
	CheckUserSuggestedInvestigationsGetSignalsHook::class,
	'MediaWiki\\CheckUser\\Hook\\CheckUserSuggestedInvestigationsGetSignalsHook'
);
// @codeCoverageIgnoreEnd
