<?php

namespace MediaWiki\Extension\DiscussionTools\Tests;

use FormatJson;
use MediaWiki\MediaWikiServices;
use ParserOutput;
use Title;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\DiscussionTools\CommentFormatter
 * @covers \MediaWiki\Extension\DiscussionTools\CommentUtils
 */
class CommentFormatterTest extends IntegrationTestCase {

	/**
	 * @dataProvider provideAddDiscussionToolsInternal
	 */
	public function testAddDiscussionToolsInternal(
		string $name, string $title, string $dom, string $expected, string $config, string $data, bool $isMobile
	): void {
		$dom = static::getHtml( $dom );
		$expectedPath = $expected;
		$expected = static::getText( $expectedPath );
		$config = static::getJson( $config );
		$data = static::getJson( $data );

		$this->setupEnv( $config, $data );
		$title = Title::newFromText( $title );
		MockCommentFormatter::$parser = static::createParser( $data );

		$commentFormatter = TestingAccessWrapper::newFromClass( MockCommentFormatter::class );

		$pout = new ParserOutput();
		$preprocessed = $commentFormatter->addDiscussionToolsInternal( $dom, $pout, $title );
		$preprocessed .= "\n<pre>\n" .
			FormatJson::encode( $pout->getJsConfigVars(), "\t", FormatJson::ALL_OK ) .
			"\n</pre>";

		$mockSubStore = new MockSubscriptionStore();
		$qqxLang = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'qqx' );

		\OutputPage::setupOOUI();

		$actual = $preprocessed;

		$actual = MockCommentFormatter::postprocessTopicSubscription(
			$actual, $qqxLang, $mockSubStore, static::getTestUser()->getUser(), $isMobile
		);

		$actual = MockCommentFormatter::postprocessVisualEnhancements(
			$actual, $qqxLang, static::getTestUser()->getUser(), $isMobile
		);

		$actual = MockCommentFormatter::postprocessReplyTool(
			$actual, $qqxLang, $isMobile
		);

		// OOUI ID's are non-deterministic, so strip them from test output
		$actual = preg_replace( '/ id=[\'"]ooui-php-[0-9]+[\'"]/', '', $actual );

		// Optionally write updated content to the "reply HTML" files
		if ( getenv( 'DISCUSSIONTOOLS_OVERWRITE_TESTS' ) ) {
			static::overwriteTextFile( $expectedPath, $actual );
		}

		static::assertEquals( $expected, $actual, $name );
	}

	/**
	 * @return iterable<array>
	 */
	public function provideAddDiscussionToolsInternal() {
		foreach ( static::getJson( '../cases/formatted.json' ) as $case ) {
			// Run each test case twice, for desktop and mobile output
			yield array_merge( $case, [ 'expected' => $case['expected']['desktop'], 'isMobile' => false ] );
			yield array_merge( $case, [ 'expected' => $case['expected']['mobile'], 'isMobile' => true ] );
		}
	}

}
