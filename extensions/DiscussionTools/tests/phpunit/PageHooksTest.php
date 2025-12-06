<?php

namespace MediaWiki\Extension\DiscussionTools\Tests;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DiscussionTools\Hooks\HookUtils;
use MediaWiki\Page\Article;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\DiscussionTools\Hooks\PageHooks
 * @group Database
 */
class PageHooksTest extends MediaWikiIntegrationTestCase {

	private function sharedAddEmptyStateSetup() {
		$subjectTitle = Title::newFromText( __METHOD__ );
		$talkTitle = Title::newFromText( 'Talk:' . __METHOD__ );

		// Fulfill conditions of HookUtils::shouldDisplayEmptyState
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Liquid Threads' ) ) {
			$this->overrideConfigValue( 'LqtTalkPages', false );
		}
		$this->editPage( $subjectTitle, "" );

		// Sanity check, to avoid confusing error messages later if the test fails
		$this->assertTrue( $subjectTitle->exists() );
		$this->assertFalse( $talkTitle->exists() );

		return $talkTitle;
	}

	private function sharedAddEmptyStateView( $talkTitle ) {
		// Simulate a page view
		$context = new RequestContext();
		$context->setTitle( $talkTitle );
		$context->setLanguage( $this->getServiceContainer()->getLanguageFactory()->getLanguage( 'qqx' ) );

		// Sanity check, to avoid confusing error messages later if the test fails
		$this->assertTrue( HookUtils::shouldDisplayEmptyState( $context ) );

		$article = Article::newFromTitle( $talkTitle, $context );
		$article->view();

		$html = $context->getOutput()->getHTML();
		$this->assertSame(
			1,
			substr_count( $html, '(discussiontools-emptystate-desc' ),
			"Empty state message appears exactly once"
		);
		return $html;
	}

	public function testAddEmptyState_MissingPage() {
		$talkTitle = $this->sharedAddEmptyStateSetup();

		$this->sharedAddEmptyStateView( $talkTitle );
	}

	public function testAddEmptyState_ExistingPage() {
		$talkTitle = $this->sharedAddEmptyStateSetup();

		$this->editPage( $talkTitle, "" );
		$this->assertTrue( $talkTitle->exists() );

		$this->sharedAddEmptyStateView( $talkTitle );
	}

	public function testAddEmptyState_MissingPage_Talkpageheader() {
		$this->editPage( 'MediaWiki:Talkpageheader', 'TALKPAGEHEADER' );
		$talkTitle = $this->sharedAddEmptyStateSetup();

		$html = $this->sharedAddEmptyStateView( $talkTitle );
		$this->assertStringContainsString( '(talkpageheader)', $html );
	}

	public function testAddEmptyState_ExistingPage_Talkpageheader() {
		$this->editPage( 'MediaWiki:Talkpageheader', 'TALKPAGEHEADER' );
		$talkTitle = $this->sharedAddEmptyStateSetup();

		$this->editPage( $talkTitle, "" );
		$this->assertTrue( $talkTitle->exists() );

		$html = $this->sharedAddEmptyStateView( $talkTitle );
		$this->assertStringContainsString( '(talkpageheader)', $html );
	}

}
