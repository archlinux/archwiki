<?php

namespace MediaWiki\CheckUser\Tests\Unit\Investigate\Services;

use MediaWiki\CheckUser\Investigate\Services\CompareService;
use MediaWiki\CheckUser\Services\CheckUserLookupUtils;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiUnitTestCase;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * @covers \MediaWiki\CheckUser\Investigate\Services\CompareService
 */
class CompareServiceTest extends MediaWikiUnitTestCase {
	public function testGetTargetsOverLimitWhenDBDoesNotSupportOrderAndLimitInUnion() {
		// Mock that the database does not support ORDER BY and LIMIT in UNION queries
		$mockDbr = $this->createMock( IReadableDatabase::class );
		$mockDbr->method( 'unionSupportsOrderAndLimit' )
			->willReturn( false );
		$mockConnectionProvider = $this->createMock( IConnectionProvider::class );
		$mockConnectionProvider->method( 'getReplicaDatabase' )->willReturn( $mockDbr );
		// Get the object under test while using the mock IConnectionProvider that returns the mock DB.
		$compareService = new CompareService(
			new ServiceOptions(
				CompareService::CONSTRUCTOR_OPTIONS,
				[ 'CheckUserInvestigateMaximumRowCount' => 1000 ]
			),
			$mockConnectionProvider,
			$this->createMock( UserIdentityLookup::class ),
			$this->createMock( CheckUserLookupUtils::class ),
		);
		$targets = $compareService->getTargetsOverLimit( [ '1.2.3.4' ], [], '' );
		$this->assertCount(
			0, $targets,
			'The return value of ::getTargetsOverLimit() should be an empty array when the ' .
			'database does not support ORDER BY and LIMIT in UNION queries.'
		);
	}
}
