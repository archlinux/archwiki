<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\Pagers;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsPagerFactory;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\Parsoid\Core\DOMCompat;
use Wikimedia\Parsoid\Ext\DOMUtils;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsRevisionsPager
 * @group CheckUser
 * @group Database
 */
class SuggestedInvestigationsRevisionsPagerTest extends MediaWikiIntegrationTestCase {

	private static int $firstRevisionId;
	private static int $secondRevisionId;
	private static int $thirdRevisionId;
	private static int $deletedRevisionId;
	private static User $editingUser;

	/** @dataProvider provideUserPassedToPager */
	public function testForNonArchiveMode( bool $passUserToPager ) {
		$objectUnderTest = $this->getPagerFactory()->createRevisionPager(
			RequestContext::getMain(),
			[],
			[ static::$deletedRevisionId, static::$firstRevisionId, static::$secondRevisionId ],
			$passUserToPager ? static::$editingUser : null
		);
		$html = $objectUnderTest->getBody();

		// Assert that the two specified undeleted revisions are shown in the page (the deleted one is expected
		// to not be shown)
		$this->assertSame( 2, substr_count( $html, 'data-mw-revid' ) );
		$this->assertStringContainsString( 'Test page1', $html );
		$this->assertStringNotContainsString( 'Test page2', $html );
		$this->assertStringNotContainsString( 'Test page3', $html );

		$firstRevisionRowHtml = $this->assertAndGetByElementSelector(
			$html,
			'li[data-mw-revid="' . static::$firstRevisionId . '"]'
		);
		$this->assertUserCorrectlyAddedToRowHtml( $passUserToPager, $firstRevisionRowHtml );

		$secondRevisionRowHtml = $this->assertAndGetByElementSelector(
			$html,
			'li[data-mw-revid="' . static::$secondRevisionId . '"]'
		);
		$this->assertUserCorrectlyAddedToRowHtml( $passUserToPager, $secondRevisionRowHtml );
	}

	public static function provideUserPassedToPager(): array {
		return [
			'User is passed to the pager' => [ true ],
			'User is not passed to the pager' => [ false ],
		];
	}

	/** @dataProvider provideUserPassedToPager */
	public function testForArchiveMode( bool $passUserToPager ) {
		$objectUnderTest = $this->getPagerFactory()->createRevisionPager(
			RequestContext::getMain(),
			[ 'isArchive' => true ],
			[ static::$deletedRevisionId, static::$firstRevisionId ],
			$passUserToPager ? static::$editingUser : null
		);
		$html = $objectUnderTest->getBody();

		// Assert that the deleted revision is present and that the first revision is not present (as we are in
		// archive mode)
		$this->assertSame( 1, substr_count( $html, 'data-mw-revid' ) );
		$this->assertStringNotContainsString( 'Test page1', $html );
		$this->assertStringNotContainsString( 'Test page2', $html );
		$this->assertStringContainsString( 'Test page3', $html );

		$deletedRevisionRowHtml = $this->assertAndGetByElementSelector(
			$html,
			'li[data-mw-revid="' . static::$deletedRevisionId . '"]'
		);
		$this->assertUserCorrectlyAddedToRowHtml( $passUserToPager, $deletedRevisionRowHtml );
	}

	private function assertUserCorrectlyAddedToRowHtml( bool $userPassedToPager, string $rowHtml ): void {
		if ( $userPassedToPager ) {
			$this->assertStringNotContainsString(
				static::$editingUser->getName(),
				$rowHtml,
				'If user is passed to the pager, then the username should not be rendered in the row'
			);
		} else {
			$this->assertStringContainsString(
				static::$editingUser->getName(),
				$rowHtml,
				'If user is not passed to the pager, then the username should be rendered in the row'
			);
		}
	}

	/**
	 * Calls DOMCompat::querySelectorAll, expects that it returns one valid Element object and then returns
	 * the HTML inside that Element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $selector The selector that should find one element
	 * @return string The HTML inside the given class
	 */
	private function assertAndGetByElementSelector( string $html, string $selector ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, $selector );
		$this->assertCount( 1, $element, "Could not find only one element matching $selector in $html" );
		return DOMCompat::getInnerHTML( $element[0] );
	}

	private function getPagerFactory(): SuggestedInvestigationsPagerFactory {
		return $this->getServiceContainer()->get( 'CheckUserSuggestedInvestigationsPagerFactory' );
	}

	/** @inheritDoc */
	public function addDBDataOnce() {
		self::$editingUser = static::getTestUser()->getUser();

		// Make four edits using the user in $editingUser and then store those edit revision IDs in
		// the static properties.
		$firstEditStatus = $this->editPage( 'Test page1', 'Test Content 1', 'test', NS_MAIN, self::$editingUser );
		$this->assertStatusGood( $firstEditStatus );
		self::$firstRevisionId = $firstEditStatus->getNewRevision()->getId();

		$secondEditStatus = $this->editPage( 'Test page1', 'Test Content 2', 'test', NS_MAIN, self::$editingUser );
		$this->assertStatusGood( $secondEditStatus );
		self::$secondRevisionId = $secondEditStatus->getNewRevision()->getId();

		$thirdEditStatus = $this->editPage( 'Test page2', 'Test Content 3', 'test', NS_MAIN, self::$editingUser );
		$this->assertStatusGood( $thirdEditStatus );
		self::$thirdRevisionId = $thirdEditStatus->getNewRevision()->getId();

		$fourthEditStatus = $this->editPage( 'Test page3', 'Test Content 4', 'test', NS_MAIN, self::$editingUser );
		$this->assertStatusGood( $fourthEditStatus );
		self::$deletedRevisionId = $fourthEditStatus->getNewRevision()->getId();

		// Delete Test page3 to delete only the fourth revision
		$this->deletePage( 'Test page3' );
	}
}
