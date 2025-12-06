<?php

namespace MediaWiki\CheckUser\Tests\Integration;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Tests\ExtensionJsonTestBase;

/**
 * @group CheckUser
 * @coversNothing
 */
class CheckUserExtensionJsonTest extends ExtensionJsonTestBase {

	/** @inheritDoc */
	protected static string $extensionJsonPath = __DIR__ . '/../../../extension.json';

	public static function provideHookHandlerNames(): iterable {
		$extHookHandlers = [
			'AbuseFilterHandler' => 'Abuse Filter',
			'CentralAuthHandler' => 'CentralAuth',
			'GlobalPreferencesHandler' => 'GlobalPreferences',
			'GlobalBlockingHandler' => 'GlobalBlocking',
			'IPInfoHandler' => 'IPInfo',
			'UserMerge' => 'UserMerge',
		];
		foreach ( self::getExtensionJson()['HookHandlers'] ?? [] as $name => $specification ) {
			if (
				array_key_exists( $name, $extHookHandlers ) &&
				!ExtensionRegistry::getInstance()->isLoaded( $extHookHandlers[$name] )
			) {
				continue;
			}
			yield [ $name ];
		}
	}
}
