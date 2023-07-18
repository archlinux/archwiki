<?php

/**
 * @group Database
 * @covers SpecialRenameuser
 * @covers \MediaWiki\RenameUser\RenameuserSQL
 */
class SpecialRenameuserTest extends SpecialPageTestBase {
	protected function newSpecialPage() {
		$services = $this->getServiceContainer();
		return new SpecialRenameuser(
			$services->getDBLoadBalancerFactory(),
			$services->getContentLanguage(),
			$services->getMovePageFactory(),
			$services->getPermissionManager(),
			$services->getTitleFactory(),
			$services->getUserFactory(),
			$services->getUserNamePrefixSearch()
		);
	}

	public static function provideRenameAndMove() {
		return [
			'no move' => [ false, false ],
			'normal move' => [ true, false ],
			'suppress redirect' => [ true, true ]
		];
	}

	/**
	 * @dataProvider provideRenameAndMove
	 * @param bool $movePages
	 * @param bool $suppressRedirects
	 */
	public function testRenameAndMove( $movePages, $suppressRedirects ) {
		$userFactory = $this->getServiceContainer()->getUserFactory();
		$titleFactory = $this->getServiceContainer()->getTitleFactory();

		$performer = $this->getTestSysop()->getUser();
		$oldUser = $this->getTestUser()->getUser();
		$oldName = $oldUser->getName();
		$newName = $oldName . ' new';
		$oldPage = $oldUser->getUserPage();
		$oldTalkPage = $oldUser->getTalkPage();
		$this->editPage( $oldPage, 'user page' );
		$this->editPage( $oldPage->getSubpage( 'subpage' ), 'subpage' );
		$this->editPage( $oldTalkPage, 'user talk page' );

		$formData = [
			'token' => $performer->getEditToken(),
			'oldusername' => $oldName,
			'newusername' => $newName,
			'reason' => 'r',
		];
		if ( $movePages ) {
			$formData['movepages'] = '1';
		}
		if ( $suppressRedirects ) {
			$formData['suppressredirect'] = '1';
		}

		$this->executeSpecialPage(
			'',
			new FauxRequest( $formData, true ),
			null,
			$performer
		);

		$this->assertTrue( $userFactory->newFromName( $newName )->isRegistered(),
			'new user exists' );
		$this->assertSame(
			$movePages,
			$titleFactory->makeTitle( NS_USER, $newName )->exists(),
			'new user page exists'
		);
		$this->assertSame(
			$movePages,
			$titleFactory->makeTitle( NS_USER, "$newName/subpage" )->exists(),
			'new user subpage exists'
		);
		$this->assertSame(
			$movePages,
			$titleFactory->makeTitle( NS_USER_TALK, "$newName" )->exists(),
			'new user talk page exists'
		);

		$oldPage->resetArticleID( false );
		$this->assertSame( !$suppressRedirects, $oldPage->exists() );
	}
}
