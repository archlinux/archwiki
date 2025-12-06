<?php

namespace MediaWiki\Extension\ConfirmEdit;

use BadMethodCallException;
use MediaWiki\Api\Hook\APIGetAllowedParamsHook;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Content\Content;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\ConfirmEdit\Auth\CaptchaAuthenticationRequest;
use MediaWiki\Extension\ConfirmEdit\FancyCaptcha\FancyCaptcha;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\HCaptcha;
use MediaWiki\Extension\ConfirmEdit\Hooks\HookRunner;
use MediaWiki\Extension\ConfirmEdit\QuestyCaptcha\QuestyCaptcha;
use MediaWiki\Extension\ConfirmEdit\ReCaptchaNoCaptcha\ReCaptchaNoCaptcha;
use MediaWiki\Extension\ConfirmEdit\SimpleCaptcha\SimpleCaptcha;
use MediaWiki\Extension\ConfirmEdit\Turnstile\Turnstile;
use MediaWiki\Hook\AlternateEditPreviewHook;
use MediaWiki\Hook\EditFilterMergedContentHook;
use MediaWiki\Hook\EditPage__showEditForm_fieldsHook;
use MediaWiki\Hook\EditPageBeforeEditButtonsHook;
use MediaWiki\Hook\EmailUserFormHook;
use MediaWiki\Hook\EmailUserHook;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Hook\TitleReadWhitelistHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\SpecialPage\Hook\AuthChangeFormFieldsHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Storage\Hook\PageSaveCompleteHook;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ReflectionClass;
use Wikimedia\IPUtils;
use Wikimedia\ObjectCache\WANObjectCache;

class Hooks implements
	AlternateEditPreviewHook,
	EditPageBeforeEditButtonsHook,
	EmailUserFormHook,
	EmailUserHook,
	TitleReadWhitelistHook,
	ResourceLoaderRegisterModulesHook,
	PageSaveCompleteHook,
	EditPage__showEditForm_fieldsHook,
	EditFilterMergedContentHook,
	APIGetAllowedParamsHook,
	AuthChangeFormFieldsHook
{

	/**
	 * @var SimpleCaptcha[][] Captcha instances, where the keys are action => captcha type and the
	 *   values are an instance of that captcha type.
	 */
	protected static array $instance = [];

	private WANObjectCache $cache;

	public function __construct(
		WANObjectCache $cache
	) {
		$this->cache = $cache;
	}

	/**
	 * Get the global Captcha instance for a specific action.
	 *
	 * If a specific Captcha is not defined in $wgCaptchaTriggers[$action]['class'],
	 * $wgCaptchaClass will be returned instead.
	 *
	 * @stable to call - May be used by code not visible in codesearch
	 */
	public static function getInstance( string $action = '' ): SimpleCaptcha {
		static $map = [
			'SimpleCaptcha' => SimpleCaptcha::class,
			'FancyCaptcha' => FancyCaptcha::class,
			'QuestyCaptcha' => QuestyCaptcha::class,
			'ReCaptchaNoCaptcha' => ReCaptchaNoCaptcha::class,
			'HCaptcha' => HCaptcha::class,
			'Turnstile' => Turnstile::class,
		];

		$config = MediaWikiServices::getInstance()->getMainConfig();
		$captchaTriggers = $config->get( 'CaptchaTriggers' );
		$defaultCaptchaClass = $config->get( 'CaptchaClass' );

		// Check for the newer style captcha trigger array
		$class = $captchaTriggers[$action]['class'] ?? $defaultCaptchaClass;

		$hookRunner = new HookRunner(
			MediaWikiServices::getInstance()->getHookContainer()
		);
		// Allow hook implementers to override the class that's about to be cached.
		$hookRunner->onConfirmEditCaptchaClass( $action, $class );

		if ( !isset( static::$instance[$action][$class] ) ) {
			// There is not a cached instance, construct a new one based on the mapping
			/** @var SimpleCaptcha $classInstance */
			$classInstance = new ( $map[$class] ?? $map[$defaultCaptchaClass] ?? $defaultCaptchaClass );
			$classInstance->setConfig( $captchaTriggers[$action]['config'] ?? [] );
			static::$instance[$action][$class] = $classInstance;
		}

		return static::$instance[$action][$class];
	}

	/**
	 * Gets a list of all currently active Captcha classes, in the Wikis configuration.
	 *
	 * This includes the default/fallback Captcha of $wgCaptchaClass and any set under
	 * $wgCaptchaTriggers[$action]['class'].
	 */
	public static function getActiveCaptchas(): array {
		$instances = [];

		// We can't rely on static::$instance being loaded with all Captcha Types, so make our own list.
		$defaultCaptcha = self::getInstance();
		$instances[ ( new ReflectionClass( $defaultCaptcha ) )->getShortName() ] = $defaultCaptcha;

		$captchaTriggers = MediaWikiServices::getInstance()->getMainConfig()->get( 'CaptchaTriggers' );
		foreach ( $captchaTriggers as $action => $trigger ) {
			if ( isset( $trigger['class'] ) ) {
				$class = self::getInstance( $action );
				$instances[ $trigger['class'] ] = $class;
			}
		}

		return $instances;
	}

	/**
	 * Clears the global Captcha cache for testing
	 *
	 * @codeCoverageIgnore
	 * @internal Only for use in PHPUnit tests.
	 */
	public static function unsetInstanceForTests(): void {
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			throw new BadMethodCallException( 'Cannot unset ' . __CLASS__ . ' instance in operation.' );
		}
		static::$instance = [];
	}

	/** @inheritDoc */
	public function onEditFilterMergedContent( IContextSource $context, Content $content, Status $status,
		$summary, User $user, $minoredit
	) {
		$action = CaptchaTriggers::EDIT;
		if ( !$context->getWikiPage()->exists() ) {
			$action = CaptchaTriggers::CREATE;
		}
		$simpleCaptcha = self::getInstance( $action );
		// Set a flag indicating that ConfirmEdit's implementation of
		// EditFilterMergedContent ran.
		// This can be checked by other MediaWiki extensions, e.g. AbuseFilter.
		$simpleCaptcha->setEditFilterMergedContentHandlerInvoked();
		return $simpleCaptcha->confirmEditMerged( $context, $content, $status, $summary,
			$user, $minoredit );
	}

	/** @inheritDoc */
	public function onPageSaveComplete(
		$wikiPage,
		$user,
		$summary,
		$flags,
		$revisionRecord,
		$editResult
	) {
		$title = $wikiPage->getTitle();
		if ( $title->getText() === 'Captcha-ip-whitelist' && $title->getNamespace() === NS_MEDIAWIKI ) {
			$this->cache->delete( $this->cache->makeKey( 'confirmedit', 'ipbypasslist' ) );
		}

		return true;
	}

	/** @inheritDoc */
	public function onEditPageBeforeEditButtons( $editpage, &$buttons, &$tabindex ) {
		self::getInstance( CaptchaTriggers::EDIT )->editShowCaptcha( $editpage );
	}

	/** @inheritDoc */
	public function onEditPage__showEditForm_fields( $editor, $out ) {
		self::getInstance( CaptchaTriggers::EDIT )->showEditFormFields( $editor, $out );
	}

	/** @inheritDoc */
	public function onEmailUserForm( &$form ) {
		return self::getInstance( CaptchaTriggers::SENDEMAIL )->injectEmailUser( $form );
	}

	/** @inheritDoc */
	public function onEmailUser( &$to, &$from, &$subject, &$text, &$error ) {
		return self::getInstance( CaptchaTriggers::SENDEMAIL )->confirmEmailUser( $from, $to, $subject, $text, $error );
	}

	/** @inheritDoc */
	public function onAPIGetAllowedParams( $module, &$params, $flags ) {
		// To quote Happy-melon from 32102375f80e72c8c4359abbeff66a75da463efa...
		// > Asking for captchas in the API is really silly

		// Create a merged array of API parameters based on active captcha types.
		// This may result in clashes/overwriting if multiple Captcha use the same parameter names,
		// but there's not a lot we can do about that...
		foreach ( self::getActiveCaptchas() as $instance ) {
			/** @var SimpleCaptcha $instance */
			$instance->apiGetAllowedParams( $module, $params, $flags );
		}
	}

	/** @inheritDoc */
	public function onAuthChangeFormFields(
		$requests, $fieldInfo, &$formDescriptor, $action
	) {
		/** @var CaptchaAuthenticationRequest $req */
		$req = AuthenticationRequest::getRequestByClass(
			$requests,
			CaptchaAuthenticationRequest::class,
			true
		);
		if ( !$req ) {
			return;
		}

		self::getInstance( $req->getAction() )
			->onAuthChangeFormFields( $requests, $fieldInfo, $formDescriptor, $action );
	}

	/** @codeCoverageIgnore */
	public static function confirmEditSetup(): void {
		global $wgCaptchaTriggers;

		// There is no need to run (core) tests with enabled ConfirmEdit - bug T44145
		if ( defined( 'MW_PHPUNIT_TEST' ) || defined( 'MW_QUIBBLE_CI' ) ) {
			$wgCaptchaTriggers = array_fill_keys( array_keys( $wgCaptchaTriggers ), false );
		}
	}

	/** @inheritDoc */
	public function onTitleReadWhitelist( $title, $user, &$whitelisted ) {
		$image = SpecialPage::getTitleFor( 'Captcha', 'image' );
		$help = SpecialPage::getTitleFor( 'Captcha', 'help' );
		if ( $title->equals( $image ) || $title->equals( $help ) ) {
			$whitelisted = true;
		}
	}

	/**
	 * Callback for extension.json of FancyCaptcha to set a default captcha directory,
	 * which depends on wgUploadDirectory
	 *
	 * @codeCoverageIgnore
	 */
	public static function onFancyCaptchaSetup(): void {
		global $wgCaptchaDirectory, $wgUploadDirectory;
		if ( !$wgCaptchaDirectory ) {
			$wgCaptchaDirectory = "$wgUploadDirectory/captcha";
		}
	}

	/** @inheritDoc */
	public function onAlternateEditPreview( $editPage, &$content, &$previewHTML,
		&$parserOutput
	) {
		$title = $editPage->getTitle();
		$exceptionTitle = Title::makeTitle( NS_MEDIAWIKI, 'Captcha-ip-whitelist' );

		if ( !$title->equals( $exceptionTitle ) ) {
			return true;
		}

		$ctx = $editPage->getArticle()->getContext();
		$out = $ctx->getOutput();
		$lang = $ctx->getLanguage();

		$lines = explode( "\n", $content->getNativeData() );
		$previewHTML .= Html::warningBox(
				$ctx->msg( 'confirmedit-preview-description' )->parse()
			) .
			Html::openElement(
				'table',
				[ 'class' => 'wikitable sortable' ]
			) .
			Html::openElement( 'thead' ) .
			Html::element( 'th', [], $ctx->msg( 'confirmedit-preview-line' )->text() ) .
			Html::element( 'th', [], $ctx->msg( 'confirmedit-preview-content' )->text() ) .
			Html::element( 'th', [], $ctx->msg( 'confirmedit-preview-validity' )->text() ) .
			Html::closeElement( 'thead' );

		foreach ( $lines as $count => $line ) {
			$ip = trim( $line );
			if ( $ip === '' || strpos( $ip, '#' ) !== false ) {
				continue;
			}
			if ( IPUtils::isIPAddress( $ip ) ) {
				$validity = $ctx->msg( 'confirmedit-preview-valid' )->escaped();
				$css = 'valid';
			} else {
				$validity = $ctx->msg( 'confirmedit-preview-invalid' )->escaped();
				$css = 'notvalid';
			}
			$previewHTML .= Html::openElement( 'tr' ) .
				Html::element(
					'td',
					[],
					$lang->formatNum( $count + 1 )
				) .
				Html::element(
					'td',
					[],
					// IPv6 max length: 8 groups * 4 digits + 7 delimiter = 39
					// + 11 chars for safety
					$lang->truncateForVisual( $ip, 50 )
				) .
				Html::rawElement(
					'td',
					// possible values:
					// mw-confirmedit-ip-valid
					// mw-confirmedit-ip-notvalid
					[ 'class' => 'mw-confirmedit-ip-' . $css ],
					$validity
				) .
				Html::closeElement( 'tr' );
		}
		$previewHTML .= Html::closeElement( 'table' );
		$out->addModuleStyles( 'ext.confirmEdit.editPreview.ipwhitelist.styles' );

		return false;
	}

	/** @inheritDoc */
	public function onResourceLoaderRegisterModules( ResourceLoader $rl ): void {
		$extensionRegistry = ExtensionRegistry::getInstance();
		$messages = [
			'colon-separator',
			'captcha-edit',
			'captcha-label'
		];

		if ( $extensionRegistry->isLoaded( 'QuestyCaptcha' ) ) {
			$messages[] = 'questycaptcha-edit';
		}

		if ( $extensionRegistry->isLoaded( 'FancyCaptcha' ) ) {
			$messages[] = 'fancycaptcha-edit';
			$messages[] = 'fancycaptcha-reload-text';
			$messages[] = 'fancycaptcha-imgcaptcha-ph';
		}

		$rl->register( [
			'ext.confirmEdit.CaptchaInputWidget' => [
				'localBasePath' => dirname( __DIR__ ),
				'remoteExtPath' => 'ConfirmEdit',
				'scripts' => 'resources/libs/ext.confirmEdit.CaptchaInputWidget.js',
				'styles' => 'resources/libs/ext.confirmEdit.CaptchaInputWidget.less',
				'messages' => $messages,
				'dependencies' => 'oojs-ui-core',
			]
		] );
	}

}
