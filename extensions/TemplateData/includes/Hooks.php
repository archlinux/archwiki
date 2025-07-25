<?php

namespace MediaWiki\Extension\TemplateData;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Config\Config;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Hook\EditPage__showEditForm_initialHook;
use MediaWiki\Hook\ParserFetchTemplateDataHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\OutputPageBeforeHTMLHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook;
use MediaWiki\ResourceLoader\ResourceLoader;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Storage\Hook\MultiContentSaveHook;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\Hook\SaveUserOptionsHook;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

/**
 * @license GPL-2.0-or-later
 * phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName
 */
class Hooks implements
	ParserFirstCallInitHook,
	MultiContentSaveHook,
	ResourceLoaderRegisterModulesHook,
	EditPage__showEditForm_initialHook,
	ParserFetchTemplateDataHook,
	OutputPageBeforeHTMLHook,
	GetPreferencesHook,
	SaveUserOptionsHook
{

	private Config $config;

	public function __construct( Config $mainConfig ) {
		$this->config = $mainConfig;
	}

	/**
	 * Register parser hooks
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'templatedata', [ $this, 'render' ] );
	}

	/**
	 * Conditionally register the jquery.uls.data module, in case they've already been
	 * registered by the UniversalLanguageSelector extension or the VisualEditor extension.
	 */
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
		$resourceModules = $resourceLoader->getConfig()->get( 'ResourceModules' );
		$name = 'jquery.uls.data';
		if ( !isset( $resourceModules[$name] ) && !$resourceLoader->isModuleRegistered( $name ) ) {
			$resourceLoader->register( [
				$name => [
					'localBasePath' => dirname( __DIR__ ),
					'remoteExtPath' => 'TemplateData',
					'scripts' => [
						'lib/jquery.uls/src/jquery.uls.data.js',
						'lib/jquery.uls/src/jquery.uls.data.utils.js',
					],
				]
			] );
		}
	}

	/**
	 * @param RenderedRevision $renderedRevision
	 * @param UserIdentity $user
	 * @param CommentStoreComment $summary
	 * @param int $flags
	 * @param Status $hookStatus
	 * @return bool
	 */
	public function onMultiContentSave(
		$renderedRevision, $user, $summary, $flags, $hookStatus
	) {
		$revisionRecord = $renderedRevision->getRevision();
		$contentModel = $revisionRecord
			->getContent( SlotRecord::MAIN )
			->getModel();

		if ( $contentModel !== CONTENT_MODEL_WIKITEXT ) {
			return true;
		}

		// Revision hasn't been parsed yet, so parse to know if self::render got a
		// valid tag (via inclusion and transclusion) and abort save if it didn't
		$parserOutput = $renderedRevision->getRevisionParserOutput( [ 'generate-html' => false ] );
		$status = TemplateDataStatus::newFromJson( $parserOutput->getExtensionData( 'TemplateDataStatus' ) );
		if ( $status && !$status->isOK() ) {
			// Abort edit, show error message from TemplateDataBlob::getStatus
			$hookStatus->merge( $status );
			return false;
		}

		return true;
	}

	/**
	 * Hook to load the GUI module only on edit action.
	 *
	 * @param EditPage $editPage
	 * @param OutputPage $output
	 */
	public function onEditPage__showEditForm_initial( $editPage, $output ) {
		if ( $this->config->get( 'TemplateDataUseGUI' ) ) {
			$editorNamespaces = $this->config->get( 'TemplateDataEditorNamespaces' );
			$isEditorNamespace = $output->getTitle()->inNamespaces( $editorNamespaces );
			if ( !$isEditorNamespace ) {
				// If we're outside the editor namespaces, allow access to GUI
				// if it's an existing page with <templatedate> (e.g. User template sandbox,
				// or some other page that's intended to be transcluded for any reason).
				$services = MediaWikiServices::getInstance();
				$props = $services->getPageProps()->getProperties( $editPage->getTitle(), 'templatedata' );
				$isEditorNamespace = (bool)$props;
			}
			if ( $isEditorNamespace ) {
				$output->addModuleStyles( 'ext.templateDataGenerator.editTemplatePage.loading' );
				$output->addHTML( '<div class="tdg-editscreen-placeholder"></div>' );
				$output->addModules( 'ext.templateDataGenerator.editTemplatePage' );
			}
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
	public function render( ?string $input, array $args, Parser $parser, PPFrame $frame ): string {
		$parserOutput = $parser->getOutput();
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$ti = TemplateDataBlob::newFromJSON( $dbr, $input ?? '' );

		$status = $ti->getStatus();
		if ( !$status->isOK() ) {
			$parserOutput->setExtensionData( 'TemplateDataStatus', TemplateDataStatus::jsonSerialize( $status ) );
			return Html::errorBox( $status->getHTML() );
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
			$parserOutput->setPageProperty( 'templatedata', $ti->getJSONForDatabase() );
		}

		$parserOutput->addModuleStyles( [
			'ext.templateData',
			'ext.templateData.images',
			'jquery.tablesorter.styles',
		] );
		$parserOutput->addModules( [ 'jquery.tablesorter' ] );
		$parserOutput->setEnableOOUI( true );

		$userLang = $parser->getOptions()->getUserLangObj();

		// FIXME: this hard-codes default skin, but it is needed because
		// ::getHtml() will need a theme singleton to be set.
		OutputPage::setupOOUI( 'bogus', $userLang->getDir() );

		$localizer = new TemplateDataMessageLocalizer( $userLang );
		$formatter = new TemplateDataHtmlFormatter( $localizer, $userLang->getCode() );
		return $formatter->getHtml( $ti, $frame->getTitle(), !$parser->getOptions()->getIsPreview() );
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/OutputPageBeforeHTML
	 *
	 * @param OutputPage $output
	 * @param string &$text
	 */
	public function onOutputPageBeforeHTML( $output, &$text ) {
		$services = MediaWikiServices::getInstance();
		$props = $services->getPageProps()->getProperties( $output->getTitle(), 'templatedata' );
		if ( $props ) {
			$lang = $output->getLanguage();
			$localizer = new TemplateDataMessageLocalizer( $lang );
			$formatter = new TemplateDataHtmlFormatter( $localizer, $lang->getCode() );
			$formatter->replaceEditLink( $text );
		}
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
	 * @param \stdClass[] &$tplData
	 * @return bool
	 */
	public function onParserFetchTemplateData( array $tplTitles, array &$tplData ): bool {
		$tplData = [];

		$services = MediaWikiServices::getInstance();
		$pageProps = $services->getPageProps();
		$wikiPageFactory = $services->getWikiPageFactory();
		$dbr = $services->getConnectionProvider()->getReplicaDatabase();

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
				$title = $wikiPageFactory->newFromTitle( $title )->getRedirectTarget();
				if ( !$title ) {
					// Invalid redirecting title
					$tplData[$tplTitle] = null;
					continue;
				}
			}

			if ( !$title->exists() ) {
				$tplData[$tplTitle] = (object)[ 'missing' => true ];
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
			$props = $pageProps->getProperties( $title, 'templatedata' );
			if ( !isset( $props[$pageId] ) ) {
				// No templatedata
				$tplData[$tplTitle] = (object)[ 'notemplatedata' => true ];
				continue;
			}

			$tdb = TemplateDataBlob::newFromDatabase( $dbr, $props[$pageId] );
			$status = $tdb->getStatus();
			if ( !$status->isOK() ) {
				// Invalid data. Parsoid has no use for the error.
				$tplData[$tplTitle] = (object)[ 'notemplatedata' => true ];
				continue;
			}

			$tplData[$tplTitle] = $tdb->getData();
		}
		return true;
	}

	/**
	 * GetPreferences hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetPreferences
	 *
	 * @param User $user
	 * @param array &$defaultPreferences
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onGetPreferences( $user, &$defaultPreferences ) {
		$defaultPreferences['templatedata-favorite-templates'] = [
			'type' => 'api',
		];
	}

	/**
	 * SaveUserOptions hook handler
	 *
	 * Validates the favorite templates JSON array before saving it to the database.
	 * - Removes duplicates
	 * - Removes non-integer values
	 * - Removes values less than 1
	 * - Aborts if the array has more than wgTemplateDataMaxFavorites values
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SaveUserOptions
	 *
	 * @param UserIdentity $user
	 * @param array &$modifiedOptions
	 * @param array $originalOptions
	 */
	public function onSaveUserOptions( $user, &$modifiedOptions, $originalOptions ) {
		if ( isset( $modifiedOptions['templatedata-favorite-templates'] ) ) {
			$maximumFavorites = $this->config->get( 'TemplateDataMaxFavorites' );

			$favoriteTemplates = json_decode( $modifiedOptions['templatedata-favorite-templates'] );
			// If we can't decode the JSON, we don't want to save it
			if ( $favoriteTemplates === null ) {
				unset( $modifiedOptions['templatedata-favorite-templates'] );
				return;
			}

			// Remove duplicates
			$favoriteTemplates = array_unique( $favoriteTemplates );
			// Filter out non-integers and values less than 1
			$favoriteTemplates = array_filter( $favoriteTemplates, static function ( $favorite ) {
				return is_int( $favorite ) && $favorite > 0;
			} );
			// Check if we have more than wgTemplateDataMaxFavorites values
			if ( count( $favoriteTemplates ) > intval( $maximumFavorites ) ) {
				unset( $modifiedOptions['templatedata-favorite-templates'] );
				return;
			}
			// After these steps the indexes are not preserved, so we need to 're-index' the array
			$favoriteTemplates = array_values( $favoriteTemplates );

			$encodedFavorites = json_encode( $favoriteTemplates );
			// If we can't encode the array, we don't want to save it
			if ( $encodedFavorites === false ) {
				unset( $modifiedOptions['templatedata-favorite-templates'] );
				return;
			}

			$modifiedOptions['templatedata-favorite-templates'] = $encodedFavorites;
		}
	}

}
