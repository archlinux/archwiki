<?php
namespace MediaWiki\Extension\CheckUser\Tests\Integration\AbuseFilter;

use MediaWiki\Extension\AbuseFilter\Filter\Filter;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\FilterFromSpecsTestTrait;
use TestUser;
use TestUserRegistry;
use Wikimedia\Rdbms\IDatabase;

trait FilterFactoryProxyTrait {

	/**
	 * Returns a factory class that wraps a call to getFilterFromSpecs() from
	 * the FilterFromSpecsTestTrait provided by AbuseFilter, therefore acting as
	 * a proxy for the original trait.
	 *
	 * A factory class is used instead of applying the method directly to the
	 * test case class since, when applied to the test class directly, it will
	 * fail to load unless AbuseFilter is available in the extension directory
	 * AND loaded in LocalSettings.php.
	 *
	 * The anonymous class returned by this method allows for calling the
	 * private method from the trait, while allowing for not trying to load the
	 * trait at all if AbuseFilter is not loaded.
	 */
	private function getFilterFactoryProxy(): object {
		return new class( $this->getDb() ) {
			use FilterFromSpecsTestTrait;

			public function __construct(
				private readonly IDatabase $dbw,
			) {
			}

			public function getFilter( array $specs ): Filter {
				return $this->getFilterFromSpecs( $specs );
			}

			protected function getDb(): IDatabase {
				return $this->dbw;
			}

			/**
			 * @param array $groups Groups the test user belongs to
			 */
			protected function getTestUser( $groups = [] ): TestUser {
				return TestUserRegistry::getImmutableTestUser( $groups );
			}
		};
	}

}
