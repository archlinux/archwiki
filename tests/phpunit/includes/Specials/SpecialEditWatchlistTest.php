<?php
namespace MediaWiki\Tests\Specials;

use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\UserNotLoggedIn;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Specials\SpecialEditWatchlist;
use MediaWiki\Title\Title;
use MediaWiki\Watchlist\WatchlistLabel;
use TestUser;

/**
 * @author Addshore
 *
 * @group Database
 *
 * @covers \MediaWiki\Specials\SpecialEditWatchlist
 */
class SpecialEditWatchlistTest extends SpecialPageTestBase {

	/**
	 * Returns a new instance of the special page under test.
	 *
	 * @return SpecialPage
	 */
	protected function newSpecialPage() {
		$services = $this->getServiceContainer();
		return new SpecialEditWatchlist(
			$services->getWatchedItemStore(),
			$services->getWatchlistLabelStore(),
			$services->getTitleParser(),
			$services->getGenderCache(),
			$services->getLinkBatchFactory(),
			$services->getNamespaceInfo(),
			$services->getWikiPageFactory(),
			$services->getWatchlistManager()
		);
	}

	public function testNotLoggedIn_throwsException() {
		$this->expectException( UserNotLoggedIn::class );
		$this->executeSpecialPage();
	}

	public function testRootPage_displaysNormalTitle() {
		$user = new TestUser( __METHOD__ );
		[ $html, ] = $this->executeSpecialPage( '', null, 'qqx', $user->getUser() );
		$this->assertStringContainsString( '(editwatchlist-summary)', $html );
	}

	public function testClearPage_hasClearButtonForm() {
		$user = new TestUser( __METHOD__ );
		[ $html, ] = $this->executeSpecialPage( 'clear', null, 'qqx', $user->getUser() );
		$this->assertMatchesRegularExpression(
			'/<form action=\'.*?Special:EditWatchlist\/clear\'/',
			$html
		);
	}

	public function testEditRawPage_hasTitlesBox() {
		$user = new TestUser( __METHOD__ );
		[ $html, ] = $this->executeSpecialPage( 'raw', null, 'qqx', $user->getUser() );
		$this->assertStringContainsString(
			'<div id=\'mw-input-wpTitles\'',
			$html
		);
	}

	public function testLabelSubjectAndTalkPageTogether(): void {
		$this->overrideConfigValue( MainConfigNames::EnableWatchlistLabels, true );
		$user = $this->getMutableTestUser()->getUser();

		// Watch a page (and its talk page).
		$titleSubject = Title::makeTitle( NS_MAIN, 'Watchlist labels for talk page' );
		$titleTalk = Title::makeTitle( NS_TALK, $titleSubject->getText() );
		$this->getServiceContainer()
			->getWatchedItemStore()
			->addWatchBatchForUser( $user, [ $titleSubject, $titleTalk ] );

		// Add a label.
		$label = new WatchlistLabel( $user, 'Test label' );
		$this->getServiceContainer()
			->getWatchlistLabelStore()
			->save( $label );

		// Label that watched item.
		$context = RequestContext::getMain();
		$context->setUser( $user );
		$context->setTitle( Title::makeTitle( NS_SPECIAL, 'EditWatchlist' ) );
		$context->setLanguage( 'qqx' );
		$request = new FauxRequest( [
			'watchlistlabels' => [ $label->getId() ],
			'watchlistlabels-action' => 'assign',
			'wpTitles' => [ $titleSubject->getDBkey() ],
		], true );
		$context->setRequest( $request );
		$request->setVal( 'wpEditToken', $context->getCsrfTokenSet()->getToken() );
		[ $html, ] = $this->executeSpecialPage( null, $request, null, null, false, $context );
		$this->assertStringContainsString( '(watchlistlabels-assign-labels-done: 1, 1)', $html );
	}
}
