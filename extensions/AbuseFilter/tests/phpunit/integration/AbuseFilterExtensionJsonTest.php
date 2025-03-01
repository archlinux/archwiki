<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Tests\ExtensionJsonTestBase;

/**
 * @group Test
 * @group AbuseFilter
 * @coversNothing
 */
class AbuseFilterExtensionJsonTest extends ExtensionJsonTestBase {

	/** @inheritDoc */
	protected string $extensionJsonPath = __DIR__ . '/../../../extension.json';

	public function provideHookHandlerNames(): iterable {
		$extHookHandlers = [ 'CheckUser', 'ConfirmEdit', 'Echo', 'UserMerge' ];
		foreach ( $this->getExtensionJson()['HookHandlers'] ?? [] as $name => $specification ) {
			if ( in_array( $name, $extHookHandlers ) && !ExtensionRegistry::getInstance()->isLoaded( $name ) ) {
				continue;
			}
			yield [ $name ];
		}
	}
}
