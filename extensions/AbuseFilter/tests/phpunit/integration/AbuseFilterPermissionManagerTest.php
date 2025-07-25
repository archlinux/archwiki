<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiIntegrationTestCase;
use StatusValue;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager
 */
class AbuseFilterPermissionManagerTest extends MediaWikiIntegrationTestCase {
	use MockAuthorityTrait;

	public function testCanViewProtectedVariablesInFilterWhenHookDisallows() {
		// Define the AbuseFilterCanViewProtectedVariables hook to make the status a fatal status
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( $performer, $variables, StatusValue $status ) {
				$status->fatal( 'test-error' );
			}
		);

		$filter = MutableFilter::newDefault();
		$filter->setProtected( true );
		/** @var AbuseFilterPermissionManager $permissionManager */
		$permissionManager = $this->getServiceContainer()->get( AbuseFilterPermissionManager::SERVICE_NAME );
		$actualStatus = $permissionManager->canViewProtectedVariablesInFilter(
			$this->mockRegisteredUltimateAuthority(),
			$filter
		);

		$this->assertStatusError( 'test-error', $actualStatus );
	}
}
