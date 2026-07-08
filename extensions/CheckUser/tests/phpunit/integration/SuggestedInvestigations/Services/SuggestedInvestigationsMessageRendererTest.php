<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Services;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsMessageRenderer;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals\SuggestedInvestigationsSignalMatchResult;
use MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\SuggestedInvestigationsTestTrait;
use MediaWikiIntegrationTestCase;
use OOUI\BlankTheme;
use OOUI\Theme;
use Wikimedia\Codex\Utility\Codex;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsMessageRenderer
 */
class SuggestedInvestigationsMessageRendererTest extends MediaWikiIntegrationTestCase {
	use SuggestedInvestigationsTestTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->enableSuggestedInvestigations();
		Theme::setSingleton( new BlankTheme() );
	}

	public function testReturnsNonEmptyStringWhenUsersHaveOpenCases(): void {
		$user = $this->getMutableTestUser()->getUser();

		$caseManager = $this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseManager' );
		$signal = SuggestedInvestigationsSignalMatchResult::newPositiveResult( 'TestSignal', 'test-value', false );
		$caseManager->createCase( [ $user ], [ $signal ] );

		$pager = $this->createMock( CheckUserGetUsersPager::class );
		$pager->expects( $this->once() )
			->method( 'getResultUsernameMap' )
			->willReturn( [ $user->getId() => $user->getName() ] );

		$renderer = new SuggestedInvestigationsMessageRenderer(
			$this->getServiceContainer()->getService( 'CheckUserSuggestedInvestigationsCaseLookup' ),
			new Codex()
		);

		$result = $renderer->getOpenCasesNotice(
			$pager,
			RequestContext::getMain(),
			$this->getServiceContainer()->getLinkRenderer()
		);

		$this->assertNotSame( '', $result );
	}

	public function testGetUserDismissableWarning(): void {
		$renderer = new SuggestedInvestigationsMessageRenderer(
			$this->createMock( SuggestedInvestigationsCaseLookupService::class ),
			new Codex()
		);

		$result = $renderer->getUserDismissableWarning( 'Test HTML', 'test-class-abc' );

		$this->assertAndGetByElementClass(
			$result,
			'.ext-checkuser-suggestedinvestigations-warning-dismiss'
		);
		$actualMessageHtml = $this->assertAndGetByElementClass(
			$result,
			'.ext-checkuser-suggestedinvestigations-dismissable-warning.test-class-abc'
		);
		$this->assertStringContainsString( 'Test HTML', $actualMessageHtml );
	}

	/**
	 * Calls DOMCompat::querySelectorAll, expects that it returns one valid Element object and then returns
	 * that Element objects outer HTML.
	 */
	private function assertAndGetByElementClass( string $html, string $selector ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, $selector );
		$this->assertCount(
			1,
			$element,
			"Could not find only one element with CSS selector $selector in $html"
		);
		return DOMCompat::getOuterHTML( $element[0] );
	}
}
