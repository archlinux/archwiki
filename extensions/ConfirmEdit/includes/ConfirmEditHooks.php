<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Storage\EditResult;
use MediaWiki\User\UserIdentity;
use Wikimedia\IPUtils;

class ConfirmEditHooks implements
	\MediaWiki\Hook\AlternateEditPreviewHook,
	\MediaWiki\Hook\EditPageBeforeEditButtonsHook,
	\MediaWiki\Hook\EmailUserFormHook,
	\MediaWiki\Hook\EmailUserHook,
	\MediaWiki\Permissions\Hook\TitleReadWhitelistHook,
	\MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook,
	\MediaWiki\Storage\Hook\PageSaveCompleteHook
{

	protected static $instanceCreated = false;

	/**
	 * Get the global Captcha instance
	 *
	 * @return SimpleCaptcha
	 */
	public static function getInstance() {
		global $wgCaptcha, $wgCaptchaClass;

		if ( !static::$instanceCreated ) {
			static::$instanceCreated = true;
			$class = $wgCaptchaClass ?: SimpleCaptcha::class;
			$wgCaptcha = new $class;
		}

		return $wgCaptcha;
	}

	/**
	 * @param RequestContext $context
	 * @param Content $content
	 * @param Status $status
	 * @param string $summary
	 * @param User $user
	 * @param bool $minorEdit
	 * @return bool
	 */
	public static function confirmEditMerged( $context, $content, $status, $summary, $user,
		$minorEdit
	) {
		return self::getInstance()->confirmEditMerged( $context, $content, $status, $summary,
			$user, $minorEdit );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageSaveComplete
	 *
	 * @param WikiPage $wikiPage
	 * @param UserIdentity $user
	 * @param string $summary
	 * @param int $flags
	 * @param RevisionRecord $revisionRecord
	 * @param EditResult $editResult
	 * @return bool|void
	 */
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
			$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			$cache->delete( $cache->makeKey( 'confirmedit', 'ipwhitelist' ) );
		}

		return true;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/EditPageBeforeEditButtons
	 *
	 * @param EditPage $editpage Current EditPage object
	 * @param array &$buttons Array of edit buttons, "Save", "Preview", "Live", and "Diff"
	 * @param int &$tabindex HTML tabindex of the last edit check/button
	 */
	public function onEditPageBeforeEditButtons( $editpage, &$buttons, &$tabindex ) {
		self::getInstance()->editShowCaptcha( $editpage );
	}

	/**
	 * @param EditPage $editPage
	 * @param OutputPage $out
	 */
	public static function showEditFormFields( EditPage $editPage, OutputPage $out ) {
		self::getInstance()->showEditFormFields( $editPage, $out );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/EmailUserForm
	 *
	 * @param HTMLForm &$form HTMLForm object
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onEmailUserForm( &$form ) {
		return self::getInstance()->injectEmailUser( $form );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/EmailUser
	 *
	 * @param MailAddress &$to MailAddress object of receiving user
	 * @param MailAddress &$from MailAddress object of sending user
	 * @param MailAddress &$subject subject of the mail
	 * @param string &$text text of the mail
	 * @param bool|Status|MessageSpecifier|array &$error Out-param for an error.
	 *   Should be set to a Status object or boolean false.
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onEmailUser( &$to, &$from, &$subject, &$text, &$error ) {
		return self::getInstance()->confirmEmailUser( $from, $to, $subject, $text, $error );
	}

	/**
	 * APIGetAllowedParams hook handler
	 * Default $flags to 1 for backwards-compatible behavior
	 * @param ApiBase $module
	 * @param array &$params
	 * @param int $flags
	 * @return bool
	 */
	public static function onAPIGetAllowedParams( ApiBase $module, &$params, $flags = 1 ) {
		return self::getInstance()->apiGetAllowedParams( $module, $params, $flags );
	}

	/**
	 * @param array $requests
	 * @param array $fieldInfo
	 * @param array &$formDescriptor
	 * @param string $action
	 */
	public static function onAuthChangeFormFields(
		array $requests, array $fieldInfo, array &$formDescriptor, $action
	) {
		self::getInstance()->onAuthChangeFormFields( $requests, $fieldInfo, $formDescriptor, $action );
	}

	public static function confirmEditSetup() {
		global $wgCaptchaTriggers, $wgWikimediaJenkinsCI;

		// There is no need to run (core) tests with enabled ConfirmEdit - bug T44145
		if ( isset( $wgWikimediaJenkinsCI ) && $wgWikimediaJenkinsCI === true ) {
			$wgCaptchaTriggers = array_fill_keys( array_keys( $wgCaptchaTriggers ), false );
		}
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/TitleReadWhitelist
	 *
	 * @param Title $title
	 * @param User $user
	 * @param bool &$whitelisted
	 * @return bool|void
	 */
	public function onTitleReadWhitelist( $title, $user, &$whitelisted ) {
		$image = SpecialPage::getTitleFor( 'Captcha', 'image' );
		$help = SpecialPage::getTitleFor( 'Captcha', 'help' );
		if ( $title->equals( $image ) || $title->equals( $help ) ) {
			$whitelisted = true;
		}
	}

	/**
	 *
	 * Callback for extension.json of FancyCaptcha to set a default captcha directory,
	 * which depends on wgUploadDirectory
	 */
	public static function onFancyCaptchaSetup() {
		global $wgCaptchaDirectory, $wgUploadDirectory;
		if ( !$wgCaptchaDirectory ) {
			$wgCaptchaDirectory = "$wgUploadDirectory/captcha";
		}
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/AlternateEditPreview
	 *
	 * @param EditPage $editPage
	 * @param Content &$content
	 * @param string &$previewHTML
	 * @param ParserOutput &$parserOutput
	 * @return bool|void
	 */
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
		$previewHTML .= Html::rawElement(
				'div',
				[ 'class' => 'warningbox' ],
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

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderRegisterModules
	 *
	 * @param ResourceLoader $resourceLoader
	 * @return void
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		$extensionRegistry = ExtensionRegistry::getInstance();
		$messages = [];

		$messages[] = 'colon-separator';
		$messages[] = 'captcha-edit';
		$messages[] = 'captcha-label';

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
				'targets' => [ 'desktop', 'mobile' ],
			]
		] );
	}

}
