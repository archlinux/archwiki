<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser;

use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\Config\Config;
use MediaWiki\Html\FormOptions;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockBuilder;
use ReflectionClass;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUser
 */
class SpecialCheckUserTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;

	private function setUpObjectInTestingAccessWrapper(): TestingAccessWrapper {
		return TestingAccessWrapper::newFromObject( $this->setUpObject() );
	}

	private function setUpObject(): SpecialCheckUser {
		return new SpecialCheckUser( ...$this->getMockConstructorArguments() );
	}

	private function setUpMockBuilder(): MockBuilder {
		return $this->getMockBuilder( SpecialCheckUser::class )
			->setConstructorArgs( $this->getMockConstructorArguments() );
	}

	/**
	 * These are the mocked arguments provided to the constructor method
	 * of SpecialCheckUser. These should update automatically with
	 * changes to the SpecialCheckUser class.
	 *
	 * Code for this is copied from MockServiceDependenciesTrait::newServiceInstance
	 * but modified to just return the parameters instead of using them to create an instance
	 * of the class.
	 */
	private function getMockConstructorArguments(): array {
		$params = [];
		$reflectionClass = new ReflectionClass( SpecialCheckUser::class );
		$constructor = $reflectionClass->getConstructor();
		foreach ( $constructor->getParameters() as $parameter ) {
			$params[] = $parameterOverrides[$parameter->getName()]
				?? $this->getMockValueForParam( $parameter );
		}
		return $params;
	}

	public function testDoesWrites() {
		$this->assertTrue(
			$this->setUpObjectInTestingAccessWrapper()->doesWrites(),
			'Special:CheckUser writes to the cu_log table so it does writes.'
		);
	}

	/** @dataProvider provideCheckReason */
	public function testCheckReason( $config, $reason, $expected ) {
		// Create a mock Config that returns $config for the key CheckUserForceSummary
		$mockConfig = $this->createMock( Config::class );
		$mockConfig->expects( $this->once() )
			->method( 'get' )
			->with( 'CheckUserForceSummary' )
			->willReturn( $config );
		$mockConfig->expects( $this->never() )
			->method( 'has' );
		// Create a SpecialCheckUser that only mocks getConfig to return the mocked
		// config that is created above.
		$specialCheckUser = $this->setUpMockBuilder()
			->onlyMethods( [ 'getConfig' ] )
			->getMock();
		$specialCheckUser->expects( $this->once() )
			->method( 'getConfig' )
			->willReturn( $mockConfig );
		// Add the reason to the FormOptions.
		$specialCheckUser = TestingAccessWrapper::newFromObject( $specialCheckUser );
		$specialCheckUser->opts = new FormOptions();
		$specialCheckUser->opts->add( 'reason', $expected );
		// Now test ::checkReason.
		$this->assertSame(
			$expected,
			$specialCheckUser->checkReason(),
			'::checkReason did not return the expected value for the given reason and config.'
		);
	}

	public static function provideCheckReason() {
		return [
			'Empty reason with wgCheckUserForceSummary as false' => [ false, '', true ],
			'Non-empty reason with wgCheckUserForceSummary as false' => [ false, 'Test Reason', true ],
			'Empty reason with wgCheckUserForceSummary as true' => [ true, '', false ],
			'Non-empty reason with wgCheckUserForceSummary as true' => [ true, 'Test Reason', true ]
		];
	}
}
