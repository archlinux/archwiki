<?php

use MediaWiki\Extension\Nuke\Maintenance\NormalizeNukeTags;
use MediaWiki\Page\DeletePage;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Title\Title;

/**
 * @group Database
 * @covers \MediaWiki\Extension\Nuke\Maintenance\NormalizeNukeTags
 */
class NormalizeNukeTagsTest extends MaintenanceBaseTestCase {

	/**
	 * @inheritDoc
	 */
	protected function getMaintenanceClass() {
		return NormalizeNukeTags::class;
	}

	public function testExecute() {
		$services = $this->getServiceContainer();

		// Create the tag
		$services->getChangeTagsStore()
			->defineTag( "Nuke" );

		// Create and delete a page with that tag
		$this->insertPage( 'Test' );
		$user = $this->getTestSysop();
		$delete = $services->getDeletePageFactory()->newDeletePage(
			Title::newFromText( 'Test' )->toPageIdentity(),
			$user->getUser()
		)
			->setTags( [ 'Nuke' ] )
			->forceImmediate( true );
		$delete->deleteUnsafe( "Test deletion" );
		$successfulDeletionsIDs = $delete->getSuccessfulDeletionsIDs();
		$this->runJobs();

		if ( !$successfulDeletionsIDs[ DeletePage::PAGE_BASE ] ) {
			$this->fail( "Test condition failure: no successful deletion ID for page 'Test'" );
		}

		// This sets `ctd_user_defined` to 0.
		// Since the old tag is no longer in extension.json, this means
		// the tag will be recognized as unused.
		$services->getChangeTagsStore()
			->undefineTag( "Nuke" );

		// Run the maintenance script
		$this->maintenance->execute();

		$dbr = $this->getDb();

		// Ensure that the tag is gone. Check the tags of the
		// just-completed deletion action.
		$ctd = $services->getChangeTagsStore()->getTags(
			$dbr,
			null,
			null,
			$successfulDeletionsIDs[ DeletePage::PAGE_BASE ]
		);
		$this->assertNotContains( 'Nuke', $ctd );
		$this->assertContains( 'nuke', $ctd );

		// Ensure that the tag definition is gone.
		$this->assertNotContains(
			'Nuke',
			$services->getChangeTagsStore()->listDefinedTags()
		);
	}

	public function testExecuteBatch() {
		$services = $this->getServiceContainer();

		// Create the tag
		$services->getChangeTagsStore()
			->defineTag( "Nuke" );

		// Create and delete pages with that tag
		for ( $i = 0; $i < 51; $i++ ) {
			$this->insertPage( "Test$i" );
		}

		$user = $this->getTestSysop();
		$logIDs = [];
		for ( $i = 0; $i < 51; $i++ ) {
			$delete = $services->getDeletePageFactory()->newDeletePage(
				Title::newFromText( "Test$i" )->toPageIdentity(),
				$user->getUser()
			)
				->setTags( [ 'Nuke' ] )
				->forceImmediate( true );
			$delete->deleteUnsafe( "Test deletion" );
			$successfulDeletionsIDs = $delete->getSuccessfulDeletionsIDs();
			if ( !$successfulDeletionsIDs[ DeletePage::PAGE_BASE ] ) {
				$this->fail( "Test condition failure: no successful deletion ID for page 'Test$i'" );
			}
			$logIDs[] = $successfulDeletionsIDs[ DeletePage::PAGE_BASE ];
		}
		$this->runJobs();
		$this->assertCount( 51, $logIDs );

		// This sets `ctd_user_defined` to 0.
		// Since the old tag is no longer in extension.json, this means
		// the tag will be recognized as unused.
		$services->getChangeTagsStore()
			->undefineTag( "Nuke" );

		// Run the maintenance script
		$this->maintenance->setOption( "batch-size", 10 );
		$this->maintenance->execute();

		$dbr = $this->getDb();

		// Ensure that all tags are gone. Check the tags of the
		// just-completed deletion action.
		foreach ( $logIDs as $logID ) {
			$ctd = $services->getChangeTagsStore()->getTags(
				$dbr,
				null,
				null,
				$logID
			);
			$this->assertNotContains( 'Nuke', $ctd );
			$this->assertContains( 'nuke', $ctd );
		}

		// Ensure that the tag definition is gone.
		$this->assertNotContains(
			'Nuke',
			$services->getChangeTagsStore()->listDefinedTags()
		);
	}

	public function testExecuteNoOp() {
		$services = $this->getServiceContainer();

		// Create and delete a page with the normal tag
		$this->insertPage( 'Test' );
		$user = $this->getTestSysop();
		$delete = $services->getDeletePageFactory()->newDeletePage(
			Title::newFromText( 'Test' )->toPageIdentity(),
			$user->getUser()
		)
			->setTags( [ 'nuke' ] )
			->forceImmediate( true );
		// deleteIfAllowed will run tag checks, which we don't want.
		$status = $delete->deleteUnsafe( "Test deletion" );
		if ( !$status->isOK() ) {
			$messages = $status->getMessages();
			if ( count( $messages ) > 1 ) {
				$errors = array_map(
					static function ( $message ) use ( $services ) {
						return "  * " . $services->getMessageCache()->get( $message[0]->getKey() );
					},
					$messages
				);
				$this->fail(
					"DeletePage failed: " .
					implode( "\n", $errors )
				);
			} else {
				$this->fail(
					"DeletePage failed: " .
					$services->getMessageCache()->get( $messages[0]->getKey() )
				);
			}
		}
		$logIDs = $delete->getSuccessfulDeletionsIDs();
		$this->runJobs();

		// Run the maintenance script
		$this->maintenance->execute();
		// There should be no exceptions

		$dbr = $this->getDb();

		// Ensure that there is no tag
		$ctd = $services->getChangeTagsStore()->getTags(
			$dbr,
			null,
			null,
			$logIDs[ DeletePage::PAGE_BASE ]
		);
		$this->assertNotContains( 'Nuke', $ctd );
		$this->assertContains( 'nuke', $ctd );
	}
}
