<?php

namespace MediaWiki\Tests\Unit\Permissions;

use Language;
use MediaWiki\Block\Block;
use MediaWiki\Block\BlockErrorFormatter;
use MediaWiki\Block\SystemBlock;
use MediaWiki\Context\IContextSource;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Permissions\RateLimiter;
use MediaWiki\Permissions\RateLimitSubject;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Permissions\UserAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use PHPUnit\Framework\MockObject\MockObject;
use StatusValue;

/**
 * Various useful Authority mocks.
 * @stable to use (since 1.37)
 */
trait MockAuthorityTrait {

	/**
	 * Create mock ultimate Authority for anon user.
	 *
	 * @return Authority
	 */
	private function mockAnonUltimateAuthority(): Authority {
		return new UltimateAuthority( new UserIdentityValue( 0, '127.0.0.1' ) );
	}

	/**
	 * Create mock ultimate Authority for registered user.
	 *
	 * @return Authority
	 */
	private function mockRegisteredUltimateAuthority(): Authority {
		return new UltimateAuthority( new UserIdentityValue( 9999, 'Petr' ) );
	}

	/**
	 * Create mock Authority for anon user with no permissions.
	 *
	 * @return Authority
	 */
	private function mockAnonNullAuthority(): Authority {
		return new SimpleAuthority( new UserIdentityValue( 0, '127.0.0.1' ), [] );
	}

	/**
	 * Create mock Authority for a registered user with no permissions.
	 *
	 * @return Authority
	 */
	private function mockRegisteredNullAuthority(): Authority {
		return new SimpleAuthority( new UserIdentityValue( 9999, 'Petr' ), [] );
	}

	/**
	 * Create a mock Authority for anon user with $permissions.
	 *
	 * @param array $permissions
	 * @return Authority
	 */
	private function mockAnonAuthorityWithPermissions( array $permissions ): Authority {
		return new SimpleAuthority( new UserIdentityValue( 0, '127.0.0.1' ), $permissions );
	}

	/**
	 * Create a mock Authority for a registered user with $permissions.
	 *
	 * @param array $permissions
	 * @return Authority
	 */
	private function mockRegisteredAuthorityWithPermissions( array $permissions ): Authority {
		return new SimpleAuthority( new UserIdentityValue( 9999, 'Petr' ), $permissions );
	}

	/**
	 * Create a mock Authority for a $user with $permissions.
	 *
	 * @param UserIdentity $user
	 * @param array $permissions
	 * @return Authority
	 */
	private function mockUserAuthorityWithPermissions(
		UserIdentity $user,
		array $permissions
	): Authority {
		return new SimpleAuthority( $user, $permissions );
	}

	/**
	 * Create a mock Authority for $user with $block and $permissions.
	 *
	 * @param UserIdentity $user
	 * @param Block $block
	 * @param array $permissions
	 *
	 * @return Authority
	 */
	private function mockUserAuthorityWithBlock(
		UserIdentity $user,
		Block $block,
		array $permissions = []
	): Authority {
		return $this->mockAuthority(
			$user,
			static function ( $permission ) use ( $permissions ) {
				return in_array( $permission, $permissions );
			},
			$block
		);
	}

	/**
	 * Create a mock Authority for an anon user with all but $permissions
	 * @param array $permissions
	 * @return Authority
	 */
	private function mockAnonAuthorityWithoutPermissions( array $permissions ): Authority {
		return $this->mockUserAuthorityWithoutPermissions(
			new UserIdentityValue( 0, '127.0.0.1' ),
			$permissions
		);
	}

	/**
	 * Create a mock Authority for a registered user with all but $permissions
	 * @param array $permissions
	 * @return Authority
	 */
	private function mockRegisteredAuthorityWithoutPermissions( array $permissions ): Authority {
		return $this->mockUserAuthorityWithoutPermissions(
			new UserIdentityValue( 9999, 'Petr' ),
			$permissions
		);
	}

	/**
	 * Create a mock Authority for a $user with all but $permissions
	 * @param UserIdentity $user
	 * @param array $permissions
	 * @return Authority
	 */
	private function mockUserAuthorityWithoutPermissions(
		UserIdentity $user,
		array $permissions
	): Authority {
		return $this->mockAuthority(
			$user,
			static function ( $permission ) use ( $permissions ) {
				return !in_array( $permission, $permissions );
			}
		);
	}

	/**
	 * Create mock Authority for anon user where permissions are determined by $callback.
	 *
	 * @param callable $permissionCallback
	 * @return Authority
	 */
	private function mockAnonAuthority( callable $permissionCallback ): Authority {
		return $this->mockAuthority(
			new UserIdentityValue( 0, '127.0.0.1' ),
			$permissionCallback
		);
	}

	/**
	 * Create mock Authority for registered user where permissions are determined by $callback.
	 *
	 * @param callable $permissionCallback
	 * @return Authority
	 */
	private function mockRegisteredAuthority( callable $permissionCallback ): Authority {
		return $this->mockAuthority(
			new UserIdentityValue( 9999, 'Petr' ),
			$permissionCallback
		);
	}

	/**
	 * Create mock Authority for $user where permissions are determined by $callback.
	 *
	 * @param UserIdentity $user
	 * @param callable $permissionCallback ( string $permission, PageIdentity $page = null )
	 * @param Block|null $block
	 *
	 * @return Authority
	 */
	private function mockAuthority(
		UserIdentity $user,
		callable $permissionCallback,
		Block $block = null
	): Authority {
		$mock = $this->createMock( Authority::class );
		$mock->method( 'getUser' )->willReturn( $user );
		$methods = [ 'isAllowed', 'probablyCan', 'definitelyCan', 'authorizeRead', 'authorizeWrite' ];
		foreach ( $methods as $method ) {
			$mock->method( $method )->willReturnCallback( $permissionCallback );
		}
		$mock->method( 'isAllowedAny' )
			->willReturnCallback( static function ( ...$permissions ) use ( $permissionCallback ) {
				foreach ( $permissions as $permission ) {
					if ( $permissionCallback( $permission ) ) {
						return true;
					}
				}
				return false;
			} );
		$mock->method( 'isAllowedAll' )
			->willReturnCallback( static function ( ...$permissions ) use ( $permissionCallback ) {
				foreach ( $permissions as $permission ) {
					if ( !$permissionCallback( $permission ) ) {
						return false;
					}
				}
				return true;
			} );
		$mock->method( 'getBlock' )->willReturn( $block );
		return $mock;
	}

	/** @return string[] Some dummy message parameters to test error message formatting. */
	private function getFakeBlockMessageParams(): array {
		return [
			'[[User:Blocker|Blocker]]',
			'Block reason that can contain {{templates}}',
			'192.168.0.1',
			'Blocker',
		];
	}

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

		$fakeBlockMessageParams = $this->getFakeBlockMessageParams();
		// If the user has a block, the block applies to all actions except for 'read'
		$permissionManager->method( 'getPermissionErrors' )->willReturnCallback(
			static function ( $permission, $user, $target ) use ( $permissionManager, $fakeBlockMessageParams ) {
				$errors = [];
				if ( !$permissionManager->userCan( $permission, $user, $target ) ) {
					$errors[] = [ 'permissionserrors' ];
				}

				if ( $user->getBlock() && $permission !== 'read' ) {
					$errors[] = array_merge(
						[ 'blockedtext-partial' ],
						$fakeBlockMessageParams
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

		// If the page's title is "Forbidden", will return a SystemBlock. Likewise,
		// if the action is 'blocked', this will return a SystemBlock.
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
		$blockErrorFormatter = $this->createNoOpMock( BlockErrorFormatter::class, [ 'getMessages' ] );
		$blockErrorFormatter->method( 'getMessages' )->willReturn( [ new Message( 'blocked' ) ] );
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

	private function newUserAuthority( array $options = [] ): UserAuthority {
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
}
