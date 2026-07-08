<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences\Consequence;

use MediaWiki\Extension\AbuseFilter\BlockAutopromoteStore;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\GlobalNameUtils;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentityUtils;

/**
 * Consequence that blocks/delays autopromotion of a registered user.
 */
class BlockAutopromote extends Consequence implements HookAborterConsequence, ReversibleConsequence {
	public function __construct(
		Parameters $params,
		private readonly int $duration,
		private readonly BlockAutopromoteStore $blockAutopromoteStore,
		private readonly MessageLocalizer $messageLocalizer,
		private readonly UserIdentityUtils $userIdentityUtils
	) {
		parent::__construct( $params );
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): bool {
		$target = $this->parameters->getUser();
		if ( !$this->userIdentityUtils->isNamed( $target ) ) {
			return false;
		}

		return $this->blockAutopromoteStore->blockAutoPromote(
			$target,
			$this->messageLocalizer->msg(
				'abusefilter-blockautopromotereason',
				$this->parameters->getFilter()->getName(),
				$this->parameters->getFilter()->getID()
			)->inContentLanguage()->text(),
			$this->duration
		);
	}

	/**
	 * @inheritDoc
	 */
	public function revert( Authority $performer, string $reason ): bool {
		return $this->blockAutopromoteStore->unblockAutopromote(
			$this->parameters->getUser(),
			$performer->getUser(),
			$reason
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage(): array {
		$filter = $this->parameters->getFilter();
		return [
			'abusefilter-autopromote-blocked',
			$filter->getName(),
			GlobalNameUtils::buildGlobalName( $filter->getID(), $this->parameters->getIsGlobalFilter() ),
			$this->duration
		];
	}
}
