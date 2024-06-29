<?php

use MediaWiki\Block\BlockActionInfo;
use MediaWiki\Block\BlockRestrictionStore;
use MediaWiki\Block\BlockUtils;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Block\HideUserUtils;
use MediaWiki\Block\Restriction\NamespaceRestriction;
use MediaWiki\Block\Restriction\PageRestriction;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CommentFormatter\RowCommentFormatter;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\Context\RequestContext;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MainConfigNames;
use MediaWiki\Pager\BlockListPager;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 * @coversDefaultClass \MediaWiki\Pager\BlockListPager
 */
class BlockListPagerTest extends MediaWikiIntegrationTestCase {

	/** @var BlockActionInfo */
	private $blockActionInfo;

	/** @var BlockRestrictionStore */
	private $blockRestrictionStore;

	/** @var BlockUtils */
	private $blockUtils;

	/** @var HideUserUtils */
	private $hideUserUtils;

	/** @var CommentStore */
	private $commentStore;

	/** @var LinkRenderer */
	private $linkRenderer;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var IConnectionProvider */
	private $dbProvider;

	/** @var RowCommentFormatter */
	private $rowCommentFormatter;

	/** @var SpecialPageFactory */
	private $specialPageFactory;

	protected function setUp(): void {
		parent::setUp();

		$services = $this->getServiceContainer();
		$this->blockActionInfo = $services->getBlockActionInfo();
		$this->blockRestrictionStore = $services->getBlockRestrictionStore();
		$this->blockUtils = $services->getBlockUtils();
		$this->hideUserUtils = $services->getHideUserUtils();
		$this->commentStore = $services->getCommentStore();
		$this->linkBatchFactory = $services->getLinkBatchFactory();
		$this->linkRenderer = $services->getLinkRenderer();
		$this->dbProvider = $services->getConnectionProvider();
		$this->rowCommentFormatter = $services->getRowCommentFormatter();
		$this->specialPageFactory = $services->getSpecialPageFactory();
	}

	private function getBlockListPager() {
		return new BlockListPager(
			RequestContext::getMain(),
			$this->blockActionInfo,
			$this->blockRestrictionStore,
			$this->blockUtils,
			$this->hideUserUtils,
			$this->commentStore,
			$this->linkBatchFactory,
			$this->linkRenderer,
			$this->dbProvider,
			$this->rowCommentFormatter,
			$this->specialPageFactory,
			[]
		);
	}

	/**
	 * @covers ::formatValue
	 * @dataProvider formatValueEmptyProvider
	 * @dataProvider formatValueDefaultProvider
	 */
	public function testFormatValue( $name, $expected = null, $row = null ) {
		// Set the time to now so it does not get off during the test.
		MWTimestamp::setFakeTime( MWTimestamp::time() );

		$value = $name === 'bl_timestamp' ? MWTimestamp::time() : '';
		$expected ??= MWTimestamp::getInstance()->format( 'H:i, j F Y' );

		$row = $row ?: (object)[];
		$pager = $this->getBlockListPager();
		$wrappedPager = TestingAccessWrapper::newFromObject( $pager );
		$wrappedPager->mCurrentRow = $row;

		$formatted = $pager->formatValue( $name, $value );
		$this->assertStringMatchesFormat( $expected, $formatted );
	}

	/**
	 * Test empty values.
	 */
	public static function formatValueEmptyProvider() {
		return [
			[
				'test',
				'Unable to format test',
			],
			[
				'bl_timestamp',
			],
			[
				'bl_expiry',
				'infinite<br />0 minutes left',
			],
		];
	}

	/**
	 * Test the default row values.
	 */
	public static function formatValueDefaultProvider() {
		$row = (object)[
			'bt_user' => 0,
			'bt_user_text' => null,
			'bt_address' => '127.0.0.1',
			'bl_by_text' => 'Admin',
			'bt_auto' => 0,
			'bl_anon_only' => 0,
			'bl_create_account' => 1,
			'bl_enable_autoblock' => 1,
			'bl_deleted' => 0,
			'bl_block_email' => 0,
			'bl_allow_usertalk' => 0,
			'bl_sitewide' => 1,
		];

		return [
			[
				'test',
				'Unable to format test',
				$row,
			],
			[
				'bl_timestamp',
				null,
				$row,
			],
			[
				'bl_expiry',
				'infinite<br />0 minutes left',
				$row,
			],
			[
				'by',
				'<a %s><bdi>Admin</bdi></a>%s',
				$row,
			],
			[
				'params',
				'<ul><li>editing (sitewide)</li>' .
					'<li>account creation disabled</li><li>cannot edit own talk page</li></ul>',
				$row,
			]
		];
	}

	/**
	 * @covers ::formatValue
	 * @covers ::getRestrictionListHTML
	 */
	public function testFormatValueRestrictions() {
		$this->overrideConfigValues( [
			MainConfigNames::ArticlePath => '/wiki/$1',
			MainConfigNames::Script => '/w/index.php',
		] );

		$pager = $this->getBlockListPager();

		$row = (object)[
			'bl_id' => 0,
			'bt_user' => 0,
			'bl_anon_only' => 0,
			'bl_enable_autoblock' => 0,
			'bl_create_account' => 0,
			'bl_block_email' => 0,
			'bl_allow_usertalk' => 1,
			'bl_sitewide' => 0,
			'bl_deleted' => 0,
		];
		$wrappedPager = TestingAccessWrapper::newFromObject( $pager );
		$wrappedPager->mCurrentRow = $row;

		$pageName = 'Victor Frankenstein';
		$page = $this->insertPage( $pageName );
		$title = $page['title'];
		$pageId = $page['id'];

		$restrictions = [
			( new PageRestriction( 0, $pageId ) )->setTitle( $title ),
			new NamespaceRestriction( 0, NS_MAIN ),
			// Deleted page.
			new PageRestriction( 0, 999999 ),
		];

		$wrappedPager = TestingAccessWrapper::newFromObject( $pager );
		$wrappedPager->restrictions = $restrictions;

		$formatted = $pager->formatValue( 'params', '' );
		$this->assertEquals( '<ul><li>'
			// FIXME: Expectation value should not be dynamic
			// and must not depend on a localisation message.
			// TODO: Mock the message or consider using qqx.
			. wfMessage( 'blocklist-editing' )->text()
			. '<ul><li>'
			. wfMessage( 'blocklist-editing-page' )->text()
			. '<ul><li>'
			. '<a href="/wiki/Victor_Frankenstein" title="'
			. $pageName
			. '">'
			. $pageName
			. '</a></li></ul></li><li>'
			. wfMessage( 'blocklist-editing-ns' )->text()
			. '<ul><li>'
			. '<a href="/w/index.php?title=Special:AllPages&amp;namespace=0" title="'
			. 'Special:AllPages'
			. '">'
			. wfMessage( 'blanknamespace' )->text()
			. '</a></li></ul></li></ul></li></ul>',
			$formatted
		);
	}

	/**
	 * @covers ::preprocessResults
	 */
	public function testPreprocessResults() {
		// Test the Link Cache.
		$linkCache = $this->getServiceContainer()->getLinkCache();
		$wrappedlinkCache = TestingAccessWrapper::newFromObject( $linkCache );
		$admin = $this->getTestSysop()->getUser();

		$links = [
			'User:127.0.0.1',
			'User_talk:127.0.0.1',
			$admin->getUserPage()->getPrefixedDBkey(),
			$admin->getTalkPage()->getPrefixedDBkey(),
			'Comment_link'
		];

		foreach ( $links as $link ) {
			$this->assertNull( $wrappedlinkCache->entries->get( $link ) );
		}

		$row = (object)[
			'bt_address' => '127.0.0.1',
			'bt_user' => null,
			'bt_user_text' => null,
			'bl_by' => $admin->getId(),
			'bl_by_text' => $admin->getName(),
			'bl_sitewide' => 1,
			'bl_timestamp' => $this->db->timestamp( wfTimestamp( TS_MW ) ),
			'bl_reason_text' => '[[Comment link]]',
			'bl_reason_data' => null,
		];
		$pager = $this->getBlockListPager();
		$pager->preprocessResults( new FakeResultWrapper( [ $row ] ) );

		foreach ( $links as $link ) {
			$this->assertTrue( $wrappedlinkCache->isBadLink( $link ), "Bad link [[$link]]" );
		}

		// Test sitewide blocks.
		$row = (object)[
			'bt_address' => '127.0.0.1',
			'bt_user' => null,
			'bt_user_text' => null,
			'bl_by' => $admin->getId(),
			'bl_by_text' => $admin->getName(),
			'bl_sitewide' => 1,
			'bl_reason_text' => '',
			'bl_reason_data' => null,
		];
		$pager = $this->getBlockListPager();
		$pager->preprocessResults( new FakeResultWrapper( [ $row ] ) );

		$this->assertObjectNotHasProperty( 'bl_restrictions', $row );

		$page = $this->getExistingTestPage( 'Victor Frankenstein' );
		$title = $page->getTitle();

		$target = '127.0.0.1';

		// Test partial blocks.
		$block = new DatabaseBlock( [
			'address' => $target,
			'by' => $this->getTestSysop()->getUser(),
			'reason' => 'Parce que',
			'expiry' => $this->db->getInfinity(),
			'sitewide' => false,
		] );
		$block->setRestrictions( [
			new PageRestriction( 0, $page->getId() ),
		] );
		$blockStore = $this->getServiceContainer()->getDatabaseBlockStore();
		$blockStore->insertBlock( $block );

		$pager = $this->getBlockListPager();
		$result = $this->db->newSelectQueryBuilder()
			->queryInfo( $pager->getQueryInfo() )
			->where( [ 'ipb_id' => $block->getId() ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$pager->preprocessResults( $result );

		$wrappedPager = TestingAccessWrapper::newFromObject( $pager );

		$restrictions = $wrappedPager->restrictions;
		$this->assertIsArray( $restrictions );

		$restriction = $restrictions[0];
		$this->assertEquals( $page->getId(), $restriction->getValue() );
		$this->assertEquals( $page->getId(), $restriction->getTitle()->getArticleID() );
		$this->assertEquals( $title->getDBkey(), $restriction->getTitle()->getDBkey() );
		$this->assertEquals( $title->getNamespace(), $restriction->getTitle()->getNamespace() );
	}

	/**
	 * T352310 regression test
	 * @coversNothing
	 */
	public function testOffset() {
		if ( $this->db->getType() === 'postgres' ) {
			$this->markTestSkipped( "PostgreSQL fatals when the first part of " .
				"the offset parameter has the wrong timestamp format" );
		}
		$request = new FauxRequest( [
			'offset' => '20231115010645|7'
		] );
		RequestContext::getMain()->setRequest( $request );
		$pager = $this->getBlockListPager();
		$pager->getFullOutput();
		$this->assertTrue( true );
	}
}
