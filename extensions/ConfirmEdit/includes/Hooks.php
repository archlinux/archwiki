<?php

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace MediaWiki\Extension\ConfirmEdit;

use MediaWiki\Api\Hook\APIGetAllowedParamsHook;
use MediaWiki\Content\Content;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\ConfirmEdit\FancyCaptcha\FancyCaptcha;
use MediaWiki\Extension\ConfirmEdit\hCaptcha\HCaptcha;
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

	protected static ?SimpleCaptcha $instance = null;

	private WANObjectCache $cache;

	public function __construct(
		WANObjectCache $cache
	) {
		$this->cache = $cache;
	}

	/**
	 * Get the global Captcha instance
	 *
	 * @return SimpleCaptcha
	 */
	public static function getInstance() {
		global $wgCaptchaClass;
		static $map = [
			'SimpleCaptcha' => SimpleCaptcha::class,
			'FancyCaptcha' => FancyCaptcha::class,
			'QuestyCaptcha' => QuestyCaptcha::class,
			'ReCaptchaNoCaptcha' => ReCaptchaNoCaptcha::class,
			'HCaptcha' => HCaptcha::class,
			'Turnstile' => Turnstile::class,
		];
		// Support PHP 7.4: Avoid `new ( $map[$wgCaptchaClass] ?? $wgCaptchaClass )`
		$className = $map[$wgCaptchaClass] ?? $wgCaptchaClass;

		static::$instance ??= new $className;
		return static::$instance;
	}

	/** @inheritDoc */
	public function onEditFilterMergedContent( IContextSource $context, Content $content, Status $status,
		$summary, User $user, $minorEdit
	) {
		$simpleCaptcha = self::getInstance();
		// Set a flag indicating that ConfirmEdit's implementation of
		// EditFilterMergedContent ran.
		// This can be checked by other MediaWiki extensions, e.g. AbuseFilter.
		$simpleCaptcha->setEditFilterMergedContentHandlerInvoked();
		return $simpleCaptcha->confirmEditMerged( $context, $content, $status, $summary,
			$user, $minorEdit );
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
		self::getInstance()->editShowCaptcha( $editpage );
	}

	/** @inheritDoc */
	public function onEditPage__showEditForm_fields( $editPage, $out ) {
		self::getInstance()->showEditFormFields( $editPage, $out );
	}

	/** @inheritDoc */
	public function onEmailUserForm( &$form ) {
		return self::getInstance()->injectEmailUser( $form );
	}

	/** @inheritDoc */
	public function onEmailUser( &$to, &$from, &$subject, &$text, &$error ) {
		return self::getInstance()->confirmEmailUser( $from, $to, $subject, $text, $error );
	}

	/** @inheritDoc */
	public function onAPIGetAllowedParams( $module, &$params, $flags ) {
		return self::getInstance()->apiGetAllowedParams( $module, $params, $flags );
	}

	/** @inheritDoc */
	public function onAuthChangeFormFields(
		$requests, $fieldInfo, &$formDescriptor, $action
	) {
		self::getInstance()->onAuthChangeFormFields( $requests, $fieldInfo, $formDescriptor, $action );
	}

	public static function confirmEditSetup() {
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
	 */
	public static function onFancyCaptchaSetup() {
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
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
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

		$resourceLoader->register( [
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
