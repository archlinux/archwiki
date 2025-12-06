<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Hooks;

use MediaWiki\Content\Content;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\Hooks\Handlers\ConfirmEditHandler;
use MediaWiki\Extension\ConfirmEdit\AbuseFilter\CaptchaConsequence;
use MediaWiki\Extension\ConfirmEdit\CaptchaTriggers;
use MediaWiki\Extension\ConfirmEdit\Hooks;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Hooks\Handlers\ConfirmEditHandler
 * @group Database
 */
class ConfirmEditHandlerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'ConfirmEdit' );
	}

	protected function tearDown(): void {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'ConfirmEdit' ) ) {
			Hooks::getInstance()->setForceShowCaptcha( false );
		}
		parent::tearDown();
	}

	public function testOnEditFilterMergedContent() {
		$this->markTestSkipped( 'Can be unskipped after I71d9894a908469c5f4e191ce36406b9e1a8113b2' );
		$this->overrideConfigValue(
			'CaptchaTriggers',
			[ 'edit' => [
				'trigger' => true,
				'class' => 'QuestyCaptcha',
			] ]
		);
		$this->editPage( 'Test', 'Foo' );
		$confirmEditHandler = new ConfirmEditHandler();
		$status = Status::newGood();
		$title = $this->getServiceContainer()->getTitleFactory()->newFromText( 'Test' );
		$context = RequestContext::getMain();
		$context->setTitle( $title );
		$confirmEditHandler->onEditFilterMergedContent(
			$context,
			$this->createMock( Content::class ),
			$status,
			'',
			$this->createMock( User::class ),
			false
		);
		$this->assertStatusGood( $status, 'The default is to not show a CAPTCHA' );

		$simpleCaptcha = Hooks::getInstance( CaptchaTriggers::EDIT );
		$simpleCaptcha->setEditFilterMergedContentHandlerInvoked();
		$simpleCaptcha->setAction( CaptchaTriggers::EDIT );
		$parameters = $this->createMock( Parameters::class );
		$parameters->method( 'getAction' )->willReturn( 'edit' );
		$captchaConsequence = new CaptchaConsequence( $parameters );
		$captchaConsequence->execute();
		$confirmEditHandler->onEditFilterMergedContent(
			$context,
			$this->createMock( Content::class ),
			$status,
			'',
			$this->createMock( User::class ),
			false
		);

		$this->assertStatusError( 'captcha-edit', $status );
	}
}
