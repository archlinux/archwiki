<?php

namespace MediaWiki\CheckUser\Tests\Integration\GlobalContributions;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\CheckUser\GlobalContributions\ExternalPermissions;
use MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPager;
use MediaWiki\CommentFormatter\CommentFormatter;
use MediaWiki\CommentFormatter\RevisionCommentBatch;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\DAO\WikiAwareEntity;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\UserLinkRenderer;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\RevisionStoreFactory;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\CheckUser\GlobalContributions\GlobalContributionsPager
 * @group CheckUser
 * @group Database
 */
class GlobalContributionsPagerTest extends MediaWikiIntegrationTestCase {
	private const TEMP_USERNAME = '~2024-123';

	/**
	 * This mock is used to prevent dealing with how
	 * UserLinkRenderer handles caching and external users.
	 *
	 * @var (UserLinkRenderer&MockObject)
	 */
	private UserLinkRenderer $userLinkRenderer;

	/**
	 * @var (LinkRenderer&MockObject)
	 */
	private LinkRenderer $linkRenderer;

	/**
	 * @var (RevisionRecord&MockObject)
	 */
	private $revisionRecord;

	/**
	 * @var (RevisionStore&MockObject)
	 */
	private $revisionStore;

	/**
	 * Stop the factory from trying to instantiate stores on databases
	 * that don't exist/it doesn't have access to by using this mock to
	 * return the local one by default.
	 *
	 * @var (RevisionStoreFactory&MockObject)
	 */
	private $revisionStoreFactory;

	public function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
		$this->setUserLang( 'qqx' );

		$this->userLinkRenderer = $this->createMock( UserLinkRenderer::class );
		$this->linkRenderer = $this->createMock( LinkRenderer::class );
		$this->linkRenderer
			->method( 'makeExternalLink' )
			->willReturnCallback(
				static fn ( $url ) => sprintf(
					'https://external.wiki/%s',
					str_replace( ' ', '_', $url )
				)
			);

		$this->revisionRecord = $this->createMock( RevisionRecord::class );
		$this->revisionStore = $this->createMock( RevisionStore::class );

		// External revisions should use their wikis' revision store. See T398722.
		// For the purposes of testing everything else, pretend like that's happening by default.
		$this->revisionStoreFactory = $this->createMock( RevisionStoreFactory::class );
		$this->revisionStoreFactory
			->method( 'getRevisionStore' )
			->willReturn( $this->revisionStore );
	}

	private function getPagerWithOverrides( $overrides ) {
		$services = $this->getServiceContainer();
		return new GlobalContributionsPager(
			$this->linkRenderer,
			$overrides['LinkBatchFactory'] ?? $services->getLinkBatchFactory(),
			$overrides['HookContainer'] ?? $services->getHookContainer(),
			$overrides['RevisionStore'] ?? $services->getRevisionStore(),
			$overrides['NamespaceInfo'] ?? $services->getNamespaceInfo(),
			$overrides['CommentFormatter'] ?? $services->getCommentFormatter(),
			$overrides['UserFactory'] ?? $services->getUserFactory(),
			$overrides['TempUserConfig'] ?? $services->getTempUserConfig(),
			$overrides['CheckUserLookupUtils'] ?? $services->get( 'CheckUserLookupUtils' ),
			$overrides['CentralIdLookup'] ?? $services->get( 'CentralIdLookup' ),
			$overrides['GlobalContributionsLookup'] ?? $services->get( 'CheckUserGlobalContributionsLookup' ),
			$overrides['PermissionManager'] ?? $services->getPermissionManager(),
			$overrides['PreferencesFactory'] ?? $services->getPreferencesFactory(),
			$overrides['LoadBalancerFactory'] ?? $services->getConnectionProvider(),
			$overrides['JobQueueGroup'] ?? $services->getJobQueueGroup(),
			$overrides['UserLinkRenderer'] ?? $this->userLinkRenderer,
			$overrides['RevisionStoreFactory'] ?? $this->revisionStoreFactory,
			$overrides['Context'] ?? RequestContext::getMain(),
			$overrides['options'] ?? [ 'revisionsOnly' => true ],
			new UserIdentityValue( 0, $overrides['UserName'] ?? '127.0.0.1' )
		);
	}

	private function getPager( $userName ): GlobalContributionsPager {
		return $this->getServiceContainer()->get( 'CheckUserGlobalContributionsPagerFactory' )
			->createPager(
				RequestContext::getMain(),
				[ 'revisionsOnly' => true ],
				new UserIdentityValue( 0, $userName )
			);
	}

	private function getWrappedPager( string $userName, $pageTitle, $pageNamespace = 0 ) {
		$pager = $this->wrapPager( $this->getPager( $userName ) );
		$pager->currentPage = Title::makeTitle( $pageNamespace, $pageTitle );
		return $pager;
	}

	private function wrapPager( GlobalContributionsPager $pager ) {
		return TestingAccessWrapper::newFromObject( $pager );
	}

	private function getRow( $options = [] ) {
		return (object)( array_merge(
			[
				'rev_id' => '2',
				'rev_page' => '1',
				'rev_actor' => '1',
				'rev_user' => '1',
				'rev_user_text' => self::TEMP_USERNAME,
				'rev_timestamp' => '20240101000000',
				'rev_minor_edit' => '0',
				'rev_deleted' => '0',
				'rev_len' => '100',
				'rev_parent_id' => '1',
				'rev_sha1' => '',
				'rev_comment_text' => '',
				'rev_comment_data' => null,
				'rev_comment_cid' => '1',
				'page_latest' => '2',
				'page_is_new' => '0',
				'page_namespace' => '0',
				'page_title' => 'Test page',
				'cuc_timestamp' => '20240101000000',
				'ts_tags' => null,
			],
			$options
		) );
	}

	public function testPopulateAttributes() {
		$row = $this->getRow( [ 'sourcewiki' => 'otherwiki' ] );

		$this->revisionStore
			->expects( $this->once() )
			->method( 'newRevisionFromRow' )
			->with( $row )
			->willReturn( $this->revisionRecord );

		$this->revisionRecord
			->method( 'getComment' )
			->with(
				RevisionRecord::RAW,
				RequestContext::getMain()->getAuthority()
			)->willReturn(
				new CommentStoreComment( null, $row->rev_comment_text )
			);

		// formatRow() calls getTemplateParams(), which calls formatUserLink(),
		// which calls UserLinkRenderer's userLink(): We need to mock that to
		// prevent it from calling WikiMap static methods that check if the user
		// is external, since those calls can't be mocked and seeding the DB
		// with data that would make WikiMap behave the way we need may make
		// tests that also modify the sites table to fail.
		$this->userLinkRenderer
			->expects( $this->once() )
			->method( 'userLink' )
			->with(
				$this->isInstanceOf( UserIdentityValue::class ),
				RequestContext::getMain()
			)->willReturnCallback(
				function ( UserIdentityValue $user, IContextSource $context ) {
					$this->assertEquals(
						self::TEMP_USERNAME,
						$user->getName()
					);

					return '<a href="https://example.com/User:username">username</a>';
				}
			);

		// Get a pager that uses the mock in $this->userLinkRenderer. That's
		// needed to avoid the calls the regular UserLinkRenderer does to
		// WikiMap, since we can't mock static calls from a test.
		//
		// Additionally, a wrapper is needed to set a mock comment for the
		// revision associated with $row, since that would be called by
		// IndexPager::getBody() but here we are calling formatRow() directly,
		// which is called later by that same method.
		$wrapper = $this->wrapPager(
			$this->getPagerWithOverrides( [
				'UserName' => '127.0.0.1',
				'RevisionStore' => $this->revisionStore,
			] )
		);
		$wrapper->formattedComments = [
			$row->rev_id => '<span>Formatted comment</span>',
		];

		// We can't call populateAttributes directly because TestingAccessWrapper
		// can't pass by reference: T287318
		$formatted = $wrapper->formatRow( $row );
		$this->assertStringNotContainsString( 'data-mw-revid', $formatted );
	}

	/**
	 * @dataProvider provideFormatArticleLink
	 */
	public function testFormatArticleLink( $namespace, $expectedPageLinkText ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'page_namespace' => $namespace,
			'page_title' => 'Test',
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title, $row->page_namespace );

		$formatted = $pager->formatArticleLink( $row );
		$this->assertStringContainsString( 'external', $formatted );
		$this->assertStringContainsString( $row->page_title, $formatted );

		$this->assertStringContainsString(
			$expectedPageLinkText,
			$formatted
		);
	}

	public static function provideFormatArticleLink() {
		return [
			'Known external namespace is shown' => [
				'namespace' => NS_TALK,
				'expectedPageLinkText' => NamespaceInfo::CANONICAL_NAMES[NS_TALK] . ':Test',
			],
			'Unknown external namespace is not shown' => [
				'namespace' => 1000,
				'expectedPageLinkText' =>
					'(checkuser-global-contributions-page-when-no-namespace-translation-available: 1,000, Test)',
			],
		];
	}

	/**
	 * @dataProvider provideFormatDiffHistLinks
	 */
	public function testFormatDiffHistLinks( $isNewPage, $isHidden, $expectDiffLink ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_parent_id' => $isNewPage ? '0' : '1',
			'rev_id' => '2',
			'rev_deleted' => $isHidden ? '1' : '0',
			'rev_page' => '100',
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatDiffHistLinks( $row );
		$this->assertStringContainsString( 'external', $formatted );
		$this->assertStringContainsString( 'diff', $formatted );
		$this->assertStringContainsString( 'action=history', $formatted );
		$this->assertStringContainsString( 'curid=100', $formatted );
		if ( $expectDiffLink ) {
			$this->assertStringContainsString( 'oldid=2', $formatted );
		} else {
			$this->assertStringNotContainsString( 'oldid=2', $formatted );
		}
	}

	public static function provideFormatDiffHistLinks() {
		return [
			'No diff link for a new page' => [ true, false, false ],
			'No diff link for not a new page, hidden from user' => [ false, true, false ],
			'Diff link for not a new page, visible to user' => [ false, false, true ],
		];
	}

	/**
	 * @dataProvider provideFormatDateLink
	 */
	public function testFormatDateLink( $isHidden ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_timestamp' => '20240101000000',
			'rev_deleted' => $isHidden ? '1' : '0',
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatDateLink( $row );
		$this->assertStringContainsString( '2024', $formatted );
		if ( $isHidden ) {
			$this->assertStringNotContainsString( 'external', $formatted );
		} else {
			$this->assertStringContainsString( 'external', $formatted );
		}
	}

	public static function provideFormatDateLink() {
		return [ [ true ], [ false ] ];
	}

	/**
	 * @dataProvider provideFormatTopMarkText
	 */
	public function testFormatTopMarkText( $revisionIsLatest ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_id' => '2',
			'page_latest' => $revisionIsLatest ? '2' : '3',
		] );

		$context = RequestContext::getMain();
		$this->revisionStore
			->expects( $this->once() )
			->method( 'newRevisionFromRow' )
			->with( $row )
			->willReturn( $this->revisionRecord );

		$this->revisionRecord
			->method( 'getComment' )
			->with( RevisionRecord::RAW, $context->getAuthority() )
			->willReturn( $row->rev_comment_text ?
				new CommentStoreComment( null, $row->rev_comment_text ) :
				null
			);

		// formatRow() calls getTemplateParams(), which calls formatUserLink(),
		// which calls UserLinkRenderer's userLink(): We need to mock that to
		// prevent it from calling WikiMap static methods that check if the user
		// is external, since those calls can't be mocked and seeding the DB
		// with data that would make WikiMap behave the way we need may make
		// tests that also modify the sites table to fail.
		$this->userLinkRenderer
			->expects( $this->once() )
			->method( 'userLink' )
			->with( $this->isInstanceOf( UserIdentityValue::class ), $context )
			->willReturn(
				'<a href="http://example.com/User:username">username</a>'
			);

		// Get a pager that uses the mock in $this->userLinkRenderer. That's
		// needed to avoid the calls the regular UserLinkRenderer does to
		// WikiMap, since we can't mock static calls from a test.
		//
		// Additionally, a wrapper is needed to set a mock comment for the
		// revision associated with $row, since that would be called by
		// IndexPager::getBody() but here we are calling formatRow(), which is
		// called later by that same method.
		$wrapper = $this->wrapPager(
			$this->getPagerWithOverrides( [
				'UserName' => '127.0.0.1',
				'RevisionStore' => $this->revisionStore,
			] )
		);
		$wrapper->formattedComments = [
			$row->rev_id => '<span>Formatted comment</span>',
		];

		// We can't call formatTopMarkText directly because TestingAccessWrapper
		// can't pass by reference: T287318
		$formatted = $wrapper->formatRow( $row );
		if ( $revisionIsLatest ) {
			$this->assertStringContainsString( 'uctop', $formatted );
		} else {
			$this->assertStringNotContainsString( 'uctop', $formatted );
		}
	}

	public static function provideFormatTopMarkText() {
		return [ [ true ], [ false ] ];
	}

	/**
	 * @dataProvider formatCommentDataProvider
	 */
	public function testFormatComment(
		string $expected,
		callable $sourceWiki,
		array $row,
		bool $hasRevisionRecord,
		array $permissions,
		bool $canAccessLocalComment,
		bool $hasStoredComment,
		?string $formattedComment
	): void {
		// Call wiki ID providers, then merge the row with the default values
		$sourceWiki = $sourceWiki();
		$row[ 'sourcewiki' ] = $row[ 'sourcewiki' ]();
		$row = $this->getRow( $row );

		// Create a context mocking the current user
		$authority = new SimpleAuthority(
			new UserIdentityValue( 0, '127.0.0.1' ),
			[]
		);
		$context = RequestContext::getMain();
		$context->setAuthority( $authority );
		$context->setLanguage( 'qqx' );

		$commentFormatter = $this->createMock( CommentFormatter::class );

		// Get a pager that uses the previous mocks, then wrap it with a
		// TestingAccessWrapper so that we can initialize a mock comment for the
		// revision associated with $row, as normally that would be filed by
		// IndexPager::getBody() but here we are calling formatComment()
		// directly instead.
		$pager = $this->wrapPager(
			$this->getPagerWithOverrides( [
				'CommentFormatter' => $commentFormatter,
				'Context' => $context,
				'RevisionStore' => $this->revisionStore,
				'UserName' => '127.0.0.1',
			] )
		);

		// Normally, permissions would be filled by fetchWikisToQuery(), which
		// would end up being called after calling getBody(). However, as this
		// test calls formatComment() directly, we need to set up the permission
		// data that otherwise would be set up by a call to fetchWikisToQuery()
		// initiated by IndexPager::getBody().
		$pager->permissions = new ExternalPermissions( [ $sourceWiki => $permissions ] );

		if ( $row->sourcewiki === 'otherwiki' ) {
			$this->revisionStore
				->method( 'newRevisionFromRow' )
				->with( $row )
				->willReturn( $this->revisionRecord );

			$this->revisionRecord
				->method( 'getComment' )
				->with( RevisionRecord::RAW, $authority )
				->willReturn(
					$hasStoredComment ?
						new CommentStoreComment( null, $formattedComment ) :
						null
				);

			$commentFormatter
				->method( 'formatRevision' )
				->with( $this->revisionRecord, $authority )
				->willReturn( $formattedComment );
		} else {
			// Setup values expected by the parent class
			$pager->formattedComments = [
				$row->rev_id => $formattedComment,
			];

			// Ensure formatRevisions is not called by GlobalContributionsPager
			// but, instead, the parent class used parent::$formattedComments
			// instead (previously initialized by RevisionCommentBatch).
			$commentFormatter
				->expects( $this->never() )
				->method( 'formatRevision' );
		}

		if ( $hasRevisionRecord ) {
			// RevisionRecord::userCan() is called by RevisionRecord::audienceCan()
			// which, in turn, is called by RevisionRecord::getComment(), which
			// is called by the pager.
			$mockRevisionRecord = $this->createMock( RevisionRecord::class );
			$mockRevisionRecord
				->expects( $this->atMost( 1 ) )
				->method( 'userCan' )
				->with(
					RevisionRecord::DELETED_COMMENT,
					$authority
				)->willReturn( $canAccessLocalComment );

			$pager->currentRevRecord = $mockRevisionRecord;
		}

		$this->assertSame( $expected, $pager->formatComment( $row ) );
	}

	public function formatCommentDataProvider(): array {
		$localWikIdProvider = static fn () => WikiMap::getCurrentWikiId();
		$otherWikIdProvider = static fn () => 'otherwiki';
		$summaryUnavailableMessage =
			'<span class="history-deleted comment">' .
			'(rev-deleted-comment)</span>';
		$noCommentMessage =
			'<span class="comment mw-comment-none">(changeslist-nocomment)</span>';

		return [
			'External wiki, non-deleted comment' => [
				'expected' => 'Formatted comment',
				'sourceWiki' => $otherWikIdProvider,
				'row' => [
					'sourcewiki' => $otherWikIdProvider,
					'rev_deleted' => 0x0,
				],
				'hasRevisionRecord' => false,
				'permissions' => [],
				'canAccessLocalComment' => false,
				'hasStoredComment' => true,
				'formattedComment'  => 'Formatted comment',
			],
			'External wiki, non-deleted comment, null comment' => [
				'expected' => $noCommentMessage,
				'sourceWiki' => $otherWikIdProvider,
				'row' => [
					'sourcewiki' => $otherWikIdProvider,
					'rev_deleted' => 0x0,
				],
				'hasRevisionRecord' => false,
				'permissions' => [],
				'canAccessLocalComment' => false,
				'hasStoredComment' => false,
				'formattedComment'  => null,
			],
			'External wiki, non-deleted comment, empty comment' => [
				'expected' => $noCommentMessage,
				'sourceWiki' => $otherWikIdProvider,
				'row' => [
					'sourcewiki' => $otherWikIdProvider,
					'rev_deleted' => 0x0,
				],
				'hasRevisionRecord' => false,
				'permissions' => [],
				'canAccessLocalComment' => false,
				'hasStoredComment' => true,
				'formattedComment'  => '',
			],
			'External wiki, non-deleted comment, has deletedhistory' => [
				'expected' => 'Formatted comment',
				'sourceWiki' => $otherWikIdProvider,
				'row' => [
					'sourcewiki' => $otherWikIdProvider,
					'rev_deleted' => 0x0,
				],
				'hasRevisionRecord' => false,
				'permissions' => [
					'deletedhistory' => [],
				],
				'canAccessLocalComment' => false,
				'hasStoredComment' => true,
				'formattedComment'  => 'Formatted comment',
			],
			'External wiki, deleted comment, has other permission' => [
				'expected' => $summaryUnavailableMessage,
				'sourceWiki' => $otherWikIdProvider,
				'row' => [
					'sourcewiki' => $otherWikIdProvider,
					'rev_deleted' => RevisionRecord::DELETED_COMMENT,
				],
				'hasRevisionRecord' => false,
				'permissions' => [
					// This one doesn't grant access to the comments
					'deletedtext' => [],
				],
				'canAccessLocalComment' => false,
				'hasStoredComment' => true,
				'formattedComment'  => 'Formatted comment',
			],
			'External wiki, deleted comment, multiple permissions not granting access' => [
				'expected' => $summaryUnavailableMessage,
				'sourceWiki' => $otherWikIdProvider,
				'row' => [
					'sourcewiki' => $otherWikIdProvider,
					'rev_deleted' => RevisionRecord::DELETED_COMMENT,
				],
				'hasRevisionRecord' => false,
				'permissions' => [
					'deletedtext' => [],
					'something-else' => [],
				],
				'canAccessLocalComment' => false,
				'hasStoredComment' => true,
				'formattedComment'  => 'Formatted comment',
			],
			'External wiki, deleted comment, multiple permissions granting access ' => [
				'expected' => 'Formatted comment',
				'sourceWiki' => $otherWikIdProvider,
				'row' => [
					'sourcewiki' => $otherWikIdProvider,
					'rev_deleted' => RevisionRecord::DELETED_COMMENT,
				],
				'hasRevisionRecord' => false,
				'permissions' => [
					'deletedtext' => [],
					'deletedhistory' => [],
				],
				'canAccessLocalComment' => false,
				'hasStoredComment' => true,
				'formattedComment'  => 'Formatted comment',
			],
			'Local wiki, has access to comment' => [
				// Comments for records from the local wiki are delegated to the
				// parent class, so this only tests the most-common scenario
				// (non-deleted comment).
				'expected' => 'Formatted comment',
				'sourceWiki' => $localWikIdProvider,
				'row' => [
					'sourcewiki' => $localWikIdProvider,
					'rev_deleted' => 0x0,
				],
				'hasRevisionRecord' => true,
				'permissions' => [],
				'canAccessLocalComment' => true,
				'hasStoredComment' => true,
				'formattedComment'  => 'Formatted comment',
			],
		];
	}

	/**
	 * @dataProvider provideFormatUserLink
	 */
	public function testFormatUserLink(
		array $expectedStrings,
		array $unexpectedStrings,
		string $username,
		?string $sourcewiki,
		bool $hasRevisionRecord,
		bool $isDeleted
	): void {
		// The pager relies on UserLinkRenderer to provide for local and external users
		// and on LinkRenderer for talk and contribution links, so this test only checks
		// that the result from those methods is included in the output.
		$row = $this->getRow( [
			'sourcewiki' => $sourcewiki ?? WikiMap::getCurrentWikiId(),
			'rev_user' => 123,
			'rev_user_text' => $username,
			'rev_deleted' => $isDeleted ? '4' : '8',
		] );
		if ( $hasRevisionRecord ) {
			$mockRevRecord = $this->createMock( RevisionRecord::class );
			$mockRevRecord->method( 'getUser' )
				->willReturn( new UserIdentityValue( 123, $username ) );
		} else {
			$mockRevRecord = null;
		}

		$context = RequestContext::getMain();
		$context->setLanguage( 'qqx' );

		$this->userLinkRenderer
			->method( 'userLink' )
			->willReturnCallback(
				function (
					UserIdentity $user,
					IContextSource $linkContext
				) use ( $username, $context, $row ) {
					$this->assertEquals( $row->rev_user, $user->getId( $user->getWikiId() ) );
					$this->assertEquals( $row->rev_user_text, $user->getName() );

					$actualSourceWiki = $user->getWikiId();
					if ( $actualSourceWiki === WikiAwareEntity::LOCAL ) {
						$actualSourceWiki = WikiMap::getCurrentWikiId();
					}
					$this->assertEquals( $row->sourcewiki, $actualSourceWiki );

					$this->assertSame( $context, $linkContext );

					if ( $user->getWikiId() === WikiAwareEntity::LOCAL ) {
						$domain = 'https://local.wiki';
					} else {
						$domain = 'https://external.wiki';
					}
					return Html::element( 'a', [ 'href' => $domain . '/User:' . $username ], $username );
				}
			);

		$services = $this->getServiceContainer();
		$pager = $this->getMockBuilder( GlobalContributionsPager::class )
			->onlyMethods( [ 'getForeignUrl' ] )
			->setConstructorArgs( [
				$this->linkRenderer,
				$services->getLinkBatchFactory(),
				$services->getHookContainer(),
				$services->getRevisionStore(),
				$services->getNamespaceInfo(),
				$services->getCommentFormatter(),
				$services->getUserFactory(),
				$services->getTempUserConfig(),
				$services->get( 'CheckUserLookupUtils' ),
				$services->get( 'CentralIdLookup' ),
				$services->get( 'CheckUserGlobalContributionsLookup' ),
				$services->getPermissionManager(),
				$services->getPreferencesFactory(),
				$services->getDBLoadBalancerFactory(),
				$services->getJobQueueGroup(),
				$this->userLinkRenderer,
				$this->revisionStoreFactory,
				$context,
				[ 'revisionsOnly' => true ],
				new UserIdentityValue( 0, '127.0.0.1' ),
			] )
			->getMock();
		$pager->expects( $this->any() )
			->method( 'getForeignUrl' )
			->willReturnArgument( 1 );
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$pager->currentPage = Title::makeTitle( 0, $row->page_title );
		$pager->currentRevRecord = $mockRevRecord;

		$formatted = $pager->formatUserLink( $row );

		foreach ( $expectedStrings as $value ) {
			$this->assertStringContainsString( $value, $formatted );
		}

		foreach ( $unexpectedStrings as $value ) {
			$this->assertStringNotContainsString( $value, $formatted );
		}
	}

	public static function provideFormatUserLink() {
		return [
			'Registered account, external wiki, hidden' => [
				'expectedStrings' => [ 'empty-username' ],
				'unexpectedStrings' => [
					'RegisteredUser1',
					'https://local.wiki',
					'https://external.wiki',
					'Special:Contributions/RegisteredUser1',
				],
				'username' => 'RegisteredUser1',
				'sourcewiki' => 'otherwiki',
				'hasRevisionRecord' => false,
				'isDeleted' => true,
			],
			'Registered account, external wiki, visible' => [
				'expectedStrings' => [
					'User_talk:RegisteredUser1',
					'https://external.wiki',
					'Special:Contributions/RegisteredUser1',
				],
				'unexpectedStrings' => [ 'https://local.wiki' ],
				'username' => 'RegisteredUser1',
				'sourcewiki' => 'otherwiki',
				'hasRevisionRecord' => false,
				'isDeleted' => false,
			],
			'Registered account, local wiki, hidden' => [
				'expectedStrings' => [ 'empty-username' ],
				'unexpectedStrings' => [
					'RegisteredUser1',
					'https://local.wiki',
					'https://external.wiki',
					'Special:Contributions/RegisteredUser1',
				],
				'username' => 'RegisteredUser1',
				// null is replaced with the local wiki ID
				'sourcewiki' => null,
				'hasRevisionRecord' => true,
				'isDeleted' => true,
			],
			'Registered account, local wiki, visible' => [
				'expectedStrings' => [
					'User_talk:RegisteredUser1',
					'Special:Contributions/RegisteredUser1',
					'https://local.wiki',
				],
				'unexpectedStrings' => [ 'https://external.wiki' ],
				'username' => 'RegisteredUser1',
				'sourcewiki' => null,
				'hasRevisionRecord' => true,
				'isDeleted' => false,
			],
			'Registered account, local wiki, rev record is not found' => [
				'expectedStrings' => [ 'empty-username' ],
				'unexpectedStrings' => [
					'RegisteredUser1',
					'https://local.wiki',
					'https://external.wiki',
					'Special:Contributions/RegisteredUser1',
				],
				'username' => 'RegisteredUser1',
				'sourcewiki' => null,
				'hasRevisionRecord' => false,
				'isDeleted' => false,
			],
		];
	}

	/**
	 * @dataProvider provideFormatFlags
	 */
	public function testFormatFlags( $hasFlags ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'rev_minor_edit' => $hasFlags ? '1' : '0',
			'rev_parent_id' => $hasFlags ? '0' : '1',
		] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$this->revisionStore
			->method( 'newRevisionFromRow' )
			->with( $row )
			->willReturn( $this->revisionRecord );

		$this->revisionRecord
			->method( 'getComment' )
			->with(
				RevisionRecord::RAW,
				RequestContext::getMain()->getAuthority()
			)->willReturn( null );

		$flags = $pager->formatFlags( $row );
		if ( $hasFlags ) {
			$this->assertCount( 2, $flags );
		} else {
			$this->assertCount( 0, $flags );
		}
	}

	public static function provideFormatFlags() {
		return [ [ true ], [ false ] ];
	}

	public function testFormatVisibilityLink() {
		$row = $this->getRow( [ 'sourcewiki' => 'otherwiki' ] );
		$pager = $this->getWrappedPager( '127.0.0.1', $row->page_title );

		$formatted = $pager->formatVisibilityLink( $row );
		$this->assertSame( '', $formatted );
	}

	/**
	 * @dataProvider provideFormatTags
	 */
	public function testFormatTags( $hasTags ) {
		$row = $this->getRow( [
			'sourcewiki' => 'otherwiki',
			'ts_tags' => $hasTags ? 'sometag' : null,
		] );

		// formatRow() calls getTemplateParams(), which calls formatUserLink(),
		// which calls UserLinkRenderer's userLink(): We need to mock that to
		// prevent it from calling WikiMap static methods that check if the user
		// is external, since those calls can't be mocked and seeding the DB
		// with data that would make WikiMap behave the way we need may make
		// tests that also modify the sites table to fail.
		$this->userLinkRenderer
			->expects( $this->once() )
			->method( 'userLink' )
			->with(
				$this->isInstanceOf( UserIdentityValue::class ),
				RequestContext::getMain()
			)->willReturn(
				'<a href="http://example.com/User:username">username</a>'
			);

		// Formatting external comments makes use of the Authority from the
		// RequestContext: Create a context mocking the current user
		$authority = new SimpleAuthority(
			new UserIdentityValue( 0, '127.0.0.1' ),
			[]
		);
		$context = RequestContext::getMain();
		$context->setAuthority( $authority );
		$context->setLanguage( 'qqx' );

		// Get a pager that uses the mock in $this->userLinkRenderer. That's
		// needed to avoid the calls the regular UserLinkRenderer does to
		// WikiMap, since we can't mock static calls from a test.
		$pager = $this->getPagerWithOverrides( [
			'Context' => $context,
			'RevisionStore' => $this->revisionStore,
			'UserName' => '127.0.0.1',
		] );

		$this->revisionStore
			->method( 'newRevisionFromRow' )
			->with( $row )
			->willReturn( $this->revisionRecord );

		$this->revisionRecord
			->method( 'getComment' )
			->with(
				RevisionRecord::RAW,
				// May be called with the current Authority when called from
				// GlobalContributionsPager or null when called from the
				// CommentFormatter.
				$this->logicalOr(
					RequestContext::getMain()->getAuthority(),
					null
				)
			)->willReturn(
				new CommentStoreComment( null, 'Formatted comment' )
			);

		$formatted = $pager->formatRow( $row );
		if ( $hasTags ) {
			$this->assertStringContainsString( 'sometag', $formatted );
		} else {
			$this->assertStringNotContainsString( 'sometag', $formatted );
		}
	}

	public static function provideFormatTags() {
		return [ [ true ], [ false ] ];
	}

	/**
	 * @dataProvider provideExternalWikiPermissions
	 */
	public function testExternalWikiPermissions( array $rawPermissions, array $permissions, int $expectedCount ) {
		$localWiki = WikiMap::getCurrentWikiId();
		$externalWiki = 'otherwiki';

		// Mock the user has a central id
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromLocalUser' )
			->willReturn( 1 );

		// Mock fetching the recently active wikis
		$globalContributionsLookup = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$globalContributionsLookup->method( 'getActiveWikis' )
			->willReturn( [ $localWiki, $externalWiki ] );

		// Mock making the permission API call
		$globalContributionsLookup->method( 'getAndUpdateExternalWikiPermissions' )
			->willReturn( new ExternalPermissions( [ 'otherwiki' => $rawPermissions ] ) );

		$pager = $this->getPagerWithOverrides( [
			'CentralIdLookup' => $centralIdLookup,
			'GlobalContributionsLookup' => $globalContributionsLookup,
		] );
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$wikis = $pager->fetchWikisToQuery();

		$this->assertCount( $expectedCount, $wikis );
		$this->assertSame(
			$permissions,
			$pager->permissions->getPermissionsOnWiki( 'otherwiki' )
		);
	}

	public static function provideExternalWikiPermissions() {
		return [
			'Can always reveal IP at external wiki' => [
				'rawPermissions' => [
					'checkuser-temporary-account' => [ 'error' ],
					'checkuser-temporary-account-no-preference' => [],
				],
				'permissions' => [
					'checkuser-temporary-account-no-preference',
				],
				'expectedCount' => 1,
			],
			'Can reveal IP at external wiki with preference' => [
				'rawPermissions' => [
					'checkuser-temporary-account' => [],
					'checkuser-temporary-account-no-preference' => [ 'error' ],
				],
				'permissions' => [
					'checkuser-temporary-account',
				],
				'expectedCount' => 0,
			],
			'Can not reveal IP at external wiki' => [
				'rawPermissions' => [
					'checkuser-temporary-account' => [ 'error' ],
					'checkuser-temporary-account-no-preference' => [ 'error' ],
				],
				'permissions' => [],
				'expectedCount' => 0,
			],
		];
	}

	public function testGetExternalWikiPermissionsNoCentralId() {
		// Mock the user has no central id
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromLocalUser' )
			->willReturn( 0 );

		$pager = $this->getPagerWithOverrides( [
			'CentralIdLookup' => $centralIdLookup,
		] );
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$wikis = $pager->getExternalWikiPermissions( [] );

		$this->assertFalse( $pager->permissions->hasAnyWiki() );
	}

	public function testExternalWikiPermissionsNotCheckedForUser() {
		$localWiki = WikiMap::getCurrentWikiId();
		$externalWiki = 'otherwiki';

		// Mock fetching the recently active wikis
		$globalContributionsLookup = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$globalContributionsLookup->method( 'getActiveWikis' )
			->willReturn( [ $localWiki, $externalWiki ] );

		$globalContributionsLookup->expects( $this->never() )
			->method( 'getAndUpdateExternalWikiPermissions' );

		// Mock the central user exists
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromName' )
			->willReturn( 45678 );

		$pager = $this->getPagerWithOverrides( [
			'CentralIdLookup' => $centralIdLookup,
			'GlobalContributionsLookup' => $globalContributionsLookup,
			'UserName' => 'SomeUser',
		] );
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$wikis = $pager->fetchWikisToQuery();

		$this->assertCount( 2, $wikis );
		$this->assertFalse( $pager->permissions->hasAnyWiki() );
	}

	public function testSkipWikisInConfig() {
		$this->overrideConfigValue( 'CheckUserGlobalContributionsSkippedWikiIds', [ 'skippedwiki' ] );

		// Mock fetching the recently active wikis
		$globalContributionsLookup = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$globalContributionsLookup->method( 'getActiveWikis' )
			->willReturn( [ 'somewiki', 'skippedwiki' ] );

		// Mock the central user exists
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromName' )
			->willReturn( 45678 );

		$pager = $this->getPagerWithOverrides( [
			'CentralIdLookup' => $centralIdLookup,
			'GlobalContributionsLookup' => $globalContributionsLookup,
			'UserName' => 'SomeUser',
		] );
		$pager = TestingAccessWrapper::newFromObject( $pager );
		$wikis = $pager->fetchWikisToQuery();

		$this->assertSame( [ 'somewiki' ], $wikis );
	}

	/**
	 * @dataProvider provideQueryData
	 *
	 * @param IResultWrapper[] $resultsByWiki Map of result sets keyed by wiki ID
	 * @param string[] $paginationParams The pagination parameters to set on the pager
	 * @param array $expectedParentSizeLookups The expected parent revision IDs to be queried for each wiki
	 * @param int $expectedCount The expected number of rows in the result set
	 * @param array|false $expectedPrevQuery The expected query parameters for the 'prev' page,
	 * or `false` if there is no previous page
	 * @param array|false $expectedNextQuery The expected query parameters for the 'next' page,
	 * or `false` if there is no next page
	 * @param int[] $expectedDiffSizes The expected byte sizes of the shown diffs
	 * @param array $expectRevisionsReturned Whether or not the revision store is expected to get a record
	 */
	public function testQuery(
		array $resultsByWiki,
		array $paginationParams,
		array $expectedParentSizeLookups,
		int $expectedCount,
		$expectedPrevQuery,
		$expectedNextQuery,
		array $expectedDiffSizes,
		array $expectRevisionsReturned
	): void {
		$wikiIds = array_keys( $resultsByWiki );

		// Mock fetching the recently active wikis
		$globalContributionsLookup = $this->createMock( CheckUserGlobalContributionsLookup::class );
		$globalContributionsLookup->method( 'getActiveWikis' )
			->willReturn( $wikiIds );

		// Mock returning the permissions
		$permsByWiki = array_fill_keys(
			$wikiIds,
			[
				'checkuser-temporary-account' => [ 'error' ],
				'checkuser-temporary-account-no-preference' => [],
			],
		);
		$globalContributionsLookup->method( 'getAndUpdateExternalWikiPermissions' )
			->willReturn( new ExternalPermissions( $permsByWiki ) );

		$parentSizeMap = [];
		foreach ( $expectedParentSizeLookups as $wikiId => $parentRevIds ) {
			$parentSizes = array_fill_keys( $parentRevIds, 5 );
			$parentSizeMap[] = [ $wikiId, $parentRevIds, $parentSizes ];
		}

		$globalContributionsLookup->method( 'getRevisionSizes' )
			->willReturnMap( $parentSizeMap );

		$checkUserDb = $this->createMock( IReadableDatabase::class );
		$dbMap = [
			[ CheckUserQueryInterface::VIRTUAL_GLOBAL_DB_DOMAIN, null, $checkUserDb ],
		];

		foreach ( $resultsByWiki as $wikiId => $result ) {
			$localQueryBuilder = $this->createMock( SelectQueryBuilder::class );
			$localQueryBuilder->method( $this->logicalNot( $this->equalTo( 'fetchResultSet' ) ) )
				->willReturnSelf();
			$localQueryBuilder->method( 'fetchResultSet' )
				->willReturn( $result );

			$localDb = $this->createMock( IReadableDatabase::class );
			$localDb->method( 'newSelectQueryBuilder' )
				->willReturn( $localQueryBuilder );

			$dbMap[] = [ $wikiId, null, $localDb ];
		}

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider->method( 'getReplicaDatabase' )
			->willReturnMap( $dbMap );

		// Mock the user has a central id
		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromLocalUser' )
			->willReturn( 1 );

		// Since this pager calls out to other wikis, extension hooks should not be run
		// because the extension may not be loaded on the external wiki (T385092).
		$hookContainer = $this->createMock( HookContainer::class );
		$hookContainer->expects( $this->never() )
			->method( 'run' );

		// Object representing the current user
		$authority = new SimpleAuthority(
			new UserIdentityValue( 0, '127.0.0.1' ),
			[]
		);

		// This test handles results for external wikis and, when that happens,
		// formatComment() in the parent pager expects having a comment for each
		// row in $formattedComments indexed by revisionId.
		//
		// The data provider assigns fake revision IDs starting at 1 and going
		// up to the number of fake revisions returned, so we can just provide a
		// big enough array with dummy comments for each ID in the range 1-10.
		$formattedComments = array_fill( 0, 10, 'test' );

		// Create a context that returns the current user.
		//
		// Setting the language to qqx is needed to be able to preg_match the
		// output later.
		$context = RequestContext::getMain();
		$context->setAuthority( $authority );
		$context->setLanguage( 'qqx' );

		// Mock different services called internally to return dummy values
		$commentBatch = $this->createMock( RevisionCommentBatch::class );
		$commentBatch
			->method( 'authority' )
			->with( $authority )
			->willReturnSelf();
		$commentBatch
			->method( 'revisions' )
			->willReturnSelf();
		$commentBatch
			->method( 'hideIfDeleted' )
			->willReturnSelf();
		$commentBatch
			->method( 'execute' )
			->willReturn( $formattedComments );

		$commentFormatter = $this->createMock( CommentFormatter::class );
		$commentFormatter
			->expects( $this->never() )
			->method( 'formatRevisions' );
		$commentFormatter
			->expects( $this->once() )
			->method( 'createRevisionBatch' )
			->willReturn( $commentBatch );
		$commentFormatter
			->method( 'formatRevision' )
			->with( $this->revisionRecord, $authority )
			->willReturn( 'Formatted comment' );

		$this->revisionRecord
			->method( 'getComment' )
			->with( RevisionRecord::RAW, $authority )
			->willReturn(
				new CommentStoreComment( null, 'Unformatted comment' )
			);

		$revisionStore = $this->getServiceContainer()->getRevisionStore();

		// Setting up the revision stores that each external wiki is expected to return
		// $revisionStoreFactory will stub out the return values using these arrays
		$revisionStoreProxies = [];

		// Pager also uses the local revision store, save it here when the loop handles
		// its case so the test can pass it into the pager constructor
		$localRevisionStore = null;
		foreach ( $wikiIds as $wikiId ) {
			$storeProxy = $this->getMockBuilder( RevisionStore::class )
				->disableOriginalConstructor()
				->getMock();

			if ( $expectRevisionsReturned[$wikiId] ) {
				// Confirm that the revision is being called
				$storeProxy
					->expects( $this->atLeastOnce() )
					->method( 'newRevisionFromRow' )
					->willReturn( $this->revisionRecord );
			} else {
				// Depending on the test, the wiki's revision stores won't have revisions
				// in the expected to call so we shouldn't set the expectation in those cases
				$storeProxy
					->expects( $this->never() )
					->method( 'newRevisionFromRow' );
			}

			// Only the local store should be calling this function
			if ( $wikiId === WikiAwareEntity::LOCAL ) {
				$storeProxy
					->expects( $this->atLeastOnce() )
					->method( 'newSelectQueryBuilder' )
					->willReturnCallback( static fn ( IReadableDatabase $db ) =>
						$revisionStore->newSelectQueryBuilder( $db )
				);
			} else {
				$storeProxy
					->expects( $this->never() )
					->method( 'newSelectQueryBuilder' );
			}

			$storeProxy
				->method( 'getRevisionSizes' )
				->willReturnCallback( static fn ( array $revIds ) =>
					$revisionStore->getRevisionSizes( $revIds )
				);

			// External revision store shouldn't be called for local revisions so only
			// save the local revision store to the variable instead of the return map
			if ( $wikiId === WikiAwareEntity::LOCAL ) {
				$localRevisionStore = $storeProxy;
			} else {
				$revisionStoreProxies[] = [ $wikiId, $storeProxy ];
			}
		}

		$revisionStoreFactory = $this->createMock( RevisionStoreFactory::class );

		// Diff size is an indicator for how many revisions were rendered so use their
		// count to check that the revision store is called for each revision
		$revisionStoreFactory
			->expects( $this->exactly( count( $expectedDiffSizes ) ) )
			->method( 'getRevisionStore' )
			->willReturnMap(
				$revisionStoreProxies
			);

		// Initialize the subject under test
		$pager = $this->getPagerWithOverrides( [
			'CentralIdLookup' => $centralIdLookup,
			'HookContainer' => $hookContainer,
			'LoadBalancerFactory' => $dbProvider,
			'GlobalContributionsLookup' => $globalContributionsLookup,
			'Context' => $context,
			'CommentFormatter' => $commentFormatter,
			'RevisionStore' => $localRevisionStore,
			'RevisionStoreFactory' => $revisionStoreFactory,
		] );
		$pager->mIsBackwards = ( $paginationParams['dir'] ?? '' ) === 'prev';
		$pager->setLimit( $paginationParams['limit'] );
		$pager->setOffset( $paginationParams['offset'] ?? '' );

		$pager->doQuery();

		$pagingQueries = $pager->getPagingQueries();
		$result = $pager->getResult();
		$body = $pager->getBody();

		preg_match_all( '/\(rc-change-size: (\d+)\)/', $body, $matches, PREG_SET_ORDER );
		$diffSizes = array_map( static fn ( array $match ) => (int)$match[1], $matches );

		$this->assertSame( $expectedCount, $result->numRows(), 'Unexpected result row count' );
		$this->assertSame( $expectedPrevQuery, $pagingQueries['prev'], 'Invalid prev pagination link' );
		$this->assertSame( $expectedNextQuery, $pagingQueries['next'], 'Invalid next pagination link' );
		$this->assertSame( $expectedDiffSizes, $diffSizes, 'Mismatched byte counts in diff links' );
		$this->assertApiLookupErrorCount( 0 );
	}

	public static function provideQueryData(): iterable {
		$testResults = [
			'testwiki' => self::makeMockResult( [
				'20250110000000',
				'20250107000000',
				'20250108000000',
			] ),
			'otherwiki' => self::makeMockResult( [
				'20250109000000',
				'20250108000000',
			] ),
		];

		yield '5 rows, limit=4, first page' => [
			'resultsByWiki' => $testResults,
			'paginationParams' => [
				'limit' => 4,
			],
			'expectedParentSizeLookups' => [
				'testwiki' => [ 1 ],
				'otherwiki' => [ 1 ],
			],
			// 4 rows shown + 1 row for the next page link
			'expectedCount' => 5,
			'expectedPrevQuery' => false,
			'expectedNextQuery' => [
				'offset' => '20250108000000|-1|2',
				'limit' => 4,
			],
			'expectedDiffSizes' => [ 0, 0, 0, 95 ],
			'expectRevisionsReturned' => [
				'testwiki' => true,
				'otherwiki' => true,
			],
		];

		yield '5 rows, limit=4, second page' => [
			'resultsByWiki' => $testResults,
			'paginationParams' => [
				'offset' => '20250108000000|-1|2',
				'limit' => 4,
			],
			'expectedParentSizeLookups' => [
				'testwiki' => [ 1 ],
				'otherwiki' => [ 1 ],
			],
			'expectedCount' => 1,
			'expectedPrevQuery' => [
				'dir' => 'prev',
				'offset' => '20250107000000|0|2',
				'limit' => 4,
			],
			'expectedNextQuery' => false,
			'expectedDiffSizes' => [ 95 ],
			'expectRevisionsReturned' => [
				'testwiki' => true,
				'otherwiki' => false,
			],
		];

		yield '5 rows, limit=4, backwards from second page' => [
			'resultsByWiki' => $testResults,
			'paginationParams' => [
				'dir' => 'prev',
				'offset' => '20250107000000|0|2',
				'limit' => 4,
			],
			'expectedParentSizeLookups' => [
				'testwiki' => [ 2 ],
				'otherwiki' => [ 1 ],
			],
			'expectedCount' => 4,
			'expectedPrevQuery' => false,
			'expectedNextQuery' => [
				'offset' => '20250108000000|-1|2',
				'limit' => 4,
			],
			'expectedDiffSizes' => [ 0, 0, 95, 95 ],
			'expectRevisionsReturned' => [
				'testwiki' => true,
				'otherwiki' => true,
			],
		];

		$resultsWithIdenticalTimestamps = [
			'testwiki' => self::makeMockResult( [
				'20250108000000',
				'20250108000000',
			] ),
			'otherwiki' => self::makeMockResult( [
				'20250108000000',
			] ),
		];

		yield '3 rows, identical timestamps, limit=2, first page' => [
			'resultsByWiki' => $resultsWithIdenticalTimestamps,
			'paginationParams' => [
				'limit' => 2,
			],
			'expectedParentSizeLookups' => [
				'testwiki' => [ 1 ],
				'otherwiki' => [ 1 ],
			],
			// 2 rows shown + 1 row for the next page link
			'expectedCount' => 3,
			'expectedPrevQuery' => false,
			'expectedNextQuery' => [
				'offset' => '20250108000000|0|2',
				'limit' => 2,
			],
			'expectedDiffSizes' => [ 0, 95 ],
			'expectRevisionsReturned' => [
				'testwiki' => true,
				'otherwiki' => false,
			],
		];

		yield '3 rows, identical timestamps, limit=2, second page' => [
			'resultsByWiki' => $resultsWithIdenticalTimestamps,
			'paginationParams' => [
				'offset' => '20250108000000|0|2',
				'limit' => 2,
			],
			'expectedParentSizeLookups' => [
				'otherwiki' => [ 1 ],
			],
			'expectedCount' => 1,
			'expectedPrevQuery' => [
				'dir' => 'prev',
				'offset' => '20250108000000|-1|2',
				'limit' => 2,
			],
			'expectedNextQuery' => false,
			'expectedDiffSizes' => [ 95 ],
			'expectRevisionsReturned' => [
				'testwiki' => false,
				'otherwiki' => true,
			],
		];

		yield '3 rows, identical timestamps, limit=2, backwards from second page' => [
			'resultsByWiki' => $resultsWithIdenticalTimestamps,
			'paginationParams' => [
				'dir' => 'prev',
				'offset' => '20250108000000|-1|2',
				'limit' => 2,
			],
			'expectedParentSizeLookups' => [
				'testwiki' => [ 1 ],
			],
			'expectedCount' => 2,
			'expectedPrevQuery' => false,
			'expectedNextQuery' => [
				'offset' => '20250108000000|0|2',
				'limit' => 2,
			],
			'expectedDiffSizes' => [ 0, 95 ],
			'expectRevisionsReturned' => [
				'testwiki' => true,
				'otherwiki' => false,
			],
		];
	}

	/**
	 * Convenience function to create an ordered result set of mock revision data
	 * with the specified timestamps.
	 *
	 * @param string[] $timestamps The MW timestamps of the revisions.
	 * @return IResultWrapper
	 */
	private static function makeMockResult( array $timestamps ): IResultWrapper {
		$rows = [];
		$revId = 1 + count( $timestamps );

		// Sort the timestamps in descending order, since the DB would sort the revisions in the same way.
		usort( $timestamps, static fn ( string $ts, string $other ): int => $other <=> $ts );

		foreach ( $timestamps as $timestamp ) {
			$rows[] = (object)[
				'rev_id' => $revId,
				'rev_page' => '1',
				'rev_actor' => '1',
				'rev_user' => '1',
				'rev_user_text' => self::TEMP_USERNAME,
				'rev_timestamp' => $timestamp,
				'rev_minor_edit' => '0',
				'rev_deleted' => '0',
				'rev_len' => '100',
				'rev_parent_id' => $revId - 1,
				'rev_sha1' => '',
				'rev_comment_text' => '',
				'rev_comment_data' => null,
				'rev_comment_cid' => '1',
				'page_latest' => '2',
				'page_is_new' => '0',
				'page_namespace' => '0',
				'page_title' => 'Test page',
				'cuc_timestamp' => $timestamp,
				'ts_tags' => null,
			];

			$revId--;
		}

		return new FakeResultWrapper( $rows );
	}

	public function testBodyIsWrappedWithPlainlinksClass(): void {
		$localWiki = WikiMap::getCurrentWikiId();
		$externalWiki = 'otherwiki';

		// Mock fetching the recently active wikis
		$queryBuilder = $this->createMock( SelectQueryBuilder::class );
		$queryBuilder
			->method(
				$this->logicalOr(
					'select', 'from', 'distinct', 'where', 'andWhere',
					'join', 'orderBy', 'limit', 'queryInfo', 'caller'
				)
			)->willReturnSelf();
		$queryBuilder
			->method( 'fetchFieldValues' )
			->willReturn( [ $localWiki, $externalWiki ] );

		$database = $this->createMock( IReadableDatabase::class );
		$database
			->method( 'newSelectQueryBuilder' )
			->willreturn( $queryBuilder );

		$dbProvider = $this->createMock( IConnectionProvider::class );
		$dbProvider
			->method( 'getReplicaDatabase' )
			->willReturn( $database );

		// Since this pager calls out to other wikis, extension hooks should not be run
		// because the extension may not be loaded on the external wiki (T385092).
		$hookContainer = $this->createMock( HookContainer::class );
		$hookContainer
			->expects( $this->never() )
			->method( 'run' );

		$pager = $this->getPagerWithOverrides( [
			'HookContainer' => $hookContainer,
			'LoadBalancerFactory' => $dbProvider,
		] );

		$pager = TestingAccessWrapper::newFromObject( $pager );
		$pager->currentPage = Title::makeTitle( 0, 'Test page' );
		$pager->currentRevRecord = null;
		$pager->needsToEnableGlobalPreferenceAtWiki = false;

		$this->assertSame(
			"<section class=\"mw-pager-body plainlinks\">\n",
			$pager->getStartBody()
		);
		$this->assertSame(
			"</section>\n",
			$pager->getEndBody()
		);
	}

	/**
	 * Convenience function to assert that the API lookup error counter metric has a given count.
	 *
	 * @param int $expectedCount
	 * @return void
	 */
	private function assertApiLookupErrorCount( int $expectedCount ): void {
		$counter = $this->getServiceContainer()
			->getStatsFactory()
			->getCounter( CheckUserGlobalContributionsLookup::API_LOOKUP_ERROR_METRIC_NAME );

		$sampleValues = array_map( static fn ( $sample ) => $sample->getValue(), $counter->getSamples() );

		$this->assertSame( $expectedCount, $counter->getSampleCount() );
		$this->assertSame(
			(float)$expectedCount,
			(float)array_sum( $sampleValues )
		);
	}
}
