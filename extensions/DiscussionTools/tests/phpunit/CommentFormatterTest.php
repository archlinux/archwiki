<?php

namespace MediaWiki\Extension\DiscussionTools\Tests;

use FormatJson;
use GenderCache;
use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentity;
use ParserOutput;
use Skin;
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
		string $name, string $titleText, string $dom, string $expected, string $config, string $data, bool $isMobile
	): void {
		$this->setService( 'GenderCache', $this->createMock( GenderCache::class ) );
		$dom = static::getHtml( $dom );
		$expectedPath = $expected;
		$expected = static::getText( $expectedPath );
		$config = static::getJson( $config );
		$data = static::getJson( $data );

		$this->setupEnv( $config, $data );
		$this->overrideConfigValues( [
			MainConfigNames::ScriptPath => '/w',
			MainConfigNames::Script => '/w/index.php',
		] );

		$title = Title::newFromText( $titleText );
		$subscriptionStore = new MockSubscriptionStore();
		$user = $this->createMock( UserIdentity::class );
		$qqxLang = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'qqx' );
		$skin = $this->createMock( Skin::class );
		$outputPage = $this->createMock( OutputPage::class );
		$outputPage->method( 'getConfig' )->willReturn( $this->createMock( Config::class ) );
		$outputPage->method( 'getTitle' )->willReturn( $title );
		$outputPage->method( 'getUser' )->willReturn( $user );
		$outputPage->method( 'getLanguage' )->willReturn( $qqxLang );
		$outputPage->method( 'getSkin' )->willReturn( $skin );
		$outputPage->method( 'msg' )->willReturn( 'a label' );

		MockCommentFormatter::$parser = static::createParser( $data );

		$commentFormatter = TestingAccessWrapper::newFromClass( MockCommentFormatter::class );

		$pout = new ParserOutput();
		$preprocessed = $commentFormatter->addDiscussionToolsInternal( $dom, $pout, $title );
		$preprocessed .= "\n<pre>\n" .
			"newestComment: " . FormatJson::encode(
				$pout->getExtensionData( 'DiscussionTools-newestComment' ), "\t", FormatJson::ALL_OK ) . "\n" .
			( $pout->getExtensionData( 'DiscussionTools-hasLedeContent' ) ?
			 "hasLedeContent\n" : '' ) .
			( $pout->getExtensionData( 'DiscussionTools-hasCommentsInLedeContent' ) ?
			 "hasCommentsInLedeContent\n" : '' ) .
			( $pout->getExtensionData( 'DiscussionTools-isEmptyTalkPage' ) ?
			 "isEmptyTalkPage\n" : '' ) .
			FormatJson::encode( $pout->getJsConfigVars(), "\t", FormatJson::ALL_OK ) .
			"\n</pre>";

		\OutputPage::setupOOUI();

		$actual = $preprocessed;

		$actual = MockCommentFormatter::postprocessTopicSubscription(
			$actual, $outputPage, $subscriptionStore, $isMobile
		);

		$actual = MockCommentFormatter::postprocessVisualEnhancements(
			$actual, $outputPage, $isMobile
		);

		$actual = MockCommentFormatter::postprocessReplyTool(
			$actual, $outputPage, $isMobile
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
	public static function provideAddDiscussionToolsInternal() {
		foreach ( static::getJson( '../cases/formatted.json' ) as $case ) {
			// Run each test case twice, for desktop and mobile output
			yield array_merge( $case, [ 'expected' => $case['expected']['desktop'], 'isMobile' => false ] );
			yield array_merge( $case, [ 'expected' => $case['expected']['mobile'], 'isMobile' => true ] );
		}
	}

}
