<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services;

use LogicException;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForUserJob;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use Psr\Log\LoggerInterface;

class SuggestedInvestigationsAutoCloseCrossWikiJobDispatcher {

	public function __construct(
		private readonly JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly LoggerInterface $logger,
		private readonly bool $isCentralAuthLoaded,
		private readonly string $currentWikiId
	) {
	}

	/**
	 * Creates {@link SuggestedInvestigationsAutoCloseForUserJob} jobs for the provided username
	 * on all wikis that the user has a local account.
	 */
	public function dispatch( string $username ): void {
		if ( !$this->isCentralAuthLoaded ) {
			$this->logger->warning(
				'Found no attached wikis for user {username} and cannot check autoclose of blocks',
				[ 'username' => $username ]
			);

			return;
		}

		$attachedWikis = CentralAuthUser::getInstanceByName( $username )->listAttached();

		foreach ( $attachedWikis as $wikiId ) {
			if ( $wikiId === $this->currentWikiId ) {
				continue;
			}

			try {
				$this->jobQueueGroupFactory->makeJobQueueGroup( $wikiId )->lazyPush(
					SuggestedInvestigationsAutoCloseForUserJob::newSpec( $username )
				);
			} catch ( LogicException $e ) {
				$this->logger->warning(
					'Failed to push cross-wiki auto-close job to wiki {wikiId}: {error}',
					[ 'wikiId' => $wikiId, 'error' => $e->getMessage() ]
				);
			}
		}
	}

}
