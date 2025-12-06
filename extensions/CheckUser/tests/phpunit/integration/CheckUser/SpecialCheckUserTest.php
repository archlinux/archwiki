<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser;

use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetActionsPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager;
use MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager;
use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWiki\CheckUser\Tests\SpecialCheckUserTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Html\FormOptions;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use SpecialPageTestBase;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\TestingAccessWrapper;

/**
 * Test class for SpecialCheckUser class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUser\SpecialCheckUser
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetActionsPager
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetIPsPager
 */
class SpecialCheckUserTest extends SpecialPageTestBase {

	use MockAuthorityTrait;
	use SpecialCheckUserTestTrait;
	use SuggestedInvestigationsTestTrait;
	use TempUserTestTrait;

	private static UserIdentity $usernameTarget;
	private static UserIdentity $tempAccountTarget;

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'CheckUser' );
	}

	/**
	 * Gets a test user with the checkuser group and also assigns that user as the user for the main request context.
	 *
	 * @return User
	 */
	private function getTestCheckUser(): User {
		$testCheckUser = $this->getTestUser( [ 'checkuser' ] )->getUser();
		RequestContext::getMain()->setUser( $testCheckUser );
		return $testCheckUser;
	}

	/** @return TestingAccessWrapper */
	protected function setUpObject() {
		$this->getTestCheckUser();
		$object = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'CheckUser' );
		$testingWrapper = TestingAccessWrapper::newFromObject( $object );
		$testingWrapper->opts = new FormOptions();
		return $testingWrapper;
	}

	/** @dataProvider provideGetPager */
	public function testGetPager( $checkType, $userIdentity, $xfor = null ) {
		$object = $this->setUpObject();
		$object->opts->add( 'limit', 0 );
		$object->opts->add( 'reason', '' );
		$object->opts->add( 'period', 0 );
		if ( $checkType === SpecialCheckUser::SUBTYPE_GET_IPS ) {
			$this->assertInstanceOf( CheckUserGetIPsPager::class,
				$object->getPager( $checkType, $userIdentity, 'untested', $xfor ),
				'The Get IPs checktype should return the Get IPs pager.'
			);
		} elseif ( $checkType === SpecialCheckUser::SUBTYPE_GET_ACTIONS ) {
			$this->assertInstanceOf( CheckUserGetActionsPager::class,
				$object->getPager( $checkType, $userIdentity, 'untested', $xfor ),
				'The Get actions checktype should return the Get actions pager.'
			);
		} elseif ( $checkType === SpecialCheckUser::SUBTYPE_GET_USERS ) {
			$this->assertInstanceOf( CheckUserGetUsersPager::class,
				$object->getPager( $checkType, $userIdentity, 'untested', $xfor ),
				'The Get users checktype should return the Get users pager.'
			);
		} else {
			$this->assertNull(
				$object->getPager( $checkType, $userIdentity, 'untested' ),
				'An unrecognised check type should return no pager.'
			);
		}
	}

	public static function provideGetPager() {
		return [
			'Get IPs checktype' =>
				[ SpecialCheckUser::SUBTYPE_GET_IPS, UserIdentityValue::newRegistered( 1, 'test' ) ],
			'Get actions checktype with a registered user' =>
				[ SpecialCheckUser::SUBTYPE_GET_ACTIONS, UserIdentityValue::newRegistered( 1, 'test' ) ],
			'Get actions checktype with a IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_ACTIONS, UserIdentityValue::newAnonymous( '127.0.0.1' ), false ],
			'Get actions checktype with a XFF IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_ACTIONS, UserIdentityValue::newAnonymous( '127.0.0.1' ), true ],
			'Get users checktype with a IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_USERS, UserIdentityValue::newAnonymous( '127.0.0.1' ), false ],
			'Get users checktype with a XFF IP' =>
				[ SpecialCheckUser::SUBTYPE_GET_USERS, UserIdentityValue::newAnonymous( '127.0.0.1' ), true ],
			'An invalid checktype' => [ '', UserIdentityValue::newRegistered( 1, 'test' ) ],
		];
	}

	/**
	 * @dataProvider provideRequiredGroupAccess
	 */
	public function testRequiredRightsByGroup( $groups, $allowed ) {
		$checkUserLog = $this->getServiceContainer()->getSpecialPageFactory()
			->getPage( 'CheckUser' );
		if ( $checkUserLog === null ) {
			$this->fail( 'CheckUser special page does not exist' );
		}
		$requiredRight = $checkUserLog->getRestriction();
		if ( !is_array( $groups ) ) {
			$groups = [ $groups ];
		}
		$rightsGivenInGroups = $this->getServiceContainer()->getGroupPermissionsLookup()
			->getGroupPermissions( $groups );
		if ( $allowed ) {
			$this->assertContains(
				$requiredRight,
				$rightsGivenInGroups,
				'Groups/rights given to the test user should allow it to access CheckUser.'
			);
		} else {
			$this->assertNotContains(
				$requiredRight,
				$rightsGivenInGroups,
				'Groups/rights given to the test user should not include access to CheckUser.'
			);
		}
	}

	public static function provideRequiredGroupAccess() {
		return [
			'No user groups' => [ '', false ],
			'Checkuser only' => [ 'checkuser', true ],
			'Checkuser and sysop' => [ [ 'checkuser', 'sysop' ], true ],
		];
	}

	/**
	 * @dataProvider provideRequiredRights
	 */
	public function testRequiredRights( $groups, $allowed ) {
		if ( ( is_array( $groups ) && isset( $groups['checkuser-log'] ) ) || $groups === "checkuser-log" ) {
			$this->setGroupPermissions(
				[ 'checkuser-log' => [ 'checkuser-log' => true, 'read' => true ] ]
			);
		}
		$this->testRequiredRightsByGroup( $groups, $allowed );
	}

	public static function provideRequiredRights() {
		return [
			'No user groups' => [ '', false ],
			'checkuser right only' => [ 'checkuser', true ],
		];
	}

	public function testLoadSpecialPageWhenMissingRequiredRight() {
		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage();
	}

	/**
	 * Expects that one element exists in the provided HTML with the given ID and then returns the
	 * HTML inside the element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $id The ID to search for, excluding the "#" character
	 * @return string
	 */
	private function assertAndGetByElementId( string $html, string $id ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::getElementById( $specialPageDocument, $id );
		$this->assertNotNull( $element, "Could not find element with ID $id in $html" );
		return DOMCompat::getInnerHTML( $element );
	}

	/**
	 * Expects that one element exists with the given class inside the provided HTML and then returns
	 * the HTML inside that element
	 *
	 * @param string $html The HTML to search through
	 * @param string $class The CSS class to search for, excluding the "." character
	 * @return string
	 */
	private function assertAndGetByElementClass( string $html, string $class ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, '.' . $class );
		$this->assertCount( 1, $element, "Could not find only one element with CSS class $class in $html" );
		return DOMCompat::getOuterHTML( $element[0] );
	}

	/**
	 * Verifies that the form fields are present for the Special:CheckUser search form
	 */
	private function commonVerifyFormFieldsPresent( string $html ): void {
		$formHtml = $this->assertAndGetByElementId( $html, 'checkuserform' );

		$this->assertStringContainsString( '(checkuser-target', $formHtml );
		$this->assertStringContainsString( '(checkuser-period', $formHtml );
		$this->assertStringContainsString( '(checkuser-reason', $formHtml );
		$this->assertStringContainsString( '(checkuser-ips', $formHtml );
		$this->assertStringContainsString( '(checkuser-actions', $formHtml );
		$this->assertStringContainsString( '(checkuser-users', $formHtml );
		$this->assertStringContainsString( '(checkuser-check', $formHtml );
	}

	/**
	 * Verifies that the CIDR form is shown on the page
	 */
	private function verifyCidrFormExists( string $html ): void {
		$cidrFormHtml = $this->assertAndGetByElementClass( $html, 'mw-checkuser-cidrform' );
		$this->assertStringContainsString( '(checkuser-cidr-label', $cidrFormHtml );
	}

	public function testLoadSpecialPageBeforeFormSubmission() {
		// Execute the special page. We need the full HTML to verify the subtitle links.
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest(), null, $this->getTestCheckUser(), true );

		$this->commonVerifyFormFieldsPresent( $html );
		$this->verifyCidrFormExists( $html );

		// Assert that the "Try out Special:Investigate" link is present
		$this->assertStringContainsString( '(checkuser-link-investigate-label', $html );
		// Assert that the normal subtitle links are present (those without a specific target)
		$this->assertStringContainsString( '(checkuser-show-investigate', $html );
		$this->assertStringContainsString( '(checkuser-showlog', $html );
		// Verify that the summary text is present
		$this->assertStringContainsString( '(checkuser-summary', $html );
	}

	public function testSubmitFormForGetIPsCheckWithResults() {
		$request = new FauxRequest( [ 'checktype' => 'subuserips', 'reason' => 'Test check' ], true );
		$testCheckUser = $this->getTestCheckUser();
		[ $html ] = $this->executeSpecialPage( self::$usernameTarget->getName(), $request, null, $testCheckUser );

		$this->commonVerifyFormFieldsPresent( $html );
		$this->verifyCidrFormExists( $html );

		// Verify that the CheckUser helper fieldset is not present for a Get IPs check
		$this->assertCount(
			0,
			DOMCompat::querySelectorAll( DOMUtils::parseHTML( $html ), '.mw-checkuser-helper-fieldset' ),
			'The CheckUserHelper fieldset should not be present for a "Get IPs" check'
		);

		// Verify that the results contain the IP '1.2.3.4'
		$resultHtml = $this->assertAndGetByElementClass( $html, 'mw-checkuser-get-ips-results' );
		$this->assertStringContainsString( '1.2.3.4', $resultHtml );

		$this->verifyCheckUserLogEntryCreated(
			$testCheckUser, 'Test check', self::$usernameTarget->getName(), 'userips'
		);
	}

	/** @dataProvider provideSubmitFormForGetActionsCheckWithResults */
	public function testSubmitFormForGetActionsCheckWithResults( $tempAccountsHidden ) {
		// We need to set a title in the RequestContext because the HTMLFieldsetCheckUser (used to make the
		// checkuser helper) uses HTMLForm code which unconditionally uses the RequestContext title.
		RequestContext::getMain()->setTitle( SpecialPage::getTitleFor( 'CheckUser' ) );

		$testCheckUser = $this->getTestCheckUser();
		$request = new FauxRequest(
			[
				'checktype' => 'subactions', 'reason' => 'Test check',
				'wpHideTemporaryAccounts' => $tempAccountsHidden,
			],
			true
		);
		[ $html ] = $this->executeSpecialPage( '1.2.3.4', $request, null, $testCheckUser );

		$this->commonVerifyFormFieldsPresent( $html );
		$this->verifyCidrFormExists( $html );
		$this->assertAndGetByElementClass( $html, 'mw-checkuser-helper-fieldset' );

		// Verify that the results contain the IP '1.2.3.4' and target username
		$resultHtml = $this->assertAndGetByElementClass( $html, 'mw-checkuser-get-actions-results' );
		$this->assertStringContainsString( '1.2.3.4', $resultHtml );
		$this->assertStringContainsString( self::$usernameTarget->getName(), $resultHtml );

		// Verify the temporary account edit is shown or not shown, depending on the state of the filters
		if ( $tempAccountsHidden ) {
			$this->assertStringNotContainsString( self::$tempAccountTarget->getName(), $resultHtml );
		} else {
			$this->assertStringContainsString( self::$tempAccountTarget->getName(), $resultHtml );
		}

		$this->verifyCheckUserLogEntryCreated( $testCheckUser, 'Test check', '1.2.3.4', 'ipedits' );
	}

	public static function provideSubmitFormForGetActionsCheckWithResults(): array {
		return [
			'Temporary accounts hidden' => [ true ],
			'Temporary accounts not hidden' => [ false ],
		];
	}

	public function testSubmitFormForGetActionsCheckOnUsernameWithResults() {
		// We need to set a title in the RequestContext because the HTMLFieldsetCheckUser (used to make the
		// checkuser helper) uses HTMLForm code which unconditionally uses the RequestContext title.
		RequestContext::getMain()->setTitle( SpecialPage::getTitleFor( 'CheckUser' ) );

		$testCheckUser = $this->getTestCheckUser();
		$request = new FauxRequest( [ 'checktype' => 'subactions', 'reason' => 'Test check' ], true );
		[ $html ] = $this->executeSpecialPage( self::$usernameTarget->getName(), $request, null, $testCheckUser );

		$this->commonVerifyFormFieldsPresent( $html );
		$this->verifyCidrFormExists( $html );
		$this->assertAndGetByElementClass( $html, 'mw-checkuser-helper-fieldset' );

		// Verify that the results contain the IP '1.2.3.4' and target username
		$resultHtml = $this->assertAndGetByElementClass( $html, 'mw-checkuser-get-actions-results' );
		$this->assertStringContainsString( '1.2.3.4', $resultHtml );
		$this->assertStringContainsString( self::$usernameTarget->getName(), $resultHtml );

		// Verify the temporary account edit is not shown, as the check was on a specific user and not their IP
		$this->assertStringNotContainsString( self::$tempAccountTarget->getName(), $resultHtml );

		$this->verifyCheckUserLogEntryCreated(
			$testCheckUser, 'Test check', self::$usernameTarget->getName(), 'useredits'
		);
	}

	/** @dataProvider provideLinkToSuggestedInvestigationsPresent */
	public function testLinkToSuggestedInvestigationsPresent(
		bool $enabled, bool $hidden, bool $linkExpected
	) {
		if ( $enabled ) {
			$this->enableSuggestedInvestigations();
		} else {
			$this->disableSuggestedInvestigations();
		}
		if ( $hidden ) {
			$this->hideSuggestedInvestigations();
		} else {
			$this->unhideSuggestedInvestigations();
		}

		[ $html ] = $this->executeSpecialPage( '', new FauxRequest(), null, $this->getTestCheckUser(), true );

		if ( $linkExpected ) {
			$this->assertStringContainsString( '(checkuser-show-suggestedinvestigations', $html );
		} else {
			$this->assertStringNotContainsString( '(checkuser-show-suggestedinvestigations', $html );
		}
	}

	public static function provideLinkToSuggestedInvestigationsPresent() {
		return [
			'Feature disabled, not hidden' => [
				'enabled' => false,
				'hidden' => false,
				'linkExpected' => false,
			],
			'Feature enabled, not hidden' => [
				'enabled' => true,
				'hidden' => false,
				'linkExpected' => true,
			],
			'Feature disabled, hidden' => [
				'enabled' => false,
				'hidden' => true,
				'linkExpected' => false,
			],
			'Feature enabled, hidden' => [
				'enabled' => true,
				'hidden' => true,
				'linkExpected' => false,
			],
		];
	}

	/** @dataProvider provideSubmitFormForGetUsersCheckWithResults */
	public function testSubmitFormForGetUsersCheckWithResults( $tempAccountsHidden ) {
		// We need to set a title in the RequestContext because the HTMLFieldsetCheckUser (used to make the
		// checkuser helper) uses HTMLForm code which unconditionally uses the RequestContext title.
		RequestContext::getMain()->setTitle( SpecialPage::getTitleFor( 'CheckUser' ) );

		$testCheckUser = $this->getTestCheckUser();
		$request = new FauxRequest(
			[
				'checktype' => 'subipusers', 'reason' => 'Test check', 'user' => '1.2.3.4',
				'wpHideTemporaryAccounts' => $tempAccountsHidden,
			],
			true
		);
		[ $html ] = $this->executeSpecialPage( '', $request, null, $testCheckUser );

		$this->commonVerifyFormFieldsPresent( $html );
		$this->verifyCidrFormExists( $html );
		$this->assertAndGetByElementClass( $html, 'mw-checkuser-helper-fieldset' );

		// Verify that the results contain the target IP '1.2.3.4' and the user who has used that IP
		$resultHtml = $this->assertAndGetByElementClass( $html, 'mw-checkuser-get-users-results' );
		$this->assertStringContainsString( '1.2.3.4', $resultHtml );
		$this->assertStringContainsString( self::$usernameTarget->getName(), $resultHtml );

		// Verify the temporary account is shown or not shown, depending on the state of the filters
		if ( $tempAccountsHidden ) {
			$this->assertStringNotContainsString( self::$tempAccountTarget->getName(), $resultHtml );
		} else {
			$this->assertStringContainsString( self::$tempAccountTarget->getName(), $resultHtml );
		}

		$this->verifyCheckUserLogEntryCreated( $testCheckUser, 'Test check', '1.2.3.4', 'ipusers' );
	}

	public static function provideSubmitFormForGetUsersCheckWithResults(): array {
		return [
			'Temporary accounts hidden' => [ true ],
			'Temporary accounts not hidden' => [ false ],
		];
	}

	/**
	 * Verifies that one row exists in cu_log which has the expected properties
	 */
	private function verifyCheckUserLogEntryCreated(
		UserIdentity $expectedPerformer, string $expectedReason, string $expectedTarget, string $expectedLogType
	): void {
		$this->newSelectQueryBuilder()
			->select( [ 'actor_name', 'comment_text', 'cul_target_text', 'cul_type' ] )
			->from( 'cu_log' )
			->join( 'actor', null, 'actor_id=cul_actor' )
			->join( 'comment', null, 'comment_id=cul_reason_id' )
			->caller( __METHOD__ )
			->assertRowValue( [
				$expectedPerformer->getName(), $expectedReason, $expectedTarget, $expectedLogType,
			] );
	}

	public function addDBDataOnce() {
		$this->disableAutoCreateTempUser();
		$usernameTarget = $this->getTestUser();

		// Insert test edit(s) so that we get results in Special:CheckUser to look at.
		// More rigorous testing is done in the tests that target each check subtype pager.
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		$testPage = $this->getNonexistingTestPage();
		$this->editPage(
			$testPage, 'Test content', 'Test summary', NS_MAIN, $usernameTarget->getAuthority()
		);
		$this->editPage(
			$testPage, 'Test content2', 'Test summary', NS_MAIN,
			$this->getServiceContainer()->getUserFactory()->newAnonymous( '1.2.3.4' )
		);
		$this->enableAutoCreateTempUser();
		$tempUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )->getUser();
		$this->editPage( $testPage, 'Test content3', 'Test summary', NS_MAIN, $tempUser );

		self::$usernameTarget = $usernameTarget->getUserIdentity();
		self::$tempAccountTarget = $tempUser;
	}
}
