<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\Services;

use MediaWiki\Extension\CheckUser\Services\CheckUserInsert;
use MediaWiki\Extension\CheckUser\Tests\Integration\CheckUserCommonTestTrait;
use MediaWiki\Language\Language;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\CheckUser\Services\CheckUserInsert
 */
class CheckUserInsertTest extends MediaWikiUnitTestCase {

	use CheckUserCommonTestTrait;
	use MockServiceDependenciesTrait;

	/** @dataProvider provideGetAgent */
	public function testGetAgent( $userAgentArgument, $userAgentHeader, string $expected ) {
		// Mock the content language to expect a call to truncateForDatabase if a user agent is provided.
		$mockContentLanguage = $this->createMock( Language::class );
		$mockContentLanguage->method( 'truncateForDatabase' )
			->with( $userAgentArgument ?? $userAgentHeader, CheckUserInsert::TEXT_FIELD_LENGTH )
			->willReturnArgument( 0 );
		$objectUnderTest = $this->newServiceInstance( CheckUserInsert::class, [
			'contentLanguage' => $mockContentLanguage,
		] );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$request = new FauxRequest();
		$request->setHeader( 'User-Agent', $userAgentHeader );
		$this->assertEquals(
			$expected,
			$objectUnderTest->getAgent( $userAgentArgument, $request ),
			'The expected user agent was not returned.'
		);
	}

	public static function provideGetAgent() {
		return [
			'User-Agent header is undefined' => [ null, false, '' ],
			'User-Agent header is empty' => [ null, '', '' ],
			'User-Agent header is defined' => [ null, 'Test', 'Test' ],
			'User-Agent argument is provided' => [ 'abc', 'Test', 'abc' ],
		];
	}
}
