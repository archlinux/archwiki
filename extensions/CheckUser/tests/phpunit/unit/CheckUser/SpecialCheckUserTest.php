<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\CheckUser;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\Extension\CheckUser\Tests\SpecialCheckUserTestTrait;
use MediaWiki\Html\FormOptions;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use PHPUnit\Framework\MockObject\MockBuilder;
use ReflectionClass;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @covers \MediaWiki\Extension\CheckUser\CheckUser\SpecialCheckUser
 */
class SpecialCheckUserTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;
	use SpecialCheckUserTestTrait;

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
		// Create a SpecialCheckUser that only mocks getConfig to return the config
		$specialCheckUser = $this->setUpMockBuilder()
			->onlyMethods( [ 'getConfig' ] )
			->getMock();
		$specialCheckUser->expects( $this->once() )
			->method( 'getConfig' )
			->willReturn( new HashConfig( [
				'CheckUserForceSummary' => $config,
			] ) );
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
			'Non-empty reason with wgCheckUserForceSummary as true' => [ true, 'Test Reason', true ],
		];
	}
}
