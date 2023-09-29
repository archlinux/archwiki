<?php

use MediaWiki\Permissions\UltimateAuthority;

/**
 * @author MAbualruz
 * @group Database
 * @covers SpecialContribute
 */
class SpecialContributeTest extends SpecialPageTestBase {
	/** @var string */
	private $pageName = __CLASS__ . 'BlaBlaTest';

	/** @var User */
	private $admin;

	/** @var SpecialContribute */
	private $specialContribute;

	protected function setUp(): void {
		parent::setUp();
		$this->admin = new UltimateAuthority( $this->getTestSysop()->getUser() );
	}

	/**
	 * @covers SpecialContribute::execute
	 */
	public function testExecute() {
		$this->specialContribute = new SpecialContribute();
		list( $html ) = $this->executeSpecialPage(
			$this->admin->getUser()->getName(),
			null,
			'qqx',
			$this->admin,
			true
		);
		$this->assertStringContainsString( '<div class="mw-contribute-wrapper">', $html );
		$this->assertStringContainsString( '<div class="mw-contribute-card-content">', $html );
	}

	public function testIsShowable() {
		$this->specialContribute = new SpecialContribute();
		$this->executeSpecialPage(
			$this->admin->getUser()->getName(),
			null,
			'qqx',
			$this->admin,
			true
		);
		$this->assertFalse( $this->specialContribute->isShowable() );
	}

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage(): SpecialContribute {
		return $this->specialContribute;
	}

}
