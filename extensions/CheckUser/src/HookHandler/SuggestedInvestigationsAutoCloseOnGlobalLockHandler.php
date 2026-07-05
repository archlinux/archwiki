<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\HookHandler;

use MediaWiki\Extension\CentralAuth\Hooks\CentralAuthGlobalUserLockStatusChangedHook;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
// phpcs:ignore Generic.Files.LineLength
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\UserIdentityLookup;
use Psr\Log\LoggerInterface;

class SuggestedInvestigationsAutoCloseOnGlobalLockHandler
	extends AbstractSuggestedInvestigationsAutoCloseHandler
	implements CentralAuthGlobalUserLockStatusChangedHook
{

	public function __construct(
		SuggestedInvestigationsCaseLookupService $caseLookupService,
		JobQueueGroup $jobQueueGroup,
		LoggerInterface $logger,
		private readonly UserIdentityLookup $userIdentityLookup,
		private readonly SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher $crossWikiJobDispatcher,
	) {
		parent::__construct( $caseLookupService, $jobQueueGroup, $logger );
	}

	/** @inheritDoc */
	public function onCentralAuthGlobalUserLockStatusChanged(
		CentralAuthUser $centralAuthUser,
		bool $isLocked
	): void {
		if ( !$isLocked ) {
			return;
		}

		if ( !$this->caseLookupService->areSuggestedInvestigationsEnabled() ) {
			return;
		}

		$this->crossWikiJobDispatcher->dispatch( $centralAuthUser->getName() );

		$localUser = $this->userIdentityLookup->getUserIdentityByName( $centralAuthUser->getName() );
		if ( $localUser === null || !$localUser->isRegistered() ) {
			return;
		}

		$this->enqueueAutoCloseJobsForUser( $localUser->getId() );
	}

}
