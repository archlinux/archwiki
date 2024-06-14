<?php

namespace MediaWiki\Tests\User\Options;

use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use MediaWiki\User\Options\ConditionalDefaultsLookup;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \MediaWiki\User\Options\ConditionalDefaultsLookup
 */
class ConditionalDefaultsLookupTest extends MediaWikiUnitTestCase {

	private const CONDITIONAL_USER_DEFAULTS = [
		[
			'new accounts',
			[ CUDCOND_AFTER, '20231215000000' ],
		],
		[
			'anonymous users',
			[ CUDCOND_ANON ],
		],
		[
			'named users',
			[ CUDCOND_NAMED ],
		],
	];
	private const CONDITIONAL_USER_DEFAULTS_AFTER = [
		[
			'new accounts',
			[ CUDCOND_AFTER, '20231215000000' ],
		]
	];
	private const CONDITIONAL_USER_DEFAULTS_ANON = [
		[
			'anonymous users',
			[ CUDCOND_ANON ],
		]
	];
	private const CONDITIONAL_USER_DEFAULTS_NAMED = [
		[
			'named users',
			[ CUDCOND_NAMED ],
		]
	];

	/**
	 * Construct ServiceOptions using a HashConfig
	 *
	 * @param array $configOverrides
	 * @return ServiceOptions
	 */
	private function getServiceOptions( array $configOverrides = [] ) {
		return new ServiceOptions(
			ConditionalDefaultsLookup::CONSTRUCTOR_OPTIONS,
			new HashConfig( $configOverrides + [
				MainConfigNames::ConditionalUserOptions => [],
			] )
		);
	}

	/**
	 * @covers ::hasConditionalDefault
	 * @dataProvider provideIsConditionallyDefault
	 * @param bool $expected
	 * @param string $option
	 * @return void
	 */
	public function testIsConditionallyDefault( bool $expected, string $option ) {
		$lookup = new ConditionalDefaultsLookup(
			$this->getServiceOptions( [
				MainConfigNames::ConditionalUserOptions => [
					'foo-option' => self::CONDITIONAL_USER_DEFAULTS,
					'bar-option' => [
						[ 'all accounts' ]
					],
				]
			] ),
			$this->createNoOpMock( UserRegistrationLookup::class ),
			$this->createNoOpMock( UserIdentityUtils::class )
		);

		$this->assertSame(
			$expected,
			$lookup->hasConditionalDefault( $option )
		);
	}

	public static function provideIsConditionallyDefault() {
		return [
			'foo-option' => [ true, 'foo-option' ],
			'bar-option' => [ true, 'bar-option' ],
			'some-option' => [ false, 'some-option' ],
		];
	}

	/**
	 * @covers ::getOptionDefaultForUser
	 */
	public function testGetOptionDefaultForUser__notConditionallyDefault() {
		$lookup = new ConditionalDefaultsLookup(
			$this->getServiceOptions(),
			$this->createNoOpMock( UserRegistrationLookup::class ),
			$this->createNoOpMock( UserIdentityUtils::class )
		);

		$this->assertNull( $lookup->getOptionDefaultForUser(
			'foo-option',
			new UserIdentityValue( 1, 'Admin' )
		) );
	}

	/**
	 * @covers ::getOptionDefaultForUser
	 * @covers ::checkConditionsForUser
	 * @covers ::checkConditionForUser
	 * @dataProvider provideGetOptionDefaultForUser__registration
	 * @param string|null $expected
	 * @param string|null $registrationTS
	 * @param array $conditions
	 */
	public function testGetOptionDefaultForUser__registration(
		?string $expected,
		?string $registrationTS,
		array $conditions
	) {
		$userIdentity = new UserIdentityValue( 1, 'User' );

		$registrationLookup = $this->createMock( UserRegistrationLookup::class );
		$registrationLookup->expects( $this->once() )
			->method( 'getRegistration' )
			->with( $userIdentity )
			->willReturn( $registrationTS );

		$lookup = new ConditionalDefaultsLookup(
			$this->getServiceOptions( [
				MainConfigNames::ConditionalUserOptions => [
					'foo-option' => $conditions,
				]
			] ),
			$registrationLookup,
			$this->createNoOpMock( UserIdentityUtils::class )
		);

		$this->assertSame(
			$expected,
			$lookup->getOptionDefaultForUser( 'foo-option', $userIdentity )
		);
	}

	public static function provideGetOptionDefaultForUser__registration(): array {
		return [
			[ null, '20231101000000', self::CONDITIONAL_USER_DEFAULTS_AFTER ],
			[ 'new accounts', '20241101000000', self::CONDITIONAL_USER_DEFAULTS_AFTER ],
			[ null, null, self::CONDITIONAL_USER_DEFAULTS_AFTER ],
		];
	}

	/**
	 * @covers ::getOptionDefaultForUser
	 * @covers ::checkConditionsForUser
	 * @covers ::checkConditionForUser
	 * @dataProvider provideGetOptionDefaultForUser__anon
	 * @param int $id the user ID
	 * @param string|null $expected the default option or null if none apply
	 */
	public function testGetOptionDefaultForUser__anon( int $id, ?string $expected ) {
		$userIdentity = new UserIdentityValue( $id, 'test user' );

		$options = $this->getServiceOptions( [
			MainConfigNames::ConditionalUserOptions => [
				'test-option' => self::CONDITIONAL_USER_DEFAULTS_ANON,
			]
		] );
		$registrationLookup = $this->createNoOpMock( UserRegistrationLookup::class );
		$userIdentityUtils = $this->createNoOpMock( UserIdentityUtils::class );

		$lookup = new ConditionalDefaultsLookup( $options, $registrationLookup, $userIdentityUtils );

		$this->assertSame( $expected, $lookup->getOptionDefaultForUser( 'test-option', $userIdentity ) );
	}

	public static function provideGetOptionDefaultForUser__anon(): array {
		return [
			[ 0, 'anonymous users' ],
			[ 1, null ],
		];
	}

	/**
	 * @covers ::getOptionDefaultForUser
	 * @covers ::checkConditionsForUser
	 * @covers ::checkConditionForUser
	 * @dataProvider provideGetOptionDefaultForUser__named
	 * @param bool $isNamed whether the user is named or not (logged in and not temporary)
	 * @param string|null $expected the default option or null if none apply
	 */
	public function testGetOptionDefaultForUser__named( bool $isNamed, ?string $expected ) {
		$userIdentity = new UserIdentityValue( 1, 'test user' );

		$options = $this->getServiceOptions( [
			MainConfigNames::ConditionalUserOptions => [
				'test-option' => self::CONDITIONAL_USER_DEFAULTS_NAMED,
			]
		] );
		$registrationLookup = $this->createNoOpMock( UserRegistrationLookup::class );
		$userIdentityUtils = $this->createMock( UserIdentityUtils::class );
		$userIdentityUtils->expects( $this->once() )
			->method( 'isNamed' )
			->with( $userIdentity )
			->willReturn( $isNamed );

		$lookup = new ConditionalDefaultsLookup( $options, $registrationLookup, $userIdentityUtils );

		$this->assertSame( $expected, $lookup->getOptionDefaultForUser( 'test-option', $userIdentity ) );
	}

	public static function provideGetOptionDefaultForUser__named(): array {
		return [
			[ true, 'named users' ],
			[ false, null ],
		];
	}
}
