<?php

use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Parser\AFPData;
use MediaWiki\Extension\AbuseFilter\Variables\LazyLoadedVariable;

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
		'page_restrictions',
		'user',
		'recentchanges',
		'image',
		'oldimage',
	];

	/**
	 * @inheritDoc
	 */
	protected function tearDown(): void {
		MWTimestamp::setFakeTime( false );
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
		$title = Title::newFromText( 'AbuseFilter testing page' );
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

		switch ( $type ) {
			case 'create':
				$expectedValues['old_wikitext'] = '';
			// Fallthrough
			case 'edit':
				$newText = 'Some new text for testing RC vars.';
				$this->editPage( $title->getText(), $newText, $summary, $title->getNamespace(), $user );
				$expectedValues += [
					'page_id' => $page->getId(),
					'page_namespace' => $title->getNamespace(),
					'page_title' => $title->getText(),
					'page_prefixedtitle' => $title->getPrefixedText()
				];
				break;
			case 'move':
				$newTitle = Title::newFromText( 'Another AbuseFilter testing page' );
				$mpf = $services->getMovePageFactory();
				$mp = $mpf->newMovePage( $title, $newTitle );
				$mp->move( $user, $summary, false );
				$newID = $wikiPageFactory->newFromTitle( $newTitle )->getId();

				$expectedValues += [
					'moved_from_id' => $page->getId(),
					'moved_from_namespace' => $title->getNamespace(),
					'moved_from_title' => $title->getText(),
					'moved_from_prefixedtitle' => $title->getPrefixedText(),
					'moved_to_id' => $newID,
					'moved_to_namespace' => $newTitle->getNamespace(),
					'moved_to_title' => $newTitle->getText(),
					'moved_to_prefixedtitle' => $newTitle->getPrefixedText()
				];
				break;
			case 'delete':
				$page->doDeleteArticleReal( $summary, $user );
				$expectedValues += [
					'page_id' => $page->getId(),
					'page_namespace' => $title->getNamespace(),
					'page_title' => $title->getText(),
					'page_prefixedtitle' => $title->getPrefixedText()
				];
				break;
			case 'newusers':
				$accountName = 'AbuseFilter dummy user';
				$this->createAccount( $accountName, $user, $action === 'autocreateaccount' );

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

		if ( $type === 'edit' ) {
			$where = [ 'rc_source' => 'mw.edit' ];
		} elseif ( $type === 'create' ) {
			$where = [ 'rc_source' => 'mw.new' ];
		} else {
			$where = [ 'rc_log_type' => $type ];
		}
		$rcQuery = RecentChange::getQueryInfo();
		$row = $this->db->selectRow(
			$rcQuery['tables'],
			$rcQuery['fields'],
			$where,
			__METHOD__,
			[ 'ORDER BY rc_id DESC' ],
			$rcQuery['joins']
		);

		$rc = RecentChange::newFromRow( $row );
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
}
