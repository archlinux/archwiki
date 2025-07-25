<?php

namespace MediaWiki\CheckUser\Tests\Unit\Services;

use MediaWiki\CheckUser\Api\ApiQueryCheckUser;
use MediaWiki\CheckUser\Services\ApiQueryCheckUserResponseFactory;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use RuntimeException;

/**
 * @covers \MediaWiki\CheckUser\Services\ApiQueryCheckUserResponseFactory
 */
class ApiQueryCheckUserResponseFactoryTest extends MediaWikiUnitTestCase {

	use MockServiceDependenciesTrait;

	public function testNewFromRequestOnUnknownRequest() {
		// Create a mock Exception to simulate that ::dieWithError throws an exception.
		// This is needed so that the method under test throws an exception and therefore
		// does not have to return something.
		$mockException = $this->createMock( RuntimeException::class );
		$this->expectExceptionObject( $mockException );
		// Create a mock ApiBase object that expects one call to ::dieWithError
		$mockModule = $this->createMock( ApiQueryCheckUser::class );
		$mockModule->expects( $this->once() )
			->method( 'dieWithError' )
			->with( 'apierror-checkuser-invalidmode', 'invalidmode' )
			->willThrowException( $mockException );
		$mockModule->method( 'extractRequestParams' )
			->willReturn( [ 'request' => 'test' ] );
		// Get the object under test and call ::newFromRequest
		/** @var ApiQueryCheckUserResponseFactory $factory */
		$factory = $this->newServiceInstance( ApiQueryCheckUserResponseFactory::class, [] );
		$factory->newFromRequest( $mockModule );
	}
}
