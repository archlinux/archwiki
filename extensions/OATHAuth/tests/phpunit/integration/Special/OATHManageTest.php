<?php

/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Special;

use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Extension\OATHAuth\Enforce2FA\Mandatory2FAChecker;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\Special\OATHManage;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Site\MediaWikiSite;
use MediaWiki\Site\SiteLookup;
use MediaWiki\Tests\Specials\SpecialPageTestBase;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @author Taavi Väänänen <hi@taavi.wtf>
 * @group Database
 * @covers \MediaWiki\Extension\OATHAuth\Special\OATHManage
 * @covers \MediaWiki\Extension\OATHAuth\OATHUser
 */
class OATHManageTest extends SpecialPageTestBase {
	use BypassReauthTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		$this->bypassReauthentication();
	}

	protected function newSpecialPage() {
		$services = OATHAuthServices::getInstance( $this->getServiceContainer() );
		return new OATHManage(
			$services->getUserRepository(),
			$services->getModuleRegistry(),
			$services->getMandatory2FAChecker(),
			$this->getServiceContainer()->getAuthManager(),
			$this->getServiceContainer()->getUserGroupManager()
		);
	}

	public function testPageLoadsWithSummary() {
		$user = $this->getTestUser()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		[ $output ] = $this->executeSpecialPage( '', null, null, $user );
		$this->assertStringContainsString( '(oathmanage-summary)', $output );
	}

	public function testTOTPEnableCreationForm() {
		$user = $this->getTestUser()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );
		$request = new FauxRequest(
			[ 'action' => 'enable', 'module' => 'totp' ],
		);

		[ $output ] = $this->executeSpecialPage( '', $request, null, $user );
		$this->assertStringContainsString( 'oathauth-step1', $output );
	}

	public function testTOTPDisableForm() {
		$user = $this->getTestUser()->getUser();
		$context = RequestContext::getMain();
		$context->getRequest()->getSession()->setUser( $user );
		$context->setLanguage( 'qqx' );
		$request = new FauxRequest(
			[ 'action' => 'disable', 'module' => 'totp' ]
		 );

		[ $output ] = $this->executeSpecialPage( '', $request, null, $user );
		$this->assertStringContainsString( 'oathauth-disable-method-warning', $output );
	}

	public function testRecoveryCodeFormRenders() {
		$user = $this->getTestUser()->getUser();
		$context = RequestContext::getMain();
		$context->getRequest()->getSession()->setUser( $user );
		$context->setLanguage( 'qqx' );
		$request = new FauxRequest(
			[ 'module' => 'recoverycodes' ]
		);

		[ $output ] = $this->executeSpecialPage( '', $request, null, $user );
		$this->assertStringContainsString( 'oathauth-recoverycodes-regenerate-warning', $output );
	}

	public function testMaxKeysPerUser() {
		$user = $this->getTestUser();
		$maxTestKeys = 5;
		$this->setMwGlobals( 'wgOATHMaxKeysPerUser', $maxTestKeys );
		$userRepository = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();
		for ( $i = 0; $i < 6; $i++ ) {
			$key = TOTPKey::newFromRandom();
			$userRepository->createKey(
				$userRepository->findByUser( $user->getUserIdentity() ),
				OATHAuthServices::getInstance( $this->getServiceContainer() )
					->getModuleRegistry()
					->getModuleByKey( 'totp' ),
				$key->jsonSerialize(),
				'127.0.0.1'
			);
		}

		$oathUser = $userRepository->findByUser( $user->getUser() );
		$this->assertCount( 6, $oathUser->getKeys() );

		$context = RequestContext::getMain();
		$context->getRequest()->getSession()->setUser( $user->getUser() );
		$request = new FauxRequest(
			[ 'action' => 'enable', 'module' => 'totp' ]
		 );

		$this->expectException( ErrorPageError::class );
		$this->expectExceptionMessage( wfMessage( 'oathauth-max-keys-exceeded-message', $maxTestKeys ) );
		$this->executeSpecialPage( '', $request, null, $user->getUser() );
	}

	public function testPasskeysSectionAllowsAddingPasskeysWhenUserHas2fa() {
		// Setup user + existing TOTP key
		$user = $this->getTestUser()->getUser();
		$userRepo = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();

		$totpKey = TOTPKey::newFromRandom();
		$userRepo->createKey(
			$userRepo->findByUser( $user ),
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getModuleRegistry()
				->getModuleByKey( 'totp' ),
			$totpKey->jsonSerialize(),
			'127.0.0.1'
		);

		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );
		RequestContext::getMain()->setLanguage( 'qqx' );

		[ $output ] = $this->executeSpecialPage( '', null, null, $user );

		$this->assertStringContainsString( '(oathauth-passkeys-header)', $output );
		$this->assertStringContainsString( '(oathauth-passkeys-add)', $output );
		$this->assertStringNotContainsString( '(oathauth-passkeys-no2fa)', $output );
	}

	public function testDeleteLastKeyWithCorrectConfirmation() {
		$user = $this->getTestUser();
		$userRepo = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();

		$key = TOTPKey::newFromRandom();
		$userRepo->createKey(
		$userRepo->findByUser( $user->getUserIdentity() ),
		OATHAuthServices::getInstance( $this->getServiceContainer() )
			->getModuleRegistry()
			->getModuleByKey( 'totp' ),
		$key->jsonSerialize(),
		'127.0.0.1'
		);

		$oathUser = $userRepo->findByUser( $user->getUser() );
		$this->assertCount( 1, $oathUser->getKeys() );
		$keyId = $oathUser->getKeys()[0]->getId();

		$context = RequestContext::getMain();
		$context->setLanguage( 'qqx' );
		$session = $context->getRequest()->getSession();
		$session->setUser( $user->getUser() );

		$confirmText = wfMessage( 'oathauth-authenticator-delete-text' )
		->inLanguage( 'qqx' )
		->text();

		$token = $session->getToken( '' );

		$request = new FauxRequest(
		[
			'action' => 'delete',
			'module' => 'totp',
			'keyId' => $keyId,
			'wpremove-confirm-box' => $confirmText,
			'wpEditToken' => $token,
		],
		true,
		$session
		);

		$this->executeSpecialPage( '', $request, null, $user->getUser() );

		$oathUser = $userRepo->findByUser( $user->getUser() );
		$this->assertCount( 0, $oathUser->getKeys() );
	}

	public function testDeleteLastKeyCreatesCheckUserEntry() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$user = $this->getTestUser();
		$userRepo = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();

		$key = TOTPKey::newFromRandom();
		$userRepo->createKey(
			$userRepo->findByUser( $user->getUserIdentity() ),
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getModuleRegistry()
				->getModuleByKey( 'totp' ),
			$key->jsonSerialize(),
			'127.0.0.1'
		);

		$oathUser = $userRepo->findByUser( $user->getUserIdentity() );
		$keyId = $oathUser->getKeys()[0]->getId();

		$logCountBefore = $this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cu_private_event' )
			->where( [
				'cupe_log_type' => 'oath',
				'cupe_log_action' => 'disable-self',
				'cupe_actor' => $user->getUser()->getActorId(),
			] )
			->fetchField();

		$context = RequestContext::getMain();
		$context->setLanguage( 'qqx' );
		$session = $context->getRequest()->getSession();
		$session->setUser( $user->getUser() );

		$confirmText = wfMessage( 'oathauth-authenticator-delete-text' )
			->inLanguage( 'qqx' )
			->text();

		$request = new FauxRequest(
			[
				'action' => 'delete',
				'module' => 'totp',
				'keyId' => $keyId,
				'wpremove-confirm-box' => $confirmText,
				'wpEditToken' => $session->getToken( '' ),
			],
			true,
			$session
		);

		$this->executeSpecialPage( '', $request, null, $user->getUser() );

		$logCountAfter = $this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cu_private_event' )
			->where( [
				'cupe_log_type' => 'oath',
				'cupe_log_action' => 'disable-self',
				'cupe_actor' => $user->getUser()->getActorId(),
			] )
			->fetchField();

		$this->assertSame(
			(int)$logCountBefore + 1,
			(int)$logCountAfter,
			'A disable-self entry should be created in cu_private_event'
		);
	}

	public function testCreateFirstKeyCreatesCheckUserEntry() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$user = $this->getTestUser();
		$userRepo = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();
		$oathUser = $userRepo->findByUser( $user->getUserIdentity() );

		$logCountBefore = $this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cu_private_event' )
			->where( [
				'cupe_log_type' => 'oath',
				'cupe_log_action' => 'enable-self',
				'cupe_actor' => $user->getUser()->getActorId(),
			] )
			->fetchField();

		$userRepo->createKey(
			$oathUser,
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getModuleRegistry()
				->getModuleByKey( 'totp' ),
			TOTPKey::newFromRandom()->jsonSerialize(),
			'127.0.0.1'
		);

		$logCountAfter = $this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'cu_private_event' )
			->where( [
				'cupe_log_type' => 'oath',
				'cupe_log_action' => 'enable-self',
				'cupe_actor' => $user->getUser()->getActorId(),
			] )
			->fetchField();

		$this->assertSame(
			(int)$logCountBefore + 1,
			(int)$logCountAfter,
			'An enable-self entry should be created in cu_private_event'
		);
	}

	public function testDeleteLastKeyWithWrongConfirmation() {
		$testUser = $this->getTestUser();
		$user = $testUser->getUser();
		$userRepository = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();

		$userRepository->createKey(
		$userRepository->findByUser( $testUser->getUserIdentity() ),
		OATHAuthServices::getInstance( $this->getServiceContainer() )
			->getModuleRegistry()
			->getModuleByKey( 'totp' ),
		TOTPKey::newFromRandom()->jsonSerialize(),
		'127.0.0.1'
		);

		$oathUser = $userRepository->findByUser( $user );
		$this->assertCount( 1, $oathUser->getKeys() );
		$keyId = $oathUser->getKeys()[0]->getId();

		$context = RequestContext::getMain();
		$context->getRequest()->getSession()->setUser( $user );
		$context->setLanguage( 'qqx' );
		$session = $context->getRequest()->getSession();

		$request = new FauxRequest(
		[
			'action' => 'delete',
			'keyId' => $keyId,
			'wpremove-confirm-box' => 'wrong-string',
			'wpEditToken' => $session->getToken( '' ),
		],
		true,
		$session
		);

		[ $output ] = $this->executeSpecialPage( '', $request, null, $user );

		$oathUser = $userRepository->findByUser( $user );
		$this->assertCount( 1, $oathUser->getKeys() );

		$this->assertStringContainsString( 'oathauth-delete-wrong-confirm-message', $output );
	}

	public function testDeleteNonLastKeyWithoutConfirmation() {
		$mandatory2FAChecker = $this->createMock( Mandatory2FAChecker::class );
		$mandatory2FAChecker->method( 'getGroupsRequiring2FAAcrossWikiFarm' )
			->willReturn( [ 'remote-wiki' => [ 'interface-admin' ] ] );
		$this->setService( 'OATHAuth.Mandatory2FAChecker', $mandatory2FAChecker );

		$this->setFakeSiteLookup();

		$user = $this->getTestUser();
		$userRepository = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();
		for ( $i = 0; $i < 2; $i++ ) {
			$key = TOTPKey::newFromRandom();
			$userRepository->createKey(
				$userRepository->findByUser( $user->getUserIdentity() ),
				OATHAuthServices::getInstance( $this->getServiceContainer() )
					->getModuleRegistry()
					->getModuleByKey( 'totp' ),
				$key->jsonSerialize(),
				'127.0.0.1'
			);
		}

		$oathUser = $userRepository->findByUser( $user->getUser() );
		$this->assertCount( 2, $oathUser->getKeys() );
		$keyId = $oathUser->getKeys()[0]->getId();

		$context = RequestContext::getMain();
		$context->getRequest()->getSession()->setUser( $user->getUser() );
		$session = $context->getRequest()->getSession();
		$request = new FauxRequest(
			[
				'action' => 'delete',
				'module' => 'totp',
				'keyId' => $keyId
			],
			true,
			$session
		);

		$this->executeSpecialPage( '', $request, null, $user->getUser() );

		$oathUser = $userRepository->findByUser( $user->getUser() );
		$this->assertCount( 1, $oathUser->getKeys() );
	}

	public function testDeleteLastKeyUnsuccessfulIfUserRequires2FA() {
		$user = $this->getTestUser();
		$userRepo = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();

		$key = TOTPKey::newFromRandom();
		$userRepo->createKey(
			$userRepo->findByUser( $user->getUserIdentity() ),
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getModuleRegistry()
				->getModuleByKey( 'totp' ),
			$key->jsonSerialize(),
			'127.0.0.1'
		);

		$oathUser = $userRepo->findByUser( $user->getUserIdentity() );
		$keyId = $oathUser->getKeys()[0]->getId();

		$mandatory2FAChecker = $this->createMock( Mandatory2FAChecker::class );
		$mandatory2FAChecker->method( 'getGroupsRequiring2FAAcrossWikiFarm' )
			->willReturn( [ 'remote-wiki' => [ 'interface-admin' ] ] );
		$this->setService( 'OATHAuth.Mandatory2FAChecker', $mandatory2FAChecker );

		$this->setFakeSiteLookup();

		$context = RequestContext::getMain();
		$session = $context->getRequest()->getSession();
		$session->setUser( $user->getUser() );

		$confirmText = wfMessage( 'oathauth-authenticator-delete-text' )
			->inLanguage( 'qqx' )
			->text();

		$request = new FauxRequest(
			[
				'action' => 'delete',
				'module' => 'totp',
				'keyId' => $keyId,
				'wpremove-confirm-box' => $confirmText,
				'wpEditToken' => $session->getToken( '' ),
			],
			true,
			$session
		);

		$this->expectExceptionMessage( wfMessage( 'oathauth-remove-lastkey-required' )->text() );
		$this->executeSpecialPage( '', $request, null, $user->getUser() );
	}

	public function testDisplaysIrremovableWarning() {
		$localWiki = WikiMap::getCurrentWikiId();
		$user = $this->getTestUser();
		$userRepo = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();

		$key = TOTPKey::newFromRandom();
		$userRepo->createKey(
			$userRepo->findByUser( $user->getUserIdentity() ),
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getModuleRegistry()
				->getModuleByKey( 'totp' ),
			$key->jsonSerialize(),
			'127.0.0.1'
		);

		$mandatory2FAChecker = $this->createMock( Mandatory2FAChecker::class );
		$mandatory2FAChecker->method( 'getGroupsRequiring2FAAcrossWikiFarm' )
			->willReturn( [
				$localWiki => [ 'interface-admin', 'suppress', 'sysop' ],
				'remote-wiki' => [ 'interface-admin' ]
			] );
		$this->setService( 'OATHAuth.Mandatory2FAChecker', $mandatory2FAChecker );

		$this->setFakeSiteLookup();

		$removalPages = [
			'sysop' => 'SomePage',
			'interface-admin' => 'SomePage',
			'*' => 'DefaultPage'
		];
		$siteConfiguration = $this->createMock( SiteConfiguration::class );
		$siteConfiguration->method( 'get' )
			->willReturnCallback( static function ( $setting ) use ( $removalPages ) {
				if ( $setting === 'wgOATH2FARequiredGroupRemovalPages' ) {
					return $removalPages;
				}
				return null;
			} );

		global $wgConf;
		$wgConf = $siteConfiguration;
		$this->overrideConfigValue( 'OATH2FARequiredGroupRemovalPages', $removalPages );

		$context = RequestContext::getMain();
		$session = $context->getRequest()->getSession();
		$session->setUser( $user->getUser() );

		$request = new FauxRequest(
			[],
			false,
			$session
		);

		[ $html ] = $this->executeSpecialPage( '', $request, null, $user->getUser() );

		$entryCount = substr_count( $html, '(oathauth-2fa-groups-notice-multiple-links-entry:' );
		$this->assertSame( 3, $entryCount, 'There should be 3 entries in the irremovable warning notice' );

		$userName = $user->getUser()->getName();
		$expectedMessages = [
			"(oathauth-2fa-groups-notice-multiple-links-entry: 1, (group-interface-admin-member: $userName)" .
				', remote-wiki.local',
			"(oathauth-2fa-groups-notice-multiple-links-entry: 2, (group-interface-admin-member: $userName)(and)" .
				"(word-separator)(group-sysop-member: $userName), $localWiki.local",
			"(oathauth-2fa-groups-notice-multiple-links-entry: 1, (group-suppress-member: $userName), $localWiki.local",
		];
		foreach ( $expectedMessages as $expected ) {
			$this->assertStringContainsString( $expected, $html );
		}
	}

	public function testDisplays2FARequiredNoticeWhenCurrentWikiNotInWikiMap() {
		$localWiki = WikiMap::getCurrentWikiId();

		$mandatory2FAChecker = $this->createMock( Mandatory2FAChecker::class );
		$mandatory2FAChecker->method( 'getGroupsRequiring2FAAcrossWikiFarm' )
			->willReturn( [ $localWiki => [ 'sysop' ] ] );
		$this->setService( 'OATHAuth.Mandatory2FAChecker', $mandatory2FAChecker );

		// Pretend that the current wiki unresolvable via WikiMap, like on wikis configuring
		// neither $wgConf nor the sites table.
		global $wgConf;
		$wgConf = new SiteConfiguration();
		$siteLookup = $this->createMock( SiteLookup::class );
		$siteLookup->method( 'getSite' )->willReturn( null );
		$this->setService( 'SiteLookup', $siteLookup );
		$this->assertNull( WikiMap::getWiki( $localWiki ) );

		$this->overrideConfigValue( MainConfigNames::Sitename, 'WikiMapless Wiki' );

		$user = $this->getTestUser()->getUser();
		$context = RequestContext::getMain();
		$context->setLanguage( 'qqx' );
		$context->getRequest()->getSession()->setUser( $user );

		[ $html ] = $this->executeSpecialPage( '', null, null, $user );

		$this->assertStringContainsString( '(oathauth-2fa-required)', $html );
		$this->assertStringContainsString( 'WikiMapless Wiki', $html );
	}

	public function testDisplaysTemporaryCodesAccordion() {
		ConvertibleTimestamp::setFakeTime( '20260101000000' );
		$user = $this->getTestUser();
		$userRepo = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();

		$key = RecoveryCodeKeys::newFromArray( [ 'recoverycodekeys' => [
			'TESTCODE',
			[ 'EXPIRINGCODE', [ 'expiry' => '20270101000000' ] ],
		] ] );
		$userRepo->createKey(
			$userRepo->findByUser( $user->getUserIdentity() ),
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getModuleRegistry()
				->getModuleByKey( 'recoverycodes' ),
			$key->jsonSerialize(),
			'127.0.0.1'
		);

		$context = RequestContext::getMain();
		$session = $context->getRequest()->getSession();
		$session->setUser( $user->getUser() );

		$request = new FauxRequest(
			[],
			false,
			$session
		);

		[ $html ] = $this->executeSpecialPage( '', $request, null, $user->getUser() );

		$this->assertStringContainsString( '(oathauth-recoverycodes-temporary: 1)', $html );
	}

	private function setFakeSiteLookup() {
		$siteLookup = $this->createMock( SiteLookup::class );
		$siteLookup->method( 'getSite' )
			->willReturnCallback( function ( $wikiId ) {
				$site = $this->createMock( MediaWikiSite::class );
				$site->method( 'getPageUrl' )->willReturn( 'http://' . $wikiId . '.local/wiki/$1' );
				return $site;
			} );
		$this->setService( 'SiteLookup', $siteLookup );
	}
}
