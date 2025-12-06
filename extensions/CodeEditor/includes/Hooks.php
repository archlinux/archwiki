<?php

namespace MediaWiki\Extension\CodeEditor;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Exception\ErrorPageError;
use MediaWiki\Extension\BetaFeatures\BetaFeatures;
use MediaWiki\Extension\CodeEditor\Hooks\HookRunner;
use MediaWiki\Hook\EditPage__showEditForm_initialHook;
use MediaWiki\Hook\EditPage__showReadOnlyForm_initialHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Output\OutputPage;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;

class Hooks implements
	GetPreferencesHook,
	EditPage__showEditForm_initialHook,
	EditPage__showReadOnlyForm_initialHook
{
	private readonly HookRunner $hookRunner;
	private readonly array $enabledModes;

	public function __construct(
		private readonly UserOptionsLookup $userOptionsLookup,
		HookContainer $hookContainer,
		Config $config,
	) {
		$this->hookRunner = new HookRunner( $hookContainer );
		$this->enabledModes = $config->get( 'CodeEditorEnabledModes' );
	}

	private function getPageLanguage( Title $title, string $model, string $format ): ?string {
		if ( $model === CONTENT_MODEL_JAVASCRIPT ) {
			return 'javascript';
		} elseif ( $model === CONTENT_MODEL_CSS ) {
			return 'css';
		} elseif ( $model === CONTENT_MODEL_JSON ) {
			return 'json';
		} elseif ( $model === CONTENT_MODEL_VUE ) {
			return 'vue';
		}

		// Give extensions a chance
		$lang = null;
		$this->hookRunner->onCodeEditorGetPageLanguage( $title, $lang, $model, $format );

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
		$model = $editpage->contentModel;
		$title = $editpage->getContextTitle();
		$format = $editpage->contentFormat;
		$lang = $this->getPageLanguage( $title, $model, $format );

		if ( $lang &&
			// @phan-suppress-next-line PhanAccessReadOnlyProperty
			isset( $this->enabledModes[$lang] ) &&
			$this->enabledModes[$lang] === false &&
			self::tempIsCodeMirrorEnabled()
		) {
			return;
		}

		if ( $lang && $this->userOptionsLookup->getOption( $output->getUser(), 'usebetatoolbar' ) ) {
			$output->addModules( 'ext.codeEditor' );
			$output->addModuleStyles( 'ext.codeEditor.styles' );
			$output->addJsConfigVars( 'wgCodeEditorCurrentLanguage', $lang );
			// Needed because ACE adds a blob: url web-worker.
			$output->getCSP()->addScriptSrc( 'blob:' );

			if ( $this->userOptionsLookup->getOption( $output->getUser(), 'usecodeeditor' ) ) {
				$output->addBodyClasses( 'codeeditor-loading' );
			}
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

	/**
	 * Temporary code while CodeMirror is still in beta. This should be checked
	 * against in every CodeEditorGetPageLanguageHook implementation where the
	 * extension has CodeMirror integration (via the CodeMirrorGetMode hook).
	 *
	 * In such cases, we want to fallback to using CodeEditor.
	 * Set $wgCodeEditorEnabledModes for the content model to false if you
	 * do not want to use CodeEditor or CodeMirror for that mode.
	 *
	 * See T373711#11018957
	 *
	 * @return bool
	 */
	public static function tempIsCodeMirrorEnabled(): bool {
		$extensionRegistry = ExtensionRegistry::getInstance();
		$requestContext = RequestContext::getMain();
		return $extensionRegistry->isLoaded( 'CodeMirror' ) && (
			// $wgCodeMirrorV6 is explicitly set
			$requestContext->getConfig()->get( 'CodeMirrorV6' ) ||
			// ?cm6enable=1 URL parameter is set
			$requestContext->getRequest()->getBool( 'cm6enable' ) ||
			// Beta feature is enabled for the user
			(
				$extensionRegistry->isLoaded( 'BetaFeatures' ) &&
				BetaFeatures::isFeatureEnabled( $requestContext->getUser(), 'codemirror-beta-feature-enable' )
			)
		);
	}
}
