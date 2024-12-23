<?php

namespace Shellbox\Command;

trait UserDataTrait {
	/** @var array */
	private $userData = [];

	/**
	 * Set some data on the object. This will not be transmitted to the server
	 * or otherwise used.
	 *
	 * @since 4.1.0
	 * @param string $name
	 * @param mixed $value
	 * @return $this
	 */
	public function userData( string $name, $value ) {
		$this->userData[$name] = $value;
		return $this;
	}

	/**
	 * Get data which was previously set.
	 *
	 * @since 4.1.0
	 * @param string $name
	 * @return mixed|null
	 */
	public function getUserData( string $name ) {
		return $this->userData[$name] ?? null;
	}

}
