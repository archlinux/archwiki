<?php

namespace MediaWiki\CheckUser\Hook;

interface CheckUserSuggestedInvestigationsGetSignalsHook {
	/**
	 * This hook is run when the list of defined Suggested investigation signals is being fetched.
	 *
	 * Any signals defined using private code should also hook into this hook to define their signals.
	 * It is used to generate a list of signals for UI elements where we want the full list of signals
	 * as opposed to the signals we see in open suggested investigations.
	 *
	 * NOTE: Private code handles this hook, so updating it's signature may break code not visible
	 * in codesearch.
	 *
	 * @since 1.45
	 *
	 * @param array &$signals The list of signals that are defined. These names should match up with the
	 *   names for the signals as returned by {@link SuggestedInvestigationsSignalMatchResult::getName}.
	 *   Handlers should add signals to this array.
	 */
	public function onCheckUserSuggestedInvestigationsGetSignals( array &$signals ): void;
}
