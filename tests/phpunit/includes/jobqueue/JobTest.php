<?php

use MediaWiki\MainConfigNames;
use MediaWiki\Title\Title;

/**
 * @author Addshore
 */
class JobTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideTestToString
	 *
	 * @param Job $job
	 * @param string $expected
	 *
	 * @covers Job::toString
	 */
	public function testToString( $job, $expected ) {
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'en' );
		$this->assertEquals( $expected, $job->toString() );
	}

	public function provideTestToString() {
		$mockToStringObj = $this->getMockBuilder( stdClass::class )
			->addMethods( [ '__toString' ] )->getMock();
		$mockToStringObj->method( '__toString' )
			->willReturn( '{STRING_OBJ_VAL}' );

		$requestId = 'requestId=' . WebRequest::getRequestId();

		return [
			[
				$this->getMockJob( [ 'key' => 'val' ] ),
				'someCommand Special: key=val ' . $requestId
			],
			[
				$this->getMockJob( [ 'key' => [ 'inkey' => 'inval' ] ] ),
				'someCommand Special: key={"inkey":"inval"} ' . $requestId
			],
			[
				$this->getMockJob( [ 'val1' ] ),
				'someCommand Special: 0=val1 ' . $requestId
			],
			[
				$this->getMockJob( [ 'val1', 'val2' ] ),
				'someCommand Special: 0=val1 1=val2 ' . $requestId
			],
			[
				$this->getMockJob( [ (object)[] ] ),
				'someCommand Special: 0=object(stdClass) ' . $requestId
			],
			[
				$this->getMockJob( [ $mockToStringObj ] ),
				'someCommand Special: 0={STRING_OBJ_VAL} ' . $requestId
			],
			[
				$this->getMockJob( [
					"pages" => [
						"932737" => [
							0,
							"Robert_James_Waller"
						]
					],
					"rootJobSignature" => "45868e99bba89064e4483743ebb9b682ef95c1a7",
					"rootJobTimestamp" => "20160309110158",
					"masterPos" => [
						"file" => "db1023-bin.001288",
						"pos" => "308257743",
						"asOfTime" => 1457521464.3814
					],
					"triggeredRecursive" => true
				] ),
				'someCommand Special: pages={"932737":[0,"Robert_James_Waller"]} ' .
				'rootJobSignature=45868e99bba89064e4483743ebb9b682ef95c1a7 ' .
				'rootJobTimestamp=20160309110158 masterPos=' .
				'{"file":"db1023-bin.001288","pos":"308257743",' .
				'"asOfTime":1457521464.3814} triggeredRecursive=1 ' .
				$requestId
			],
		];
	}

	public function getMockJob( $params ) {
		$mock = $this->getMockForAbstractClass(
			Job::class,
			[ 'someCommand', $params ],
			'SomeJob'
		);

		return $mock;
	}

	/**
	 * @covers Job::__construct()
	 */
	public function testInvalidParamsArgument() {
		$params = false;
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( '$params must be an array' );
		$job = $this->getMockJob( $params );
	}

	/**
	 * @dataProvider provideTestJobFactory
	 *
	 * @covers Job::factory
	 */
	public function testJobFactory( $handler, $expectedClass ) {
		$this->overrideConfigValue( 'JobClasses', [ 'testdummy' => $handler ] );

		$job = Job::factory( 'testdummy', Title::newMainPage(), [] );
		$this->assertInstanceOf( $expectedClass, $job );

		$job2 = Job::factory( 'testdummy', [] );
		$this->assertInstanceOf( $expectedClass, $job2 );
		$this->assertNotSame( $job, $job2, 'should not reuse instance' );

		$job3 = Job::factory( 'testdummy', [ 'namespace' => NS_MAIN, 'title' => 'JobTestTitle' ] );
		$this->assertInstanceOf( $expectedClass, $job3 );
		$this->assertNotSame( $job, $job3, 'should not reuse instance' );
	}

	public function provideTestJobFactory() {
		return [
			'class name, no title' => [ 'NullJob', NullJob::class ],
			'class name with title' => [ DeleteLinksJob::class, DeleteLinksJob::class ],
			'closure' => [ static function ( Title $title, array $params ) {
				return new NullJob( $params );
			}, NullJob::class ],
			'function' => [ [ $this, 'newNullJob' ], NullJob::class ],
			'object spec, no title' => [ [ 'class' => 'NullJob' ], NullJob::class ],
			'object spec with title' => [ [ 'class' => DeleteLinksJob::class ], DeleteLinksJob::class ],
			'object spec with no title and not subclass of GenericParameterJob' => [
				[
					'class' => ParsoidCachePrewarmJob::class,
					'services' => [
						'ParsoidOutputAccess',
						'PageStore',
						'RevisionLookup'
					],
					'needsPage' => false
				],
				ParsoidCachePrewarmJob::class
			]
		];
	}

	public function newNullJob( Title $title, array $params ) {
		return new NullJob( $params );
	}

	/**
	 * @covers Job::factory
	 * @covers Job::__construct()
	 */
	public function testJobSignatureGeneric() {
		$testPage = Title::makeTitle( NS_PROJECT, 'x' );
		$blankTitle = Title::makeTitle( NS_SPECIAL, '' );
		$params = [ 'z' => 1, 'lives' => 1, 'usleep' => 0 ];
		$paramsWithTitle = $params + [ 'namespace' => NS_PROJECT, 'title' => 'x' ];

		$job = new NullJob( [ 'namespace' => NS_PROJECT, 'title' => 'x' ] + $params );
		$this->assertEquals( $testPage->getPrefixedText(), $job->getTitle()->getPrefixedText() );
		$this->assertJobParamsMatch( $job, $paramsWithTitle );

		$job = Job::factory( 'null', $testPage, $params );
		$this->assertEquals( $blankTitle->getPrefixedText(), $job->getTitle()->getPrefixedText() );
		$this->assertJobParamsMatch( $job, $params );

		$job = Job::factory( 'null', $paramsWithTitle );
		$this->assertEquals( $testPage->getPrefixedText(), $job->getTitle()->getPrefixedText() );
		$this->assertJobParamsMatch( $job, $paramsWithTitle );

		$job = Job::factory( 'null', $params );
		$this->assertTrue( $blankTitle->equals( $job->getTitle() ) );
		$this->assertJobParamsMatch( $job, $params );
	}

	/**
	 * @covers Job::factory
	 * @covers Job::__construct()
	 */
	public function testJobSignatureTitleBased() {
		$testPage = Title::makeTitle( NS_PROJECT, 'X' );
		$blankPage = Title::makeTitle( NS_SPECIAL, 'Blankpage' );
		$params = [ 'z' => 1, 'causeAction' => 'unknown', 'causeAgent' => 'unknown' ];
		$paramsWithTitle = $params + [ 'namespace' => NS_PROJECT, 'title' => 'X' ];
		$paramsWithBlankpage = $params + [ 'namespace' => NS_SPECIAL, 'title' => 'Blankpage' ];

		$job = new RefreshLinksJob( $testPage, $params );
		$this->assertEquals( $testPage->getPrefixedText(), $job->getTitle()->getPrefixedText() );
		$this->assertTrue( $testPage->equals( $job->getTitle() ) );
		$this->assertJobParamsMatch( $job, $paramsWithTitle );

		$job = Job::factory( 'htmlCacheUpdate', $testPage, $params );
		$this->assertEquals( $testPage->getPrefixedText(), $job->getTitle()->getPrefixedText() );
		$this->assertJobParamsMatch( $job, $paramsWithTitle );

		$job = Job::factory( 'htmlCacheUpdate', $paramsWithTitle );
		$this->assertEquals( $testPage->getPrefixedText(), $job->getTitle()->getPrefixedText() );
		$this->assertJobParamsMatch( $job, $paramsWithTitle );

		$job = Job::factory( 'htmlCacheUpdate', $params );
		$this->assertTrue( $blankPage->equals( $job->getTitle() ) );
		$this->assertJobParamsMatch( $job, $paramsWithBlankpage );
	}

	/**
	 * @covers Job::factory
	 * @covers Job::__construct()
	 */
	public function testJobSignatureTitleBasedIncomplete() {
		$testPage = Title::makeTitle( NS_PROJECT, 'X' );
		$blankTitle = Title::makeTitle( NS_SPECIAL, '' );
		$params = [ 'z' => 1, 'causeAction' => 'unknown', 'causeAgent' => 'unknown' ];

		$job = new RefreshLinksJob( $testPage, $params + [ 'namespace' => 0 ] );
		$this->assertEquals( $blankTitle->getPrefixedText(), $job->getTitle()->getPrefixedText() );
		$this->assertJobParamsMatch( $job, $params + [ 'namespace' => 0 ] );

		$job = new RefreshLinksJob( $testPage, $params + [ 'title' => 'x' ] );
		$this->assertEquals( $blankTitle->getPrefixedText(), $job->getTitle()->getPrefixedText() );
		$this->assertJobParamsMatch( $job, $params + [ 'title' => 'x' ] );
	}

	private function assertJobParamsMatch( IJobSpecification $job, array $params ) {
		$actual = $job->getParams();
		unset( $actual['requestId'] );

		$this->assertEquals( $actual, $params );
	}
}
