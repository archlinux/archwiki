<?php

namespace MediaWiki\Extension\DiscussionTools;

use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;

/**
 * Representation of a subscription to a given topic.
 */
class SubscriptionItem {
	/**
	 * @param UserIdentity $user
	 * @param string $itemName
	 * @param LinkTarget $linkTarget
	 * @param int $state One of SubscriptionStore::STATE_* constants
	 * @param string|null $createdTimestamp When the subscription was created
	 * @param string|null $notifiedTimestamp When the item subscribed to last tried to trigger
	 *                                       a notification (even if muted).
	 */
	public function __construct(
		private readonly UserIdentity $user,
		private readonly string $itemName,
		private readonly LinkTarget $linkTarget,
		private readonly int $state,
		private readonly ?string $createdTimestamp,
		private readonly ?string $notifiedTimestamp
	) {
	}

	public function getUserIdentity(): UserIdentity {
		return $this->user;
	}

	public function getItemName(): string {
		return $this->itemName;
	}

	public function getLinkTarget(): LinkTarget {
		return $this->linkTarget;
	}

	/**
	 * Get the creation timestamp of this entry.
	 */
	public function getCreatedTimestamp(): ?string {
		return $this->createdTimestamp;
	}

	/**
	 * Get the notification timestamp of this entry.
	 */
	public function getNotificationTimestamp(): ?string {
		return $this->notifiedTimestamp;
	}

	/**
	 * Get the subscription status of this entry.
	 *
	 * @return int One of SubscriptionStore::STATE_* constants
	 */
	public function getState(): int {
		return $this->state;
	}

	/**
	 * Check if the notification is muted
	 */
	public function isMuted(): bool {
		return $this->state === SubscriptionStore::STATE_UNSUBSCRIBED;
	}
}
