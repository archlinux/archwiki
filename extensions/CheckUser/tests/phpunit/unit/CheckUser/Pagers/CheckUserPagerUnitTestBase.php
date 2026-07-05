<?php

namespace MediaWiki\Extension\CheckUser\Tests\Unit\CheckUser\Pagers;

use LogicException;
use MediaWiki\Extension\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

abstract class CheckUserPagerUnitTestBase extends MediaWikiUnitTestCase {

	/**
	 * Gets the name of the Pager class currently under test.
	 *
	 * @return class-string<AbstractCheckUserPager> The pager class name
	 */
	abstract protected function getPagerClass(): string;

	public function commonGetQueryInfoForTableSpecificMethod(
		string $methodName,
		array $propertiesToSet,
		array $expectedQueryInfo
	): void {
		$object = $this->getMockBuilder( $this->getPagerClass() )
			->disableOriginalConstructor()
			->getMock();
		$object = TestingAccessWrapper::newFromObject( $object );
		foreach ( $propertiesToSet as $propertyName => $propertyValue ) {
			$object->$propertyName = $propertyValue;
		}
		$this->assertArrayContains(
			$expectedQueryInfo,
			$object->$methodName(),
			"The ::$methodName response was not as expected."
		);
	}

	public function testGetQueryInfoWithNoProvidedTableThrowsException() {
		/** @var AbstractCheckUserPager $objectUnderTest */
		$objectUnderTest = $this->getMockBuilder( $this->getPagerClass() )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$this->expectException( LogicException::class );
		$objectUnderTest->getQueryInfo();
	}
}
