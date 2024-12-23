<?php

namespace MediaWiki\Extension\Thanks\Tests\Integration;

use MediaWiki\Request\FauxRequest;
use MediaWiki\Specials\SpecialLog;
use SpecialPageTestBase;

/**
 * @covers \MediaWiki\Extension\Thanks\Hooks
 * @group Database
 */
class ThanksSpecialLogTest extends SpecialPageTestBase {

	public function testDoNotShowThanksLinkToTempUsersOnSpecialLog() {
		// Create a logged event that can be thanked.
		$this->editPage( 'Test', 'Foo', '', NS_MAIN, $this->getMutableTestUser()->getUser() );
		$tempUser = $this->getServiceContainer()->getTempUserCreator()->create(
			'~2024-1', new FauxRequest()
		)->getUser();
		// Load Special:Log as a temp user, check that the thanks link doesn't appear.
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest(),
			null,
			$tempUser
		);
		$this->assertStringNotContainsString( 'mw-thanks-thank-link', $html );
		// Load Special:Log as an anon user, check that the thanks link doesn't appear.
		[ $html ] = $this->executeSpecialPage();
		$this->assertStringNotContainsString( 'mw-thanks-thank-link', $html );
		// Load Special:Log as a named user, check that the thanks link does appear.
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest(),
			null,
			$this->getMutableTestUser()->getUser()
		);
		$this->assertStringContainsString( 'mw-thanks-thank-link', $html );
	}

	protected function newSpecialPage() {
		$services = $this->getServiceContainer();
		return new SpecialLog(
			$services->getLinkBatchFactory(),
			$services->getConnectionProvider(),
			$services->getActorNormalization(),
			$services->getUserIdentityLookup(),
			$services->getUserNameUtils(),
			$services->getLogFormatterFactory()
		);
	}
}
