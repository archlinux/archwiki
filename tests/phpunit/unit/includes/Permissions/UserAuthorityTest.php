<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\Tests\Unit\Permissions;

use IContextSource;
use InvalidArgumentException;
use Language;
use MediaWiki\Block\AbstractBlock;
use MediaWiki\Block\Block;
use MediaWiki\Block\BlockErrorFormatter;
use MediaWiki\Block\SystemBlock;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\Permissions\RateLimiter;
use MediaWiki\Permissions\RateLimitSubject;
use MediaWiki\Permissions\UserAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use Message;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;

/**
 * @covers \MediaWiki\Permissions\UserAuthority
 */
class UserAuthorityTest extends MediaWikiUnitTestCase {

	/** @var string[] Some dummy message parameters to test error message formatting. */
	private const FAKE_BLOCK_MESSAGE_PARAMS = [
		'[[User:Blocker|Blocker]]',
		'Block reason that can contain {{templates}}',
		'192.168.0.1',
		'Blocker',
	];

	/**
	 * @param bool $limited
	 * @return RateLimiter
	 */
	private function newRateLimiter( $limited = false ): RateLimiter {
		/** @var RateLimiter|MockObject $rateLimiter */
		$rateLimiter = $this->createNoOpMock(
			RateLimiter::class,
			[ 'limit', 'isLimitable' ]
		);

		$rateLimiter->method( 'limit' )->willReturn( $limited );
		$rateLimiter->method( 'isLimitable' )->willReturn( true );

		return $rateLimiter;
	}

	/**
	 * @param string[] $permissions
	 * @return PermissionManager
	 */
	private function newPermissionsManager( array $permissions ): PermissionManager {
		/** @var PermissionManager|MockObject $permissionManager */
		$permissionManager = $this->createNoOpMock(
			PermissionManager::class,
			[
				'userHasRight',
				'userHasAnyRight',
				'userHasAllRights',
				'userCan',
				'getPermissionErrors',
				'isBlockedFrom',
				'getApplicableBlock',
				'newFatalPermissionDeniedStatus',
			]
		);

		$permissionManager->method( 'userHasRight' )->willReturnCallback(
			static function ( $user, $permission ) use ( $permissions ) {
				return in_array( $permission, $permissions );
			}
		);

		$permissionManager->method( 'userHasAnyRight' )->willReturnCallback(
			static function ( $user, ...$actions ) use ( $permissions ) {
				return array_diff( $actions, $permissions ) != $actions;
			}
		);

		$permissionManager->method( 'userHasAllRights' )->willReturnCallback(
			static function ( $user, ...$actions ) use ( $permissions ) {
				return !array_diff( $actions, $permissions );
			}
		);

		$permissionManager->method( 'userCan' )->willReturnCallback(
			static function ( $permission, $user ) use ( $permissionManager ) {
				return $permissionManager->userHasRight( $user, $permission );
			}
		);

		$permissionManager->method( 'getPermissionErrors' )->willReturnCallback(
			static function ( $permission, $user, $target ) use ( $permissionManager ) {
				$errors = [];
				if ( !$permissionManager->userCan( $permission, $user, $target ) ) {
					$errors[] = [ 'permissionserrors' ];
				}

				if ( $user->getBlock() && $permission !== 'read' ) {
					$errors[] = array_merge(
						[ 'blockedtext-partial' ],
						self::FAKE_BLOCK_MESSAGE_PARAMS
					);
				}

				return $errors;
			}
		);

		$permissionManager->method( 'newFatalPermissionDeniedStatus' )->willReturnCallback(
			static function ( $permission, $context ) use ( $permissionManager ) {
				return StatusValue::newFatal( 'permissionserrors' );
			}
		);

		$permissionManager->method( 'getApplicableBlock' )->willReturnCallback(
			static function ( $action, User $user, $rigor, $page ) {
				if ( $page && $page->getDBkey() === 'Forbidden' ) {
					return new SystemBlock();
				}

				if ( $action === 'blocked' ) {
					return new SystemBlock();
				}

				return null;
			}
		);

		$permissionManager->method( 'isBlockedFrom' )->willReturnCallback(
			static function ( User $user, $page ) {
				return $page->getDBkey() === 'Forbidden';
			}
		);

		return $permissionManager;
	}

	private function newUser( Block $block = null ): User {
		/** @var User|MockObject $actor */
		$actor = $this->createNoOpMock( User::class, [ 'getBlock', 'isNewbie', 'toRateLimitSubject' ] );
		$actor->method( 'getBlock' )->willReturn( $block );
		$actor->method( 'isNewbie' )->willReturn( false );

		$subject = new RateLimitSubject( $actor, '::1', [] );
		$actor->method( 'toRateLimitSubject' )->willReturn( $subject );
		return $actor;
	}

	private function newBlockErrorFormatter(): BlockErrorFormatter {
		$blockErrorFormatter = $this->createNoOpMock( BlockErrorFormatter::class, [ 'getMessage' ] );
		$blockErrorFormatter->method( 'getMessage' )->willReturn( new Message( 'blocked' ) );
		return $blockErrorFormatter;
	}

	private function newContext(): IContextSource {
		$language = $this->createNoOpMock( Language::class, [ 'getCode' ] );
		$language->method( 'getCode' )->willReturn( 'en' );

		$context = $this->createNoOpMock( IContextSource::class, [ 'getLanguage' ] );
		$context->method( 'getLanguage' )->willReturn( $language );
		return $context;
	}

	private function newRequest(): WebRequest {
		$request = new FauxRequest();
		$request->setIP( '1.2.3.4' );
		return $request;
	}

	private function newAuthority( array $options = [] ): Authority {
		$permissionManager = $options['permissionManager']
			?? $this->newPermissionsManager( $options['permissions'] ?? [] );

		$rateLimiter = $options['rateLimiter']
			?? $this->newRateLimiter( $options['limited'] ?? false );

		$blockErrorFormatter = $options['blockErrorFormatter']
			?? $this->newBlockErrorFormatter();

		return new UserAuthority(
			$options['actor'] ?? $this->newUser(),
			$options['request'] ?? $this->newRequest(),
			$options['context'] ?? $this->newContext(),
			$permissionManager,
			$rateLimiter,
			$blockErrorFormatter
		);
	}

	public function testGetUser() {
		$user = $this->newUser();
		$authority = $this->newAuthority( [ 'actor' => $user ] );

		$this->assertSame( $user, $authority->getUser() );
	}

	public function testGetUserBlockNotBlocked() {
		$authority = $this->newAuthority();
		$this->assertNull( $authority->getBlock() );
	}

	public function testGetUserBlockWasBlocked() {
		$block = $this->createNoOpMock( AbstractBlock::class );
		$user = $this->newUser( $block );

		$authority = $this->newAuthority( [ 'actor' => $user ] );
		$this->assertSame( $block, $authority->getBlock() );
	}

	public function testRateLimitApplies() {
		$target = new PageIdentityValue( 321, NS_MAIN, __METHOD__, PageIdentity::LOCAL );
		$authority = $this->newAuthority( [ 'permissions' => [ 'edit' ], 'limited' => true ] );

		$this->assertTrue( $authority->isAllowed( 'edit' ) );
		$this->assertTrue( $authority->probablyCan( 'edit', $target ) );

		$this->assertFalse( $authority->isDefinitelyAllowed( 'edit' ) );
		$this->assertFalse( $authority->definitelyCan( 'edit', $target ) );

		$this->assertFalse( $authority->authorizeRead( 'edit', $target ) );
		$this->assertFalse( $authority->authorizeWrite( 'edit', $target ) );
		$this->assertFalse( $authority->authorizeAction( 'edit' ) );
	}

	public function testRateLimiterBypassedForReading() {
		$permissionManager = $this->newPermissionsManager( [ 'read' ] );

		// Key assertion: limit() is not called.
		$rateLimiter = $this->createNoOpMock( RateLimiter::class, [ 'isLimitable' ] );
		$rateLimiter->method( 'isLimitable' )->willReturn( false );

		// Key assertion: toRateLimitSubject() is not called.
		$actor = $this->createNoOpMock( User::class, [ 'getBlock', 'isNewbie' ] );
		$actor->method( 'getBlock' )->willReturn( null );
		$actor->method( 'isNewbie' )->willReturn( false );

		$authority = $this->newAuthority( [
			'actor' => $actor,
			'permissionManager' => $permissionManager,
			'rateLimiter' => $rateLimiter
		] );

		$target = new PageIdentityValue( 321, NS_MAIN, __METHOD__, PageIdentity::LOCAL );
		$this->assertTrue( $authority->authorizeRead( 'read', $target ) );
	}

	/**
	 * @covers \MediaWiki\Permissions\UserAuthority::limit
	 */
	public function testPingLimiterCaching() {
		$permissionManager = $this->newPermissionsManager( [] );
		$rateLimiter = $this->createNoOpMock( RateLimiter::class, [ 'limit', 'isLimitable' ] );

		$rateLimiter->method( 'isLimitable' )
			->willReturn( true );

		// We expect exactly five calls to go through to the RateLimiter,
		// see the comments below.
		$rateLimiter->expects( $this->exactly( 5 ) )
			->method( 'limit' )
			->willReturn( false );

		$authority = $this->newAuthority( [
			'permissionManager' => $permissionManager,
			'rateLimiter' => $rateLimiter
		] );

		// The rate limit cache is usually disabled during testing.
		// Enable it so we can test it.
		$authority->setUseLimitCache( true );

		// The first call should go through to the RateLimiter (count 1).
		$this->assertFalse( $authority->limit( 'edit', 0, null ) );

		// The second call should also go through to the RateLimiter,
		// because now we are incrementing, and before we were just peeking (count 2).
		$this->assertFalse( $authority->limit( 'edit', 1, null ) );

		// The third call should hit the cache
		$this->assertFalse( $authority->limit( 'edit', 0, null ) );

		// The forth call should hit the cache, even if incrementing.
		// This makes sure we don't increment the same counter multiple times
		// during a single request.
		$this->assertFalse( $authority->limit( 'edit', 1, null ) );

		// The fifth call should go to the RateLimiter again, because we are now
		// incrementing by more than one (count 3).
		$this->assertFalse( $authority->limit( 'edit', 5, null ) );

		// The next calls should not go through, since we already hit 5
		$this->assertFalse( $authority->limit( 'edit', 5, null ) );
		$this->assertFalse( $authority->limit( 'edit', 2, null ) );
		$this->assertFalse( $authority->limit( 'edit', 0, null ) );

		// When limiting another action, we should not hit the cache (count 4).
		$this->assertFalse( $authority->limit( 'move', 1, null ) );

		// After disabling  the cache, we should get through to the RateLimiter again (count 5).
		$authority->setUseLimitCache( false );
		$this->assertFalse( $authority->limit( 'move', 1, null ) );
	}

	public function testBlockedUserCanRead() {
		$block = $this->createNoOpMock( AbstractBlock::class );
		$user = $this->newUser( $block );

		$authority = $this->newAuthority(
			[ 'permissions' => [ 'read', 'edit' ], 'actor' => $user ]
		);

		$status = PermissionStatus::newEmpty();
		$target = new PageIdentityValue( 321, NS_MAIN, __METHOD__, PageIdentity::LOCAL );
		$this->assertTrue( $authority->authorizeRead( 'read', $target, $status ) );
		$this->assertStatusOK( $status );
	}

	public function testBlockedUserCanNotWrite() {
		$block = $this->createNoOpMock( AbstractBlock::class );
		$user = $this->newUser( $block );

		$authority = $this->newAuthority(
			[ 'permissions' => [ 'read', 'edit' ], 'actor' => $user ]
		);

		$status = PermissionStatus::newEmpty();
		$target = new PageIdentityValue( 321, NS_MAIN, __METHOD__, PageIdentity::LOCAL );
		$this->assertFalse( $authority->authorizeWrite( 'edit', $target, $status ) );
		$this->assertStatusNotOK( $status );
		$this->assertSame( 'edit', $status->getPermission() );
		$this->assertSame( $block, $status->getBlock() );
	}

	public function testBlockedUserAction() {
		$block = $this->createNoOpMock( AbstractBlock::class );
		$user = $this->newUser( $block );

		$authority = $this->newAuthority(
			[ 'permissions' => [ 'read', 'blocked' ], 'actor' => $user ]
		);

		$status = PermissionStatus::newEmpty();
		$this->assertTrue( $authority->isAllowed( 'blocked' ) );
		$this->assertFalse( $authority->isDefinitelyAllowed( 'blocked' ) );
		$this->assertFalse( $authority->authorizeAction( 'blocked', $status ) );
		$this->assertStatusNotOK( $status );
		$this->assertSame( 'blocked', $status->getPermission() );
	}

	public function testPermissions() {
		$authority = $this->newAuthority( [ 'permissions' => [ 'foo', 'bar' ] ] );

		$this->assertTrue( $authority->isAllowed( 'foo' ) );
		$this->assertTrue( $authority->isAllowed( 'bar' ) );
		$this->assertFalse( $authority->isAllowed( 'quux' ) );

		$this->assertTrue( $authority->isAllowedAll( 'foo', 'bar' ) );
		$this->assertTrue( $authority->isAllowedAny( 'bar', 'quux' ) );

		$this->assertFalse( $authority->isAllowedAll( 'foo', 'quux' ) );
		$this->assertFalse( $authority->isAllowedAny( 'xyzzy', 'quux' ) );
	}

	public function testIsAllowed() {
		$authority = $this->newAuthority( [ 'permissions' => [ 'foo', 'bar' ] ] );

		$this->assertTrue( $authority->isAllowed( 'foo' ) );
		$this->assertTrue( $authority->isAllowed( 'bar' ) );
		$this->assertFalse( $authority->isAllowed( 'quux' ) );

		$status = new PermissionStatus();
		$authority->isAllowed( 'foo', $status );
		$this->assertStatusOK( $status );

		$authority->isAllowed( 'quux', $status );
		$this->assertStatusNotOK( $status );
		$this->assertSame( 'quux', $status->getPermission() );
	}

	public function testProbablyCan() {
		$target = new PageIdentityValue( 321, NS_MAIN, __METHOD__, PageIdentity::LOCAL );
		$authority = $this->newAuthority( [ 'permissions' => [ 'foo', 'bar' ] ] );

		$this->assertTrue( $authority->probablyCan( 'foo', $target ) );
		$this->assertTrue( $authority->probablyCan( 'bar', $target ) );
		$this->assertFalse( $authority->probablyCan( 'quux', $target ) );

		$status = new PermissionStatus();
		$authority->probablyCan( 'foo', $target, $status );
		$this->assertStatusOK( $status );

		$authority->probablyCan( 'quux', $target, $status );
		$this->assertStatusNotOK( $status );
		$this->assertSame( 'quux', $status->getPermission() );
	}

	public function testIsDefinitelyAllowed() {
		$authority = $this->newAuthority( [ 'permissions' => [ 'foo', 'bar' ] ] );

		$this->assertTrue( $authority->isDefinitelyAllowed( 'foo' ) );
		$this->assertTrue( $authority->isDefinitelyAllowed( 'bar' ) );
		$this->assertFalse( $authority->isDefinitelyAllowed( 'quux' ) );

		$status = new PermissionStatus();
		$authority->isDefinitelyAllowed( 'foo', $status );
		$this->assertStatusOK( $status );

		$authority->isDefinitelyAllowed( 'quux', $status );
		$this->assertStatusNotOK( $status );
		$this->assertSame( 'quux', $status->getPermission() );
	}

	public function testDefinitelyCan() {
		$target = new PageIdentityValue( 321, NS_MAIN, __METHOD__, PageIdentity::LOCAL );
		$authority = $this->newAuthority( [ 'permissions' => [ 'foo', 'bar' ] ] );

		$this->assertTrue( $authority->definitelyCan( 'foo', $target ) );
		$this->assertTrue( $authority->definitelyCan( 'bar', $target ) );
		$this->assertFalse( $authority->definitelyCan( 'quux', $target ) );

		$status = new PermissionStatus();
		$authority->definitelyCan( 'foo', $target, $status );
		$this->assertStatusOK( $status );

		$authority->definitelyCan( 'quux', $target, $status );
		$this->assertStatusNotOK( $status );
		$this->assertSame( 'quux', $status->getPermission() );
	}

	public function testAuthorize() {
		$authority = $this->newAuthority( [ 'permissions' => [ 'foo', 'bar' ] ] );

		$this->assertTrue( $authority->authorizeAction( 'foo' ) );
		$this->assertTrue( $authority->authorizeAction( 'bar' ) );
		$this->assertFalse( $authority->authorizeAction( 'quux' ) );

		$status = new PermissionStatus();
		$authority->authorizeAction( 'foo', $status );
		$this->assertStatusOK( $status );

		$authority->authorizeAction( 'quux', $status );
		$this->assertStatusNotOK( $status );
		$this->assertSame( 'quux', $status->getPermission() );
	}

	public function testAuthorizeRead() {
		$target = new PageIdentityValue( 321, NS_MAIN, __METHOD__, PageIdentity::LOCAL );
		$authority = $this->newAuthority( [ 'permissions' => [ 'foo', 'bar' ] ] );

		$this->assertTrue( $authority->authorizeRead( 'foo', $target ) );
		$this->assertTrue( $authority->authorizeRead( 'bar', $target ) );
		$this->assertFalse( $authority->authorizeRead( 'quux', $target ) );

		$status = new PermissionStatus();
		$authority->authorizeRead( 'foo', $target, $status );
		$this->assertStatusOK( $status );

		$authority->authorizeRead( 'quux', $target, $status );
		$this->assertStatusNotOK( $status );
	}

	public function testAuthorizeWrite() {
		$target = new PageIdentityValue( 321, NS_MAIN, __METHOD__, PageIdentity::LOCAL );
		$authority = $this->newAuthority( [ 'permissions' => [ 'foo', 'bar' ] ] );

		$this->assertTrue( $authority->authorizeWrite( 'foo', $target ) );
		$this->assertTrue( $authority->authorizeWrite( 'bar', $target ) );
		$this->assertFalse( $authority->authorizeWrite( 'quux', $target ) );

		$status = new PermissionStatus();
		$authority->authorizeWrite( 'foo', $target, $status );
		$this->assertStatusOK( $status );

		$authority->authorizeWrite( 'quux', $target, $status );
		$this->assertStatusNotOK( $status );
	}

	public function testIsAllowedAnyThrowsOnEmptySet() {
		$authority = $this->newAuthority( [ 'permissions' => [ 'foo', 'bar' ] ] );

		$this->expectException( InvalidArgumentException::class );
		$authority->isAllowedAny();
	}

	public function testIsAllowedAllThrowsOnEmptySet() {
		$authority = $this->newAuthority( [ 'permissions' => [ 'foo', 'bar' ] ] );

		$this->expectException( InvalidArgumentException::class );
		$authority->isAllowedAll();
	}

	public function testGetBlock_none() {
		$actor = $this->newUser();

		$authority = $this->newAuthority( [ 'permissions' => [ 'foo', 'bar' ], 'actor' => $actor ] );

		$this->assertNull( $authority->getBlock() );
	}

	public function testGetBlock_blocked() {
		$block = $this->createNoOpMock( AbstractBlock::class );
		$actor = $this->newUser( $block );

		$authority = $this->newAuthority( [ 'permissions' => [ 'foo', 'bar' ], 'actor' => $actor ] );

		$this->assertSame( $block, $authority->getBlock() );
	}

	/**
	 * Regression test for T306494: check that when creating a PermissionStatus,
	 * the message contains all parameters and when converted to a Status, the parameters
	 * are not wikitext escaped.
	 */
	public function testInternalCanWithPermissionStatusMessageFormatting() {
		$block = $this->createNoOpMock( AbstractBlock::class );
		$user = $this->newUser( $block );

		$authority = $this->newAuthority( [ 'permissions' => [ 'foo', 'bar' ], 'actor' => $user, 'limited' => true ] );

		$permissionStatus = PermissionStatus::newEmpty();
		$target = new PageIdentityValue( 321, NS_MAIN, __METHOD__, PageIdentity::LOCAL );

		$authority->authorizeWrite(
			'edit',
			$target,
			$permissionStatus
		);

		$this->assertStatusError( 'actionthrottledtext', $permissionStatus );
		$this->assertTrue( $permissionStatus->isRateLimitExceeded() );

		$this->assertStatusError( 'blockedtext-partial', $permissionStatus );
		$this->assertNotNull( $permissionStatus->getBlock() );

		$errors = $permissionStatus->getErrors();

		// The actual index is not relevant and depends on the implementation
		$message = $errors[2]['message'];
		$this->assertEquals( 'blockedtext-partial', $message->getKey() );
		$this->assertArrayEquals(
			self::FAKE_BLOCK_MESSAGE_PARAMS,
			$message->getParams()
		);
	}
}
