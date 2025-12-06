<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations;

use MediaWiki\CheckUser\SuggestedInvestigations\Instrumentation\SuggestedInvestigationsInstrumentationClient;
use MediaWiki\CheckUser\SuggestedInvestigations\SpecialSuggestedInvestigations;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use PHPUnit\Framework\ExpectationFailedException;
use SpecialPageTestBase;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\SpecialSuggestedInvestigations
 * @group Database
 */
class SpecialSuggestedInvestigationsTest extends SpecialPageTestBase {
	use SuggestedInvestigationsTestTrait;
	use MockAuthorityTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->enableSuggestedInvestigations();
		$this->unhideSuggestedInvestigations();
	}

	protected function newSpecialPage(): SpecialSuggestedInvestigations {
		$page = $this->getServiceContainer()->getSpecialPageFactory()->getPage( 'SuggestedInvestigations' );
		$this->assertInstanceOf( SpecialSuggestedInvestigations::class, $page );
		return $page;
	}

	public function testLoadSpecialPageWhenMissingRequiredRight() {
		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage();
	}

	public function testLoadSpecialPageWithRequiredRight() {
		$checkuser = $this->getTestUser( [ 'checkuser' ] )->getUser();

		$this->setTemporaryHook(
			'CheckUserSuggestedInvestigationsGetSignals',
			static function ( &$signals ) {
				$signals = [ 'dev-signal-1', 'dev-signal-2' ];
			}
		);

		$context = RequestContext::getMain();
		$context->setUser( $checkuser );
		$context->setLanguage( 'qqx' );

		[ $html ] = $this->executeSpecialPage(
			'', new FauxRequest(), null, null, true, $context
		);

		$descriptionHtml = $this->assertAndGetByElementClass(
			$html, 'ext-checkuser-suggestedinvestigations-description'
		);
		$this->assertStringContainsString(
			'(checkuser-suggestedinvestigations-summary',
			$descriptionHtml
		);
		$this->assertAndGetByElementClass(
			$descriptionHtml, 'ext-checkuser-suggestedinvestigations-signals-popover-icon'
		);

		$actualJsConfigVars = $context->getOutput()->getJsConfigVars();
		$this->assertArrayHasKey( 'wgCheckUserSuggestedInvestigationsSignals', $actualJsConfigVars );
		$this->assertArrayEquals(
			[ 'dev-signal-1', 'dev-signal-2' ],
			$actualJsConfigVars['wgCheckUserSuggestedInvestigationsSignals']
		);
	}

	/**
	 * Calls DOMCompat::querySelectorAll, expects that it returns one valid Element object and then returns
	 * the HTML inside that Element.
	 *
	 * @param string $html The HTML to search through
	 * @param string $class The CSS class to search for, excluding the "." character
	 * @return string The HTML inside the given class
	 */
	private function assertAndGetByElementClass( string $html, string $class ): string {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, '.' . $class );
		$this->assertCount( 1, $element, "Could not find only one element with CSS class $class in $html" );
		return DOMCompat::getInnerHTML( $element[0] );
	}

	/** @dataProvider provideUnavailableSpecialPage */
	public function testUnavailableSpecialPage( bool $enabled, bool $hidden ) {
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

		// This exception is thrown in `newSpecialPage` when the assertion fails
		$this->expectException( ExpectationFailedException::class );
		$this->executeSpecialPage();
	}

	public static function provideUnavailableSpecialPage() {
		return [
			'Feature disabled, not hidden' => [
				'enabled' => false,
				'hidden' => false,
			],
			'Feature disabled, hidden' => [
				'enabled' => false,
				'hidden' => true,
			],
			'Feature enabled, hidden' => [
				'enabled' => true,
				'hidden' => true,
			],
		];
	}

	/** @dataProvider providePageLoadInstrumentation */
	public function testPageLoadInstrumentation( $queryParameters, $expectedActionContext ) {
		$context = RequestContext::getMain();
		$context->setUser( $this->getTestUser( [ 'checkuser' ] )->getUser() );
		$context->setRequest( new FauxRequest( $queryParameters ) );

		// Mock SuggestedInvestigationsInstrumentationClient so that we can check the correct event is created
		$client = $this->createMock( SuggestedInvestigationsInstrumentationClient::class );
		$client->expects( $this->once() )
			->method( 'submitInteraction' )
			->with( $context, 'page_load', [ 'action_context' => json_encode( $expectedActionContext ) ] );
		$this->setService( 'CheckUserSuggestedInvestigationsInstrumentationClient', $client );

		$this->executeSpecialPage( '', null, null, null, false, $context );
	}

	public static function providePageLoadInstrumentation(): array {
		return [
			'Page load with no additional query parameters' => [ [], [ 'is_paging_results' => false, 'limit' => 10 ] ],
			'Page load with offset and custom limit' => [
				[ 'offset' => '20250405060708', 'limit' => 20 ],
				[ 'is_paging_results' => true, 'limit' => 20 ],
			],
			'Page load with no offset but backwards direction and custom limit' => [
				[ 'dir' => 'prev' ],
				[ 'is_paging_results' => true, 'limit' => 10 ],
			],
		];
	}
}
