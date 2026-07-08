<?php

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Instrumentation;

use MediaWiki\Context\IContextSource;

/**
 * A implementation of {@link ISuggestedInvestigationsInstrumentationClient} that is
 * a no-op. Used by {@link PopulateSicUrlIdentifier} which needs to create an
 * instance of the client only to pass to code that then doesn't use it.
 *
 * @codeCoverageIgnore Merely declarative
 */
class NoOpSuggestedInvestigationsInstrumentationClient implements ISuggestedInvestigationsInstrumentationClient {

	/** @inheritDoc */
	public function submitInteraction(
		IContextSource $context,
		string $action,
		array $interactionData
	): void {
	}

	/** @inheritDoc */
	public function getUserFragmentsArray( array $userIdentities ): array {
		return [];
	}
}
