<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OATHAuth\Enforce2FA\Mandatory2FAChecker;
use MediaWiki\Extension\OATHAuth\Key\EncryptionHelper;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\Module\WebAuthn;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\Extension\OATHAuth\WebAuthnAuthenticator;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use Wikimedia\ObjectCache\HashBagOStuff;

/** @phpcs-require-sorted-array */
return [
	'OATHAuth.EncryptionHelper' => static function ( MediaWikiServices $services ): EncryptionHelper {
		return new EncryptionHelper(
			new ServiceOptions(
				EncryptionHelper::CONSTRUCTOR_OPTIONS,
				$services->getMainConfig(),
			),
		);
	},
	'OATHAuth.Logger' => static function ( MediaWikiServices $services ): OATHAuthLogger {
		return new OATHAuthLogger(
			$services->getExtensionRegistry(),
			RequestContext::getMain(),
			LoggerFactory::getInstance( 'authentication' )
		);
	},
	'OATHAuth.Mandatory2FAChecker' => static function ( MediaWikiServices $services ): Mandatory2FAChecker {
		return new Mandatory2FAChecker(
			$services->getUserRequirementsConditionCheckerFactory(),
			$services->getRestrictedUserGroupConfigReader(),
			$services->getUserGroupManagerFactory(),
			$services->getExtensionRegistry(),
			$services->getMainConfig()
		);
	},
	'OATHAuth.ModuleRegistry' => static function ( MediaWikiServices $services ): OATHAuthModuleRegistry {
		return new OATHAuthModuleRegistry(
			$services->getDBLoadBalancerFactory(),
			$services->getObjectFactory(),
			ExtensionRegistry::getInstance()->getAttribute( 'OATHAuthModules' ),
		);
	},
	'OATHAuth.UserRepository' => static function ( MediaWikiServices $services ): OATHUserRepository {
		return new OATHUserRepository(
			$services->getDBLoadBalancerFactory(),
			new HashBagOStuff( [
				'maxKey' => 5
			] ),
			$services->getService( 'OATHAuth.ModuleRegistry' ),
			$services->getCentralIdLookupFactory(),
			LoggerFactory::getInstance( 'authentication' )
		);
	},
	'OATHAuth.WebAuthnAuthenticator' => static function ( MediaWikiServices $services ): WebAuthnAuthenticator {
		/** @var OATHAuthModuleRegistry $moduleRegistry */
		$moduleRegistry = $services->getService( 'OATHAuth.ModuleRegistry' );

		/** @var WebAuthn $webAuthn */
		$webAuthn = $moduleRegistry->getModuleByKey( WebAuthn::MODULE_ID );
		/** @var RecoveryCodes $recovery */
		$recovery = $moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME );

		return new WebAuthnAuthenticator(
			$services->getService( 'OATHAuth.UserRepository' ),
			$webAuthn,
			$recovery,
			$services->getService( 'OATHAuth.Logger' ),
			RequestContext::getMain(),
			LoggerFactory::getInstance( 'authentication' ),
			$services->getAuthManager(),
			$services->getUrlUtils(),
			$services->getUserFactory(),
		);
	}
];
