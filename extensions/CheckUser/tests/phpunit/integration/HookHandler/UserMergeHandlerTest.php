<?php

namespace MediaWiki\CheckUser\Tests\Unit\HookHandler;

use MediaWiki\CheckUser\HookHandler\UserMergeHandler;
use MediaWikiIntegrationTestCase;

/**
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\HookHandler\UserMergeHandler
 */
class UserMergeHandlerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'UserMerge' );
	}

	public function testOnUserMergeAccountFields() {
		// @todo Test that the array items of $updateFields are as expected?
		$updateFields = [];
		$expectedCount = 5;
		$objectUnderTest = new UserMergeHandler();
		$objectUnderTest->onUserMergeAccountFields( $updateFields );
		$this->assertCount(
			$expectedCount,
			$updateFields,
			'The expected number of updates were not added to $updateFields by ::onUserMergeAccountFields.'
		);
	}
}
