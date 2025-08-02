<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate;

use CentralAuthTestUser;
use MediaWiki\CheckUser\Investigate\Pagers\PreliminaryCheckPagerFactory;
use MediaWiki\CheckUser\Investigate\Services\PreliminaryCheckService;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\MainConfigNames;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Request\FauxResponse;
use MediaWiki\Request\WebRequest;
use MediaWiki\Tests\SpecialPage\FormSpecialPageTestCase;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use TestUser;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\Investigate\SpecialInvestigate
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\TimelinePager
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\ComparePager
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\PreliminaryCheckPager
 * @group CheckUser
 * @group Database
 */
class SpecialInvestigateTest extends FormSpecialPageTestCase {

	private static User $testCheckUser;
	private static User $testSuppressor;
	private static User $firstTestUser;

	protected function newSpecialPage() {
		return $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'Investigate' );
	}

	/**
	 * Gets a test user with the checkuser group and also assigns that user as the user for the main context.
	 *
	 * @return User
	 */
	private function getTestCheckUser(): User {
		$testCheckUser = self::$testCheckUser;
		RequestContext::getMain()->setUser( $testCheckUser );
		return $testCheckUser;
	}

	/**
	 * Returns the same string as returned by SpecialInvestigate::getTabParam for the given tab.
	 * Used to generate the correct subpage name when testing the Special:Investigate tabs.
	 *
	 * @param string $tab
	 * @return string
	 */
	private function getTabParam( string $tab ): string {
		$name = wfMessage( 'checkuser-investigate-tab-' . $tab )->inLanguage( 'en' )->text();
		return str_replace( ' ', '_', $name );
	}

	/**
	 * Assigns a valid token to the main context request and sets the URL. It then returns this request,
	 * which can be used to load Special:Investigate tabs.
	 *
	 * @param string[] $targets Targets of the check
	 * @param string[] $excludeTargets Targets to exclude from the check
	 * @param string $subPage The subpage
	 * @return FauxRequest|WebRequest
	 */
	private function getValidRequest( array $targets, array $excludeTargets, string $subPage ) {
		$request = RequestContext::getMain()->getRequest();
		// Generate a valid token and set it in the request.
		$token = $this->getServiceContainer()->get( 'CheckUserTokenQueryManager' )->updateToken(
			$request,
			[ 'offset' => null, 'reason' => 'Test reason', 'targets' => $targets, 'exclude-targets' => $excludeTargets ]
		);
		$request->setVal( 'token', $token );
		// The request URL is required to be set, as it is used by SpecialInvestigate::alterForm.
		$request->setRequestURL(
			$this->getServiceContainer()->getMainConfig()->get( MainConfigNames::CanonicalServer ) .
			"Special:Investigate/$subPage"
		);
		return $request;
	}

	/**
	 * Get the full HTML for a tab on the Special:Investigate page.
	 *
	 * @param array $targets The targets of the check
	 * @param string $subPage The value from ::getTabParam for the tab
	 * @param bool $fullHtml Whether to get the full HTML of the page (true) or just OutputPage::getHTML (false).
	 * @return string
	 */
	private function getHtmlForTab( array $targets, string $subPage, bool $fullHtml = false ): string {
		$testCheckUser = $this->getTestCheckUser();
		$request = $this->getValidRequest( $targets, [], $subPage );
		// Execute the special page and get the HTML output. We need the full HTML to verify that the new investigation
		// link is shown.
		[ $html ] = $this->executeSpecialPage( $subPage, $request, null, $testCheckUser, $fullHtml );
		return $html;
	}

	public function testViewSpecialPageBeforeCheck() {
		// Execute the special page. We need the full HTML to verify that the logs button is shown.
		[ $html ] = $this->executeSpecialPage( '', new FauxRequest(), null, $this->getTestCheckUser(), true );
		// Verify that the HTML includes the form fields needed to start an investigation.
		$this->assertStringContainsString( '(checkuser-investigate-duration-label', $html );
		$this->assertStringContainsString( '(checkuser-investigate-targets-label', $html );
		$this->assertStringContainsString( '(checkuser-investigate-reason-label', $html );
		// Verify that the form legend is displayed
		$this->assertStringContainsString( '(checkuser-investigate-legend', $html );
		// Verify that the 'Logs' button is shown
		$this->assertStringContainsString( '(checkuser-investigate-indicator-logs', $html );
	}

	public function testViewTimelineWhenTokenInvalid() {
		$testCheckUser = $this->getTestCheckUser();
		$request = $this->getValidRequest( [ '127.0.0.1' ], [], 'Timeline' );
		$request->setVal( 'token', substr( $request->getVal( 'token' ), 1 ) );
		// Execute the special page.
		[ $html ] = $this->executeSpecialPage( 'Timeline', $request, null, $testCheckUser );
		// Verify that the form legend is displayed, instead of the filters legend
		$this->assertStringContainsString( '(checkuser-investigate-legend', $html );
	}

	private function commonTestViewTimelineTab( $target ) {
		// Get the HTML for the timeline tab
		$html = $this->getHtmlForTab( [ $target ], $this->getTabParam( 'timeline' ), true );
		// Verify that the HTML includes the form field to exclude a user from the timeline results.
		$this->assertStringContainsString( '(checkuser-investigate-filters-exclude-targets-label', $html );
		$this->assertStringContainsString( '(checkuser-investigate-filters-legend', $html );
		// Verify that the 'New investigation' and 'Logs' buttons are shown
		$this->assertStringContainsString( '(checkuser-investigate-indicator-new-investigation', $html );
		$this->assertStringContainsString( '(checkuser-investigate-indicator-logs', $html );
		// Verify that the subtitle indicating the users being investigated is shown
		$this->assertStringContainsString( '(checkuser-investigate-page-subtitle', $html );
		$this->assertStringContainsString( '(checkuser-investigate-subtitle-block-accounts-button-label', $html );
		$this->assertStringContainsString( '(checkuser-investigate-subtitle-block-ips-button-label', $html );
		// Verify that the results container class is present along with the tab names
		$this->assertStringContainsString( 'ext-checkuser-investigate-tabs-indexLayout', $html );
		$this->assertStringContainsString( '(checkuser-investigate-tab-preliminary-check', $html );
		$this->assertStringContainsString( '(checkuser-investigate-tab-compare', $html );
		$this->assertStringContainsString( '(checkuser-investigate-tab-timeline', $html );
		// Return the $html for test classes that extend this method.
		return $html;
	}

	public function testViewTimelineTabWithResults() {
		// Load the special page with a target that has rows in the CheckUser result tables.
		$html = $this->commonTestViewTimelineTab( '1.2.3.4' );
		// Verify that a result from 1.2.3.4 appears in the timeline.
		$this->assertStringContainsString( 'Special:Contributions/InvestigateTestUser1', $html );
	}

	public function testViewTimelineTabWithNoResults() {
		// Load the special page with a target that has no results.
		$html = $this->commonTestViewTimelineTab( '45.6.7.8' );
		// Verify that the "No results" message is shown as no rows should have been found.
		$this->assertStringContainsString( '(checkuser-investigate-timeline-notice-no-results', $html );
	}

	private function commonTestViewCompareTab( $target ) {
		// Get the HTML for the compare tab
		$html = $this->getHtmlForTab( [ $target ], $this->getTabParam( 'compare' ), true );
		// Verify that the HTML includes the form field to exclude a user from the compare results.
		$this->assertStringContainsString( '(checkuser-investigate-filters-exclude-targets-label', $html );
		$this->assertStringContainsString( '(checkuser-investigate-filters-legend', $html );
		// Verify that the 'New investigation' and 'Logs' buttons are shown
		$this->assertStringContainsString( '(checkuser-investigate-indicator-new-investigation', $html );
		$this->assertStringContainsString( '(checkuser-investigate-indicator-logs', $html );
		// Verify that the subtitle indicating the users being investigated is shown
		$this->assertStringContainsString( 'ext-checkuser-investigate-tabs-indexLayout', $html );
		$this->assertStringContainsString( '(checkuser-investigate-tab-preliminary-check', $html );
		$this->assertStringContainsString( '(checkuser-investigate-tab-compare', $html );
		$this->assertStringContainsString( '(checkuser-investigate-tab-timeline', $html );
		// Return the $html for test classes that extend this method.
		return $html;
	}

	public function testViewCompareTabWithResults() {
		// Load the special page for the compare tab with a target that has rows in the CheckUser result tables.
		$html = $this->commonTestViewCompareTab( '1.2.3.4' );
		// Verify that a result from 1.2.3.4 appears in the compare tab.
		$this->assertStringContainsString( '(checkuser-investigate-compare-table-cell-actions', $html );
		$this->assertStringContainsString( 'data-value="InvestigateTestUser1"', $html );
	}

	public function testViewCompareTabWithResultsThatExceedLimitWithHiddenUser() {
		// No support for LIMIT and ORDER BY in a UNION query in SQLite, so this test is skipped for SQLite as there
		// will never be a situation where the limit could be exceeded.
		$this->markTestSkippedIfDbType( 'sqlite' );
		// Set wgCheckUserInvestigateMaximumRowCount to a very low value to cause the limit to be exceeded.
		$this->overrideConfigValue( 'CheckUserInvestigateMaximumRowCount', 1 );
		// Block InvestigateTestUser1 with 'hideuser' enabled.
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$this->getServiceContainer()->getUserIdentityLookup()->getUserIdentityByName( 'InvestigateTestUser1' ),
				self::$testSuppressor, 'infinity', 'block to hide the test user',
				[ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );
		// Load the special page for the compare tab with a target that has rows in the CheckUser result tables, but is
		// hidden.
		$html = $this->commonTestViewCompareTab( 'InvestigateTestUser1' );
		// Verify that the exceeded limit notice is shown and that the only item in the list of targets is marked
		// as hidden.
		$this->assertStringContainsString(
			'(checkuser-investigate-compare-notice-exceeded-limit: (rev-deleted-user))',
			$html
		);
	}

	public function testViewCompareTabWithResultsThatExceedLimit() {
		// No support for LIMIT and ORDER BY in a UNION query in SQLite, so this test is skipped for SQLite as there
		// will never be a situation where the limit could be exceeded.
		$this->markTestSkippedIfDbType( 'sqlite' );
		// Set wgCheckUserInvestigateMaximumRowCount to a very low value to cause the limit to be exceeded.
		$this->overrideConfigValue( 'CheckUserInvestigateMaximumRowCount', 1 );
		// Load the special page for the compare tab with a target that has rows in the CheckUser result tables.
		$html = $this->commonTestViewCompareTab( '127.0.0.1' );
		// Verify that the exceeded limit notice is shown.
		$this->assertStringContainsString( '(checkuser-investigate-compare-notice-exceeded-limit', $html );
	}

	public function testViewCompareTabWithNoResults() {
		// Load the special page for the compare tab with a target that has no results.
		$html = $this->commonTestViewCompareTab( '45.6.7.8' );
		// Verify that the "No results" message is shown as no rows should have been found.
		$this->assertStringContainsString( '(checkuser-investigate-compare-notice-no-results', $html );
	}

	private function commonTestViewAccountInformationTab( $target ) {
		// Get the HTML for the account information tab.
		$html = $this->getHtmlForTab( [ $target ], $this->getTabParam( 'preliminary-check' ), true );
		// Verify that the HTML does not include the form field to exclude a user from the preliminary check results,
		// as this is not supported in the preliminary check tab.
		$this->assertStringNotContainsString( '(checkuser-investigate-filters-exclude-targets-label', $html );
		$this->assertStringNotContainsString( '(checkuser-investigate-filters-legend', $html );
		// Verify that the 'New investigation' and 'Logs' buttons are shown
		$this->assertStringContainsString( '(checkuser-investigate-indicator-new-investigation', $html );
		$this->assertStringContainsString( '(checkuser-investigate-indicator-logs', $html );
		// Verify that the subtitle indicating the users being investigated is shown
		$this->assertStringContainsString( 'ext-checkuser-investigate-tabs-indexLayout', $html );
		$this->assertStringContainsString( '(checkuser-investigate-tab-preliminary-check', $html );
		$this->assertStringContainsString( '(checkuser-investigate-tab-compare', $html );
		$this->assertStringContainsString( '(checkuser-investigate-tab-timeline', $html );
		// Return the $html for test classes that extend this method.
		return $html;
	}

	public function testViewAccountInformationTabForOnlyIPTargets() {
		// Load the special page for the compare tab with an IP target (and so has no user targets).
		$html = $this->commonTestViewAccountInformationTab( '1.2.3.4' );
		// Verify that the "No results" message for IPs notice is shown.
		$this->assertStringContainsString( '(checkuser-investigate-preliminary-notice-ip-targets', $html );
		$this->assertStringNotContainsString( 'ext-checkuser-investigate-table-preliminary-check', $html );
	}

	public function testViewAccountInformationTabForNoResults() {
		// Load the special page for the compare tab with an IP target (and so has no user targets).
		$html = $this->commonTestViewAccountInformationTab( 'NonExistingTestUser' );
		// Verify that the "No results" message for IPs notice is shown.
		$this->assertStringContainsString( '(checkuser-investigate-notice-no-results', $html );
		$this->assertStringNotContainsString( 'ext-checkuser-investigate-table-preliminary-check', $html );
	}

	public function testViewAccountInformationTabWithoutCentralAuth() {
		// We need to disable CentralAuth for this test, even if it is installed (such as on CI). As such, we need to
		// redefine both the CheckUserPreliminaryCheckPagerFactory and PreliminaryCheckService service arguments, so
		// that we can use a mock ExtensionRegistry that indicates CentralAuth is not loaded.
		$services = $this->getServiceContainer();
		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$mockExtensionRegistry->method( 'isLoaded' )
			->willReturnCallback( static function ( $name ) {
				// Return false for CentralAuth, even if it is loaded.
				// For all other extensions, return the real loaded status of the extension.
				if ( $name === 'CentralAuth' ) {
					return false;
				}
				return ExtensionRegistry::getInstance()->isLoaded( $name );
			} );
		$this->setService(
			'CheckUserPreliminaryCheckPagerFactory',
			static function () use ( $services, $mockExtensionRegistry ) {
				return new PreliminaryCheckPagerFactory(
					$services->getLinkRenderer(), $services->getNamespaceInfo(),
					$mockExtensionRegistry, $services->get( 'CheckUserTokenQueryManager' ),
					$services->get( 'CheckUserPreliminaryCheckService' ), $services->getUserFactory()
				);
			}
		);
		$this->setService(
			'CheckUserPreliminaryCheckService',
			static function () use ( $services, $mockExtensionRegistry ) {
				return new PreliminaryCheckService(
					$services->getDBLoadBalancerFactory(), $mockExtensionRegistry,
					$services->getUserGroupManagerFactory(), $services->getDatabaseBlockStoreFactory(),
					WikiMap::getCurrentWikiDbDomain()->getId()
				);
			}
		);
		// Load the special page for the compare tab with a target that has rows in the CheckUser result tables.
		$html = $this->commonTestViewAccountInformationTab( self::$firstTestUser->getName() );
		// Verify that the "No results" message for IPs notice is shown.
		$this->assertStringContainsString( 'ext-checkuser-investigate-table-preliminary-check', $html );
	}

	public function testViewAccountInformationTabWithCentralAuth() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		// Create the test user in the central user DB.
		$targetUser = new CentralAuthTestUser(
			self::$firstTestUser->getName(), 'GUP@ssword', [], [ [ WikiMap::getCurrentWikiId(), 'primary' ] ], false
		);
		$targetUser->save( $this->getDb() );
		// Load the special page for the compare tab with a target that has rows in the CheckUser result tables.
		$html = $this->commonTestViewAccountInformationTab( self::$firstTestUser->getName() );
		// Verify that the "No results" message for IPs notice is shown.
		$this->assertStringContainsString( 'ext-checkuser-investigate-table-preliminary-check', $html );
	}

	public function testSubmitFiltersForm() {
		$subPage = $this->getTabParam( 'compare' );
		// Set-up the valid request and get a test checkuser user
		$testCheckUser = $this->getTestCheckUser();
		$fauxRequest = new FauxRequest(
			[
				'targets' => [ '127.0.0.1', 'InvestigateTestUser1' ],
				'exclude-targets' => [ 'InvestigateTestUser2' ],
			],
			true
		);
		RequestContext::getMain()->setRequest( $fauxRequest );
		// Generate a valid token and set it in the request.
		$token = $this->getServiceContainer()->get( 'CheckUserTokenQueryManager' )->updateToken(
			$fauxRequest,
			[ 'offset' => null, 'reason' => 'Test reason', 'targets' => [ '127.0.0.1' ], 'exclude-targets' => [] ]
		);
		$fauxRequest->setVal( 'token', $token );
		// The request URL is required to be set, as it is used by SpecialInvestigate::alterForm.
		$fauxRequest->setRequestURL(
			$this->getServiceContainer()->getMainConfig()->get( MainConfigNames::CanonicalServer ) .
			"Special:Investigate/$subPage"
		);
		// Execute the special page and get the HTML output.
		[ $html, $response ] = $this->executeSpecialPage( $subPage, $fauxRequest, null, $testCheckUser );
		$this->assertSame(
			'', $html,
			'The form should not be displayed after submitting the form using POST, as it causes a redirect.'
		);
		/** @var $response FauxResponse */
		$this->assertNotEmpty(
			$response->getHeader( 'Location' ),
			'The response should be a redirect after submitting the form using POST.'
		);
	}

	public function testSubmitFormForPost() {
		// Set-up the valid request and get a test checkuser user
		$testCheckUser = $this->getTestCheckUser();
		$fauxRequest = new FauxRequest(
			[
				'targets' => "127.0.0.1\nInvestigateTestUser1", 'duration' => '',
				'reason' => 'Test reason',
			],
			true,
			RequestContext::getMain()->getRequest()->getSession()
		);

		// The request URL is required to be set, as it is used by SpecialInvestigate::alterForm.
		$fauxRequest->setRequestURL(
			$this->getServiceContainer()->getMainConfig()->get( MainConfigNames::CanonicalServer ) .
			"Special:Investigate"
		);
		// Execute the special page and get the HTML output.
		[ $html, $response ] = $this->executeSpecialPage( '', $fauxRequest, null, $testCheckUser );
		$this->assertSame(
			'', $html,
			'The form should not be displayed after submitting the form using POST, as it causes a redirect.'
		);
		/** @var $response FauxResponse */
		$this->assertNotEmpty(
			$response->getHeader( 'Location' ),
			'The response should be a redirect after submitting the form using POST.'
		);
	}

	public function addDBDataOnce() {
		$this->overrideConfigValue( 'CheckUserLogLogins', true );
		// Create two test users that will be referenced in the tests. These are constructed here to avoid creating the
		// users on each test.
		$firstTestUser = ( new TestUser( 'InvestigateTestUser1' ) )->getUser();
		$secondTestUser = ( new TestUser( 'InvestigateTestUser2' ) )->getUser();
		// Add some testing entries to the CheckUser result tables to test the Special:Investigate when results are
		// displayed. More specific tests for the results are written for the pager and services classes.
		$testPage = $this->getExistingTestPage( 'CheckUserTestPage' )->getTitle();
		// Clear the cu_changes and cu_log_event tables to avoid log entries created by the test users being created
		// or the page being created affecting the tests.
		$this->truncateTables( [ 'cu_changes', 'cu_log_event' ] );

		// Insert a testing edit for each test user on the IP 127.0.0.1.
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.1' );
		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'user-agent-for-edit' );
		ConvertibleTimestamp::setFakeTime( '20230405060707' );
		$this->editPage( $testPage, 'Testing23', 'Test23', NS_MAIN, $firstTestUser );
		ConvertibleTimestamp::setFakeTime( '20230405060708' );
		$this->editPage( $testPage, 'Testing56', 'Test56', NS_MAIN, $secondTestUser );

		// Insert one edit with a different IP and a defined XFF header for the first test user.
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		RequestContext::getMain()->getRequest()->setHeader( 'X-Forwarded-For', '127.2.3.4' );
		ConvertibleTimestamp::setFakeTime( '20230405060711' );
		$this->editPage( $testPage, 'Testing1233', 'Test1233', NS_MAIN, $firstTestUser );

		// Simulate a logout event for the first user
		$hookRunner = new HookRunner( $this->getServiceContainer()->getHookContainer() );
		ConvertibleTimestamp::setFakeTime( '20230405060712' );
		RequestContext::getMain()->getRequest()->setHeader( 'User-Agent', 'user-agent-for-logout' );
		$injectHtml = '';
		$hookRunner->onUserLogoutComplete(
			$this->getServiceContainer()->getUserFactory()
				->newFromUserIdentity( UserIdentityValue::newAnonymous( '1.2.3.4' ) ),
			$injectHtml,
			$firstTestUser->getName()
		);

		// Reset the fake time to avoid any issues with other test classes. A fake time will be set before each
		// test in ::setUp.
		ConvertibleTimestamp::setFakeTime( false );

		// Store some testing users for the tests to use to avoid them needing to call ::getTestUser and then
		// potentially causing the users table to be truncated.
		self::$testCheckUser = $this->getTestUser( [ 'checkuser', 'sysop' ] )->getUser();
		self::$firstTestUser = $firstTestUser;
		self::$testSuppressor = $this->getTestUser( [ 'suppress', 'sysop' ] )->getUser();
	}
}
