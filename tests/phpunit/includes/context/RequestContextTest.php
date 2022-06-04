<?php

use MediaWiki\Actions\ActionFactory;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\User\UserIdentityValue;

/**
 * @covers RequestContext
 * @group Database
 * @group RequestContext
 */
class RequestContextTest extends MediaWikiIntegrationTestCase {

	/**
	 * Test the relationship between title and wikipage in RequestContext
	 * @covers RequestContext::getWikiPage
	 * @covers RequestContext::getTitle
	 */
	public function testWikiPageTitle() {
		$context = new RequestContext();

		$curTitle = Title::newFromText( "A" );
		$context->setTitle( $curTitle );
		$this->assertTrue( $curTitle->equals( $context->getWikiPage()->getTitle() ),
			"When a title is first set WikiPage should be created on-demand for that title." );

		$curTitle = Title::newFromText( "B" );
		$context->setWikiPage( WikiPage::factory( $curTitle ) );
		$this->assertTrue( $curTitle->equals( $context->getTitle() ),
			"Title must be updated when a new WikiPage is provided." );

		$curTitle = Title::newFromText( "C" );
		$context->setTitle( $curTitle );
		$this->assertTrue(
			$curTitle->equals( $context->getWikiPage()->getTitle() ),
			"When a title is updated the WikiPage should be purged "
				. "and recreated on-demand with the new title."
		);
	}

	/**
	 * @covers RequestContext::importScopedSession
	 */
	public function testImportScopedSession() {
		// Make sure session handling is started
		if ( !MediaWiki\Session\PHPSessionHandler::isInstalled() ) {
			MediaWiki\Session\PHPSessionHandler::install(
				MediaWiki\Session\SessionManager::singleton()
			);
		}
		$oldSessionId = session_id();

		$context = RequestContext::getMain();

		$oInfo = $context->exportSession();
		$this->assertEquals( '127.0.0.1', $oInfo['ip'], "Correct initial IP address." );
		$this->assertSame( 0, $oInfo['userId'], "Correct initial user ID." );
		$this->assertFalse( MediaWiki\Session\SessionManager::getGlobalSession()->isPersistent(),
			'Global session isn\'t persistent to start' );

		$user = User::newFromName( 'UnitTestContextUser' );
		$user->addToDatabase();

		$sinfo = [
			'sessionId' => 'd612ee607c87e749ef14da4983a702cd',
			'userId' => $user->getId(),
			'ip' => '192.0.2.0',
			'headers' => [
				'USER-AGENT' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:18.0) Gecko/20100101 Firefox/18.0'
			]
		];
		// importScopedSession() sets these variables
		$this->setMwGlobals( [
			'wgRequest' => new FauxRequest,
		] );
		$sc = RequestContext::importScopedSession( $sinfo ); // load new context

		$info = $context->exportSession();
		$this->assertEquals( $sinfo['ip'], $info['ip'], "Correct IP address." );
		$this->assertEquals( $sinfo['headers'], $info['headers'], "Correct headers." );
		$this->assertEquals( $sinfo['sessionId'], $info['sessionId'], "Correct session ID." );
		$this->assertEquals( $sinfo['userId'], $info['userId'], "Correct user ID." );
		$this->assertEquals(
			$sinfo['ip'],
			$context->getRequest()->getIP(),
			"Correct context IP address."
		);
		$this->assertEquals(
			$sinfo['headers'],
			$context->getRequest()->getAllHeaders(),
			"Correct context headers."
		);
		$this->assertEquals(
			$sinfo['sessionId'],
			MediaWiki\Session\SessionManager::getGlobalSession()->getId(),
			"Correct context session ID."
		);
		if ( \MediaWiki\Session\PHPSessionHandler::isEnabled() ) {
			$this->assertEquals( $sinfo['sessionId'], session_id(), "Correct context session ID." );
		} else {
			$this->assertEquals( $oldSessionId, session_id(), "Unchanged PHP session ID." );
		}
		$this->assertTrue( $context->getUser()->isRegistered(), "Correct context user." );
		$this->assertEquals( $sinfo['userId'], $context->getUser()->getId(), "Correct context user ID." );
		$this->assertEquals(
			'UnitTestContextUser',
			$context->getUser()->getName(),
			"Correct context user name."
		);

		unset( $sc ); // restore previous context

		$info = $context->exportSession();
		$this->assertEquals( $oInfo['ip'], $info['ip'], "Correct restored IP address." );
		$this->assertEquals( $oInfo['headers'], $info['headers'], "Correct restored headers." );
		$this->assertEquals( $oInfo['sessionId'], $info['sessionId'], "Correct restored session ID." );
		$this->assertEquals( $oInfo['userId'], $info['userId'], "Correct restored user ID." );
		$this->assertFalse( MediaWiki\Session\SessionManager::getGlobalSession()->isPersistent(),
			'Global session isn\'t persistent after restoring the context' );
	}

	/**
	 * @covers RequestContext::getUser
	 * @covers RequestContext::setUser
	 * @covers RequestContext::getAuthority
	 * @covers RequestContext::setAuthority
	 */
	public function testTestGetSetAuthority() {
		$context = new RequestContext();

		$user = $this->getTestUser()->getUser();

		$context->setUser( $user );
		$this->assertTrue( $user->equals( $context->getAuthority()->getUser() ) );
		$this->assertTrue( $user->equals( $context->getUser() ) );

		$authorityActor = new UserIdentityValue( 42, 'Test' );
		$authority = new UltimateAuthority( $authorityActor );

		$context->setAuthority( $authority );
		$this->assertTrue( $context->getUser()->equals( $authorityActor ) );
		$this->assertTrue( $context->getAuthority()->getUser()->equals( $authorityActor ) );
	}

	/**
	 * @covers RequestContext
	 */
	public function testGetActionName() {
		$factory = $this->createMock( ActionFactory::class );
		$factory
			// Assert calling only once.
			// Determining the action name is an expensive operation that
			// must be cached by the context, as it involved database and hooks.
			->expects( $this->once() )
			->method( 'getActionName' )
			->willReturn( 'foo' );
		$this->setService( 'ActionFactory', $factory );

		$context = new RequestContext();
		$this->assertSame( 'foo', $context->getActionName(), 'value from factory' );
		$this->assertSame( 'foo', $context->getActionName(), 'cached' );
	}

	/**
	 * @covers RequestContext
	 */
	public function testSetActionName() {
		$factory = $this->createMock( ActionFactory::class );
		$factory
			->expects( $this->never() )
			->method( 'getActionName' );
		$this->setService( 'ActionFactory', $factory );

		$context = new RequestContext();
		$context->setActionName( 'fixed' );
		$this->assertSame( 'fixed', $context->getActionName() );
	}

	/**
	 * @covers RequestContext
	 */
	public function testOverideActionName() {
		$factory = $this->createMock( ActionFactory::class );
		$factory
			->method( 'getActionName' )
			->willReturnOnConsecutiveCalls( 'aaa', 'bbb' );
		$this->setService( 'ActionFactory', $factory );

		$context = new RequestContext();
		$this->assertSame( 'aaa', $context->getActionName(), 'first from factory' );
		$this->assertSame( 'aaa', $context->getActionName(), 'cached first' );

		$context->setTitle( $this->createMock( Title::class ) );
		$this->assertSame( 'bbb', $context->getActionName(), 'second from factory' );
		$this->assertSame( 'bbb', $context->getActionName(), 'cached second' );
	}
}
