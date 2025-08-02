<?php

namespace MediaWiki\Minerva;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Message\Message;
use MediaWiki\Minerva\Menu\PageActions\ToolbarBuilder;
use MediaWiki\Minerva\Permissions\IMinervaPagePermissions;
use MediaWiki\Minerva\Skins\SkinUserPageHelper;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\Watchlist\WatchlistManager;
use MediaWikiUnitTestCase;

/**
 * @group MinervaNeue
 * @coversDefaultClass \MediaWiki\Minerva\Menu\PageActions\ToolbarBuilder
 */
class ToolbarBuilderTest extends MediaWikiUnitTestCase {
	private function makeBuilder() {
		$title = $this->createMock( Title::class );
		$outputPage = $this->createMock( OutputPage::class );
		$loginTitle = $this->createMock( Title::class );
		$user = $this->createMock( User::class );
		$ctx = $this->createMock( RequestContext::class );
		$msg = $this->createMock( Message::class );
		$ctx->method( 'msg' )->willReturn( $msg );
		$ctx->method( 'getOutput' )->willReturn( $outputPage );
		$permissions = $this->createMock( IMinervaPagePermissions::class );
		$relevantUserPageHelper = $this->createMock( SkinUserPageHelper::class );
		$languagesHelper = $this->createMock( LanguagesHelper::class );
		$serviceOptions = $this->createMock( ServiceOptions::class );
		$serviceOptions->method( 'get' )->willReturn( 1 );
		$watchlistManager = $this->createMock( WatchlistManager::class );
		$hookContainer = $this->createMock( HookContainer::class );
		$permissions->method( 'isAllowed' )->willReturn( true );
		$relevantUserPageHelper->method( 'isUserPageAccessibleToCurrentUser' )->willReturn( false );

		$skinOptions = new SkinOptions(
			$hookContainer,
			$relevantUserPageHelper
		);

		return new ToolbarBuilder(
			$title,
			$user,
			$ctx,
			$permissions,
			$skinOptions,
			$relevantUserPageHelper,
			$languagesHelper,
			$serviceOptions,
			$watchlistManager,
			$loginTitle
		);
	}

	/**
	 * @covers ::getGroup
	 */
	public function testGetGroup() {
		$builder = $this->makeBuilder();
		$group = $builder->getGroup(
			[
				'unwatch' => [
					'href' => '#',
					'text' => 'watch',
					'class' => 'watch-link',
				],
				'ext-action-no-icon' => [
					'href' => '?ext-action-no-icon',
					'text' => 'an action via a hook from an extension without an icon',
					'class' => 'custom',
				],
				// This is currently not supported.
				// Actions with exception of watchstar are expected to appear in a dropdown menu.
				'ext-action-icon' => [
					'href' => '?ext-action-icon',
					'text' => 'an action via a hook from an extension with an icon',
					'class' => 'custom',
				],
			],
			[
				'history' => [
					'href' => '?action=history',
					'text' => 'history',
					'class' => 'history-link',
				],
				've-edit' => [
					'href' => '?action=vedit',
					'text' => 'vedit',
					'class' => 'edit-link',
				],
				'viewsource' => [
					'href' => '?action=view',
					'text' => 'view source',
					'class' => 'edit-link',
				],
				'edit' => [
					'href' => '?action=edit',
					'text' => 'edit',
					'class' => 'edit-link',
				],
				'ext-view-no-icon' => [
					'href' => '?ext-view-no-icon',
					'text' => 'a view via a hook from an extension without an icon',
					'class' => 'custom',
				],
				'ext-view-icon' => [
					'href' => '?ext-view-icon',
					'icon' => 'globe',
					'text' => 'a view via a hook from an extension with an icon',
					'class' => 'custom',
				],
			]
		);

		$entries = $group->getEntries();
		$this->assertCount( 7, $entries );
		$this->assertSame( 'language-selector', $entries[0]['name'], 'check language presence' );
		$this->assertSame( 'page-actions-watch', $entries[1]['name'], 'check watchstar presence' );
		$this->assertSame( 'page-actions-history', $entries[2]['name'], 'check history presence' );
		$this->assertSame( 'page-actions-ve-edit', $entries[3]['name'], 'check ve presence' );
		$this->assertSame( 'page-actions-viewsource', $entries[4]['name'], 'check view source presence' );
		$this->assertSame( 'page-actions-edit', $entries[5]['name'], 'check edit wikitext presence' );
		$this->assertSame(
			'page-actions-ext-view-icon',
			$entries[6]['name'],
			'check the view with an icon declaration got added and the one without got ignored' );
	}
}
