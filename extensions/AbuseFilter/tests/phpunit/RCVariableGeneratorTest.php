<?php

use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Parser\AFPData;
use MediaWiki\Extension\AbuseFilter\Variables\LazyLoadedVariable;
use MediaWiki\Revision\RevisionRecord;

/**
 * @group Test
 * @group AbuseFilter
 * @group AbuseFilterGeneric
 * @group Database
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\VariableGenerator\RCVariableGenerator
 * @todo Make this a unit test?
 */
class RCVariableGeneratorTest extends MediaWikiIntegrationTestCase {
	use AbuseFilterCreateAccountTestTrait;
	use AbuseFilterUploadTestTrait;

	/** @inheritDoc */
	protected $tablesUsed = [
		'page',
		'text',
		'user',
		'recentchanges',
		'image',
		'oldimage',
	];

	/**
	 * @inheritDoc
	 */
	protected function tearDown(): void {
		$this->clearUploads();
		parent::tearDown();
	}

	/**
	 * Check all methods used to retrieve variables from an RC row
	 *
	 * @param string $type Type of the action the row refers to
	 * @param string $action Same as the 'action' variable
	 * @covers \MediaWiki\Extension\AbuseFilter\VariableGenerator\RCVariableGenerator
	 * @dataProvider provideRCRowTypes
	 */
	public function testGetVarsFromRCRow( string $type, string $action ) {
		$timestamp = '1514700000';
		MWTimestamp::setFakeTime( $timestamp );
		$user = $this->getMutableTestUser()->getUser();
		$title = Title::makeTitle( NS_MAIN, 'AbuseFilter testing page' );
		$services = $this->getServiceContainer();
		$wikiPageFactory = $services->getWikiPageFactory();
		$page = $type === 'create' ? $wikiPageFactory->newFromTitle( $title ) : $this->getExistingTestPage( $title );
		$page->clear();

		$summary = 'Abuse Filter summary for RC tests';
		$expectedValues = [
			'user_name' => $user->getName(),
			'action' => $action,
			'summary' => $summary,
			'timestamp' => $timestamp
		];
		$rcConds = [];

		switch ( $type ) {
			case 'create':
				$expectedValues['old_wikitext'] = '';
			// Fallthrough
			case 'edit':
				$status = $this->editPage( $title, 'Some new text for testing RC vars.', $summary, NS_MAIN, $user );
				$this->assertArrayHasKey( 'revision-record', $status->value, 'Edit successed' );
				/** @var RevisionRecord $revRecord */
				$revRecord = $status->value['revision-record'];
				$rcConds['rc_this_oldid'] = $revRecord->getId();

				$expectedValues += [
					'page_id' => $page->getId(),
					'page_namespace' => $title->getNamespace(),
					'page_title' => $title->getText(),
					'page_prefixedtitle' => $title->getPrefixedText()
				];
				break;
			case 'move':
				$newTitle = Title::makeTitle( NS_MAIN, 'Another AbuseFilter testing page' );
				$mpf = $services->getMovePageFactory();
				$mp = $mpf->newMovePage( $title, $newTitle );
				$status = $mp->move( $user, $summary, false );
				$this->assertArrayHasKey( 'nullRevision', $status->value, 'Move successed' );
				/** @var RevisionRecord $revRecord */
				$revRecord = $status->value['nullRevision'];
				$rcConds['rc_this_oldid'] = $revRecord->getId();

				$expectedValues += [
					'moved_from_id' => $page->getId(),
					'moved_from_namespace' => $title->getNamespace(),
					'moved_from_title' => $title->getText(),
					'moved_from_prefixedtitle' => $title->getPrefixedText(),
					'moved_to_id' => $revRecord->getPageId(),
					'moved_to_namespace' => $newTitle->getNamespace(),
					'moved_to_title' => $newTitle->getText(),
					'moved_to_prefixedtitle' => $newTitle->getPrefixedText()
				];
				break;
			case 'delete':
				$status = $page->doDeleteArticleReal( $summary, $user );
				$rcConds['rc_logid'] = $status->value;

				$expectedValues += [
					'page_id' => $page->getId(),
					'page_namespace' => $title->getNamespace(),
					'page_title' => $title->getText(),
					'page_prefixedtitle' => $title->getPrefixedText()
				];
				break;
			case 'newusers':
				$accountName = 'AbuseFilter dummy user';
				$status = $this->createAccount( $accountName, $user, $action === 'autocreateaccount' );
				$rcConds['rc_logid'] = $status->value;

				$expectedValues = [
					'action' => $action,
					'accountname' => $accountName,
					'user_name' => $user->getName(),
					'timestamp' => $timestamp
				];
				break;
			case 'upload':
				$fileName = 'My File.svg';
				$destTitle = Title::makeTitle( NS_FILE, $fileName );
				$page = $wikiPageFactory->newFromTitle( $destTitle );
				[ $status, $this->clearPath ] = $this->doUpload( $user, $fileName, 'Some text', $summary );
				if ( !$status->isGood() ) {
					throw new LogicException( "Cannot upload file:\n$status" );
				}
				$rcConds['rc_namespace'] = $destTitle->getNamespace();
				$rcConds['rc_title'] = $destTitle->getDbKey();

				// Since the SVG is randomly generated, we need to read some properties live
				$file = $services->getRepoGroup()->getLocalRepo()->newFile( $destTitle );
				$expectedValues += [
					'page_id' => $page->getId(),
					'page_namespace' => $destTitle->getNamespace(),
					'page_title' => $destTitle->getText(),
					'page_prefixedtitle' => $destTitle->getPrefixedText(),
					'file_sha1' => \Wikimedia\base_convert( $file->getSha1(), 36, 16, 40 ),
					'file_size' => $file->getSize(),
					'file_mime' => 'image/svg+xml',
					'file_mediatype' => 'DRAWING',
					'file_width' => $file->getWidth(),
					'file_height' => $file->getHeight(),
					'file_bits_per_channel' => $file->getBitDepth(),
				];
				break;
			default:
				throw new LogicException( "Type $type not recognized!" );
		}

		DeferredUpdates::doUpdates();
		$rc = RecentChange::newFromConds( $rcConds, __METHOD__, DB_PRIMARY );
		$this->assertNotNull( $rc, 'RC item found' );

		$varGenerator = AbuseFilterServices::getVariableGeneratorFactory()->newRCGenerator(
			$rc,
			$this->getTestSysop()->getUser()
		);
		$actual = $varGenerator->getVars()->getVars();

		// Convert PHP variables to AFPData
		$expected = array_map( [ AFPData::class, 'newFromPHPVar' ], $expectedValues );

		// Remove lazy variables (covered in other tests) and variables coming
		// from other extensions (may not be generated, depending on the test environment)
		$coreVariables = AbuseFilterServices::getKeywordsManager()->getCoreVariables();
		foreach ( $actual as $var => $value ) {
			if ( !in_array( $var, $coreVariables, true ) || $value instanceof LazyLoadedVariable ) {
				unset( $actual[ $var ] );
			}
		}

		// Not assertSame because we're comparing different AFPData objects
		$this->assertEquals( $expected, $actual );
	}

	/**
	 * Data provider for testGetVarsFromRCRow
	 * @return array
	 */
	public function provideRCRowTypes() {
		return [
			'edit' => [ 'edit', 'edit' ],
			'create' => [ 'create', 'edit' ],
			'move' => [ 'move', 'move' ],
			'delete' => [ 'delete', 'delete' ],
			'createaccount' => [ 'newusers', 'createaccount' ],
			'autocreateaccount' => [ 'newusers', 'autocreateaccount' ],
			'upload' => [ 'upload', 'upload' ],
		];
	}

	/**
	 * @covers ::addEditVars
	 * @covers ::addEditVarsForRow
	 * @covers ::addGenericVars
	 * @covers \MediaWiki\Extension\AbuseFilter\Variables\LazyVariableComputer
	 */
	public function testAddEditVarsForRow() {
		$timestamp = 1514700000;
		MWTimestamp::setFakeTime( $timestamp );

		$title = Title::makeTitle( NS_MAIN, 'AbuseFilter testing page' );

		$oldLink = "https://wikipedia.org";
		$newLink = "https://en.wikipedia.org";
		$oldText = "test $oldLink";
		$newText = "new test $newLink";

		$this->editPage( $title, $oldText, 'Creating the test page' );

		$timestamp += 10;
		MWTimestamp::setFakeTime( $timestamp );

		$status = $this->editPage( $title, $newText, 'Editing the test page' );
		$this->assertArrayHasKey( 'revision-record', $status->value, 'Edit successed' );
		/** @var RevisionRecord $revRecord */
		$revRecord = $status->value['revision-record'];

		$rc = RecentChange::newFromConds(
			[ 'rc_this_oldid' => $revRecord->getId() ],
			__METHOD__,
			DB_PRIMARY
		);
		$this->assertNotNull( $rc, 'RC item found' );

		// one more tick to reliably test page_age
		MWTimestamp::setFakeTime( $timestamp + 10 );

		$generator = AbuseFilterServices::getVariableGeneratorFactory()->newRCGenerator(
			$rc,
			$this->getMutableTestUser()->getUser()
		);
		$varHolder = $generator->getVars();
		$manager = AbuseFilterServices::getVariablesManager();

		$expected = [
			'page_age' => 10,
			'old_wikitext' => $oldText,
			'old_size' => strlen( $oldText ),
			'old_links' => [ $oldLink ],
			'new_wikitext' => $newText,
			'new_size' => strlen( $newText ),
			'all_links' => [ $newLink ],
			'timestamp' => (string)$timestamp,
		];
		foreach ( $expected as $var => $value ) {
			$this->assertSame(
				$value,
				$manager->getVar( $varHolder, $var )->toNative()
			);
		}
	}

}
