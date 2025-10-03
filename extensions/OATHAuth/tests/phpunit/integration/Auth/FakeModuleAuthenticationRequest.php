<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Auth;

use MediaWiki\Auth\AuthenticationRequest;

class FakeModuleAuthenticationRequest extends AuthenticationRequest {

	/**
	 * @param string $moduleName The module this request is for (as in IModule::getName).
	 * @param bool|null $pass Whether the user passed the check.
	 */
	public function __construct(
		public string $moduleName,
		public ?bool $pass = null
	) {
	}

	/** @inheritDoc */
	public function getFieldInfo() {
	}

}
