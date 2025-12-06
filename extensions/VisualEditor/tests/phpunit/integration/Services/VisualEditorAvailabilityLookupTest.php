<?php

namespace MediaWiki\Extension\VisualEditor\Tests\Integration\Services;

use MediaWiki\Extension\VisualEditor\Services\VisualEditorAvailabilityLookup;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use MockTitleTrait;
use Wikimedia\ScopedCallback;

/**
 * @covers \MediaWiki\Extension\VisualEditor\Services\VisualEditorAvailabilityLookup
 */
class VisualEditorAvailabilityLookupTest extends MediaWikiIntegrationTestCase {

	use MockTitleTrait;

	private function getObjectUnderTest(): VisualEditorAvailabilityLookup {
		return $this->getServiceContainer()->get( VisualEditorAvailabilityLookup::SERVICE_NAME );
	}

	public function testIsAllowedNamespace() {
		$this->overrideConfigValue( 'VisualEditorAvailableNamespaces', [ NS_MAIN => true, NS_TALK => false ] );

		$objectUnderTest = $this->getObjectUnderTest();
		$this->assertTrue( $objectUnderTest->isAllowedNamespace( 0 ) );
		$this->assertFalse( $objectUnderTest->isAllowedNamespace( 1 ) );
	}

	public function testGetAvailableNamespaceIds() {
		$scopedCallback = $this->getServiceContainer()->getExtensionRegistry()->setAttributeForTest(
			'VisualEditorAvailableNamespaces',
			[ 'User' => true, 'Template_Talk' => true ]
		);

		$this->overrideConfigValue(
			'VisualEditorAvailableNamespaces',
			[
				0 => true,
				1 => false,
				-1 => true,
				999999 => true,
				2 => false,
				'Template' => true,
				'Foobar' => true,
			]
		);

		$this->assertArrayEquals(
			[ -1, 0, 10, 11 ],
			$this->getObjectUnderTest()->getAvailableNamespaceIds()
		);

		ScopedCallback::consume( $scopedCallback );
	}

	public function testIsAllowedContentType() {
		$this->overrideConfigValue( 'VisualEditorAvailableContentModels', [ 'on' => true, 'off' => false ] );

		$objectUnderTest = $this->getObjectUnderTest();
		$this->assertTrue( $objectUnderTest->isAllowedContentType( 'on' ) );
		$this->assertFalse( $objectUnderTest->isAllowedContentType( 'off' ) );
		$this->assertFalse( $objectUnderTest->isAllowedContentType( 'unknown' ) );
	}

	/** @dataProvider provideIsAvailable */
	public function testIsAvailable(
		string $contentModel, int $namespace, string $veActionValue, array $userOptions,
		bool $enableBetaFeatureConfigValue, bool $expectedReturnValue
	) {
		$this->overrideConfigValues( [
			'VisualEditorEnableBetaFeature' => $enableBetaFeatureConfigValue,
			'VisualEditorAvailableContentModels' => [ 'on' => true, 'off' => false ],
			'VisualEditorAvailableNamespaces' => [ NS_MAIN => true, NS_TALK => false ],
		] );
		$this->setService(
			'UserOptionsLookup',
			new StaticUserOptionsLookup( [], $userOptions )
		);

		$title = $this->makeMockTitle( 'Test', [ 'namespace' => $namespace, 'contentModel' => $contentModel ] );

		$request = new FauxRequest();
		$request->setVal( 'veaction', $veActionValue );

		$userIdentity = UserIdentityValue::newRegistered( 1, 'TestUser' );

		$this->assertSame(
			$expectedReturnValue,
			$this->getObjectUnderTest()->isAvailable( $title, $request, $userIdentity )
		);
	}

	public static function provideIsAvailable(): array {
		return [
			'Content model is not supported' => [
				'contentModel' => 'off', 'namespace' => NS_MAIN, 'veaction' => 'edit', 'userOptions' => [],
				'enableBetaFeatureConfigValue' => false, 'expectedReturnValue' => false,
			],
			'Namespace is not supported' => [
				'contentModel' => 'on', 'namespace' => NS_TALK, 'veaction' => '', 'userOptions' => [],
				'enableBetaFeatureConfigValue' => false, 'expectedReturnValue' => false,
			],
			'User has disabled VisualEditor via visualeditor-autodisable preference' => [
				'contentModel' => 'on', 'namespace' => NS_MAIN, 'veaction' => '',
				'userOptions' => [ 'visualeditor-autodisable' => true ], 'enableBetaFeatureConfigValue' => false,
				'expectedReturnValue' => false,
			],
			'User has disabled VisualEditor via visualeditor-enable preference' => [
				'contentModel' => 'on', 'namespace' => NS_MAIN, 'veaction' => '',
				'userOptions' => [ 'visualeditor-autodisable' => false, 'visualeditor-enable' => false ],
				'enableBetaFeatureConfigValue' => true, 'expectedReturnValue' => false,
			],
			'User has disabled VisualEditor via visualeditor-betatempdisable preference' => [
				'contentModel' => 'on', 'namespace' => NS_MAIN, 'veaction' => '',
				'userOptions' => [ 'visualeditor-autodisable' => false, 'visualeditor-betatempdisable' => true ],
				'enableBetaFeatureConfigValue' => false, 'expectedReturnValue' => false,
			],
			'Namespace is not supported, but veaction=edit is set in request' => [
				'contentModel' => 'on', 'namespace' => NS_TALK, 'veaction' => 'edit', 'userOptions' => [],
				'enableBetaFeatureConfigValue' => false, 'expectedReturnValue' => true,
			],
			'VisualEditor supported on page and user has it enabled' => [
				'contentModel' => 'on', 'namespace' => NS_MAIN, 'veaction' => '',
				'userOptions' => [ 'visualeditor-autodisable' => false, 'visualeditor-enable' => true ],
				'enableBetaFeatureConfigValue' => false, 'expectedReturnValue' => true,
			],
		];
	}
}
