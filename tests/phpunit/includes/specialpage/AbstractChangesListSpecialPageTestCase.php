<?php

use MediaWiki\Html\FormOptions;
use MediaWiki\MainConfigNames;

/**
 * Abstract base class for shared logic when testing ChangesListSpecialPage
 * and subclasses
 *
 * @group Database
 */
abstract class AbstractChangesListSpecialPageTestCase extends MediaWikiIntegrationTestCase {
	// Must be initialized by subclass
	/**
	 * @var ChangesListSpecialPage
	 */
	protected $changesListSpecialPage;

	protected $oldPatrollersGroup;

	protected function setUp(): void {
		global $wgGroupPermissions;

		parent::setUp();
		$this->overrideConfigValues( [
			MainConfigNames::RCWatchCategoryMembership => true,
			MainConfigNames::UseRCPatrol => true,
		] );

		if ( isset( $wgGroupPermissions['patrollers'] ) ) {
			$this->oldPatrollersGroup = $wgGroupPermissions['patrollers'];
		}

		$wgGroupPermissions['patrollers'] = [
			'patrol' => true,
		];

		# setup the ChangesListSpecialPage (or subclass) object
		$this->changesListSpecialPage = $this->getPageAccessWrapper();
		$context = $this->changesListSpecialPage->getContext();
		$context = new DerivativeContext( $context );
		$context->setUser( $this->getTestUser( [ 'patrollers' ] )->getUser() );
		$this->changesListSpecialPage->setContext( $context );
		$this->changesListSpecialPage->registerFilters();
	}

	/**
	 * @return ChangesListSpecialPage
	 */
	abstract protected function getPageAccessWrapper();

	protected function tearDown(): void {
		global $wgGroupPermissions;

		if ( $this->oldPatrollersGroup !== null ) {
			$wgGroupPermissions['patrollers'] = $this->oldPatrollersGroup;
		}

		parent::tearDown();
	}

	abstract public function provideParseParameters();

	/**
	 * @dataProvider provideParseParameters
	 */
	public function testParseParameters( $params, $expected ) {
		$opts = new FormOptions();
		foreach ( $expected as $key => $value ) {
			// Register it as null so sets aren't rejected.
			$opts->add(
				$key,
				null,
				FormOptions::guessType( $expected )
			);
		}

		$this->changesListSpecialPage->parseParameters(
			$params,
			$opts
		);

		$this->assertArrayEquals(
			$expected,
			$opts->getAllValues(),
			/** ordered= */ false,
			/** named= */ true
		);
	}

	/**
	 * @dataProvider validateOptionsProvider
	 */
	public function testValidateOptions(
		$optionsToSet,
		$expectedRedirect,
		$expectedRedirectOptions,
		$rcfilters
	) {
		$redirectQuery = [];
		$redirected = false;
		$output = $this->getMockBuilder( OutputPage::class )
			->disableProxyingToOriginalMethods()
			->disableOriginalConstructor()
			->getMock();
		$output->method( 'redirect' )->willReturnCallback(
			static function ( $url ) use ( &$redirectQuery, &$redirected ) {
				$urlParts = wfParseUrl( $url );
				$query = $urlParts[ 'query' ] ?? '';
				parse_str( $query, $redirectQuery );
				$redirected = true;
			}
		);

		// Disable this hook or it could break changeType
		// depending on which other extensions are running.
		$this->setTemporaryHook(
			'ChangesListSpecialPageStructuredFilters',
			null
		);

		// Give users patrol permissions so we can test that.
		$user = $this->getTestSysop()->getUser();
		$this->getServiceContainer()->getUserOptionsManager()->setOption(
			$user,
			'rcenhancedfilters-disable',
			$rcfilters ? 0 : 1
		);
		$ctx = new RequestContext();
		$ctx->setUser( $user );

		$ctx->setOutput( $output );
		$clsp = $this->changesListSpecialPage;
		$clsp->setContext( $ctx );
		$opts = $clsp->getDefaultOptions();

		foreach ( $optionsToSet as $option => $value ) {
			$opts->setValue( $option, $value );
		}

		$clsp->validateOptions( $opts );

		$this->assertEquals( $expectedRedirect, $redirected, 'redirection - ' . print_r( $optionsToSet, true ) );

		if ( $expectedRedirect ) {
			if ( count( $expectedRedirectOptions ) > 0 ) {
				$expectedRedirectOptions += [
					'title' => $clsp->getPageTitle()->getPrefixedText(),
				];
			}

			$this->assertArrayEquals(
				$expectedRedirectOptions,
				$redirectQuery,
				/* $ordered= */ false,
				/* $named= */ true,
				'redirection query'
			);
		}
	}

	abstract public function validateOptionsProvider();
}
