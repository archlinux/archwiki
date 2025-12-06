<?php

namespace MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers;

use MediaWiki\CheckUser\CheckUser\SpecialCheckUser;
use MediaWiki\CheckUser\ClientHints\ClientHintsLookupResults;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Services\UserAgentClientHintsFormatter;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWiki\CheckUser\Tests\Integration\CheckUser\Pagers\Mocks\MockTemplateParser;
use MediaWiki\Config\ConfigException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Output\OutputPage;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\ArrayUtils\ArrayUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Test class for CheckUserGetUsersPager class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\CheckUser\Pagers\CheckUserGetUsersPager
 */
class CheckUserGetUsersPagerTest extends CheckUserPagerTestBase {
	use CheckUserClientHintsCommonTraitTest;

	protected function setUp(): void {
		parent::setUp();

		$this->checkSubtype = SpecialCheckUser::SUBTYPE_GET_USERS;
		$this->defaultUserIdentity = UserIdentityValue::newAnonymous( '127.0.0.1' );
		$this->defaultCheckType = 'ipusers';
	}

	/** @dataProvider provideFormatUserRow */
	public function testFormatUserRow(
		$userSets, $userText, $clientHintsLookupResults, $expectedTemplateParams
	) {
		$objectUnderTest = $this->setUpObject();
		$objectUnderTest->templateParser = new MockTemplateParser();
		$objectUnderTest->userSets = $userSets;
		$objectUnderTest->clientHintsLookupResults = $clientHintsLookupResults;
		$objectUnderTest->mQueryDone = true;
		$objectUnderTest->mResult = new FakeResultWrapper( [] );
		$objectUnderTest->formatUserRow( $userText );
		$this->assertNotNull(
			$objectUnderTest->templateParser->lastCalledWith,
			'The template parser was not called by ::formatUserRow.'
		);
		$this->assertSame(
			'GetUsersLine',
			$objectUnderTest->templateParser->lastCalledWith[0],
			'::formatUserRow did not call the correct mustache file.'
		);
		$this->assertArrayEquals(
			$expectedTemplateParams,
			array_filter(
				$objectUnderTest->templateParser->lastCalledWith[1],
				static function ( $key ) use ( $expectedTemplateParams ) {
					return array_key_exists( $key, $expectedTemplateParams );
				},
				ARRAY_FILTER_USE_KEY
			),
			false,
			true,
			'The template parameters do not match the expected template parameters. If changes have been ' .
			'made to the template parameters make sure you update the tests.'
		);
	}

	public function testFormatUserRowWithClientHintsEnabled() {
		$smallestFakeTimestamp = ConvertibleTimestamp::convert(
			TS_MW,
			ConvertibleTimestamp::time() - 1600
		);
		$largestFakeTimestamp = ConvertibleTimestamp::now();
		/** @var UserAgentClientHintsFormatter $clientHintsFormatter */
		$clientHintsFormatter = $this->getServiceContainer()->get( 'UserAgentClientHintsFormatter' );
		$exampleClientHintsDataObject = self::getExampleClientHintsDataObjectFromJsApi();
		$formattedExampleClientHintsDataObject = $clientHintsFormatter
			->formatClientHintsDataObject( $exampleClientHintsDataObject );
		$this->testFormatUserRow(
			[
				'first' => [ '127.0.0.1' => $smallestFakeTimestamp ],
				'last' => [ '127.0.0.1' => $largestFakeTimestamp ],
				'edits' => [ '127.0.0.1' => 123 ],
				'ids' => [ '127.0.0.1' => 0 ],
				'infosets' => [ '127.0.0.1' => [ [ '127.0.0.1', null ], [ '127.0.0.1', '124.5.6.7' ] ] ],
				'agentsets' => [ '127.0.0.1' => [ 'Testing user agent', 'Testing useragent2' ] ],
				'clienthints' => [
					'127.0.0.1' => new ClientHintsReferenceIds( [
						UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1 ],
						UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [ 123, 2 ],
					] ),
				],
			],
			'127.0.0.1',
			new ClientHintsLookupResults(
				[
					UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [
						1 => 0,
					],
					UserAgentClientHintsManager::IDENTIFIER_CU_LOG_EVENT => [
						123 => 0,
					],
				],
				[
					0 => $exampleClientHintsDataObject,
				],
			),
			[
				'userText' => '127.0.0.1',
				'editCount' => 123,
				'agentsList' => [ 'Testing useragent2', 'Testing user agent' ],
				'clientHintsList' => [ $formattedExampleClientHintsDataObject ],
			],
		);
	}

	public static function provideFormatUserRow() {
		// @todo Test more template parameters.
		$smallestFakeTimestamp = ConvertibleTimestamp::convert(
			TS_MW,
			ConvertibleTimestamp::time() - 1600
		);
		$largestFakeTimestamp = ConvertibleTimestamp::now();
		return [
			'Row for IP address' => [
				// $object->userSets
				[
					'first' => [ '127.0.0.1' => $smallestFakeTimestamp ],
					'last' => [ '127.0.0.1' => $largestFakeTimestamp ],
					'edits' => [ '127.0.0.1' => 123 ],
					'ids' => [ '127.0.0.1' => 0 ],
					'infosets' => [ '127.0.0.1' => [ [ '127.0.0.1', null ], [ '127.0.0.1', '124.5.6.7' ] ] ],
					'agentsets' => [ '127.0.0.1' => [ 'Testing user agent', 'Testing useragent2' ] ],
					'clienthints' => [ '127.0.0.1' => new ClientHintsReferenceIds( [
						UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1 ],
					] ) ],
				],
				// $user_text parameter
				'127.0.0.1',
				// $object->clientHintsLookupResults
				new ClientHintsLookupResults( [], [] ),
				// Expected template parameters.
				[
					'userText' => '127.0.0.1',
					'editCount' => 123,
					'agentsList' => [ 'Testing useragent2', 'Testing user agent' ],
				],
			],
		];
	}

	/** @dataProvider provideFormatUserRowWithUsernameHidden */
	public function testFormatUserRowWithUsernameHidden( $authorityCanSeeUser ) {
		// Get a test user and then block it with 'hideuser' enabled.
		$hiddenUser = $this->getMutableTestUser()->getUser();
		$blockingUser = $this->getTestUser( [ 'sysop', 'suppress' ] )->getUser();
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$hiddenUser, $blockingUser, 'infinity',
				'block to hide the test user', [ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );

		$smallestFakeTimestamp = ConvertibleTimestamp::convert(
			TS_MW,
			ConvertibleTimestamp::time() - 1600
		);
		$largestFakeTimestamp = ConvertibleTimestamp::now();
		$objectUnderTest = $this->setUpObject();

		// Set the user who is viewing the row in the results.
		$viewUserGroups = [ 'checkuser' ];
		if ( $authorityCanSeeUser ) {
			$viewUserGroups[] = 'suppress';
		}
		RequestContext::getMain()->setUser( $this->getTestUser( $viewUserGroups )->getUser() );

		$objectUnderTest->templateParser = new MockTemplateParser();
		$objectUnderTest->userSets = [
			'first' => [ $hiddenUser->getName() => $smallestFakeTimestamp ],
			'last' => [ $hiddenUser->getName() => $largestFakeTimestamp ],
			'edits' => [ $hiddenUser->getName() => 123 ],
			'ids' => [ $hiddenUser->getName() => $hiddenUser->getId() ],
			'infosets' => [ $hiddenUser->getName() => [ [ '127.0.0.1', null ], [ '127.0.0.1', '124.5.6.7' ] ] ],
			'agentsets' => [ $hiddenUser->getName() => [ 'Testing user agent', 'Testing useragent2' ] ],
			'clienthints' => [ $hiddenUser->getName() => new ClientHintsReferenceIds( [
				UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1 ],
			] ) ],
		];
		$objectUnderTest->clientHintsLookupResults = new ClientHintsLookupResults( [], [] );
		// Add a fake row to the mResult so that we can say at least one row is present. The data is read from
		// userSets, so this row can be anything.
		$objectUnderTest->mQueryDone = true;
		$objectUnderTest->mResult = new FakeResultWrapper( [ [ 'test' ] ] );
		$objectUnderTest->formatUserRow( $hiddenUser->getName() );
		$this->assertNotNull(
			$objectUnderTest->templateParser->lastCalledWith,
			'The template parser was not called by ::formatUserRow.'
		);
		$this->assertSame(
			'GetUsersLine',
			$objectUnderTest->templateParser->lastCalledWith[0],
			'::formatUserRow did not call the correct mustache file.'
		);
		$expectedTemplateParams = [
			'userText' => $authorityCanSeeUser ? $hiddenUser->getName() : '',
			'editCount' => 123,
			'agentsList' => [ 'Testing useragent2', 'Testing user agent' ],
			'clientHintsList' => [],
		];
		$this->assertArrayEquals(
			$expectedTemplateParams,
			array_filter(
				$objectUnderTest->templateParser->lastCalledWith[1],
				static function ( $key ) use ( $expectedTemplateParams ) {
					return array_key_exists( $key, $expectedTemplateParams );
				},
				ARRAY_FILTER_USE_KEY
			),
			false,
			true,
			'The template parameters do not match the expected template parameters. If changes have been ' .
			'made to the template parameters make sure you update the tests.'
		);
	}

	public static function provideFormatUserRowWithUsernameHidden() {
		return [
			'Authority can see hidden user' => [ true ],
			'Authority cannot see hidden user' => [ false ],
		];
	}

	/** @dataProvider provideFormatUserRowCanPerformBlocks */
	public function testFormatUserRowCanPerformBlocks( $canPerformBlocks ) {
		$objectUnderTest = $this->setUpObject();
		$objectUnderTest->canPerformBlocks = $canPerformBlocks;
		$timestamp = ConvertibleTimestamp::now();
		$objectUnderTest->templateParser = new MockTemplateParser();
		$objectUnderTest->userSets = [
			'first' => [ '127.0.0.1' => $timestamp ],
			'last' => [ '127.0.0.1' => $timestamp ],
			'edits' => [ '127.0.0.1' => 1 ],
			'ids' => [ '127.0.0.1' => 0 ],
			'infosets' => [ '127.0.0.1' => [ [ '127.0.0.1', null ], [ '127.0.0.1', '124.5.6.7' ] ] ],
			'agentsets' => [ '127.0.0.1' => [ 'Testing user agent' ] ],
			'clienthints' => [ '127.0.0.1' => new ClientHintsReferenceIds( [] ) ],
		];
		$objectUnderTest->clientHintsLookupResults = new ClientHintsLookupResults( [], [] );
		$expectedTemplateParams = [
			'canPerformBlocksOrLocks' => $canPerformBlocks,
			'userText' => '127.0.0.1',
			'editCount' => 1,
			'agentsList' => [ 'Testing user agent' ],
		];
		// Add a fake row to the mResult so that we can say at least one row is present. The data is read from
		// userSets, so this row can be anything.
		$objectUnderTest->mQueryDone = true;
		$objectUnderTest->mResult = new FakeResultWrapper( [ [ 'test' ] ] );
		$objectUnderTest->formatUserRow( '127.0.0.1' );
		$this->assertNotNull(
			$objectUnderTest->templateParser->lastCalledWith,
			'The template parser was not called by ::formatUserRow.'
		);
		$this->assertSame(
			'GetUsersLine',
			$objectUnderTest->templateParser->lastCalledWith[0],
			'::formatUserRow did not call the correct mustache file.'
		);
		$this->assertArrayEquals(
			$expectedTemplateParams,
			array_filter(
				$objectUnderTest->templateParser->lastCalledWith[1],
				static function ( $key ) use ( $expectedTemplateParams ) {
					return array_key_exists( $key, $expectedTemplateParams );
				},
				ARRAY_FILTER_USE_KEY
			),
			false,
			true,
			'The template parameters do not match the expected template parameters. If changes have been ' .
			'made to the template parameters make sure you update the tests.'
		);
	}

	public static function provideFormatUserRowCanPerformBlocks() {
		return [
			'Can perform blocks' => [ true ],
			'Cannot perform blocks' => [ false ],
		];
	}

	public function testFormatUserRowWhenGlobalBlockingLinkPresent() {
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );

		$this->overrideConfigValue(
			'CheckUserGBtoollink',
			[ 'centralDB' => WikiMap::getCurrentWikiId(), 'groups' => [ 'steward' ] ]
		);
		$objectUnderTest = $this->setUpObject();

		// Set the user who is viewing the row to have the rights to globally block (i.e. steward group).
		$testUser = $this->getTestUser( [ 'checkuser', 'steward' ] )->getUser();
		RequestContext::getMain()->setUser( $testUser );
		if ( ExtensionRegistry::getInstance()->isLoaded( 'CentralAuth' ) ) {
			// If CentralAuth is loaded, we need to also set the CentralUser to have the steward group globally
			// for the test to work.
			$centralAuthUser = CentralAuthUser::getInstanceByName( $testUser->getName() );
			$centralAuthUser->addToGlobalGroup( 'steward' );
		}
		$this->setUserLang( 'qqx' );

		$testUser = $this->getTestUser()->getUserIdentity();
		$objectUnderTest->templateParser = new MockTemplateParser();
		$objectUnderTest->userSets = [
			'first' => [ $testUser->getName() => "20240405060708" ],
			'last' => [ $testUser->getName() => "20240405060709" ],
			'edits' => [ $testUser->getName() => 123 ],
			'ids' => [ $testUser->getName() => $testUser->getId() ],
			'infosets' => [ $testUser->getName() => [ [ '127.0.0.1', null ] ] ],
			'agentsets' => [ $testUser->getName() => [ 'Testing user agent' ] ],
			'clienthints' => [ $testUser->getName() => new ClientHintsReferenceIds( [
				UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => [ 1 ],
			] ) ],
		];
		$objectUnderTest->clientHintsLookupResults = new ClientHintsLookupResults( [], [] );
		$objectUnderTest->mQueryDone = true;
		$objectUnderTest->mResult = new FakeResultWrapper( [] );
		$objectUnderTest->formatUserRow( $testUser->getName() );
		// Expect that a globalBlockLink template parameter has been added and that is contains the expected link.
		$this->assertNotNull(
			$objectUnderTest->templateParser->lastCalledWith,
			'The template parser was not called by ::formatUserRow.'
		);
		$this->assertArrayHasKey(
			'globalBlockLink',
			$objectUnderTest->templateParser->lastCalledWith[1],
			'The global block link was not added to the template parameters.'
		);
		$this->assertStringContainsString(
			'(globalblocking-block-submit',
			$objectUnderTest->templateParser->lastCalledWith[1]['globalBlockLink'],
			'The global block link does not contain the expected link title property'
		);
		$this->assertStringContainsString(
			'Special:GlobalBlock',
			$objectUnderTest->templateParser->lastCalledWith[1]['globalBlockLink'],
			'The global blocking special page was not present inside the global block link'
		);
	}

	/** @dataProvider provideGetQueryInfo */
	public function testGetQueryInfo( $xfor, $table, $expectedQueryInfo ) {
		$this->overrideConfigValue( 'CheckUserCIDRLimit', [ 'IPv4' => 16, 'IPv6' => 19 ] );
		$target = UserIdentityValue::newAnonymous( '127.0.0.1' );
		// Add the IExpression for the IP target as a string to the expected query info for comparison.
		$expectedQueryInfo['conds'][] = $this->getServiceContainer()->get( 'CheckUserLookupUtils' )
			->getIPTargetExpr( $target, $xfor, $table )
			->toSql( $this->getDb() );
		$this->commonTestGetQueryInfo( $target, $xfor, $table, $expectedQueryInfo );
	}

	public static function provideGetQueryInfo() {
		return [
			'cu_changes table' => [
				// The xfor property of the object (false for normal IP address or true for XFF IP)
				false,
				// The $table argument to ::getQueryInfo
				'cu_changes',
				// The expected query info returned by ::getQueryInfo (we are only interested in testing the query info
				// added by ::getQueryInfo and not the info added by the table specific methods).
				[
					'tables' => [ 'cu_changes' ],
					'conds' => [],
					'options' => [ 'USE INDEX' => [ 'cu_changes' => 'cuc_ip_hex_time' ] ],
					// Verify that fields and join_conds set as arrays, but we are not testing their values.
					'fields' => [], 'join_conds' => [],
				],
			],
			'cu_log_event table' => [
				false, 'cu_log_event',
				[
					'tables' => [ 'cu_log_event' ],
					'conds' => [],
					'options' => [ 'USE INDEX' => [ 'cu_log_event' => 'cule_ip_hex_time' ] ],
					'fields' => [], 'join_conds' => [],
				],
			],
			'cu_private_event table' => [
				false, 'cu_private_event',
				[
					'tables' => [ 'cu_private_event' ],
					'conds' => [],
					'options' => [ 'USE INDEX' => [ 'cu_private_event' => 'cupe_ip_hex_time' ] ],
					'fields' => [], 'join_conds' => [],
				],
			],
			'cu_changes table with XFF IP' => [
				true, 'cu_changes',
				[
					'tables' => [ 'cu_changes' ],
					'conds' => [],
					'options' => [ 'USE INDEX' => [ 'cu_changes' => 'cuc_xff_hex_time' ] ],
					'fields' => [], 'join_conds' => [],
				],
			],
		];
	}

	/** @inheritDoc */
	protected function getDefaultRowFieldValues(): array {
		return [
			'timestamp' => ConvertibleTimestamp::now(),
			'ip' => '127.0.0.1',
			'agent' => '',
			'xff' => '',
			'user' => 0,
			'user_text' => '127.0.0.1',
		];
	}

	public function testGetEndBodyThrowsIfCentralAuthMissingButCheckUserCAMultiLockSet() {
		// Define $wgCheckUserCAMultiLockSet to anything other than false
		$this->overrideConfigValue( 'CheckUserCAMultiLock', [] );
		// Get the object under test and set the extensionRegistry to return false for 'CentralAuth'.
		$objectUnderTest = $this->setUpObject();
		$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
		$mockExtensionRegistry->method( 'isLoaded' )->with( 'CentralAuth' )->willReturn( false );
		$objectUnderTest->extensionRegistry = $mockExtensionRegistry;
		$objectUnderTest->mQueryDone = true;
		$objectUnderTest->mResult = new FakeResultWrapper( [ [ 'test' ] ] );
		// Call ::shouldShowBlockFieldset and expect an exception.
		$this->expectException( ConfigException::class );
		$objectUnderTest->getEndBody();
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

	/** @dataProvider provideGetEndBodyForBlockFieldset */
	public function testGetEndBodyForBlockFieldset(
		bool $hasLocalBlockRights, bool $hasGlobalLockRights, bool $hasGlobalBlockRights
	) {
		// Set the centralDB as a non-existent but valid format wiki ID. This will cause the URLs to fallback to
		// the local URLs which are easier to test.
		$centralAuthLoaded = $this->getServiceContainer()->getExtensionRegistry()->isLoaded( 'CentralAuth' );
		if ( $centralAuthLoaded ) {
			$this->overrideConfigValue(
				'CheckUserCAMultiLock', [ 'centralDB' => 'otherwiki', 'groups' => [ 'globallock' ] ]
			);
		}
		$this->overrideConfigValue(
			'CheckUserGBtoollink', [ 'centralDB' => 'otherwiki', 'groups' => [ 'globalblock' ] ]
		);

		// Make the test user have the necessary groups based on the configured rights they should have.
		$groups = [ 'checkuser' ];
		if ( $hasLocalBlockRights ) {
			$groups[] = 'sysop';
		}
		if ( $hasGlobalLockRights ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
			$this->setGroupPermissions( 'globallock', 'centralauth-lock', true );
			$groups[] = 'globallock';
		}
		if ( $hasGlobalBlockRights ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );
			$this->setGroupPermissions( 'globalblock', 'globalblock', true );
			$groups[] = 'globalblock';
		}

		$objectUnderTest = $this->setUpObject( null, null, $groups );

		// Add the local groups as global groups to the test user if CentralAuth, as the global lock and block
		// checks use the global groups.
		if ( $centralAuthLoaded ) {
			$localUser = $objectUnderTest->getUser();
			$centralUser = CentralAuthUser::getInstanceByName( $localUser );
			$centralUser->register( wfRandomString(), null );
			$centralUser->attach( WikiMap::getCurrentWikiId() );
			foreach ( $groups as $group ) {
				$centralUser->addToGlobalGroup( $group );
			}
		}

		// Add one fake result row to the mResult in the object under test.
		$objectUnderTest->mQueryDone = true;
		$objectUnderTest->mResult = new FakeResultWrapper( [ [ 'test' ] ] );
		// We need to set a title for the RequestContext for HTMLForm.
		RequestContext::getMain()->setTitle( SpecialPage::getTitleFor( 'CheckUser' ) );
		$this->setUserLang( 'qqx' );

		$html = $objectUnderTest->getEndBody();
		/** @var OutputPage $output */
		$output = $objectUnderTest->getOutput();

		if ( !$hasLocalBlockRights && !$hasGlobalLockRights && !$hasGlobalBlockRights ) {
			$this->assertStringNotContainsString( 'mw-checkuser-massblock', $html );
			$this->assertStringNotContainsString( 'mw-checkbox-toggle-controls', $html );
			$this->assertStringNotContainsString( '(checkuser-massblock', $html );
			$fieldsetHtml = $html;

			$this->assertNotContains( 'ext.checkUser', $output->getModules() );
		} else {
			$this->assertStringContainsString( 'mw-checkbox-toggle-controls', $html );
			$this->assertStringContainsString( '(checkuser-massblock-text', $html );
			$fieldsetHtml = $this->assertAndGetByElementClass( $html, 'mw-checkuser-massblock' );

			$this->assertContains( 'ext.checkUser', $output->getModules() );
		}

		// Check that the local block buttons are added if they should be
		if ( $hasLocalBlockRights ) {
			$this->assertStringContainsString( '(checkuser-massblock-commit-accounts', $fieldsetHtml );
			$this->assertStringContainsString( '(checkuser-massblock-commit-ips', $fieldsetHtml );

			// Section title only exists if the global block buttons also displayed
			if ( $hasGlobalBlockRights ) {
				$this->assertStringContainsString( '(checkuser-massblock-localblocks-section', $fieldsetHtml );
			} else {
				$this->assertStringNotContainsString( '(checkuser-massblock-localblocks-section', $fieldsetHtml );
			}
		} else {
			$this->assertStringNotContainsString( '(checkuser-massblock-commit-accounts', $fieldsetHtml );
			$this->assertStringNotContainsString( '(checkuser-massblock-commit-ips', $fieldsetHtml );

			if ( $hasGlobalBlockRights || $hasGlobalLockRights ) {
				$this->assertStringContainsString(
					'(checkuser-massblock-text-without-local-block-buttons', $fieldsetHtml
				);
			}
		}

		// Check that Special:MassGlobalBlock buttons are added if they should be
		if ( $hasGlobalBlockRights ) {
			$this->assertStringContainsString( '(checkuser-massglobalblock-commit-accounts', $fieldsetHtml );
			$this->assertStringContainsString( '(checkuser-massglobalblock-commit-ips', $fieldsetHtml );

			// Section title only exists if the local block buttons also displayed
			if ( $hasLocalBlockRights ) {
				$this->assertStringContainsString( '(checkuser-massblock-globalblocks-section', $fieldsetHtml );
			} else {
				$this->assertStringNotContainsString( '(checkuser-massblock-globalblocks-section', $fieldsetHtml );
			}

			$this->assertArrayContains(
				[ 'wgCUMassGlobalBlockUrl' => '/wiki/Special:MassGlobalBlock' ],
				$output->getJsConfigVars()
			);
		} else {
			$this->assertArrayNotHasKey( 'wgCUMassGlobalBlockUrl', $output->getJsConfigVars() );
			$this->assertStringNotContainsString( '(checkuser-massglobalblock-commit-accounts', $fieldsetHtml );
			$this->assertStringNotContainsString( '(checkuser-massglobalblock-commit-ips', $fieldsetHtml );
		}

		// Check that MultiLock URL is set if it should be
		if ( $hasGlobalLockRights ) {
			$this->assertArrayContains(
				[ 'wgCUCAMultiLockCentral' => '/wiki/Special:MultiLock' ],
				$output->getJsConfigVars()
			);
		} else {
			$this->assertArrayNotHasKey( 'wgCUCAMultiLockCentral', $output->getJsConfigVars() );
		}
	}

	public static function provideGetEndBodyForBlockFieldset(): iterable {
		$testCases = ArrayUtils::cartesianProduct(
			// Has local block rights
			[ true, false ],
			// Has global lock rights
			[ true, false ],
			// Has global block rights
			[ true, false ],
		);

		foreach ( $testCases as $params ) {
			yield sprintf(
				'%s local block rights, %s global lock rights, %s global block rights',
				$params[0] ? 'has' : 'does not have',
				$params[1] ? 'has' : 'does not have',
				$params[2] ? 'has' : 'does not have'
			) => $params;
		}
	}

	public function testGetStartBodyWhenNoResults() {
		$objectUnderTest = $this->setUpObject( null, null, [ 'checkuser', 'sysop' ] );
		// Simulate that no results are present
		$objectUnderTest->mQueryDone = true;
		$objectUnderTest->mResult = new FakeResultWrapper( [] );
		// We need to set a title for the RequestContext for HTMLForm.
		RequestContext::getMain()->setTitle( SpecialPage::getTitleFor( 'CheckUser' ) );
		// Assert that the list toggle is not added.
		$html = $objectUnderTest->getStartBody();
		$this->assertStringNotContainsString( 'mw-checkbox-toggle-controls', $html );
		// Check that the class for the CheckUser results is added.
		$this->assertStringContainsString( 'mw-checkuser-get-users-results', $html );
	}

	public function testGetStartBodyWhenUserHasLocalBlockRights() {
		$objectUnderTest = $this->setUpObject( null, null, [ 'checkuser', 'sysop' ] );
		// We need to set a title for the RequestContext for HTMLForm.
		RequestContext::getMain()->setTitle( SpecialPage::getTitleFor( 'CheckUser' ) );
		// Add one fake result row to the mResult in the object under test.
		$objectUnderTest->mQueryDone = true;
		$objectUnderTest->mResult = new FakeResultWrapper( [ [ 'test' ] ] );
		$html = $objectUnderTest->getStartBody();
		// Assert that the list toggle is added.
		$this->assertStringContainsString( 'mw-checkbox-toggle-controls', $html );
		// Check that the class for the CheckUser results is added.
		$this->assertStringContainsString( 'mw-checkuser-get-users-results', $html );
	}
}
