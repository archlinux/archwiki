<?php

namespace MediaWiki\CheckUser\Tests\Unit\Services;

use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Language\Language;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Unit\MockServiceDependenciesTrait;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\Services\CheckUserInsert
 */
class CheckUserInsertTest extends MediaWikiUnitTestCase {

	use CheckUserCommonTraitTest;
	use MockServiceDependenciesTrait;

	/** @dataProvider provideGetAgent */
	public function testGetAgent( $userAgent, string $expected ) {
		// Mock the content language to expect a call to truncateForDatabase if a user agent is provided.
		$mockContentLanguage = $this->createMock( Language::class );
		$mockContentLanguage->expects( $userAgent !== false ? $this->once() : $this->never() )
			->method( 'truncateForDatabase' )
			->with( $userAgent, CheckUserInsert::TEXT_FIELD_LENGTH )
			->willReturnArgument( 0 );
		$objectUnderTest = $this->newServiceInstance( CheckUserInsert::class, [
			'contentLanguage' => $mockContentLanguage,
		] );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		$request = new FauxRequest();
		$request->setHeader( 'User-Agent', $userAgent );
		$this->assertEquals(
			$expected,
			$objectUnderTest->getAgent( $request ),
			'The expected user agent was not returned.'
		);
	}

	public static function provideGetAgent() {
		return [
			'User-Agent is undefined' => [ false, '' ],
			'User-Agent is empty' => [ '', '' ],
			'User-Agent is defined' => [ 'Test', 'Test' ],
		];
	}
}
