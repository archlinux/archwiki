<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\View;

use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\ServiceNames;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseFilter;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\ProtectedVarsTestTrait;
use MediaWiki\Language\RawMessage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Tests\Specials\SpecialPageTestBase;

/**
 * @group Database
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterViewDiff
 *
 * Indirectly covers:
 * @covers \MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager
 * @covers \MediaWiki\Extension\AbuseFilter\View\AbuseFilterView
 */
class AbuseFilterViewDiffTest extends SpecialPageTestBase {
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

	/**
	 * @dataProvider provideViewDiffWhenDiffInvalid
	 */
	public function testViewDiffWhenDiffInvalid( string $subPage ) {
		[ $html, ] = $this->executeSpecialPage(
			$subPage,
			performer: $this->authorityCannotUseProtectedVar
		);

		$this->assertStringContainsString( '(abusefilter-diff-invalid)', $html );
	}

	public static function provideViewDiffWhenDiffInvalid() {
		return [
			'Filter ID is not numeric' => [ 'history/abc/diff/prev/1' ],
			'Version IDs do not exist' => [ 'history/1/diff/prev/123456' ],
		];
	}

	/**
	 * @dataProvider provideViewDiffWhenAtLeastOneVersionContainsProtectedFilterVersion
	 */
	public function testViewDiffForProtectedFilterWhenUserLacksAuthority( string $subPage ) {
		[ $html, ] = $this->executeSpecialPage( $subPage, performer: $this->authorityCannotUseProtectedVar );

		$this->assertStringContainsString(
			'(abusefilter-history-error-protected-due-to-permission: (action-abusefilter-access-protected-vars))',
			$html,
			'The protected filter permission error was not present.'
		);
	}

	public static function provideViewDiffWhenAtLeastOneVersionContainsProtectedFilterVersion() {
		return [
			'Diff between version which was not protected and a version which is protected' => [
				'history/1/diff/next/1'
			],
			'Diff between version which was protected and a version which is not protected' => [
				'history/1/diff/prev/2'
			],
			'Diff between two protected versions of the filter' => [ 'history/1/diff/3/prev' ],
		];
	}

	/**
	 * @dataProvider provideViewDiffWhenAtLeastOneVersionContainsProtectedFilterVersion
	 */
	public function testViewDiffForProtectedFilterWhenHookPreventsAccess( string $subPage ) {
		$this->setTemporaryHook(
			'AbuseFilterCanViewProtectedVariables',
			static function ( $performer, $variables, AbuseFilterPermissionStatus $returnStatus ) {
				$returnStatus->fatal( new RawMessage( 'Testing-custom-message-for-abuse-filter' ) );
			}
		);

		[ $html, ] = $this->executeSpecialPage( $subPage, performer: $this->authorityCanUseProtectedVar );

		$this->assertStringContainsString(
			'(abusefilter-history-error-protected: ',
			$html,
			'The protected filter access error was not present.'
		);
		$this->assertStringContainsString( 'Testing-custom-message-for-abuse-filter', $html );
	}
}
