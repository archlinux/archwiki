<?php

use MediaWiki\MediaWikiServices;

/**
 * Hooks for TemplateData extension
 *
 * @file
 * @ingroup Extensions
 */

class TemplateDataHooks {

	/**
	 * @param EditPage $editPage
	 * @param OutputPage $out
	 */
	public static function onEditPageShowEditFormFields( EditPage $editPage, OutputPage $out ) {
		// TODO: Remove when not needed any more, see T267926
		if ( $out->getRequest()->getBool( 'TemplateDataGeneratorUsed' ) ) {
			// Recreate the dynamically created field after the user clicked "preview"
			$out->addHTML( Html::hidden( 'TemplateDataGeneratorUsed', true ) );
		}
	}

	/**
	 * Register parser hooks
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'templatedata', [ __CLASS__, 'render' ] );
	}

	/**
	 * Conditionally register the jquery.uls.data module, in case they've already been
	 * registered by the UniversalLanguageSelector extension or the VisualEditor extension.
	 *
	 * @param ResourceLoader &$resourceLoader
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader &$resourceLoader ) {
		$resourceModules = $resourceLoader->getConfig()->get( 'ResourceModules' );
		$name = 'jquery.uls.data';
		if ( !isset( $resourceModules[$name] ) && !$resourceLoader->isModuleRegistered( $name ) ) {
			$resourceLoader->register( [
				'jquery.uls.data' => [
					'localBasePath' => dirname( __DIR__ ),
					'remoteExtPath' => 'TemplateData',
					'scripts' => [
						'lib/jquery.uls/src/jquery.uls.data.js',
						'lib/jquery.uls/src/jquery.uls.data.utils.js',
					],
					'targets' => [ 'desktop', 'mobile' ],
				]
			] );
		}
	}

	/**
	 * @param WikiPage &$page
	 * @param User &$user
	 * @param Content &$content
	 * @param string &$summary
	 * @param bool $minor
	 * @param bool|null $watchthis
	 * @param string $sectionanchor
	 * @param int &$flags
	 * @param Status &$status
	 * @return bool
	 */
	public static function onPageContentSave( WikiPage &$page, &$user, &$content, &$summary, $minor,
		$watchthis, $sectionanchor, &$flags, &$status
	) {
		if ( $page->getContentModel() !== CONTENT_MODEL_WIKITEXT ) {
			return true;
		}

		// The PageContentSave hook provides raw $text, but not $parser because at this stage
		// the page is not actually parsed yet. Which means we can't know whether self::render()
		// got a valid tag or not. Looking at $text directly is not a solution either as
		// it may not be in the current page (it can be transcluded).
		// Since there is no later hook that allows aborting the save and showing an error,
		// we will have to trigger the parser ourselves.
		// Fortunately this causes no overhead since the below (copied from WikiPage::doEditContent,
		// right after this hook is ran) has guards that lazy-init and return early if called again
		// later by the real WikiPage.

		// Specify format the same way the API and EditPage do to avoid extra parsing
		$format = $content->getContentHandler()->getDefaultFormat();
		$editInfo = $page->prepareContentForEdit( $content, null, $user, $format );
		$parserOutput = $editInfo->getOutput();

		$templateDataStatus = self::getStatusFromParserOutput( $parserOutput );
		if ( $templateDataStatus instanceof Status && !$templateDataStatus->isOK() ) {
			// Abort edit, show error message from TemplateDataBlob::getStatus
			$status->merge( $templateDataStatus );
			return false;
		}

		// TODO: Remove when not needed any more, see T267926
		self::logChangeEvent( $page, $parserOutput->getProperty( 'templatedata' ), $user );

		return true;
	}

	/**
	 * @param WikiPage $page
	 * @param string|false $newPageProperty
	 * @param User $user
	 */
	private static function logChangeEvent( WikiPage $page, $newPageProperty, User $user ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'EventLogging' ) ) {
			return;
		}

		$services = MediaWikiServices::getInstance();
		$title = $page->getTitle();
		$pageId = $page->getId();
		$props = $services->getPageProps()->getProperties( $title, 'templatedata' );
		// The JSON strings here are guaranteed to be normalized (and possibly compressed) the same
		// way. No need to normalize them again for this comparison.
		if ( $newPageProperty === ( $props[$pageId] ?? false ) ) {
			return;
		}

		$generatorUsed = RequestContext::getMain()->getRequest()->getBool( 'TemplateDataGeneratorUsed' );
		$revision = $page->getRevisionRecord();

		// Note: We know that irrelevant changes (e.g. whitespace changes) aren't logged here
		EventLogging::logEvent(
			'TemplateDataEditor',
			-1,
			[
				// Note: The "Done" button is disabled unless something changed, which means it's
				// very likely (but not guaranteed) the generator was used to make the changes
				'action' => $generatorUsed ? 'save-tag-edit-generator-used' : 'save-tag-edit-no-generator',
				'page_id' => $pageId,
				'page_namespace' => $title->getNamespace(),
				'page_title' => $title->getText(),
				'rev_id' => $revision ? $revision->getId() : 0,
				'user_edit_count' => $user->getEditCount() ?? 0,
				'user_id' => $user->getId(),
			]
		);
	}

	/**
	 * Parser hook registering the GUI module only in edit pages.
	 *
	 * @param EditPage $editPage
	 * @param OutputPage $output
	 */
	public static function onEditPage( EditPage $editPage, OutputPage $output ) {
		global $wgTemplateDataUseGUI;
		if ( $wgTemplateDataUseGUI ) {
			if ( $output->getTitle()->inNamespace( NS_TEMPLATE ) ) {
				$output->addModules( 'ext.templateDataGenerator.editTemplatePage' );
			}
		}
	}

	/**
	 * Include config when appropriate.
	 *
	 * @param array &$vars
	 * @param OutputPage $output
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/MakeGlobalVariablesScript
	 */
	public static function onMakeGlobalVariablesScript( array &$vars, OutputPage $output ) {
		if ( $output->getTitle()->inNamespace( NS_TEMPLATE ) ) {
			$vars['wgTemplateDataSuggestedValuesEditor'] =
				$output->getConfig()->get( 'TemplateDataSuggestedValuesEditor' );
		}
	}

	/**
	 * Parser hook for <templatedata>.
	 * If there is any JSON provided, render the template documentation on the page.
	 *
	 * @param string|null $input The content of the tag.
	 * @param array $args The attributes of the tag.
	 * @param Parser $parser Parser instance available to render
	 *  wikitext into html, or parser methods.
	 * @param PPFrame $frame Can be used to see what template parameters ("{{{1}}}", etc.)
	 *  this hook was used with.
	 *
	 * @return string HTML to insert in the page.
	 */
	public static function render( $input, $args, Parser $parser, $frame ) {
		$ti = TemplateDataBlob::newFromJSON( wfGetDB( DB_REPLICA ), $input ?? '' );

		$status = $ti->getStatus();
		if ( !$status->isOK() ) {
			self::setStatusToParserOutput( $parser->getOutput(), $status );
			return '<div class="errorbox">' . $status->getHTML() . '</div>';
		}

		// Store the blob as page property for retrieval by ApiTemplateData.
		// But, don't store it if we're parsing a doc sub page,  because:
		// - The doc subpage should not appear in ApiTemplateData as a documented
		// template itself, which is confusing to users (T54448).
		// - The doc subpage should not appear at Special:PagesWithProp.
		// - Storing the blob twice in the database is inefficient (T52512).
		$title = $parser->getTitle();
		$docPage = wfMessage( 'templatedata-doc-subpage' )->inContentLanguage();
		if ( !$title->isSubpage() || $title->getSubpageText() !== $docPage->plain() ) {
			$parser->getOutput()->setProperty( 'templatedata', $ti->getJSONForDatabase() );
		}

		$parser->getOutput()->addModuleStyles( [
			'ext.templateData',
			'ext.templateData.images',
			'jquery.tablesorter.styles',
		] );
		$parser->getOutput()->addModules( 'jquery.tablesorter' );
		$parser->getOutput()->setEnableOOUI( true );

		$userLang = $parser->getOptions()->getUserLangObj();

		// FIXME: this hard-codes default skin, but it is needed because
		// ::getHtml() will need a theme singleton to be set.
		OutputPage::setupOOUI( 'bogus', $userLang->getDir() );
		return $ti->getHtml( $userLang );
	}

	/**
	 * Fetch templatedata for an array of titles.
	 *
	 * @todo Document this hook
	 *
	 * The following questions are yet to be resolved.
	 * (a) Should we extend functionality to looking up an array of titles instead of one?
	 *     The signature allows for an array of titles to be passed in, but the
	 *     current implementation is not optimized for the multiple-title use case.
	 * (b) Should this be a lookup service instead of this faux hook?
	 *     This will be resolved separately.
	 *
	 * @param array $tplTitles
	 * @param stdclass[] &$tplData
	 */
	public static function onParserFetchTemplateData( array $tplTitles, array &$tplData ): void {
		$tplData = [];

		// This inefficient implementation is currently tuned for
		// Parsoid's use case where it requests info for exactly one title.
		// For a real batch use case, this code will need an overhaul.
		foreach ( $tplTitles as $tplTitle ) {
			$title = Title::newFromText( $tplTitle );
			if ( !$title ) {
				// Invalid title
				$tplData[$tplTitle] = null;
				continue;
			}

			if ( $title->isRedirect() ) {
				$title = ( new WikiPage( $title ) )->getRedirectTarget();
				if ( !$title ) {
					// Invalid redirecting title
					$tplData[$tplTitle] = null;
					continue;
				}
			}

			if ( !$title->exists() ) {
				$tplData[$tplTitle] = (object)[ "missing" => true ];
				continue;
			}

			// FIXME: PageProps returns takes titles but returns by page id.
			// This means we need to do our own look up and hope it matches.
			// Spoiler, sometimes it won't. When that happens, the user won't
			// get any templatedata-based interfaces for that template.
			// The fallback is to not serve data for that template, which
			// the clients have to support anyway, so the impact is minimal.
			// It is also expected that such race conditions resolve themselves
			// after a few seconds so the old "try again later" should cover this.
			$pageId = $title->getArticleID();
			$props = PageProps::getInstance()->getProperties( $title, 'templatedata' );
			if ( !isset( $props[$pageId] ) ) {
				// No templatedata
				$tplData[$tplTitle] = (object)[ "notemplatedata" => true ];
				continue;
			}

			$tdb = TemplateDataBlob::newFromDatabase( wfGetDB( DB_REPLICA ), $props[$pageId] );
			$status = $tdb->getStatus();
			if ( !$status->isOK() ) {
				// Invalid data. Parsoid has no use for the error.
				$tplData[$tplTitle] = (object)[ "notemplatedata" => true ];
				continue;
			}

			$tplData[$tplTitle] = $tdb->getData();
		}
	}

	/**
	 * Write the status to ParserOutput object.
	 * @param ParserOutput $parserOutput
	 * @param Status $status
	 */
	public static function setStatusToParserOutput( ParserOutput $parserOutput, Status $status ) {
		$parserOutput->setExtensionData( 'TemplateDataStatus',
			self::jsonSerializeStatus( $status ) );
	}

	/**
	 * @param ParserOutput $parserOutput
	 * @return Status|null
	 */
	public static function getStatusFromParserOutput( ParserOutput $parserOutput ) {
		$status = $parserOutput->getExtensionData( 'TemplateDataStatus' );
		if ( is_array( $status ) ) {
			return self::newStatusFromJson( $status );
		}
		return $status;
	}

	/**
	 * @param array $status contains StatusValue ok and errors fields (does not serialize value)
	 * @return Status
	 */
	public static function newStatusFromJson( array $status ): Status {
		if ( $status['ok'] ) {
			return Status::newGood();
		} else {
			$statusObj = new Status();
			$errors = $status['errors'];
			foreach ( $errors as $error ) {
				$statusObj->fatal( $error['message'], ...$error['params'] );
			}
			$warnings = $status['warnings'];
			foreach ( $warnings as $warning ) {
				$statusObj->warning( $warning['message'], ...$warning['params'] );
			}
			return $statusObj;
		}
	}

	/**
	 * @param Status $status
	 * @return array contains StatusValue ok and errors fields (does not serialize value)
	 */
	public static function jsonSerializeStatus( Status $status ): array {
		if ( $status->isOK() ) {
			return [
				'ok' => true
			];
		} else {
			list( $errorsOnlyStatus, $warningsOnlyStatus ) = $status->splitByErrorType();
			// note that non-scalar values are not supported in errors or warnings
			return [
				'ok' => false,
				'errors' => $errorsOnlyStatus->getErrors(),
				'warnings' => $warningsOnlyStatus->getErrors()
			];
		}
	}
}
