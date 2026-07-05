<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Specials\Hook\BlockIpCompleteHook;

class SuggestedInvestigationsAutoCloseOnUsersBlockedHandler
	extends AbstractSuggestedInvestigationsAutoCloseHandler
	implements BlockIpCompleteHook
{

	/** @inheritDoc */
	public function onBlockIpComplete( $block, $user, $priorBlock ): void {
		if ( !$this->caseLookupService->areSuggestedInvestigationsEnabled() ) {
			return;
		}

		$targetUser = $block->getTargetUserIdentity();
		if ( $targetUser === null || !$block->isSitewide() || !$block->isIndefinite() ) {
			return;
		}

		$this->enqueueAutoCloseJobsForUser( $targetUser->getId() );
	}

}
