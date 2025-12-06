<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\ChangeTags;

use Generator;
use MediaWiki\Extension\AbuseFilter\ActionSpecifier;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagger;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagsManager;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @group Test
 * @group AbuseFilter
 * @covers \MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagger
 */
class ChangeTaggerTest extends MediaWikiUnitTestCase {

	private function getTagger(): ChangeTagger {
		$manager = $this->createMock( ChangeTagsManager::class );
		$manager->method( 'getCondsLimitTag' )->willReturn( 'tag' );
		return new ChangeTagger( $manager );
	}

	private function prepareMocks( array $actionSpecs, array $rcAttribs ): array {
		$rc = $this->createMock( RecentChange::class );
		$rc->method( 'getAttribute' )->willReturnCallback(
			static function ( $name ) use ( $rcAttribs ) {
				return $rcAttribs[$name];
			}
		);
		$actionSpecifier = new ActionSpecifier(
			$actionSpecs['action'],
			$actionSpecs['target'],
			new UserIdentityValue( 42, $actionSpecs['username'] ),
			$actionSpecs['ip'],
			$actionSpecs['accountname'] ?? null
		);
		return [ $actionSpecifier, $rc ];
	}

	public static function provideActionData(): Generator {
		$titleText = 'FOO';
		$title = new TitleValue( NS_MAIN, $titleText );
		$userName = 'Foobar';
		$baseAttribs = [
			'rc_namespace' => NS_MAIN,
			'rc_title' => $titleText,
			'rc_user' => 42,
			'rc_user_text' => $userName,
			'rc_ip' => '127.0.0.1',
		];
		$baseSpecs = [ 'username' => $userName, 'target' => $title, 'ip' => '127.0.0.1' ];

		$rcAttribs = [ 'rc_log_type' => null ] + $baseAttribs;
		yield 'edit' => [
			'specifier' => [ 'action' => 'edit' ] + $baseSpecs,
			'recentchange' => $rcAttribs
		];

		$rcAttribs = [ 'rc_log_type' => 'newusers', 'rc_log_action' => 'create2' ] + $baseAttribs;
		yield 'createaccount' => [
			'specifier' => [ 'action' => 'createaccount', 'accountname' => $userName ] + $baseSpecs,
			'recentchange' => $rcAttribs
		];

		$rcAttribs = [ 'rc_log_type' => 'newusers', 'rc_log_action' => 'autocreate' ] + $baseAttribs;
		yield 'autocreate' => [
			'specifier' => [ 'action' => 'autocreateaccount', 'accountname' => $userName ] + $baseSpecs,
			'recentchange' => $rcAttribs
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		$this->getTagger()->clearBuffer();
	}

	/**
	 * @dataProvider provideActionData
	 */
	public function testTagsToSetWillNotContainDuplicates( array $actionSpecs, array $rcAttribs ) {
		[ $specifier, $rc ] = $this->prepareMocks( $actionSpecs, $rcAttribs );
		$tagger = $this->getTagger();

		$iterations = 3;
		while ( $iterations-- ) {
			$tagger->addTags( $specifier, [ 'uniqueTag' ] );
			$this->assertSame( [ 'uniqueTag' ], $tagger->getTagsForRecentChange( $rc ) );
		}
	}

	/**
	 * @dataProvider provideActionData
	 */
	public function testClearBuffer( array $actionSpecs, array $rcAttribs ) {
		[ $specifier, $rc ] = $this->prepareMocks( $actionSpecs, $rcAttribs );
		$tagger = $this->getTagger();

		$tagger->addTags( $specifier, [ 'a', 'b', 'c' ] );
		$tagger->clearBuffer();
		$this->assertSame( [], $tagger->getTagsForRecentChange( $rc ) );
	}

	/**
	 * @dataProvider provideActionData
	 */
	public function testAddConditionsLimitTag( array $actionSpecs, array $rcAttribs ) {
		[ $specifier, $rc ] = $this->prepareMocks( $actionSpecs, $rcAttribs );
		$tagger = $this->getTagger();

		$tagger->addConditionsLimitTag( $specifier );
		$this->assertCount( 1, $tagger->getTagsForRecentChange( $rc ) );
	}

	/**
	 * @dataProvider provideActionData
	 */
	public function testAddGetTags( array $actionSpecs, array $rcAttribs ) {
		[ $specifier, $rc ] = $this->prepareMocks( $actionSpecs, $rcAttribs );
		$tagger = $this->getTagger();

		$expected = [ 'foo', 'bar', 'baz' ];
		$tagger->addTags( $specifier, $expected );
		$this->assertSame( $expected, $tagger->getTagsForRecentChange( $rc ) );
	}

	/**
	 * @dataProvider provideActionData
	 */
	public function testAddTags_multiple( array $actionSpecs, array $rcAttribs ) {
		[ $specifier, $rc ] = $this->prepareMocks( $actionSpecs, $rcAttribs );
		$tagger = $this->getTagger();

		$expected = [ 'foo', 'bar', 'baz' ];
		foreach ( $expected as $tag ) {
			$tagger->addTags( $specifier, [ $tag ] );
		}
		$this->assertSame( $expected, $tagger->getTagsForRecentChange( $rc ) );
	}

	/**
	 * @dataProvider provideActionData
	 */
	public function testGetTags_clear( array $actionSpecs, array $rcAttribs ) {
		[ $specifier, $rc ] = $this->prepareMocks( $actionSpecs, $rcAttribs );
		$tagger = $this->getTagger();

		$expected = [ 'foo', 'bar', 'baz' ];
		$tagger->addTags( $specifier, $expected );

		$tagger->getTagsForRecentChange( $rc, false );
		$this->assertSame( $expected, $tagger->getTagsForRecentChange( $rc ), 'no clear' );
		$tagger->getTagsForRecentChange( $rc );
		$this->assertSame( [], $tagger->getTagsForRecentChange( $rc ), 'clear' );
	}
}
