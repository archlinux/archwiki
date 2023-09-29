<?php
/**
 * Copyright © 2006 Yuri Astrakhan "<Firstname><Lastname>@gmail.com"
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\Linker\LinksMigration;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MainConfigNames;
use MediaWiki\ParamValidator\TypeDef\TitleDef;
use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\EnumDef;

/**
 * A query module to show basic page information.
 *
 * @ingroup API
 */
class ApiQueryInfo extends ApiQueryBase {

	/** @var ILanguageConverter */
	private $languageConverter;
	/** @var LinkBatchFactory */
	private $linkBatchFactory;
	/** @var NamespaceInfo */
	private $namespaceInfo;
	/** @var TitleFactory */
	private $titleFactory;
	/** @var TitleFormatter */
	private $titleFormatter;
	/** @var WatchedItemStore */
	private $watchedItemStore;
	/** @var RestrictionStore */
	private $restrictionStore;
	/** @var LinksMigration */
	private $linksMigration;

	private $fld_protection = false, $fld_talkid = false,
		$fld_subjectid = false, $fld_url = false,
		$fld_readable = false, $fld_watched = false,
		$fld_watchers = false, $fld_visitingwatchers = false,
		$fld_notificationtimestamp = false,
		$fld_preload = false, $fld_displaytitle = false, $fld_varianttitles = false;

	/**
	 * @var bool Whether to include link class information for the
	 *    given page titles.
	 */
	private $fld_linkclasses = false;

	/**
	 * @var bool Whether to include the name of the associated page
	 */
	private $fld_associatedpage = false;

	private $params;

	/** @var Title[] */
	private $titles;
	/** @var Title[] */
	private $missing;
	/** @var Title[] */
	private $everything;

	private $pageIsRedir, $pageIsNew, $pageTouched,
		$pageLatest, $pageLength;

	private $protections, $restrictionTypes, $watched, $watchers, $visitingwatchers,
		$notificationtimestamps, $talkids, $subjectids, $displaytitles, $variantTitles;

	/**
	 * Watchlist expiries that corresponds with the $watched property. Keyed by namespace and title.
	 * @var array<int,array<string,string>>
	 */
	private $watchlistExpiries;

	/**
	 * @var array<int,string[]> Mapping of page id to list of 'extra link
	 *   classes' for the given page
	 */
	private $linkClasses;

	private $showZeroWatchers = false;

	private $countTestedActions = 0;

	/**
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 * @param Language $contentLanguage
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param NamespaceInfo $namespaceInfo
	 * @param TitleFactory $titleFactory
	 * @param TitleFormatter $titleFormatter
	 * @param WatchedItemStore $watchedItemStore
	 * @param LanguageConverterFactory $languageConverterFactory
	 * @param RestrictionStore $restrictionStore
	 * @param LinksMigration $linksMigration
	 */
	public function __construct(
		ApiQuery $queryModule,
		$moduleName,
		Language $contentLanguage,
		LinkBatchFactory $linkBatchFactory,
		NamespaceInfo $namespaceInfo,
		TitleFactory $titleFactory,
		TitleFormatter $titleFormatter,
		WatchedItemStore $watchedItemStore,
		LanguageConverterFactory $languageConverterFactory,
		RestrictionStore $restrictionStore,
		LinksMigration $linksMigration
	) {
		parent::__construct( $queryModule, $moduleName, 'in' );
		$this->languageConverter = $languageConverterFactory->getLanguageConverter( $contentLanguage );
		$this->linkBatchFactory = $linkBatchFactory;
		$this->namespaceInfo = $namespaceInfo;
		$this->titleFactory = $titleFactory;
		$this->titleFormatter = $titleFormatter;
		$this->watchedItemStore = $watchedItemStore;
		$this->restrictionStore = $restrictionStore;
		$this->linksMigration = $linksMigration;
	}

	/**
	 * @param ApiPageSet $pageSet
	 * @return void
	 */
	public function requestExtraData( $pageSet ) {
		// If the pageset is resolving redirects we won't get page_is_redirect.
		// But we can't know for sure until the pageset is executed (revids may
		// turn it off), so request it unconditionally.
		$pageSet->requestField( 'page_is_redirect' );
		$pageSet->requestField( 'page_is_new' );
		$config = $this->getConfig();
		$pageSet->requestField( 'page_touched' );
		$pageSet->requestField( 'page_latest' );
		$pageSet->requestField( 'page_len' );
		$pageSet->requestField( 'page_content_model' );
		if ( $config->get( MainConfigNames::PageLanguageUseDB ) ) {
			$pageSet->requestField( 'page_lang' );
		}
	}

	public function execute() {
		$this->params = $this->extractRequestParams();
		if ( $this->params['prop'] !== null ) {
			$prop = array_fill_keys( $this->params['prop'], true );
			$this->fld_protection = isset( $prop['protection'] );
			$this->fld_watched = isset( $prop['watched'] );
			$this->fld_watchers = isset( $prop['watchers'] );
			$this->fld_visitingwatchers = isset( $prop['visitingwatchers'] );
			$this->fld_notificationtimestamp = isset( $prop['notificationtimestamp'] );
			$this->fld_talkid = isset( $prop['talkid'] );
			$this->fld_subjectid = isset( $prop['subjectid'] );
			$this->fld_url = isset( $prop['url'] );
			$this->fld_readable = isset( $prop['readable'] );
			$this->fld_preload = isset( $prop['preload'] );
			$this->fld_displaytitle = isset( $prop['displaytitle'] );
			$this->fld_varianttitles = isset( $prop['varianttitles'] );
			$this->fld_linkclasses = isset( $prop['linkclasses'] );
			$this->fld_associatedpage = isset( $prop['associatedpage'] );
		}

		$pageSet = $this->getPageSet();
		$this->titles = $pageSet->getGoodTitles();
		$this->missing = $pageSet->getMissingTitles();
		$this->everything = $this->titles + $this->missing;
		$result = $this->getResult();

		uasort( $this->everything, [ Title::class, 'compare' ] );
		if ( $this->params['continue'] !== null ) {
			// Throw away any titles we're gonna skip so they don't
			// clutter queries
			$cont = $this->parseContinueParamOrDie( $this->params['continue'], [ 'int', 'string' ] );
			$conttitle = $this->titleFactory->makeTitleSafe( $cont[0], $cont[1] );
			$this->dieContinueUsageIf( !$conttitle );
			foreach ( $this->everything as $pageid => $title ) {
				if ( Title::compare( $title, $conttitle ) >= 0 ) {
					break;
				}
				unset( $this->titles[$pageid] );
				unset( $this->missing[$pageid] );
				unset( $this->everything[$pageid] );
			}
		}

		// when resolving redirects, no page will have this field
		$this->pageIsRedir = !$pageSet->isResolvingRedirects()
			? $pageSet->getCustomField( 'page_is_redirect' )
			: [];
		$this->pageIsNew = $pageSet->getCustomField( 'page_is_new' );

		$this->pageTouched = $pageSet->getCustomField( 'page_touched' );
		$this->pageLatest = $pageSet->getCustomField( 'page_latest' );
		$this->pageLength = $pageSet->getCustomField( 'page_len' );

		// Get protection info if requested
		if ( $this->fld_protection ) {
			$this->getProtectionInfo();
		}

		if ( $this->fld_watched || $this->fld_notificationtimestamp ) {
			$this->getWatchedInfo();
		}

		if ( $this->fld_watchers ) {
			$this->getWatcherInfo();
		}

		if ( $this->fld_visitingwatchers ) {
			$this->getVisitingWatcherInfo();
		}

		// Run the talkid/subjectid query if requested
		if ( $this->fld_talkid || $this->fld_subjectid ) {
			$this->getTSIDs();
		}

		if ( $this->fld_displaytitle ) {
			$this->getDisplayTitle();
		}

		if ( $this->fld_varianttitles ) {
			$this->getVariantTitles();
		}

		if ( $this->fld_linkclasses ) {
			$this->getLinkClasses( $this->params['linkcontext'] );
		}

		/** @var Title $title */
		foreach ( $this->everything as $pageid => $title ) {
			$pageInfo = $this->extractPageInfo( $pageid, $title );
			$fit = $pageInfo !== null && $result->addValue( [
				'query',
				'pages'
			], $pageid, $pageInfo );
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'continue',
					$title->getNamespace() . '|' .
					$title->getText() );
				break;
			}
		}
	}

	/**
	 * Get a result array with information about a title
	 * @param int $pageid Page ID (negative for missing titles)
	 * @param Title $title
	 * @return array|null
	 */
	private function extractPageInfo( $pageid, $title ) {
		$pageInfo = [];
		// $title->exists() needs pageid, which is not set for all title objects
		$titleExists = $pageid > 0;
		$ns = $title->getNamespace();
		$dbkey = $title->getDBkey();

		$pageInfo['contentmodel'] = $title->getContentModel();

		$pageLanguage = $title->getPageLanguage();
		$pageInfo['pagelanguage'] = $pageLanguage->getCode();
		$pageInfo['pagelanguagehtmlcode'] = $pageLanguage->getHtmlCode();
		$pageInfo['pagelanguagedir'] = $pageLanguage->getDir();

		if ( $titleExists ) {
			$pageInfo['touched'] = wfTimestamp( TS_ISO_8601, $this->pageTouched[$pageid] );
			$pageInfo['lastrevid'] = (int)$this->pageLatest[$pageid];
			$pageInfo['length'] = (int)$this->pageLength[$pageid];

			if ( isset( $this->pageIsRedir[$pageid] ) && $this->pageIsRedir[$pageid] ) {
				$pageInfo['redirect'] = true;
			}
			if ( $this->pageIsNew[$pageid] ) {
				$pageInfo['new'] = true;
			}
		}

		if ( $this->fld_protection ) {
			$pageInfo['protection'] = [];
			if ( isset( $this->protections[$ns][$dbkey] ) ) {
				$pageInfo['protection'] =
					$this->protections[$ns][$dbkey];
			}
			ApiResult::setIndexedTagName( $pageInfo['protection'], 'pr' );

			$pageInfo['restrictiontypes'] = [];
			if ( isset( $this->restrictionTypes[$ns][$dbkey] ) ) {
				$pageInfo['restrictiontypes'] =
					$this->restrictionTypes[$ns][$dbkey];
			}
			ApiResult::setIndexedTagName( $pageInfo['restrictiontypes'], 'rt' );
		}

		if ( $this->fld_watched ) {
			$pageInfo['watched'] = false;

			if ( isset( $this->watched[$ns][$dbkey] ) ) {
				$pageInfo['watched'] = $this->watched[$ns][$dbkey];
			}

			if ( isset( $this->watchlistExpiries[$ns][$dbkey] ) ) {
				$pageInfo['watchlistexpiry'] = $this->watchlistExpiries[$ns][$dbkey];
			}
		}

		if ( $this->fld_watchers ) {
			if ( $this->watchers !== null && $this->watchers[$ns][$dbkey] !== 0 ) {
				$pageInfo['watchers'] = $this->watchers[$ns][$dbkey];
			} elseif ( $this->showZeroWatchers ) {
				$pageInfo['watchers'] = 0;
			}
		}

		if ( $this->fld_visitingwatchers ) {
			if ( $this->visitingwatchers !== null && $this->visitingwatchers[$ns][$dbkey] !== 0 ) {
				$pageInfo['visitingwatchers'] = $this->visitingwatchers[$ns][$dbkey];
			} elseif ( $this->showZeroWatchers ) {
				$pageInfo['visitingwatchers'] = 0;
			}
		}

		if ( $this->fld_notificationtimestamp ) {
			$pageInfo['notificationtimestamp'] = '';
			if ( isset( $this->notificationtimestamps[$ns][$dbkey] ) ) {
				$pageInfo['notificationtimestamp'] =
					wfTimestamp( TS_ISO_8601, $this->notificationtimestamps[$ns][$dbkey] );
			}
		}

		if ( $this->fld_talkid && isset( $this->talkids[$ns][$dbkey] ) ) {
			$pageInfo['talkid'] = $this->talkids[$ns][$dbkey];
		}

		if ( $this->fld_subjectid && isset( $this->subjectids[$ns][$dbkey] ) ) {
			$pageInfo['subjectid'] = $this->subjectids[$ns][$dbkey];
		}

		if ( $this->fld_associatedpage && $ns >= NS_MAIN ) {
			$pageInfo['associatedpage'] = $this->titleFormatter->getPrefixedText(
				$this->namespaceInfo->getAssociatedPage( $title )
			);
		}

		if ( $this->fld_url ) {
			$pageInfo['fullurl'] = wfExpandUrl( $title->getFullURL(), PROTO_CURRENT );
			$pageInfo['editurl'] = wfExpandUrl( $title->getFullURL( 'action=edit' ), PROTO_CURRENT );
			$pageInfo['canonicalurl'] = wfExpandUrl( $title->getFullURL(), PROTO_CANONICAL );
		}
		if ( $this->fld_readable ) {
			$pageInfo['readable'] = $this->getAuthority()->definitelyCan( 'read', $title );
		}

		if ( $this->fld_preload ) {
			if ( $titleExists ) {
				$pageInfo['preload'] = '';
			} else {
				$text = null;
				// @phan-suppress-next-line PhanTypeMismatchArgument Type mismatch on pass-by-ref args
				$this->getHookRunner()->onEditFormPreloadText( $text, $title );

				$pageInfo['preload'] = $text;
			}
		}

		if ( $this->fld_displaytitle ) {
			$pageInfo['displaytitle'] = $this->displaytitles[$pageid] ??
				htmlspecialchars( $title->getPrefixedText(), ENT_NOQUOTES );
		}

		if ( $this->fld_varianttitles && isset( $this->variantTitles[$pageid] ) ) {
			$pageInfo['varianttitles'] = $this->variantTitles[$pageid];
		}

		if ( $this->fld_linkclasses && isset( $this->linkClasses[$pageid] ) ) {
			$pageInfo['linkclasses'] = $this->linkClasses[$pageid];
		}

		if ( $this->params['testactions'] ) {
			$limit = $this->getMain()->canApiHighLimits() ? self::LIMIT_SML2 : self::LIMIT_SML1;
			if ( $this->countTestedActions >= $limit ) {
				return null; // force a continuation
			}

			$detailLevel = $this->params['testactionsdetail'];
			$errorFormatter = $this->getErrorFormatter();
			if ( $errorFormatter->getFormat() === 'bc' ) {
				// Eew, no. Use a more modern format here.
				$errorFormatter = $errorFormatter->newWithFormat( 'plaintext' );
			}

			$pageInfo['actions'] = [];
			foreach ( $this->params['testactions'] as $action ) {
				$this->countTestedActions++;

				if ( $detailLevel === 'boolean' ) {
					$pageInfo['actions'][$action] = $this->getAuthority()->authorizeRead( $action, $title );
				} else {
					$status = new PermissionStatus();
					if ( $detailLevel === 'quick' ) {
						$this->getAuthority()->probablyCan( $action, $title, $status );
					} else {
						$this->getAuthority()->definitelyCan( $action, $title, $status );
					}
					$this->addBlockInfoToStatus( $status );
					$pageInfo['actions'][$action] = $errorFormatter->arrayFromStatus( $status );
				}
			}
		}

		return $pageInfo;
	}

	/**
	 * Get information about protections and put it in $protections
	 */
	private function getProtectionInfo() {
		$this->protections = [];
		$db = $this->getDB();

		// Get normal protections for existing titles
		if ( count( $this->titles ) ) {
			$this->resetQueryParams();
			$this->addTables( 'page_restrictions' );
			$this->addFields( [ 'pr_page', 'pr_type', 'pr_level',
				'pr_expiry', 'pr_cascade' ] );
			$this->addWhereFld( 'pr_page', array_keys( $this->titles ) );

			$res = $this->select( __METHOD__ );
			foreach ( $res as $row ) {
				/** @var Title $title */
				$title = $this->titles[$row->pr_page];
				$a = [
					'type' => $row->pr_type,
					'level' => $row->pr_level,
					'expiry' => ApiResult::formatExpiry( $row->pr_expiry )
				];
				if ( $row->pr_cascade ) {
					$a['cascade'] = true;
				}
				$this->protections[$title->getNamespace()][$title->getDBkey()][] = $a;
			}
		}

		// Get protections for missing titles
		if ( count( $this->missing ) ) {
			$this->resetQueryParams();
			$lb = $this->linkBatchFactory->newLinkBatch( $this->missing );
			$this->addTables( 'protected_titles' );
			$this->addFields( [ 'pt_title', 'pt_namespace', 'pt_create_perm', 'pt_expiry' ] );
			$this->addWhere( $lb->constructSet( 'pt', $db ) );
			$res = $this->select( __METHOD__ );
			foreach ( $res as $row ) {
				$this->protections[$row->pt_namespace][$row->pt_title][] = [
					'type' => 'create',
					'level' => $row->pt_create_perm,
					'expiry' => ApiResult::formatExpiry( $row->pt_expiry )
				];
			}
		}

		// Separate good and missing titles into files and other pages
		// and populate $this->restrictionTypes
		$images = $others = [];
		foreach ( $this->everything as $title ) {
			if ( $title->getNamespace() === NS_FILE ) {
				$images[] = $title->getDBkey();
			} else {
				$others[] = $title;
			}
			// Applicable protection types
			$this->restrictionTypes[$title->getNamespace()][$title->getDBkey()] =
				array_values( $this->restrictionStore->listApplicableRestrictionTypes( $title ) );
		}

		[ $blNamespace, $blTitle ] = $this->linksMigration->getTitleFields( 'templatelinks' );
		$queryInfo = $this->linksMigration->getQueryInfo( 'templatelinks' );

		if ( count( $others ) ) {
			// Non-images: check templatelinks
			$lb = $this->linkBatchFactory->newLinkBatch( $others );
			$this->resetQueryParams();
			$this->addTables( array_merge( [ 'page_restrictions', 'page' ], $queryInfo['tables'] ) );
			// templatelinks must use PRIMARY index and not the tl_target_id.
			$this->addOption( 'USE INDEX', [ 'templatelinks' => 'PRIMARY' ] );
			$this->addFields( [ 'pr_type', 'pr_level', 'pr_expiry',
				'page_title', 'page_namespace',
				$blNamespace, $blTitle ] );
			$this->addWhere( $lb->constructSet( 'tl', $db ) );
			$this->addWhere( 'pr_page = page_id' );
			$this->addWhere( 'pr_page = tl_from' );
			$this->addWhereFld( 'pr_cascade', 1 );
			$this->addJoinConds( $queryInfo['joins'] );

			$res = $this->select( __METHOD__ );
			foreach ( $res as $row ) {
				$this->protections[$row->$blNamespace][$row->$blTitle][] = [
					'type' => $row->pr_type,
					'level' => $row->pr_level,
					'expiry' => ApiResult::formatExpiry( $row->pr_expiry ),
					'source' => $this->titleFormatter->formatTitle( $row->page_namespace, $row->page_title ),
				];
			}
		}

		if ( count( $images ) ) {
			// Images: check imagelinks
			$this->resetQueryParams();
			$this->addTables( [ 'page_restrictions', 'page', 'imagelinks' ] );
			$this->addFields( [ 'pr_type', 'pr_level', 'pr_expiry',
				'page_title', 'page_namespace', 'il_to' ] );
			$this->addWhere( 'pr_page = page_id' );
			$this->addWhere( 'pr_page = il_from' );
			$this->addWhereFld( 'pr_cascade', 1 );
			$this->addWhereFld( 'il_to', $images );

			$res = $this->select( __METHOD__ );
			foreach ( $res as $row ) {
				$this->protections[NS_FILE][$row->il_to][] = [
					'type' => $row->pr_type,
					'level' => $row->pr_level,
					'expiry' => ApiResult::formatExpiry( $row->pr_expiry ),
					'source' => $this->titleFormatter->formatTitle( $row->page_namespace, $row->page_title ),
				];
			}
		}
	}

	/**
	 * Get talk page IDs (if requested) and subject page IDs (if requested)
	 * and put them in $talkids and $subjectids
	 */
	private function getTSIDs() {
		$getTitles = $this->talkids = $this->subjectids = [];
		$nsInfo = $this->namespaceInfo;

		/** @var Title $t */
		foreach ( $this->everything as $t ) {
			if ( $nsInfo->isTalk( $t->getNamespace() ) ) {
				if ( $this->fld_subjectid ) {
					$getTitles[] = $t->getSubjectPage();
				}
			} elseif ( $this->fld_talkid ) {
				$getTitles[] = $t->getTalkPage();
			}
		}
		if ( $getTitles === [] ) {
			return;
		}

		$db = $this->getDB();

		// Construct a custom WHERE clause that matches
		// all titles in $getTitles
		$lb = $this->linkBatchFactory->newLinkBatch( $getTitles );
		$this->resetQueryParams();
		$this->addTables( 'page' );
		$this->addFields( [ 'page_title', 'page_namespace', 'page_id' ] );
		$this->addWhere( $lb->constructSet( 'page', $db ) );
		$res = $this->select( __METHOD__ );
		foreach ( $res as $row ) {
			if ( $nsInfo->isTalk( $row->page_namespace ) ) {
				$this->talkids[$nsInfo->getSubject( $row->page_namespace )][$row->page_title] =
					(int)( $row->page_id );
			} else {
				$this->subjectids[$nsInfo->getTalk( $row->page_namespace )][$row->page_title] =
					(int)( $row->page_id );
			}
		}
	}

	private function getDisplayTitle() {
		$this->displaytitles = [];

		$pageIds = array_keys( $this->titles );

		if ( $pageIds === [] ) {
			return;
		}

		$this->resetQueryParams();
		$this->addTables( 'page_props' );
		$this->addFields( [ 'pp_page', 'pp_value' ] );
		$this->addWhereFld( 'pp_page', $pageIds );
		$this->addWhereFld( 'pp_propname', 'displaytitle' );
		$res = $this->select( __METHOD__ );

		foreach ( $res as $row ) {
			$this->displaytitles[$row->pp_page] = $row->pp_value;
		}
	}

	/**
	 * Fetch the set of extra link classes associated with links to the
	 * set of titles ("link colours"), as they would appear on the
	 * given context page.
	 * @param ?LinkTarget $context_title The page context in which link
	 *   colors are determined.
	 */
	private function getLinkClasses( ?LinkTarget $context_title = null ) {
		if ( $this->titles === [] ) {
			return;
		}
		// For compatibility with legacy GetLinkColours hook:
		// $pagemap maps from page id to title (as prefixed db key)
		// $classes maps from title (prefixed db key) to a space-separated
		//   list of link classes ("link colours").
		// The hook should not modify $pagemap, and should only append to
		// $classes (being careful to maintain space separation).
		$classes = [];
		$pagemap = [];
		foreach ( $this->titles as $pageId => $title ) {
			$pdbk = $title->getPrefixedDBkey();
			$pagemap[$pageId] = $pdbk;
			$classes[$pdbk] = $title->isRedirect() ? 'mw-redirect' : '';
		}
		// legacy hook requires a real Title, not a LinkTarget
		$context_title = $this->titleFactory->newFromLinkTarget(
			$context_title ?? $this->titleFactory->newMainPage()
		);
		$this->getHookRunner()->onGetLinkColours(
			$pagemap, $classes, $context_title
		);

		// This API class expects the class list to be:
		//  (a) indexed by pageid, not title, and
		//  (b) a proper array of strings (possibly zero-length),
		//      not a single space-separated string (possibly the empty string)
		$this->linkClasses = [];
		foreach ( $this->titles as $pageId => $title ) {
			$pdbk = $title->getPrefixedDBkey();
			$this->linkClasses[$pageId] = preg_split(
				'/\s+/', $classes[$pdbk] ?? '', -1, PREG_SPLIT_NO_EMPTY
			);
		}
	}

	private function getVariantTitles() {
		if ( $this->titles === [] ) {
			return;
		}
		$this->variantTitles = [];
		foreach ( $this->titles as $pageId => $t ) {
			$this->variantTitles[$pageId] = isset( $this->displaytitles[$pageId] )
				? $this->getAllVariants( $this->displaytitles[$pageId] )
				: $this->getAllVariants( $t->getText(), $t->getNamespace() );
		}
	}

	private function getAllVariants( $text, $ns = NS_MAIN ) {
		$result = [];
		foreach ( $this->languageConverter->getVariants() as $variant ) {
			$convertTitle = $this->languageConverter->autoConvert( $text, $variant );
			if ( $ns !== NS_MAIN ) {
				$convertNs = $this->languageConverter->convertNamespace( $ns, $variant );
				$convertTitle = $convertNs . ':' . $convertTitle;
			}
			$result[$variant] = $convertTitle;
		}
		return $result;
	}

	/**
	 * Get information about watched status and put it in $this->watched
	 * and $this->notificationtimestamps
	 */
	private function getWatchedInfo() {
		$user = $this->getUser();

		if ( !$user->isRegistered() || count( $this->everything ) == 0
			|| !$this->getAuthority()->isAllowed( 'viewmywatchlist' )
		) {
			return;
		}

		$this->watched = [];
		$this->watchlistExpiries = [];
		$this->notificationtimestamps = [];

		/** @var WatchedItem[] $items */
		$items = $this->watchedItemStore->loadWatchedItemsBatch( $user, $this->everything );

		foreach ( $items as $item ) {
			$nsId = $item->getTarget()->getNamespace();
			$dbKey = $item->getTarget()->getDBkey();

			if ( $this->fld_watched ) {
				$this->watched[$nsId][$dbKey] = true;

				$expiry = $item->getExpiry( TS_ISO_8601 );
				if ( $expiry ) {
					$this->watchlistExpiries[$nsId][$dbKey] = $expiry;
				}
			}

			if ( $this->fld_notificationtimestamp ) {
				$this->notificationtimestamps[$nsId][$dbKey] = $item->getNotificationTimestamp();
			}
		}
	}

	/**
	 * Get the count of watchers and put it in $this->watchers
	 */
	private function getWatcherInfo() {
		if ( count( $this->everything ) == 0 ) {
			return;
		}

		$canUnwatchedpages = $this->getAuthority()->isAllowed( 'unwatchedpages' );
		$unwatchedPageThreshold =
			$this->getConfig()->get( MainConfigNames::UnwatchedPageThreshold );
		if ( !$canUnwatchedpages && !is_int( $unwatchedPageThreshold ) ) {
			return;
		}

		$this->showZeroWatchers = $canUnwatchedpages;

		$countOptions = [];
		if ( !$canUnwatchedpages ) {
			$countOptions['minimumWatchers'] = $unwatchedPageThreshold;
		}

		$this->watchers = $this->watchedItemStore->countWatchersMultiple(
			$this->everything,
			$countOptions
		);
	}

	/**
	 * Get the count of watchers who have visited recent edits and put it in
	 * $this->visitingwatchers
	 *
	 * Based on InfoAction::pageCounts
	 */
	private function getVisitingWatcherInfo() {
		$config = $this->getConfig();
		$db = $this->getDB();

		$canUnwatchedpages = $this->getAuthority()->isAllowed( 'unwatchedpages' );
		$unwatchedPageThreshold = $config->get( MainConfigNames::UnwatchedPageThreshold );
		if ( !$canUnwatchedpages && !is_int( $unwatchedPageThreshold ) ) {
			return;
		}

		$this->showZeroWatchers = $canUnwatchedpages;

		$titlesWithThresholds = [];
		if ( $this->titles ) {
			$lb = $this->linkBatchFactory->newLinkBatch( $this->titles );

			// Fetch last edit timestamps for pages
			$this->resetQueryParams();
			$this->addTables( [ 'page', 'revision' ] );
			$this->addFields( [ 'page_namespace', 'page_title', 'rev_timestamp' ] );
			$this->addWhere( [
				'page_latest = rev_id',
				$lb->constructSet( 'page', $db ),
			] );
			$this->addOption( 'GROUP BY', [ 'page_namespace', 'page_title' ] );
			$timestampRes = $this->select( __METHOD__ );

			$age = $config->get( MainConfigNames::WatchersMaxAge );
			$timestamps = [];
			foreach ( $timestampRes as $row ) {
				$revTimestamp = wfTimestamp( TS_UNIX, (int)$row->rev_timestamp );
				$timestamps[$row->page_namespace][$row->page_title] = (int)$revTimestamp - $age;
			}
			$titlesWithThresholds = array_map(
				static function ( LinkTarget $target ) use ( $timestamps ) {
					return [
						$target, $timestamps[$target->getNamespace()][$target->getDBkey()]
					];
				},
				$this->titles
			);
		}

		if ( $this->missing ) {
			$titlesWithThresholds = array_merge(
				$titlesWithThresholds,
				array_map(
					static function ( LinkTarget $target ) {
						return [ $target, null ];
					},
					$this->missing
				)
			);
		}
		$this->visitingwatchers = $this->watchedItemStore->countVisitingWatchersMultiple(
			$titlesWithThresholds,
			!$canUnwatchedpages ? $unwatchedPageThreshold : null
		);
	}

	public function getCacheMode( $params ) {
		// Other props depend on something about the current user
		$publicProps = [
			'protection',
			'talkid',
			'subjectid',
			'associatedpage',
			'url',
			'preload',
			'displaytitle',
			'varianttitles',
		];
		if ( array_diff( (array)$params['prop'], $publicProps ) ) {
			return 'private';
		}

		// testactions also depends on the current user
		if ( $params['testactions'] ) {
			return 'private';
		}

		return 'public';
	}

	public function getAllowedParams() {
		return [
			'prop' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'protection',
					'talkid',
					'watched', # private
					'watchers', # private
					'visitingwatchers', # private
					'notificationtimestamp', # private
					'subjectid',
					'associatedpage',
					'url',
					'readable', # private
					'preload',
					'displaytitle',
					'varianttitles',
					'linkclasses', # private: stub length (and possibly hook colors)
					// If you add more properties here, please consider whether they
					// need to be added to getCacheMode()
				],
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
				EnumDef::PARAM_DEPRECATED_VALUES => [
					'readable' => true, // Since 1.32
				],
			],
			'linkcontext' => [
				ParamValidator::PARAM_TYPE => 'title',
				ParamValidator::PARAM_DEFAULT => $this->titleFactory->newMainPage()->getPrefixedText(),
				TitleDef::PARAM_RETURN_OBJECT => true,
			],
			'testactions' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_ISMULTI => true,
			],
			'testactionsdetail' => [
				ParamValidator::PARAM_TYPE => [ 'boolean', 'full', 'quick' ],
				ParamValidator::PARAM_DEFAULT => 'boolean',
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	protected function getExamplesMessages() {
		$title = Title::newMainPage()->getPrefixedText();
		$mp = rawurlencode( $title );

		return [
			"action=query&prop=info&titles={$mp}"
				=> 'apihelp-query+info-example-simple',
			"action=query&prop=info&inprop=protection&titles={$mp}"
				=> 'apihelp-query+info-example-protection',
		];
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Info';
	}
}
