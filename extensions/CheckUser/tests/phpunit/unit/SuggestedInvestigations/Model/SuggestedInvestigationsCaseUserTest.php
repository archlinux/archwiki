<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\SuggestedInvestigations\Model;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsCaseUser;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model\SuggestedInvestigationsCaseUser
 */
class SuggestedInvestigationsCaseUserTest extends MediaWikiUnitTestCase {
	/** @dataProvider provideUserIdentityProperties */
	public function testUserIdentityProperties(
		string $methodName,
		mixed $mockReturnValue,
		array $mockArguments = []
	): void {
		// Create a mock UserIdentity which expects a call to the method with the same name as the
		// method we are testing (testing that the method just passes through)
		$userIdentity = $this->createNoOpMock( UserIdentity::class, [ $methodName ] );
		$userIdentity->expects( $this->once() )
			->method( $methodName )
			->with( ...$mockArguments )
			->willReturn( $mockReturnValue );

		// Check the return value is as expected
		$objectUnderTest = new SuggestedInvestigationsCaseUser( $userIdentity, 0 );
		$this->assertSame( $mockReturnValue, $objectUnderTest->$methodName( ...$mockArguments ) );
	}

	public static function provideUserIdentityProperties(): array {
		return [
			'::getId with no argument' => [ 'methodName' => 'getId', 'mockReturnValue' => 1 ],
			'::getId with an argument' => [
				'methodName' => 'getId', 'mockReturnValue' => 1, 'mockArguments' => [ 'testwiki' ],
			],
			'::getName' => [ 'methodName' => 'getName', 'mockReturnValue' => 'TestUser' ],
			'::getWikiId' => [ 'methodName' => 'getWikiId', 'mockReturnValue' => 'testwiki' ],
			'::equals with null as an argument' => [
				'methodName' => 'equals', 'mockReturnValue' => false, 'mockArguments' => [ null ],
			],
			'::equals with UserIdentity argument' => [
				'methodName' => 'equals', 'mockReturnValue' => true,
				'mockArguments' => [ new UserIdentityValue( 1, 'TestUser' ) ],
			],
			'::isRegistered' => [ 'methodName' => 'isRegistered', 'mockReturnValue' => false ],
		];
	}

	public function testGetUserInfoBitFlags(): void {
		$objectUnderTest = new SuggestedInvestigationsCaseUser(
			new UserIdentityValue( 1, 'TestUser' ),
			123
		);

		$this->assertSame( 123, $objectUnderTest->getUserInfoBitFlags() );
	}
}
