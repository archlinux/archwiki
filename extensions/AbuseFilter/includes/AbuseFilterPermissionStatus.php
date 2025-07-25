<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Block\Block;
use StatusValue;

/**
 * Type-safe StatusValue holding the result of a permission check performed in AbuseFilterPermissionManager.
 *
 * Modified copy of the CheckUserPermissionStatus from mediawiki/extensions/CheckUser.
 */
class AbuseFilterPermissionStatus extends StatusValue {
	/** @var Block|null The user block, if any, that caused this permission check to fail. */
	private ?Block $block = null;

	/** @var string|null The missing permission - if applicable - that caused this permission check to fail. */
	private ?string $permission = null;

	protected function __construct() {
		// Use typed static factory methods.
	}

	/**
	 * Create a new fatal AbuseFilterPermissionStatus instance holding an user block.
	 *
	 * @param Block $block The block that caused the permission check to fail.
	 * @return AbuseFilterPermissionStatus
	 */
	public static function newBlockedError( Block $block ): AbuseFilterPermissionStatus {
		$status = new static();
		$status->block = $block;
		$status->ok = false;

		return $status;
	}

	/**
	 * Create a new fatal AbuseFilterPermissionStatus instance holding a permission error.
	 *
	 * @param string $permission The missing permission.
	 * @return AbuseFilterPermissionStatus
	 */
	public static function newPermissionError( string $permission ): AbuseFilterPermissionStatus {
		$status = new static();
		$status->permission = $permission;
		$status->ok = false;

		return $status;
	}

	/**
	 * Get the block that caused this permission check to fail.
	 *
	 * @return Block|null
	 */
	public function getBlock(): ?Block {
		return $this->block;
	}

	/**
	 * Get the missing permission that caused this check to fail.
	 *
	 * @return string|null
	 */
	public function getPermission(): ?string {
		return $this->permission;
	}

	/**
	 * Sets the block that caused the permission check to fail.
	 *
	 * Use this method if you are modifying an existing instance of the {@link AbuseFilterPermissionStatus}.
	 * Otherwise, it is recommended to use {@link AbuseFilterPermissionStatus::newBlockedError}.
	 *
	 * @param Block $block
	 */
	public function setBlock( Block $block ) {
		$this->ok = false;
		$this->block = $block;
	}

	/**
	 * Sets the missing permission that caused this check to fail.
	 *
	 * Use this method if you are modifying an existing instance of the {@link AbuseFilterPermissionStatus}.
	 * Otherwise, it is recommended to use {@link AbuseFilterPermissionStatus::newPermissionError}.
	 *
	 * @param string $permission
	 */
	public function setPermission( string $permission ) {
		$this->ok = false;
		$this->permission = $permission;
	}
}
