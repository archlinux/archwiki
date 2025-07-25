<?php

namespace MediaWiki\CheckUser\Tests\Unit\CheckUser\Pagers;

use LogicException;
use MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

abstract class CheckUserPagerUnitTestBase extends MediaWikiUnitTestCase {

	/**
	 * Gets the name of the Pager class currently under test.
	 *
	 * @return class-string<AbstractCheckUserPager> The pager class name
	 */
	abstract protected function getPagerClass(): string;

	public function commonGetQueryInfoForTableSpecificMethod( $methodName, $propertiesToSet, $expectedQueryInfo ) {
		$object = $this->getMockBuilder( $this->getPagerClass() )
			->disableOriginalConstructor()
			->onlyMethods( [] )
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
		/** @var $objectUnderTest AbstractCheckUserPager */
		$objectUnderTest = $this->getMockBuilder( $this->getPagerClass() )
			->disableOriginalConstructor()
			->onlyMethods( [] )
			->getMock();
		$this->expectException( LogicException::class );
		$objectUnderTest->getQueryInfo();
	}
}
