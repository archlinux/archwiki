<?php

namespace MediaWiki\CheckUser\Hook;

use MediaWiki\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\User\UserIdentity;

interface CheckUserSuggestedInvestigationsBeforeCaseCreatedHook {
	/**
	 * This hook is run just before a Suggested investigations case is created
	 *
	 * Code can handle this hook to add additional users to a new case, primarily intended for the
	 * case where a suggested investigations signal value match can be shared over multiple users
	 * but a threshold of matched users needs to be met for a case to be created.
	 *
	 * NOTE: Private code handles this hook, so updating its signature may break code not visible
	 * in codesearch.
	 *
	 * @since 1.45
	 *
	 * @param SuggestedInvestigationsSignalMatchResult[] $signals The array of
	 *   {@link SuggestedInvestigationsSignalMatchResult} being associated with the newly created case.
	 * @param UserIdentity[] &$users The users being attached to the case. Handlers of this hook can
	 *   add additional users to this array if desired
	 */
	public function onCheckUserSuggestedInvestigationsBeforeCaseCreated(
		array $signals, array &$users
	): void;
}
