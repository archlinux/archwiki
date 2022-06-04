<?php

namespace MediaWiki\Extension\CodeEditor;

use EditPage;
use ErrorPageError;
use ExtensionRegistry;
use MediaWiki\Hook\EditPage__showEditForm_initialHook;
use MediaWiki\Hook\EditPage__showReadOnlyForm_initialHook;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\UserOptionsLookup;
use OutputPage;
use Title;
use User;

/**
 * @phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
 */
class Hooks implements
	GetPreferencesHook,
	EditPage__showEditForm_initialHook,
	EditPage__showReadOnlyForm_initialHook
{
	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/**
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		UserOptionsLookup $userOptionsLookup
	) {
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * @param Title $title
	 * @param string $model
	 * @param string $format
	 * @return null|string
	 */
	public static function getPageLanguage( Title $title, $model, $format ) {
		if ( $model === CONTENT_MODEL_JAVASCRIPT ) {
			return 'javascript';
		} elseif ( $model === CONTENT_MODEL_CSS ) {
			return 'css';
		} elseif ( $model === CONTENT_MODEL_JSON ) {
			return 'json';
		}

		// Give extensions a chance
		// Note: $model and $format were added around the time of MediaWiki 1.28.
		$lang = null;
		\Hooks::run( 'CodeEditorGetPageLanguage', [ $title, &$lang, $model, $format ] );

		return $lang;
	}

	/**
	 * @param User $user
	 * @param array &$defaultPreferences
	 */
	public function onGetPreferences( $user, &$defaultPreferences ) {
		$defaultPreferences['usecodeeditor'] = [
			'type' => 'api',
			'default' => '1',
		];
	}

	/**
	 * @param EditPage $editpage
	 * @param OutputPage $output
	 * @throws ErrorPageError
	 */
	public function onEditPage__showEditForm_initial( $editpage, $output ) {
		$title = $editpage->getContextTitle();
		$model = $editpage->contentModel;
		$format = $editpage->contentFormat;

		$lang = self::getPageLanguage( $title, $model, $format );
		if ( $lang && $this->userOptionsLookup->getOption( $output->getUser(), 'usebetatoolbar' ) ) {
			$output->addModules( 'ext.codeEditor' );
			$output->addJsConfigVars( 'wgCodeEditorCurrentLanguage', $lang );
			// Needed because ACE adds a blob: url web-worker.
			$output->getCSP()->addScriptSrc( 'blob:' );
		} elseif ( !ExtensionRegistry::getInstance()->isLoaded( 'WikiEditor' ) ) {
			throw new ErrorPageError( 'codeeditor-error-title', 'codeeditor-error-message' );
		}
	}

	/**
	 * @param EditPage $editpage
	 * @param OutputPage $output
	 * @throws ErrorPageError
	 */
	public function onEditPage__showReadOnlyForm_initial( $editpage, $output ) {
		$this->onEditPage__showEditForm_initial( $editpage, $output );
	}
}
