<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Jobs;

use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsMatchSignalsAgainstUserJob;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsSignalMatchService;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsMatchSignalsAgainstUserJob
 * @group CheckUser
 */
class SuggestedInvestigationsMatchSignalsAgainstUserJobTest extends MediaWikiUnitTestCase {

	public function testNewSpec(): void {
		$userIdentity = new UserIdentityValue( 1, 'TestUser' );
		$extraData = [ 'test' => [ 'testing-abc' ] ];

		$spec = SuggestedInvestigationsMatchSignalsAgainstUserJob::newSpec(
			$userIdentity,
			'test-event-type',
			$extraData
		);

		$this->assertSame( SuggestedInvestigationsMatchSignalsAgainstUserJob::TYPE, $spec->getType() );
		$this->assertSame( $userIdentity->getId(), $spec->getParams()['userIdentityId'] );
		$this->assertSame( $userIdentity->getName(), $spec->getParams()['userIdentityName'] );
		$this->assertSame( 'test-event-type', $spec->getParams()['eventType'] );
		$this->assertSame( $extraData, $spec->getParams()['extraData'] );
	}

	public function testExecution() {
		$userIdentity = new UserIdentityValue( 1, 'TestUser' );
		$extraData = [ 'test' => [ 'testing-abc' ] ];

		$spec = SuggestedInvestigationsMatchSignalsAgainstUserJob::newSpec(
			$userIdentity,
			'test-event-type',
			$extraData
		);

		$actualUserIdentity = null;
		$mockSuggestedInvestigationsSignalMatchService = $this->createMock(
			SuggestedInvestigationsSignalMatchService::class
		);
		$mockSuggestedInvestigationsSignalMatchService->expects( $this->once() )
			->method( 'matchSignalsAgainstUser' )
			->with(
				$this->callback( static function ( $userIdentity ) use ( &$actualUserIdentity ) {
					$actualUserIdentity = $userIdentity;
					return true;
				} ),
				'test-event-type',
				$extraData
			);

		$job = new SuggestedInvestigationsMatchSignalsAgainstUserJob(
			$spec->getParams(),
			$mockSuggestedInvestigationsSignalMatchService
		);

		$job->run();

		$this->assertTrue( $userIdentity->equals( $actualUserIdentity ) );
	}
}
