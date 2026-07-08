<?php

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Instrumentation;

use MediaWiki\Context\IContextSource;
use MediaWiki\User\UserIdentity;

/**
 * Interface for classes that emitting server-side interaction events to
 * the Suggested Investigations Metrics Platform instrument.
 */
interface ISuggestedInvestigationsInstrumentationClient {

	/**
	 * Emit an interaction event to the Suggested Investigations Metrics Platform instrument.
	 *
	 * @internal For use by Suggested Investigations code only
	 * @param IContextSource $context
	 * @param string $action The action name to use for the interaction
	 * @param array $interactionData Interaction data for the event
	 */
	public function submitInteraction( IContextSource $context, string $action, array $interactionData ): void;

	/**
	 * Given an array of user IDs, return a list of mediawiki/state/entity/user fragments as arrays
	 * that describe the users with the user IDs.
	 *
	 * @internal For use by Suggested Investigations code only
	 * @param UserIdentity[] $userIdentities The users in the case
	 * @return array[] An array of arrays returned by {@link UserEntitySerializer::toArray}
	 */
	public function getUserFragmentsArray( array $userIdentities ): array;
}
