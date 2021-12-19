<?php
/**
 * Hooks for WikiEditor extension
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;
use WikimediaEvents\WikimediaEventsHooks;

class WikiEditorHooks {
	// ID used for grouping entries all of a session's entries together in
	// EventLogging.
	private static $statsId = false;

	/* Static Methods */

	/**
	 * Should the current session be sampled for EventLogging?
	 *
	 * @param string $sessionId
	 * @return bool Whether to sample the session
	 */
	protected static function inEventSample( $sessionId ) {
		global $wgWMESchemaEditAttemptStepSamplingRate;
		// Sample 6.25%
		$samplingRate = $wgWMESchemaEditAttemptStepSamplingRate ?? 0.0625;
		$inSample = EventLogging::sessionInSample(
			(int)( 1 / $samplingRate ), $sessionId
		);
		return $inSample;
	}

	/**
	 * Log stuff to EventLogging's Schema:EditAttemptStep -
	 * see https://meta.wikimedia.org/wiki/Schema:EditAttemptStep
	 * If you don't have EventLogging installed, does nothing.
	 *
	 * @param string $action
	 * @param Article $article Which article (with full context, page, title, etc.)
	 * @param array $data Data to log for this action
	 * @return bool Whether the event was logged or not.
	 */
	public static function doEventLogging( $action, $article, $data = [] ) {
		$extensionRegistry = ExtensionRegistry::getInstance();
		if ( !$extensionRegistry->isLoaded( 'EventLogging' ) ) {
			return false;
		}
		$inSample = self::inEventSample( $data['editing_session_id'] );
		$shouldOversample = $extensionRegistry->isLoaded( 'WikimediaEvents' ) &&
			WikimediaEventsHooks::shouldSchemaEditAttemptStepOversample( $article->getContext() );
		if ( !$inSample && !$shouldOversample ) {
			return false;
		}

		$user = $article->getContext()->getUser();
		$page = $article->getPage();
		$title = $article->getTitle();
		$revisionRecord = $page->getRevisionRecord();

		$data = [
			'action' => $action,
			'version' => 1,
			'is_oversample' => !$inSample,
			'editor_interface' => 'wikitext',
			'platform' => 'desktop', // FIXME
			'integration' => 'page',
			'page_id' => $page->getId(),
			'page_title' => $title->getPrefixedText(),
			'page_ns' => $title->getNamespace(),
			'revision_id' => $revisionRecord ? $revisionRecord->getId() : 0,
			'user_id' => $user->getId(),
			'user_editcount' => $user->getEditCount() ?: 0,
			'mw_version' => MW_VERSION,
		] + $data;

		if ( $user->getOption( 'discussiontools-abtest' ) ) {
			$data['bucket'] = $user->getOption( 'discussiontools-abtest' );
		}

		if ( $user->isAnon() ) {
			$data['user_class'] = 'IP';
		}

		return EventLogging::logEvent( 'EditAttemptStep', 18530416, $data );
	}

	/**
	 * Log stuff to EventLogging's Schema:VisualEditorFeatureUse -
	 * see https://meta.wikimedia.org/wiki/Schema:VisualEditorFeatureUse
	 * If you don't have EventLogging installed, does nothing.
	 *
	 * @param string $feature
	 * @param string $action
	 * @param Article $article Which article (with full context, page, title, etc.)
	 * @param string $sessionId Session identifier
	 * @return bool Whether the event was logged or not.
	 */
	public static function doVisualEditorFeatureUseLogging( $feature, $action, $article, $sessionId ) {
		$extensionRegistry = ExtensionRegistry::getInstance();
		if ( !$extensionRegistry->isLoaded( 'EventLogging' ) ) {
			return false;
		}
		$inSample = self::inEventSample( $sessionId );
		$shouldOversample = $extensionRegistry->isLoaded( 'WikimediaEvents' ) &&
			WikimediaEventsHooks::shouldSchemaEditAttemptStepOversample( $article->getContext() );
		if ( !$inSample && !$shouldOversample ) {
			return false;
		}

		$user = $article->getContext()->getUser();

		$data = [
			'feature' => $feature,
			'action' => $action,
			'editingSessionId' => $sessionId,
			'platform' => 'desktop', // FIXME T249944
			'integration' => 'page',
			'editor_interface' => 'wikitext',
			'user_id' => $user->getId(),
			'user_editcount' => $user->getEditCount() ?: 0,
		];

		if ( $user->getOption( 'discussiontools-abtest' ) ) {
			$data['bucket'] = $user->getOption( 'discussiontools-abtest' );
		}

		return EventLogging::logEvent( 'VisualEditorFeatureUse', 21199762, $data );
	}

	/**
	 * EditPage::showEditForm:initial hook
	 *
	 * Adds the modules to the edit form
	 *
	 * @param EditPage $editPage the current EditPage object.
	 * @param OutputPage $outputPage object.
	 */
	public static function editPageShowEditFormInitial( EditPage $editPage, OutputPage $outputPage ) {
		if ( $editPage->contentModel !== CONTENT_MODEL_WIKITEXT ) {
			return;
		}

		$article = $editPage->getArticle();
		$request = $article->getContext()->getRequest();

		// Add modules if enabled
		$user = $article->getContext()->getUser();
		if ( $user->getOption( 'usebetatoolbar' ) ) {
			$outputPage->addModuleStyles( 'ext.wikiEditor.styles' );
			$outputPage->addModules( 'ext.wikiEditor' );
		}

		// Don't run this if the request was posted - we don't want to log 'init' when the
		// user just pressed 'Show preview' or 'Show changes', or switched from VE keeping
		// changes.
		if ( ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' ) && !$request->wasPosted() ) {
			$data = [];
			$data['editing_session_id'] = self::getEditingStatsId( $request );
			if ( $request->getVal( 'section' ) ) {
				$data['init_type'] = 'section';
			} else {
				$data['init_type'] = 'page';
			}
			if ( $request->getHeader( 'Referer' ) ) {
				if (
					$request->getVal( 'section' ) === 'new'
					|| !$article->getPage()->exists()
				) {
					$data['init_mechanism'] = 'new';
				} else {
					$data['init_mechanism'] = 'click';
				}
			} else {
				if (
					$request->getVal( 'section' ) === 'new'
					|| !$article->getPage()->exists()
				) {
					$data['init_mechanism'] = 'url-new';
				} else {
					$data['init_mechanism'] = 'url';
				}
			}

			self::doEventLogging( 'init', $article, $data );
		}
	}

	/**
	 * EditPage::showEditForm:fields hook
	 *
	 * Adds the event fields to the edit form
	 *
	 * @param EditPage $editPage the current EditPage object.
	 * @param OutputPage $outputPage object.
	 */
	public static function editPageShowEditFormFields( EditPage $editPage, OutputPage $outputPage ) {
		if ( $editPage->contentModel !== CONTENT_MODEL_WIKITEXT ) {
			return;
		}

		$req = $outputPage->getRequest();
		$editingStatsId = self::getEditingStatsId( $req );

		$shouldOversample = ExtensionRegistry::getInstance()->isLoaded( 'WikimediaEvents' ) &&
			WikimediaEventsHooks::shouldSchemaEditAttemptStepOversample( $outputPage->getContext() );

		$outputPage->addHTML(
			Xml::element(
				'input',
				[
					'type' => 'hidden',
					'name' => 'editingStatsId',
					'id' => 'editingStatsId',
					'value' => $editingStatsId
				]
			)
		);

		if ( $shouldOversample ) {
			$outputPage->addHTML(
				Xml::element(
					'input',
					[
						'type' => 'hidden',
						'name' => 'editingStatsOversample',
						'id' => 'editingStatsOversample',
						'value' => 1
					]
				)
			);
		}

		$outputPage->addHTML(
			Xml::element(
				'input',
				[
					'type' => 'hidden',
					'name' => 'wikieditorJavascriptSupport',
					'id' => 'wikieditorJavascriptSupport',
					'value' => ''
				]
			)
		);
	}

	/**
	 * GetPreferences hook
	 *
	 * Adds WikiEditor-related items to the preferences
	 *
	 * @param User $user current user
	 * @param array &$defaultPreferences list of default user preference controls
	 */
	public static function getPreferences( $user, &$defaultPreferences ) {
		// Ideally this key would be 'wikieditor-toolbar'
		$defaultPreferences['usebetatoolbar'] = [
			'type' => 'toggle',
			'label-message' => 'wikieditor-toolbar-preference',
			'help-message' => 'wikieditor-toolbar-preference-help',
			'section' => 'editing/editor',
		];
	}

	/**
	 * @param ResourceLoaderContext $context
	 * @param Config $config
	 * @return array
	 */
	public static function getModuleData( ResourceLoaderContext $context, Config $config ) {
		return [
			// expose magic words for use by the wikieditor toolbar
			'magicWords' => self::getMagicWords(),
			'signature' => self::getSignatureMessage( $context )
		];
	}

	/**
	 * @param ResourceLoaderContext $context
	 * @param Config $config
	 * @return array
	 */
	public static function getModuleDataSummary( ResourceLoaderContext $context, Config $config ) {
		return [
			'magicWords' => self::getMagicWords(),
			'signature' => self::getSignatureMessage( $context, true )
		];
	}

	private static function getSignatureMessage( MessageLocalizer $ml, $raw = false ) {
		$msg = $ml->msg( 'sig-text' )->params( '~~~~' )->inContentLanguage();
		return $raw ? $msg->plain() : $msg->text();
	}

	/**
	 * Expose useful magic words which are used by the wikieditor toolbar
	 * @return string[][]
	 */
	private static function getMagicWords() {
		$requiredMagicWords = [
			'redirect',
			'img_alt',
			'img_right',
			'img_left',
			'img_none',
			'img_center',
			'img_thumbnail',
			'img_framed',
			'img_frameless',
		];
		$magicWords = [];
		$factory = MediaWikiServices::getInstance()->getMagicWordFactory();
		foreach ( $requiredMagicWords as $name ) {
			$magicWords[$name] = $factory->get( $name )->getSynonyms();
		}
		return $magicWords;
	}

	/**
	 * Gets a 32 character alphanumeric random string to be used for stats.
	 * @param WebRequest $request
	 * @return string
	 */
	private static function getEditingStatsId( WebRequest $request ) {
		$fromRequest = $request->getVal( 'editingStatsId' );
		if ( $fromRequest ) {
			return $fromRequest;
		}
		if ( !self::$statsId ) {
			self::$statsId = MWCryptRand::generateHex( 32 );
		}
		return self::$statsId;
	}

	/**
	 * This is attached to the MediaWiki 'EditPage::attemptSave' hook.
	 *
	 * @param EditPage $editPage
	 */
	public static function editPageAttemptSave( EditPage $editPage ) {
		$article = $editPage->getArticle();
		$request = $article->getContext()->getRequest();
		if ( $request->getVal( 'editingStatsId' ) ) {
			self::doEventLogging(
				'saveAttempt',
				$article,
				[ 'editing_session_id' => $request->getVal( 'editingStatsId' ) ]
			);
		}
	}

	/**
	 * This is attached to the MediaWiki 'EditPage::attemptSave:after' hook.
	 *
	 * @param EditPage $editPage
	 * @param Status $status
	 */
	public static function editPageAttemptSaveAfter( EditPage $editPage, Status $status ) {
		$article = $editPage->getArticle();
		$request = $article->getContext()->getRequest();
		if ( $request->getVal( 'editingStatsId' ) ) {
			$data = [];
			$data['editing_session_id'] = $request->getVal( 'editingStatsId' );

			if ( $status->isOK() ) {
				$action = 'saveSuccess';

				if ( $request->getVal( 'wikieditorJavascriptSupport' ) === 'yes' ) {
					self::doVisualEditorFeatureUseLogging(
						'mwSave', 'source-has-js', $article, $request->getVal( 'editingStatsId' )
					);
				}
			} else {
				$action = 'saveFailure';

				// Compare to ve.init.mw.ArticleTargetEvents.js in VisualEditor.
				$typeMap = [
					'badtoken' => 'userBadToken',
					'assertanonfailed' => 'userNewUser',
					'assertuserfailed' => 'userNewUser',
					'assertnameduserfailed' => 'userNewUser',
					'abusefilter-disallowed' => 'extensionAbuseFilter',
					'abusefilter-warning' => 'extensionAbuseFilter',
					'captcha' => 'extensionCaptcha',
					'spamblacklist' => 'extensionSpamBlacklist',
					'titleblacklist-forbidden' => 'extensionTitleBlacklist',
					'pagedeleted' => 'editPageDeleted',
					'editconflict' => 'editConflict'
				];

				$errors = $status->getErrorsArray();
				// Replicate how the API generates error codes, in order to log data that is consistent with
				// all other tools (which save changes via the API)
				if ( isset( $errors[0] ) ) {
					$code = ApiMessage::create( $errors[0] )->getApiCode();
				} else {
					$code = 'unknown';
				}

				$wikiPage = $editPage->getArticle()->getPage();
				if ( isset( $wikiPage->ConfirmEdit_ActivateCaptcha ) ) {
					// TODO: :(
					$code = 'captcha';
				}

				$data['save_failure_message'] = $code;
				$data['save_failure_type'] = $typeMap[ $code ] ?? 'responseUnknown';
			}

			self::doEventLogging( $action, $article, $data );
		}
	}
}
