<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use MediaWiki\CheckUser\Services\TokenManager;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;

/**
 * Test class for TokenQueryManager class
 *
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Services\TokenQueryManager
 */
class TokenQueryManagerTest extends MediaWikiIntegrationTestCase {

	public function testUpdateToken() {
		$tokenManager = $this->createMock( TokenManager::class );
		$tokenQueryManager = $this->getMockBuilder( TokenQueryManager::class )
			->setConstructorArgs( [ $tokenManager ] )
			->onlyMethods( [ 'getDataFromRequest' ] )
			->getMock();

		$tokenData = [ 'foo' => true, 'bar' => false, 'baz' => 'test' ];

		$tokenQueryManager->method( 'getDataFromRequest' )->willReturn( $tokenData );
		$tokenManager->expects( $this->once() )
			->method( 'encode' )
			->with( $this->anything(), [ 'bar' => true, 'baz' => 'test' ] );

		$tokenQueryManager->updateToken( new FauxRequest(), [ 'foo' => null, 'bar' => true ] );
	}

	public function testGetDataFromRequest() {
		$request = new FauxRequest( [ 'token' => 'token' ] );

		$tokenManager = $this->createMock( TokenManager::class );
		$tokenManager->expects( $this->once() )->method( 'decode' )->with( $this->anything(), 'token' );

		$tokenQueryManager = new TokenQueryManager( $tokenManager );
		$tokenQueryManager->getDataFromRequest( $request );
	}

	public function testGetDataFromRequestWithNoToken() {
		$request = new FauxRequest();

		$tokenManager = $this->createMock( TokenManager::class );
		$tokenQueryManager = new TokenQueryManager( $tokenManager );
		$data = $tokenQueryManager->getDataFromRequest( $request );

		$this->assertSame( [], $data );
	}

	public function testGetDataFromRequestHandlesDecodeException() {
		$tokenManager = $this->createMock( TokenManager::class );
		$tokenManager->method( 'decode' )->willThrowException( new \Exception() );

		$tokenQueryManager = new TokenQueryManager( $tokenManager );
		$request = new FauxRequest( [ 'token' => 'token' ] );
		$data = $tokenQueryManager->getDataFromRequest( $request );

		$this->assertSame( [], $data );
	}
}
