<?php
namespace MediaWiki\CheckUser;

use MediaWiki\Block\Block;
use StatusValue;

/**
 * Type-safe StatusValue holding the result of a permission check performed by CheckUser.
 */
class CheckUserPermissionStatus extends StatusValue {
	/**
	 * The user block, if any, that caused this permission check to fail.
	 * @var Block|null
	 */
	private ?Block $block = null;

	/**
	 * The missing permission - if applicable - that caused this permission check to fail.
	 * @var string|null
	 */
	private ?string $permission = null;

	protected function __construct() {
		// Use typed static factory methods.
	}

	/**
	 * Create a new fatal CheckUserPermissionStatus instance holding an user block.
	 * @param Block $block The block that caused the permission check to fail.
	 * @return CheckUserPermissionStatus
	 */
	public static function newBlockedError( Block $block ): CheckUserPermissionStatus {
		$status = new static();
		$status->block = $block;
		$status->ok = false;

		return $status;
	}

	/**
	 * Create a new fatal CheckUserPermissionStatus instance holding a permission error.
	 * @param string $permission The missing permission.
	 * @return CheckUserPermissionStatus
	 */
	public static function newPermissionError( string $permission ): CheckUserPermissionStatus {
		$status = new static();
		$status->permission = $permission;
		$status->ok = false;

		return $status;
	}

	/**
	 * Get the block that caused this permission check to fail.
	 * @return Block|null
	 */
	public function getBlock(): ?Block {
		return $this->block;
	}

	/**
	 * Get the missing permission that caused this check to fail.
	 * @return string|null
	 */
	public function getPermission(): ?string {
		return $this->permission;
	}
}
