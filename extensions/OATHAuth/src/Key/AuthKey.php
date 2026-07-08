<?php

namespace MediaWiki\Extension\OATHAuth\Key;

use JsonSerializable;
use MediaWiki\Extension\OATHAuth\OATHUser;

abstract class AuthKey implements JsonSerializable {

	protected bool $supportsPasswordless = false;

	public function __construct(
		protected readonly ?int $id,
		protected ?string $friendlyName,
		protected readonly ?string $createdTimestamp,
	) {
	}

	/**
	 * @return string The name of the module this key is attached to
	 * @see IModule::getName()
	 */
	abstract public function getModule(): string;

	/**
	 * @return int|null the ID of this key in the oathauth_devices table, or null if this key has not been saved yet
	 */
	public function getId(): ?int {
		return $this->id;
	}

	/**
	 * @return string|null the user generated name of this key in the oathauth_devices table,
	 * or null if this key was created before this data was saved in the database
	 */
	public function getFriendlyName(): ?string {
		return $this->friendlyName;
	}

	/**
	 * @return string|null the timestamp of this key in the oathauth_devices table, or null if this key was created
	 * before timestamp data was saved in the database
	 */
	public function getCreatedTimestamp(): ?string {
		return $this->createdTimestamp;
	}

	/**
	 * @return bool whether the auth key supports passwordless login or not
	 */
	public function supportsPasswordlessLogin(): bool {
		return $this->supportsPasswordless;
	}

	abstract public function verify( OATHUser $user, array $data ): bool;
}
