<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\View;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\ServiceNames;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\ProtectedVarsTestTrait;
use MediaWiki\Language\RawMessage;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Specials\SpecialPageTestBase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewEdit
 *
 * Indirectly covers:
 * @covers \MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterView
 */
class AbuseFilterViewEditTest extends SpecialPageTestBase {
	use ProtectedVarsTestTrait;

	/**
	 * @inheritDoc
	 */
	protected function newSpecialPage(): SpecialAbuseFilter {
		$services = $this->getServiceContainer();
		$sp = new SpecialAbuseFilter(
			$services->getService( ServiceNames::PermManager ),
			$services->getObjectFactory()
		);
		$sp->setLinkRenderer(
			$services->getLinkRendererFactory()->create()
		);
		return $sp;
	}

	/**
	 * @inheritDoc
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->clearProtectedVarRelatedHooks();
	}

	/**
	 * @inheritDoc
	 */
	public function addDBDataOnce() {
		$this->createFiltersWithProtectedVariables();
	}

	public function testViewEditTokenMismatch() {
		[ $html, ] = $this->executeSpecialPage(
			'new',
			new FauxRequest(
				[
					'wpFilterDescription' => 'Test filter',
					'wpFilterRules' => 'user_name = "1.2.3.4"',
					'wpFilterNotes' => '',
				],
				// This was posted
				true,
			),
			null,
			$this->getTestSysop()->getAuthority()
		);

		$this->assertStringContainsString(
			'abusefilter-edit-token-not-match',
			$html,
			'The token mismatch warning message was not present.'
		);
	}

	/**
	 * @dataProvider provideViewEditMakePublic
	 */
	public function testViewEditMakePublic( int $isNewFilterHidden ) {
		// Create a private filter
		$performer = $this->getTestSysop();
		$name = 'Hidden filter';
		$rules = '1 = 0';
		// Use an ID that is not used in ::addDBDataOnce
		$filterId = '3';
		$this->assertStatusGood( AbuseFilterServices::getFilterStore()->saveFilter(
			$performer->getAuthority(),
			null,
			$this->getFilterFromSpecs( [
				'id' => $filterId,
				'name' => $name,
				'rules' => $rules,
				'privacy' => Flags::FILTER_HIDDEN,
			] ),
			MutableFilter::newDefault()
		) );

		$request = new FauxRequest( [
			// Avoid the abusefilter-edit-missingfields error (see FilterValidator::checkRequiredFields)
			'wpFilterDescription' => $name,
			'wpFilterRules' => $rules,
		], true );
		if ( $isNewFilterHidden ) {
			// Checkbox checked: keep the filter private
			$request->setVal( 'wpFilterHidden', 1 );
		}

		// Make sure wpEditToken is set, because wpMakePublic is evaluated after token mismatches
		$context = RequestContext::getMain();
		$context->setAuthority( $performer->getAuthority() );
		$token = $context->getCsrfTokenSet()->getToken( [ 'abusefilter', $filterId ] )->toString();
		$request->setVal( 'wpEditToken', $token );

		[ $html ] = $this->executeSpecialPage( $filterId, $request );

		$msgFragment = $isNewFilterHidden ? 'shown unexpectedly' : 'not shown';
		$this->assertSame(
			!$isNewFilterHidden,
			str_contains( $html, '(abusefilter-edit-makepublic)' ),
			"The warning for making a private filter public was $msgFragment"
		);
	}

	public static function provideViewEditMakePublic() {
		return [
			'Keeping a private filter private' => [ 1 ],
			'Making a private filter public' => [ 0 ]
		];
	}

	public function testViewEditUnrecoverableError() {
		[ $html, ] = $this->executeSpecialPage(
			'new',
			new FauxRequest(
				[
					'wpFilterDescription' => '',
					'wpFilterRules' => 'user_name = "1.2.3.4"',
					'wpFilterNotes' => '',
				],
				// This was posted
				true,
			)
		);

		$this->assertStringContainsString(
			'abusefilter-edit-notallowed',
			$html,
			'The permission error message was not present.'
		);
	}

	public function testViewEditForInvalidImport() {
		[ $html, ] = $this->executeSpecialPage(
			'new',
			new FauxRequest( [ 'wpImportText' => 'abc' ], true ),
			null,
			$this->getTestSysop()->getAuthority()
		);

		$this->assertStringContainsString(
			'(abusefilter-import-invalid-data',
			$html,
			'An unknown filter ID should cause an error message.'
		);
		$this->assertStringContainsString(
			'(abusefilter-return',
			$html,
			'Button to return the filter management was missing.'
		);
	}

	/**
	 * @dataProvider provideViewEditForBadFilter
	 */
	public function testViewEditForBadFilter( string $subPage ) {
		[ $html, ] = $this->executeSpecialPage(
			$subPage, performer: $this->getTestSysop()->getAuthority()
		);

		$this->assertStringContainsString(
			'(abusefilter-edit-badfilter',
			$html,
			'An unknown filter ID should cause an error message.'
		);
		$this->assertStringContainsString(
			'(abusefilter-return',
			$html,
			'Button to return the filter management was missing.'
		);
	}

	public static function provideViewEditForBadFilter() {
		return [
			'Unknown filter ID' => [ '12345' ],
			'Unknown history ID for existing filter' => [ 'history/1/item/123456' ],
		];
	}

	public function testViewEditProtectedVarsCheckboxPresentForProtectedFilter() {
		[ $html, ] = $this->executeSpecialPage(
			'1',
			performer: $this->getTestSysop()->getAuthority()
		);

		$this->assertStringNotContainsString(
			'abusefilter-edit-protected-help-message',
			$html,
			'The enabled checkbox to protect the filter was not present.'
		);
		$this->assertStringContainsString(
			'abusefilter-edit-protected-variable-already-protected',
			$html,
			'The disabled checkbox explaining that the filter is protected was not present.'
		);

		// Also check that the filter hit count is present and as expected for the protected filter.
		$this->assertStringContainsString( '(abusefilter-edit-hitcount', $html );
		$this->assertStringContainsString( '(abusefilter-hitcount: 1', $html );
	}

	public function testViewEditForProtectedFilterWhenUserLacksAuthority() {
		[ $html, ] = $this->executeSpecialPage(
			'1',
			performer:  $this->mockFilterEditorAuthorityWithoutProtectedVarsAccess()
		);

		$this->assertStringContainsString(
			'(abusefilter-edit-denied-protected-vars-because-of-permission: ' .
				'(action-abusefilter-access-protected-vars))',
			$html,
			'The protected filter permission error was not present.'
		);
	}

	public function testViewEditForProtectedFilterWhenHookPreventsAccess() {
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( $performer, $variables, AbuseFilterPermissionStatus $returnStatus ) {
				$returnStatus->fatal( new RawMessage( 'Testing-custom-message-for-abuse-filter' ) );
			}
		);

		[ $html, ] = $this->executeSpecialPage(
			'1',
			performer: $this->getTestSysop()->getAuthority()
		);

		$this->assertStringContainsString(
			'(abusefilter-edit-denied-protected-vars:',
			$html,
			'The protected filter access error was not present.'
		);
		$this->assertStringContainsString( 'Testing-custom-message-for-abuse-filter', $html );
	}

	public function testViewEditProtectedVarsCheckboxAbsentForUnprotectedFilter() {
		[ $html, ] = $this->executeSpecialPage(
			'2',
			performer: $this->getTestSysop()->getAuthority()
		);
		$this->assertStringNotContainsString(
			'abusefilter-edit-protected',
			$html,
			'Elements related to protected filters were present.'
		);
	}

	public function testViewEditProtectedVarsSave() {
		$authority = $this->getTestSysop()->getAuthority();
		$user = $this->getServiceContainer()->getUserFactory()->newFromUserIdentity( $authority->getUser() );

		// Set the abuse filter editor to the context user, so that the edit token matches
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		[ $html, ] = $this->executeSpecialPage(
			'new',
			new FauxRequest(
				[
					'wpFilterDescription' => 'Uses protected variable',
					'wpFilterRules' => 'user_unnamed_ip = "4.2.3.4"',
					'wpFilterNotes' => '',
					'wpEditToken' => $user->getEditToken( [ 'abusefilter', 'new' ] ),
				],
				// This was posted
				true,
				RequestContext::getMain()->getRequest()->getSession()
			),
			null,
			$authority
		);

		$this->assertStringContainsString(
			'abusefilter-edit-protected-variable-not-protected',
			$html,
			'The error message about protecting the filter was not present.'
		);

		$this->assertStringContainsString(
			'abusefilter-edit-protected-help-message',
			$html,
			'The enabled checkbox to protect the filter was not present.'
		);
	}

	public function testViewEditProtectedVarsSaveSuccess() {
		$authority = $this->getTestSysop()->getAuthority();
		$user = $this->getServiceContainer()->getUserFactory()->newFromUserIdentity( $authority->getUser() );

		// Set the abuse filter editor to the context user, so that the edit token matches
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		[ $html, $response ] = $this->executeSpecialPage(
			'new',
			new FauxRequest(
				[
					'wpFilterDescription' => 'Uses protected variable',
					'wpFilterRules' => 'user_unnamed_ip = "4.2.3.4"',
					'wpFilterProtected' => '1',
					'wpFilterNotes' => '',
					'wpEditToken' => $user->getEditToken( [ 'abusefilter', 'new' ] ),
				],
				// This was posted
				true,
				RequestContext::getMain()->getRequest()->getSession()
			),
			null,
			$authority
		);

		// On saving successfully, the page redirects
		$this->assertSame( '', $html );
		$this->assertStringContainsString( 'result=success', $response->getHeader( 'location' ) );
	}
}
