<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Jobs;

use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForUserJob;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityLookup;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * @covers \MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsAutoCloseForUserJob
 * @group CheckUser
 */
class SuggestedInvestigationsAutoCloseForUserJobTest extends MediaWikiUnitTestCase {

	private const USERNAME = 'TestUser';

	private UserIdentityLookup&MockObject $userIdentityLookup;
	private SuggestedInvestigationsCaseLookupService&MockObject $caseLookup;
	private JobQueueGroup&MockObject $jobQueueGroup;
	private SuggestedInvestigationsAutoCloseForUserJob $job;

	protected function setUp(): void {
		parent::setUp();

		$this->userIdentityLookup = $this->createMock( UserIdentityLookup::class );
		$this->caseLookup = $this->createMock( SuggestedInvestigationsCaseLookupService::class );
		$this->jobQueueGroup = $this->createMock( JobQueueGroup::class );

		$this->job = new SuggestedInvestigationsAutoCloseForUserJob(
			[ 'username' => self::USERNAME ],
			$this->userIdentityLookup,
			$this->caseLookup,
			$this->jobQueueGroup,
			$this->createMock( LoggerInterface::class )
		);
	}

	public function testNewSpec(): void {
		$spec = SuggestedInvestigationsAutoCloseForUserJob::newSpec( self::USERNAME );

		$this->assertSame( SuggestedInvestigationsAutoCloseForUserJob::TYPE, $spec->getType() );
		$this->assertSame( self::USERNAME, $spec->getParams()['username'] );
		$this->assertArrayNotHasKey( 'jobReleaseTimestamp', $spec->getParams() );
	}

	public function testRunWhenSuggestedInvestigationsDisabled(): void {
		$this->caseLookup->expects( $this->once() )
			->method( 'areSuggestedInvestigationsEnabled' )
			->willReturn( false );

		$this->userIdentityLookup->expects( $this->never() )
			->method( 'getUserIdentityByName' );

		$this->jobQueueGroup->expects( $this->never() )
			->method( 'lazyPush' );

		$this->assertTrue( $this->job->run() );
	}

	/**
	 * @dataProvider provideUserNotFoundOrNotRegistered
	 */
	public function testRunWhenUserNotFoundOrNotRegistered( bool $userExists ): void {
		$this->caseLookup->expects( $this->once() )
			->method( 'areSuggestedInvestigationsEnabled' )
			->willReturn( true );

		$user = null;
		if ( $userExists ) {
			$user = new User();
			// id = 0 means registered => false
			$user->setId( 0 );
		}

		$this->userIdentityLookup->expects( $this->once() )
			->method( 'getUserIdentityByName' )
			->with( self::USERNAME )
			->willReturn( $user );

		$this->caseLookup->expects( $this->never() )
			->method( 'getOpenCaseIdsForUser' );

		$this->jobQueueGroup->expects( $this->never() )
			->method( 'lazyPush' );

		$this->assertTrue( $this->job->run() );
	}

	public static function provideUserNotFoundOrNotRegistered(): iterable {
		yield 'user not found' => [ false ];
		yield 'user not registered' => [ true ];
	}

}
