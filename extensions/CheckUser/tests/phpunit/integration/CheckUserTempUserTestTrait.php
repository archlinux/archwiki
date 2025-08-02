<?php

declare( strict_types = 1 );

namespace MediaWiki\CheckUser\Tests\Integration;

use MediaWiki\Tests\User\TempUser\TempUserTestTrait;

trait CheckUserTempUserTestTrait {

	use TempUserTestTrait {
		TempUserTestTrait::enableAutoCreateTempUser as parentEnableAutoCreateTempUser;
		TempUserTestTrait::disableAutoCreateTempUser as parentDisableAutoCreateTempUser;
	}

	public function enableAutoCreateTempUser( array $configOverrides = [] ): void {
		$this->parentEnableAutoCreateTempUser(
			array_merge(
				[ 'genPattern' => '~check-user-test-$1' ],
				$configOverrides
			)
		);
	}

	public function disableAutoCreateTempUser( array $configOverrides = [] ): void {
		$this->parentDisableAutoCreateTempUser(
			array_merge(
				[ 'known' => true, 'matchPattern' => '~check-user-test-$1' ],
				$configOverrides
			)
		);
	}

	/**
	 * Defined to ensure that the class has the overrideConfigValue method that we can use.
	 *
	 * @see \MediaWikiIntegrationTestCase::overrideConfigValue
	 *
	 * @param string $key
	 * @param mixed $value
	 */
	abstract protected function overrideConfigValue( string $key, $value );

}
