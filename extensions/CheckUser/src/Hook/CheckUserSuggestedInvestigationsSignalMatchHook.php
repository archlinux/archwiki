<?php

namespace MediaWiki\CheckUser\Hook;

use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\User\UserIdentity;

interface CheckUserSuggestedInvestigationsSignalMatchHook {
	/**
	 * This hook is run when Suggested investigations signals are being matched against a user
	 *
	 * Code can handle this hook to implement the logic for a suggested investigations signal,
	 * adding {@link SuggestedInvestigationsSignalMatchResult} objects to the $signalMatchResults
	 * array for each signal.
	 *
	 * NOTE: Private code handles this hook, so updating it's signature may break code not visible
	 * in codesearch.
	 *
	 * @since 1.45
	 *
	 * @param UserIdentity $userIdentity The user that the signals should be matching against
	 * @param string $eventType The type of event occurring. One of the EVENT_* constants in
	 *   {@link SuggestedInvestigationsSignalMatchService}, though private code may trigger
	 *   custom event types. Used by hook handlers to exclude matching against events that
	 *   are not relevant for the hook.
	 * @param SuggestedInvestigationsSignalMatchResult[] &$signalMatchResults An array of
	 *   {@link SuggestedInvestigationsSignalMatchResult} objects used to indicate which signals matched and
	 *   which signals did not match. Hook handlers should add to this array if a signal was tested against
	 *   the user.
	 */
	public function onCheckUserSuggestedInvestigationsSignalMatch(
		$userIdentity, string $eventType, array &$signalMatchResults
	): void;
}
