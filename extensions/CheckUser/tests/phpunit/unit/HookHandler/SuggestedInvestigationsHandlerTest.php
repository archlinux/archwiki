<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsHandler;
use MediaWiki\Extension\CheckUser\Jobs\SuggestedInvestigationsMatchSignalsAgainstUserJob;
use MediaWiki\JobQueue\IJobSpecification;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Page\WikiPage;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\HookHandler\SuggestedInvestigationsHandler
 */
class SuggestedInvestigationsHandlerTest extends MediaWikiUnitTestCase {

	/** @dataProvider provideOnLocalUserCreated */
	public function testOnLocalUserCreated( bool $autocreated, string $expectedEventType ) {
		$mockUser = $this->createMock( User::class );

		$objectUnderTest = new SuggestedInvestigationsHandler(
			$this->setUpMockJobQueue( $mockUser, $expectedEventType, [] )
		);

		$objectUnderTest->onLocalUserCreated( $mockUser, $autocreated );
	}

	public static function provideOnLocalUserCreated(): array {
		return [
			'User is autocreated' => [ true, 'autocreateaccount' ],
			'User is not autocreated' => [ false, 'createaccount' ],
		];
	}

	/** @dataProvider provideOnPageSaveComplete */
	public function testOnPageSaveComplete( bool $isNullEdit, bool $expectsSignalMatch ) {
		$mockUser = $this->createMock( User::class );

		$revId = 1;
		$revisionRecord = $this->createMock( RevisionRecord::class );
		$revisionRecord->method( 'getId' )
			->willReturn( $revId );

		$mockEditResult = $this->createMock( EditResult::class );
		$mockEditResult->method( 'isNullEdit' )
			->willReturn( $isNullEdit );

		$objectUnderTest = new SuggestedInvestigationsHandler(
			$this->setUpMockJobQueue(
				$mockUser,
				'successfuledit',
				[ 'revId' => $revId ],
				$expectsSignalMatch
			)
		);

		$objectUnderTest->onPageSaveComplete(
			$this->createMock( WikiPage::class ),
			$mockUser,
			'',
			0,
			$revisionRecord,
			$mockEditResult
		);
	}

	public static function provideOnPageSaveComplete(): array {
		return [
			'Page save event is for a null edit' => [ true, false ],
			'Page save event is for a non-null edit' => [ false, true ],
		];
	}

	public function testOnUserSetEmail() {
		$mockUser = $this->createMock( User::class );

		$objectUnderTest = new SuggestedInvestigationsHandler(
			$this->setUpMockJobQueue( $mockUser, 'setemail', [] )
		);

		$email = 'test@test.com';
		$objectUnderTest->onUserSetEmail( $mockUser, $email );
	}

	public function testOnUserSetEmailAuthenticationTimestamp() {
		$mockUser = $this->createMock( User::class );

		$objectUnderTest = new SuggestedInvestigationsHandler(
			$this->setUpMockJobQueue( $mockUser, 'confirmemail', [] )
		);

		$timestamp = '20250405060708';
		$objectUnderTest->onUserSetEmailAuthenticationTimestamp( $mockUser, $timestamp );
	}

	private function setUpMockJobQueue(
		UserIdentity $expectedUserIdentity,
		string $expectedEventType,
		array $expectedExtraData,
		bool $expectsCall = true
	): JobQueueGroup {
		$mockJobQueueGroup = $this->createMock( JobQueueGroup::class );
		$mockJobQueueGroup->expects( $expectsCall ? $this->once() : $this->never() )
			->method( 'lazyPush' )
			->willReturnCallback( function ( $job ) use (
				$expectedUserIdentity,
				$expectedEventType,
				$expectedExtraData
			) {
				$this->assertInstanceOf( IJobSpecification::class, $job );
				$this->assertSame(
					SuggestedInvestigationsMatchSignalsAgainstUserJob::TYPE,
					$job->getType(),
					'Inserted job was not of the expected type'
				);

				$jobParams = $job->getParams();
				unset( $jobParams['requestId'] );
				$this->assertArrayEquals(
					[
						'userIdentityId' => $expectedUserIdentity->getId(),
						'userIdentityName' => $expectedUserIdentity->getName(),
						'eventType' => $expectedEventType,
						'extraData' => $expectedExtraData,
					],
					$jobParams,
					false,
					true,
					'Job parameters are not as expected'
				);
			} );

		return $mockJobQueueGroup;
	}
}
