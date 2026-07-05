<?php

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model;

use MediaWiki\DAO\WikiAwareEntityTrait;
use MediaWiki\User\UserIdentity;

/**
 * This class represents a user in a Suggested Investigations case, which may yet to
 * be added to the case.
 *
 * NOTE: This class is constructed by private code and so breaking changes to the constructor
 * should be avoided
 *
 * @stable to construct
 */
readonly class SuggestedInvestigationsCaseUser implements UserIdentity {

	use WikiAwareEntityTrait;

	/**
	 * @param UserIdentity $userIdentity The user being represented
	 * @param int $userInfoBitFlags Bit flags about the user
	 */
	public function __construct(
		private UserIdentity $userIdentity,
		private int $userInfoBitFlags,
	) {
	}

	/**
	 * Returns the bit flags to be stored in the siu_info column of the cusi_user table
	 * representing this user.
	 *
	 * If the user is already in the case, then this bit flag is combined with the other
	 * bit flag using the bitwise inclusive OR operator (`|`).
	 */
	public function getUserInfoBitFlags(): int {
		return $this->userInfoBitFlags;
	}

	/** @inheritDoc */
	public function getId( $wikiId = self::LOCAL ): int {
		return $this->userIdentity->getId( $wikiId );
	}

	/** @inheritDoc */
	public function getName(): string {
		return $this->userIdentity->getName();
	}

	/** @inheritDoc */
	public function getWikiId() {
		return $this->userIdentity->getWikiId();
	}

	/** @inheritDoc */
	public function equals( ?UserIdentity $user ): bool {
		return $this->userIdentity->equals( $user );
	}

	/** @inheritDoc */
	public function isRegistered(): bool {
		return $this->userIdentity->isRegistered();
	}
}

// @codeCoverageIgnoreStart
/**
 * @deprecated since 1.46
 */
class_alias(
	SuggestedInvestigationsCaseUser::class,
	'MediaWiki\\CheckUser\\SuggestedInvestigations\\Model\\SuggestedInvestigationsCaseUser'
);
// @codeCoverageIgnoreEnd
