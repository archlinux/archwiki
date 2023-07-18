<?php

use MediaWiki\Language\RawMessage;

/**
 * @group Database
 * @covers CoreParserFunctions
 */
class CoreParserFunctionsTest extends MediaWikiLangTestCase {

	public function testGender() {
		$userOptionsManager = $this->getServiceContainer()->getUserOptionsManager();

		$user = User::createNew( '*Female' );
		$userOptionsManager->setOption( $user, 'gender', 'female' );
		$user->saveSettings();

		$msg = ( new RawMessage( '{{GENDER:*Female|m|f|o}}' ) )->parse();
		$this->assertEquals( 'f', $msg, 'Works unescaped' );
		$escapedName = wfEscapeWikiText( '*Female' );
		$msg2 = ( new RawMessage( '{{GENDER:' . $escapedName . '|m|f|o}}' ) )
			->parse();
		$this->assertEquals( 'f', $msg2, 'Works escaped' );
	}

	public function provideTalkpagename() {
		yield [ 'Talk:Foo bar', 'foo_bar' ];
		yield [ 'Talk:Foo', ' foo ' ];
		yield [ 'Talk:Foo', 'Talk:Foo' ];
		yield [ 'User talk:Foo', 'User:foo' ];
		yield [ '', 'Special:Foo' ];
		yield [ '', '' ];
		yield [ '', ' ' ];
		yield [ '', '__' ];
		yield [ '', '#xyzzy' ];
		yield [ '', '#' ];
		yield [ '', ':' ];
		yield [ '', ':#' ];
		yield [ '', 'User:' ];
		yield [ '', 'User:#' ];
	}

	/**
	 * @dataProvider provideTalkpagename
	 */
	public function testTalkpagename( $expected, $title ) {
		$parser = $this->getServiceContainer()->getParser();

		$this->assertSame( $expected, CoreParserFunctions::talkpagename( $parser, $title ) );
	}

	public function provideSubjectpagename() {
		yield [ 'Foo bar', 'Talk:foo_bar' ];
		yield [ 'Foo', ' Talk:foo ' ];
		yield [ 'User:Foo', 'User talk:foo' ];
		yield [ 'Special:Foo', 'Special:Foo' ];
		yield [ '', '' ];
		yield [ '', ' ' ];
		yield [ '', '__' ];
		yield [ '', '#xyzzy' ];
		yield [ '', '#' ];
		yield [ '', ':' ];
		yield [ '', ':#' ];
		yield [ '', 'Talk:' ];
		yield [ '', 'User talk:#' ];
		yield [ '', 'User:#' ];
	}

	/**
	 * @dataProvider provideSubjectpagename
	 */
	public function testSubjectpagename( $expected, $title ) {
		$parser = $this->getServiceContainer()->getParser();

		$this->assertSame( $expected, CoreParserFunctions::subjectpagename( $parser, $title ) );
	}

}
