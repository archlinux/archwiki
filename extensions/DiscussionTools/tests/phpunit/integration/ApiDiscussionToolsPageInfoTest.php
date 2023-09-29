<?php

namespace MediaWiki\Extension\DiscussionTools\Tests;

use ApiTestCase;
use MediaWiki\Extension\DiscussionTools\ApiDiscussionToolsPageInfo;
use MediaWiki\MediaWikiServices;
use Wikimedia\TestingAccessWrapper;

/**
 * @group medium
 * @group Database
 * @covers \MediaWiki\Extension\DiscussionTools\ApiDiscussionToolsPageInfo
 */
class ApiDiscussionToolsPageInfoTest extends ApiTestCase {

	use TestUtils;

	/**
	 * Setup the MW environment
	 *
	 * @param array $config
	 * @param array $data
	 */
	protected function setupEnv( array $config, array $data ): void {
		$this->setMwGlobals( $config );
		$this->setMwGlobals( [
			'wgArticlePath' => $config['wgArticlePath'],
			'wgNamespaceAliases' => $config['wgNamespaceIds'],
			'wgMetaNamespace' => strtr( $config['wgFormattedNamespaces'][NS_PROJECT], ' ', '_' ),
			'wgMetaNamespaceTalk' => strtr( $config['wgFormattedNamespaces'][NS_PROJECT_TALK], ' ', '_' ),
			// TODO: Move this to $config
			'wgLocaltimezone' => $data['localTimezone'],
			// Data used for the tests assumes there are no variants for English.
			// Language variants are tested using other languages.
			'wgUsePigLatinVariant' => false,
		] );
		$this->setUserLang( $config['wgContentLanguage'] );
		$this->setContentLang( $config['wgContentLanguage'] );
	}

	/**
	 * @dataProvider provideGetThreadItemsHtml
	 */
	public function testGetThreadItemsHtml(
		string $name, string $title, string $dom, string $expected, string $config, string $data
	): void {
		$dom = static::getHtml( $dom );
		$expectedPath = $expected;
		$expected = static::getJson( $expected );
		$config = static::getJson( $config );
		$data = static::getJson( $data );

		$doc = static::createDocument( $dom );
		$container = static::getThreadContainer( $doc );

		$this->setupEnv( $config, $data );
		$title = MediaWikiServices::getInstance()->getTitleParser()->parseTitle( $title );
		$threadItemSet = static::createParser( $data )->parse( $container, $title );

		$pageInfo = TestingAccessWrapper::newFromClass( ApiDiscussionToolsPageInfo::class );

		$threadItemsHtml = $pageInfo->getThreadItemsHtml( $threadItemSet );

		// Optionally write updated content to the JSON files
		if ( getenv( 'DISCUSSIONTOOLS_OVERWRITE_TESTS' ) ) {
			static::overwriteJsonFile( $expectedPath, $threadItemsHtml );
		}

		static::assertEquals( $expected, $threadItemsHtml, $name );
	}

	public function provideGetThreadItemsHtml(): array {
		return static::getJson( '../cases/threaditemshtml.json' );
	}

	/**
	 * @covers \MediaWiki\Extension\DiscussionTools\ApiDiscussionToolsPageInfo::execute
	 */
	public function testExecuteApiDiscussionToolsPageInfo() {
		$page = $this->getNonexistingTestPage( __METHOD__ );
		$this->editPage( $page, 'add DT pageinfo content' );

		$params = [
			'action' => 'discussiontoolspageinfo',
			'page' => $page->getTitle()->getText(),
		];

		$result = $this->doApiRequestWithToken( $params );

		$this->assertNotEmpty( $result[0]['discussiontoolspageinfo'] );
		$this->assertArrayHasKey( 'transcludedfrom', $result[0]['discussiontoolspageinfo'] );
	}

}
