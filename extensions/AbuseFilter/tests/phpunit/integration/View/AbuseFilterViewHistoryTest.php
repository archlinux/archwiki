<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\View;

use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\ServiceNames;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\ProtectedVarsTestTrait;
use MediaWiki\Html\Html;
use MediaWiki\Language\RawMessage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Specials\SpecialPageTestBase;
use Wikimedia\Parsoid\DOM\Document;
use Wikimedia\Parsoid\Ext\DOMUtils;

/**
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewHistory
 * @covers \MediaWiki\Extension\AbuseFilter\Pager\AbuseFilterHistoryPager
 *
 * Indirectly covers:
 * @covers \MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterView
 */
class AbuseFilterViewHistoryTest extends SpecialPageTestBase {
	use ProtectedVarsTestTrait;

	private Authority $authorityCannotUseProtectedVar;
	private Authority $authorityCanUseProtectedVar;

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
		$this->authorityCannotUseProtectedVar = $this->mockFilterEditorAuthorityWithoutProtectedVarsAccess();
		$this->authorityCanUseProtectedVar = $this->mockFilterEditorAuthorityWithProtectedVarsAccess();
	}

	/**
	 * @inheritDoc
	 */
	public function addDBDataOnce() {
		$this->createFiltersWithProtectedVariables();
	}

	public function testViewHistoryForProtectedFilterWhenUserLacksAuthority() {
		[ $html, ] = $this->executeSpecialPage(
			'history/1',
			new FauxRequest(),
			null,
			$this->authorityCannotUseProtectedVar
		);

		$this->assertStringContainsString(
			'(abusefilter-history-error-protected-due-to-permission: (action-abusefilter-access-protected-vars))',
			$html,
			'The protected filter permission error was not present.'
		);
		$this->assertStringNotContainsString(
			'abusefilter-history-select-user',
			$html,
			'The filter history should not be shown if the user cannot see the filter.'
		);
	}

	public function testViewHistoryForProtectedFilterWhenHookPreventsAccess() {
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( $performer, $variables, AbuseFilterPermissionStatus $returnStatus ) {
				$returnStatus->fatal( new RawMessage( 'Testing-custom-message-for-abuse-filter' ) );
			}
		);

		[ $html, ] = $this->executeSpecialPage( 'history/1', performer: $this->authorityCanUseProtectedVar );

		$this->assertStringContainsString(
			'(abusefilter-history-error-protected:',
			$html,
			'The protected filter permission error was not present.'
		);
		$this->assertStringContainsString( 'Testing-custom-message-for-abuse-filter', $html );
		$this->assertStringNotContainsString(
			'abusefilter-history-select-user',
			$html,
			'The filter history should not be shown if the user cannot see the filter.'
		);
	}

	/**
	 * Common test code used by tests which load the history of AbuseFilter filters,
	 * used to verify that the headings on the table on the page is as expected
	 *
	 * @param Document $doc The HTML document of the special page
	 */
	private function verifyHistoryHeadingsPresent( Document $doc ) {
		$tableHtml = $this->assertSelectorMatchesOneElementInNode( $doc, '.mw-datatable', true );

		$expectedTableHeadings = [
			'abusefilter-history-timestamp',
			'abusefilter-history-user',
			'abusefilter-history-public',
			'abusefilter-history-flags',
			'abusefilter-history-actions',
			'abusefilter-history-diff',
			'abusefilter-history-timestamp',
		];

		foreach ( $expectedTableHeadings as $heading ) {
			$this->assertStringContainsString( $heading, $tableHtml );
		}
	}

	/**
	 * Common test code used by tests which load the history of AbuseFilter filters,
	 * used to verify that the search form shown on the page has the expected fields.
	 *
	 * @param string $html The HTML of the special page
	 */
	private function verifyHistorySearchFormFields( string $html ) {
		$this->assertStringContainsString( '(abusefilter-history-select-user', $html );
		$this->assertStringContainsString( '(abusefilter-history-select-filter', $html );
		$this->assertStringContainsString( '(abusefilter-history-select-submit', $html );
		$this->assertStringContainsString( '(abusefilter-history-select-legend', $html );
	}

	public function testViewHistoryWhenFilteringForSpecificFilter() {
		[ $html, ] = $this->executeSpecialPage(
			'history/1',
			performer: $this->authorityCanUseProtectedVar
		);
		$htmlDoc = DOMUtils::parseHTML( $html );

		// Verify the structure of the form fields and items near the form.
		$this->verifyHistorySearchFormFields( $html );
		$this->assertStringContainsString( '(abusefilter-history-backedit)', $html );

		// Verify the structure of the table
		$this->verifyHistoryHeadingsPresent( $htmlDoc );

		// Get the HTML for the most recent edit to the filter we are filtering for
		$rowHtml = Html::rawElement(
			'table',
			[],
			$this->assertSelectorMatchesOneElementInNode( $htmlDoc, '.mw-abusefilter-history-id-3', true )
		);

		// Verify the structure of the row we have found
		$this->assertStringContainsString(
			'UTSysop',
			$this->assertSelectorMatchesOneElement( $rowHtml, '.TablePager_col_afh_user_text' ),
			"Missing editor of the version of the filter in $rowHtml"
		);
		$this->assertStringContainsString(
			'Filter with protected variables',
			$this->assertSelectorMatchesOneElement( $rowHtml, '.TablePager_col_afh_public_comments' ),
			"Missing name of filter in $rowHtml"
		);
		$this->assertSame(
			'<td class="TablePager_col_afh_flags">' .
				$this->getServiceContainer()->get( ServiceNames::SpecsFormatter )->formatFlags(
					'protected,enabled', $this->getServiceContainer()->getLanguageFactory()->getLanguage( 'qqx' )
				) .
				'</td>',
			$this->assertSelectorMatchesOneElement( $rowHtml, '.TablePager_col_afh_flags' ),
			"Unexpected flags on the version of the filter in $rowHtml"
		);
		$this->assertStringContainsString(
			'abusefilter-action-tags',
			$this->assertSelectorMatchesOneElement( $rowHtml, '.TablePager_col_afh_actions' ),
			"Unexpected actions on the version of the filter in $rowHtml"
		);
		$this->assertStringContainsString(
			'abusefilter-history-diff',
			$this->assertSelectorMatchesOneElement( $rowHtml, '.TablePager_col_afh_id' ),
			"Missing diff for the specific version of the filter in $rowHtml"
		);
	}

	public function testViewHistoryHidesProtectedFiltersWhenUserLacksPermissions() {
		[ $html, ] = $this->executeSpecialPage(
			'history',
			performer: $this->authorityCannotUseProtectedVar
		);

		$this->verifyHistorySearchFormFields( $html );
		$this->verifyHistoryHeadingsPresent( DOMUtils::parseHTML( $html ) );

		// Verify that the only filter versions shown is the one without protected variables, including
		// versions of the filter which is now protected.
		$this->assertStringNotContainsString( 'Filter with protected variables', $html );
		$this->assertStringNotContainsString( 'Filter to be converted', $html );
		$this->assertStringContainsString( 'Filter without protected variables', $html );
	}
}
