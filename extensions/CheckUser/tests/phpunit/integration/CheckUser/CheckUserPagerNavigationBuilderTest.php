<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser;

use MediaWiki\CheckUser\CheckUser\CheckUserPagerNavigationBuilder;
use MediaWiki\CheckUser\Services\TokenManager;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\FormOptions;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\TestingAccessWrapper;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\CheckUser\CheckUserPagerNavigationBuilder
 */
class CheckUserPagerNavigationBuilderTest extends MediaWikiIntegrationTestCase {

	public function testMakeLink() {
		$opts = new FormOptions();
		$opts->add( 'reason', '' );
		$opts->add( 'period', 0 );
		$opts->add( 'limit', '' );
		$opts->add( 'dir', '' );
		$opts->add( 'offset', '' );
		$opts->add( 'wpHideTemporaryAccounts', true );

		$opts->setValue( 'reason', 'testing reason' );

		$context = RequestContext::getMain();
		$objectUnderTest = TestingAccessWrapper::newFromObject( new CheckUserPagerNavigationBuilder(
			$context, $this->getServiceContainer()->get( 'CheckUserTokenQueryManager' ), $context->getCsrfTokenSet(),
			$context->getRequest(), $opts, UserIdentityValue::newAnonymous( '1.2.3.4' )
		) );

		$actualLinkHtml = $objectUnderTest->makeLink(
			[ 'dir' => 'prev', 'offset' => '20250504050405|1', 'limit' => 123 ],
			'mw-prevlink', 'prev text', 'tooltip', 'prev'
		);

		$form = $this->assertAndGetByElementClass( $actualLinkHtml, 'mw-checkuser-paging-links-form' );
		$formHtml = DOMCompat::getInnerHTML( $form );

		$submitButton = $this->assertAndGetByElementClass( $formHtml, 'mw-checkuser-paging-links' );
		$this->assertSame( 'prev text', $submitButton->getAttribute( 'value' ) );

		// Expect that the paging links have the temporary accounts hide filter, so that the current value persists
		// across pages
		$hideTemporaryAccountsField = DOMCompat::querySelector( $form, 'input[name="wpHideTemporaryAccounts"]' );
		$this->assertNotNull( $hideTemporaryAccountsField );
		$this->assertSame( '1', $hideTemporaryAccountsField->getAttribute( 'value' ) );

		/** @var TokenManager $tokenManager */
		$tokenManager = $this->getServiceContainer()->get( 'CheckUserTokenManager' );

		$tokenField = $this->assertAndGetByElementClass( $formHtml, 'mw-checkuser-paging-links-token' );
		$actualToken = $tokenField->getAttribute( 'value' );
		$this->assertArrayEquals(
			[
				'period' => 0, 'limit' => 123, 'reason' => 'testing reason', 'offset' => '20250504050405|1',
				'dir' => 'prev', 'user' => '1.2.3.4',
			],
			$tokenManager->decode( $context->getRequest()->getSession(), $actualToken ),
			false, true,
			'CheckUser JWT token for paging returned unexpected data'
		);

		$editTokenField = $this->assertAndGetByElementClass( $formHtml, 'mw-checkuser-paging-links-edit-token' );
		$this->assertTrue(
			$context->getCsrfTokenSet()->matchToken( $editTokenField->getAttribute( 'value' ) ),
			'wpEditToken field had an invalid token specified'
		);
	}

	public function testMakeLinkWhenNoQueryProvided() {
		$opts = new FormOptions();

		$context = RequestContext::getMain();
		$objectUnderTest = TestingAccessWrapper::newFromObject( new CheckUserPagerNavigationBuilder(
			$context, $this->getServiceContainer()->get( 'CheckUserTokenQueryManager' ), $context->getCsrfTokenSet(),
			$context->getRequest(), $opts, UserIdentityValue::newAnonymous( '1.2.3.4' )
		) );

		$actualLinkHtml = $objectUnderTest->makeLink( null, 'mw-prevlink', 'prev text', 'tooltip', 'prev' );

		$pagingForm = DOMCompat::querySelector(
			DOMUtils::parseHTML( $actualLinkHtml ), '.mw-checkuser-paging-links-form'
		);
		$this->assertNull( $pagingForm, 'No paging form should be added if there $query param was null' );

		$pagingLink = DOMCompat::querySelector(
			DOMUtils::parseHTML( $actualLinkHtml ), 'span.mw-prevlink'
		);
		$this->assertNotNull( $pagingLink, 'The paging link should be rendered as text instead of a link' );
	}

	/**
	 * Calls DOMCompat::querySelectorAll, expects that it returns one valid Element object and then returns
	 * that Element object
	 *
	 * @param string $html The HTML to search through
	 * @param string $class The CSS class to search for, excluding the "." character
	 * @return Element The Element object
	 */
	private function assertAndGetByElementClass( string $html, string $class ): Element {
		$specialPageDocument = DOMUtils::parseHTML( $html );
		$element = DOMCompat::querySelectorAll( $specialPageDocument, '.' . $class );
		$this->assertCount( 1, $element, "Could not find only one element with CSS class $class in $html" );
		return $element[0];
	}
}
