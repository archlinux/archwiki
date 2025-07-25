<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences\Consequence;

use MediaWiki\Block\BlockUserFactory;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Block\UnblockUserFactory;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\Extension\AbuseFilter\GlobalNameUtils;
use MediaWiki\Permissions\Authority;
use MessageLocalizer;
use Psr\Log\LoggerInterface;

/**
 * Consequence that blocks a single user.
 */
class Block extends BlockingConsequence implements ReversibleConsequence {

	private bool $preventsTalkEdit;
	private DatabaseBlockStore $databaseBlockStore;
	private UnblockUserFactory $unblockUserFactory;

	/**
	 * @param Parameters $params
	 * @param string $expiry
	 * @param bool $preventTalkEdit
	 * @param BlockUserFactory $blockUserFactory
	 * @param UnblockUserFactory $unblockUserFactory
	 * @param DatabaseBlockStore $databaseBlockStore
	 * @param FilterUser $filterUser
	 * @param MessageLocalizer $messageLocalizer
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		Parameters $params,
		string $expiry,
		bool $preventTalkEdit,
		BlockUserFactory $blockUserFactory,
		UnblockUserFactory $unblockUserFactory,
		DatabaseBlockStore $databaseBlockStore,
		FilterUser $filterUser,
		MessageLocalizer $messageLocalizer,
		LoggerInterface $logger
	) {
		parent::__construct( $params, $expiry, $blockUserFactory, $filterUser, $messageLocalizer, $logger );
		$this->unblockUserFactory = $unblockUserFactory;
		$this->databaseBlockStore = $databaseBlockStore;
		$this->preventsTalkEdit = $preventTalkEdit;
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): bool {
		$status = $this->doBlockInternal(
			$this->parameters->getFilter()->getName(),
			$this->parameters->getFilter()->getID(),
			$this->parameters->getUser()->getName(),
			$this->expiry,
			$autoblock = true,
			$this->preventsTalkEdit
		);
		// TODO: Should we reblock in case of partial blocks? At that point we could return
		// the status of doBlockInternal
		return defined( 'MW_PHPUNIT_TEST' ) ? $status->isOK() : true;
	}

	/**
	 * @inheritDoc
	 */
	public function revert( Authority $performer, string $reason ): bool {
		$blocks = $this->databaseBlockStore->newListFromTarget(
			$this->parameters->getUser()->getName(), null, false, DatabaseBlockStore::AUTO_NONE );
		foreach ( $blocks as $block ) {
			if ( $block->getBy() === $this->filterUser->getUserIdentity()->getId() ) {
				return $this->unblockUserFactory->newRemoveBlock(
					$block,
					$performer,
					$reason,
				)->unblockUnsafe()->isOK();
			}
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage(): array {
		$filter = $this->parameters->getFilter();
		return [
			'abusefilter-blocked-display',
			$filter->getName(),
			GlobalNameUtils::buildGlobalName( $filter->getID(), $this->parameters->getIsGlobalFilter() )
		];
	}

	/**
	 * @return string
	 * @internal
	 */
	public function getExpiry(): string {
		return $this->expiry;
	}
}
