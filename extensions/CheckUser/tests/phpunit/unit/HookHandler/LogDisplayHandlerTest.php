<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\HookHandler\LogDisplayHandler;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Config\HashConfig;
use MediaWiki\Context\IContextSource;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Logging\LogEventsList;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserNameUtils;
use MediaWikiUnitTestCase;
use MockTitleTrait;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\LogDisplayHandler
 */
class LogDisplayHandlerTest extends MediaWikiUnitTestCase {

	use MockTitleTrait;
	use MockAuthorityTrait;

	/** @dataProvider provideOnLogEventsListLineEnding */
	public function testOnLogEventsListLineEnding(
		$performerIsTempAccount, $titleText, $titleNamespace, $canAccessTempAccountIPs, $expectedClasses
	) {
		// Mock that a given performer is or is not a temporary account.
		$testPerformer = new UserIdentityValue( 123, 'Testing' );
		$mockEntry = $this->createMock( DatabaseLogEntry::class );
		$mockEntry->method( 'getPerformerIdentity' )
			->willReturn( $testPerformer );

		$mockUserNameUtils = $this->createMock( UserNameUtils::class );
		$mockUserNameUtils->method( 'isTemp' )
			->with( $testPerformer->getName() )
			->willReturn( $performerIsTempAccount );

		// Mock the return value of ::isSpecial to indicate whether the current
		// title is or is not Special:Log
		$mockLogEventsList = $this->createMock( LogEventsList::class );
		$mockLogEventsList->method( 'getTitle' )
			->willReturn( $this->makeMockTitle( $titleText, [ 'namespace' => $titleNamespace ] ) );
		$mockLogEventsList->method( 'getAuthority' )
			->willReturn( $this->mockRegisteredUltimateAuthority() );

		$mockCheckUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$mockCheckUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn(
				$canAccessTempAccountIPs ?
					CheckUserPermissionStatus::newGood() :
					CheckUserPermissionStatus::newFatal( 'test' )
			);

		// Call the hook handler with the mock log entry
		$hookHandler = new LogDisplayHandler(
			$mockUserNameUtils,
			new HashConfig( [
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [ 'BlockList' ],
			] ),
			$mockCheckUserPermissionManager
		);
		$ret = '';
		$classes = [];
		$attribs = [];
		$hookHandler->onLogEventsListLineEnding(
			$mockLogEventsList, $ret, $mockEntry, $classes, $attribs
		);

		// Expect that only the CSS classes are modified, and that they are as expected.
		$this->assertSame( '', $ret );
		$this->assertArrayEquals( [], $attribs );
		$this->assertArrayEquals( $expectedClasses, $classes, 'CSS classes were not as expected' );
	}

	public static function provideOnLogEventsListLineEnding() {
		return [
			'Performer is a temporary account on Special:Log' => [
				true, 'Log', NS_SPECIAL, true, [ 'ext-checkuser-log-line-supports-ip-reveal' ],
			],
			'Performer is a temporary account on Special:MovePage' => [
				true, 'Movepage', NS_SPECIAL, true, [ 'ext-checkuser-log-line-supports-ip-reveal' ],
			],
			'Performer is a temporary account on Special:BlockList' => [ true, 'BlockList', NS_SPECIAL, true, [] ],
			'Performer is a temporary account but not shown on special page' => [ true, 'Test', NS_USER, true, [] ],
			'Performer is a temporary account on Special:Log but authority lacks permission' => [
				true, 'Log', NS_SPECIAL, false, [],
			],
			'Performer is not a temporary account on Special:Log' => [ false, 'Log', NS_SPECIAL, true, [] ],
		];
	}

	public function testOnChangesListInsertLogEntry() {
		// Mock that the log performer is a temporary account. We don't need to test a variety of inputs as
		// this is tested for us in ::testOnLogEventsListLineEnding
		$testPerformer = new UserIdentityValue( 123, '~2025-1' );
		$mockEntry = $this->createMock( DatabaseLogEntry::class );
		$mockEntry->method( 'getPerformerIdentity' )
			->willReturn( $testPerformer );

		$mockUserNameUtils = $this->createMock( UserNameUtils::class );
		$mockUserNameUtils->method( 'isTemp' )
			->with( $testPerformer->getName() )
			->willReturn( true );

		// Mock the return value of ::isSpecial to indicate that the current title is Special:RecentChanges
		$context = $this->createMock( IContextSource::class );
		$context->method( 'getTitle' )
			->willReturn( $this->makeMockTitle( 'RecentChanges', [ 'namespace' => NS_SPECIAL ] ) );
		$context->method( 'getAuthority' )
			->willReturn( $this->mockRegisteredUltimateAuthority() );

		$mockCheckUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );
		$mockCheckUserPermissionManager->method( 'canAccessTemporaryAccountIPAddresses' )
			->willReturn( CheckUserPermissionStatus::newGood() );

		// Call the hook handler with the mock log entry
		$hookHandler = new LogDisplayHandler(
			$mockUserNameUtils,
			new HashConfig( [
				'CheckUserSpecialPagesWithoutIPRevealButtons' => [ 'BlockList' ],
			] ),
			$mockCheckUserPermissionManager
		);
		$html = '';
		$classes = [];
		$attribs = [];
		$hookHandler->onChangesListInsertLogEntry(
			$mockEntry, $context, $html, $classes, $attribs
		);

		// Expect that only the CSS classes are modified, and that they are as expected.
		$this->assertSame( '', $html );
		$this->assertArrayEquals( [], $attribs );
		$this->assertArrayEquals(
			[ 'ext-checkuser-log-line-supports-ip-reveal' ], $classes, 'CSS classes were not as expected'
		);
	}
}
