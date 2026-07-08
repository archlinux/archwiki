<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Integration\hCaptcha;

use MediaWiki\Content\ContentHandler;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\HCaptcha;
use MediaWiki\Http\MWHttpRequest;
use MediaWiki\Json\FormatJson;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Status\Status;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\hCaptcha\HCaptcha
 * @group Database
 */
class HCaptchaDatabaseTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;

	public function testConfirmEditMergedWhenForceCaptchaSet() {
		$this->clearHook( 'ConfirmEditCanUserSkipCaptcha' );
		$this->setTemporaryHook( 'ConfirmEditTriggersCaptcha', static function ( $action, $title, &$result ) {
			$result = true;
		} );

		// Mock the site-verify URL call to respond with a successful response
		// to indicate the captcha did not pass
		$mwHttpRequest = $this->createMock( MWHttpRequest::class );
		$mwHttpRequest->method( 'execute' )
			->willReturn( Status::newGood() );
		$mwHttpRequest->method( 'getStatus' )
			->willReturn( 200 );
		$mwHttpRequest->method( 'getContent' )
			->willReturn( FormatJson::encode( [ 'success' => false ] ) );
		$this->installMockHttp( $mwHttpRequest );

		// Set up the request and context such that no captcha word is provided,
		// so that the captcha check will fail
		$user = $this->getTestUser()->getUser();
		$title = $this->getNonexistingTestPage()->getTitle();
		$status = Status::newGood();
		$context = RequestContext::getMain();
		$context->setUser( $user );
		$context->setRequest( new FauxRequest( [ 'captchaword' => 'abcdef' ] ) );
		$context->setTitle( $title );

		$simpleCaptcha = new HCaptcha();
		$simpleCaptcha->setForceShowCaptcha( true );

		$this->assertFalse( $simpleCaptcha->confirmEditMerged(
			$context, ContentHandler::makeContent( '', $title ), $status, '', $user, false
		) );
		$this->assertStatusError( 'hcaptcha-force-show-captcha-edit', $status );
	}
}
