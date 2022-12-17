<?php
/**
 * Helper functions for using the REST interface to Parsoid/RESTBase.
 *
 * @file
 * @ingroup Extensions
 * @copyright 2022 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

namespace MediaWiki\Extension\VisualEditor;

use Language;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parsoid\Config\PageConfigFactory;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use ParserOutput;
use RequestContext;
use Title;
use UIDGenerator;
use WikiMap;
use Wikimedia\Parsoid\Config\DataAccess;
use Wikimedia\Parsoid\Config\SiteConfig;
use Wikimedia\Parsoid\Core\SelserData;
use Wikimedia\Parsoid\Parsoid;
use Wikimedia\Parsoid\Utils\DOMUtils;
use WikitextContent;

class VisualEditorParsoidClient {
	/**
	 * Requested Parsoid HTML version.
	 * Keep this in sync with the Accept: header in
	 * ve.init.mw.ArticleTargetLoader.js
	 */
	public const PARSOID_VERSION = '2.4.0';

	/** @var array Parsoid-specific settings array from $config */
	private $parsoidSettings;

	/** @var SiteConfig */
	protected $siteConfig;

	/** @var PageConfigFactory */
	protected $pageConfigFactory;

	/** @var DataAccess */
	protected $dataAccess;

	/**
	 * @return static
	 */
	public static function factory(): VisualEditorParsoidClient {
		$services = MediaWikiServices::getInstance();
		return new static(
			$services->getMainConfig()->get( 'ParsoidSettings' ),
			$services->getParsoidSiteConfig(),
			$services->getParsoidPageConfigFactory(),
			$services->getParsoidDataAccess()
		);
	}

	/**
	 * @param array $parsoidSettings
	 * @param SiteConfig $siteConfig
	 * @param PageConfigFactory $pageConfigFactory
	 * @param DataAccess $dataAccess
	 */
	public function __construct(
		array $parsoidSettings,
		SiteConfig $siteConfig,
		PageConfigFactory $pageConfigFactory,
		DataAccess $dataAccess
	) {
		$this->parsoidSettings = $parsoidSettings;
		$this->siteConfig = $siteConfig;
		$this->pageConfigFactory = $pageConfigFactory;
		$this->dataAccess = $dataAccess;
	}

	/**
	 * Request page HTML
	 *
	 * @param RevisionRecord $revision Page revision
	 * @param Language $pageLanguage Page language
	 * @return array The response
	 */
	public function getPageHtml( RevisionRecord $revision, Language $pageLanguage ): array {
		$title = Title::newFromLinkTarget( $revision->getPageAsLinkTarget() );
		$oldid = $revision->getId();
		$lang = $pageLanguage->getCode();
		// This is /page/html/$title/$revision?redirect=false&stash=true
		// With Accept-Language: $lang
		$envOptions = [
			// $attribs['envOptions'] is created in ParsoidHandler::getRequestAttributes()
			'prefix' => WikiMap::getCurrentWikiId(),
			'pageName' => $title->getPrefixedDBkey(),
			'htmlVariantLanguage' => $lang,
			'outputContentVersion' => Parsoid::resolveContentVersion(
				self::PARSOID_VERSION
			) ?? Parsoid::defaultHTMLVersion(),
		];
		// $pageConfig originally created in
		// ParsoidHandler::tryToCreatePageConfig
		$user = RequestContext::getMain()->getUser();
		// Note: Parsoid by design isn't supposed to use the user
		// context right now, and all user state is expected to be
		// introduced as a post-parse transform.  So although we pass a
		// User here, it only currently affects the output in obscure
		// corner cases; see PageConfigFactory::create() for more.
		$pageConfig = $this->pageConfigFactory->create(
			$title, $user, $revision, null, $lang, $this->parsoidSettings
		);
		if ( $pageConfig->getRevisionContent() === null ) {
			throw new \LogicException( "Specified revision does not exist" );
		}
		$parsoid = new Parsoid( $this->siteConfig, $this->dataAccess );
		$parserOutput = new ParserOutput();
		// Note that $headers is an out parameter
		// $envOptions originally included $opts['contentmodel'] here as well
		$out = $parsoid->wikitext2html(
			$pageConfig, $envOptions, $headers, $parserOutput
		);
		$tid = UIDGenerator::newUUIDv1();
		$etag = "W/\"{$oldid}/{$tid}\"";
		# XXX: we could cache this locally using the $etag as a key,
		# then reuse it when transforming back to wikitext below.
		return [
			'body' => $out,
			'headers' => $headers + [
				'etag' => $etag,
			],
		];
	}

	/**
	 * Transform HTML to wikitext via Parsoid
	 *
	 * @param Title $title The title of the page
	 * @param Language $pageLanguage Page language
	 * @param string $html The HTML of the page to be transformed
	 * @param ?int $oldid What oldid revision, if any, to base the request from (default: `null`)
	 * @param ?string $etag The ETag to set in the HTTP request header
	 * @return array The response, 'code', 'reason', 'headers' and 'body'
	 */
	public function transformHTML(
		Title $title, Language $pageLanguage, string $html, ?int $oldid, ?string $etag
	): array {
		// This is POST /transform/html/to/wikitext/$title/$oldid
		// with header If-Match: $etag
		// and data: [ 'html' => $html ]
		$lang = $pageLanguage->getCode();
		// $pageConfig originally created in
		// ParsoidHandler::tryToCreatePageConfig
		$user = RequestContext::getMain()->getUser();
		// Note: Parsoid by design isn't supposed to use the user
		// context right now, and all user state is expected to be
		// introduced as a post-parse transform.  So although we pass a
		// User here, it only currently affects the output in obscure
		// corner cases; see PageConfigFactory::create() for more.
		$pageConfig = $this->pageConfigFactory->create(
			$title, $user, $oldid, null, $lang, $this->parsoidSettings
		);
		$doc = DOMUtils::parseHTML( $html, true );
		$vEdited = DOMUtils::extractInlinedContentVersion( $doc ) ??
				 Parsoid::defaultHTMLVersion();
		// T267990: This should be replaced by PET's ParserCache/stash
		// mechanism. (RESTBase did the fetch based on the etag, and then
		// compared vEdited to vOriginal to determine if it was usable.)
		$oldHtml = null;
		$selserData = ( $oldid === null ) ? null : new SelserData(
			$pageConfig->getPageMainContent(), $oldHtml
		);
		$parsoid = new Parsoid( $this->siteConfig, $this->dataAccess );
		$wikitext = $parsoid->dom2wikitext( $pageConfig, $doc, [
			'inputContentVersion' => $vEdited,
			'htmlSize' => mb_strlen( $html ),
		], $selserData );
		return [
			'body' => $wikitext,
		];
	}

	/**
	 * Transform wikitext to HTML via Parsoid.
	 *
	 * @param Title $title The title of the page to use as the parsing context
	 * @param Language $pageLanguage Page language
	 * @param string $wikitext The wikitext fragment to parse
	 * @param bool $bodyOnly Whether to provide only the contents of the `<body>` tag
	 * @param ?int $oldid What oldid revision, if any, to base the request from (default: `null`)
	 * @param bool $stash Whether to stash the result in the server-side cache (default: `false`)
	 * @return array The response, 'code', 'reason', 'headers' and 'body'
	 */
	public function transformWikitext(
		Title $title, Language $pageLanguage, string $wikitext,
		bool $bodyOnly, ?int $oldid, bool $stash
	): array {
		// This is POST /transform/wikitext/to/html/$title/$oldid
		// with data: [
		//   'wikitext' => $wikitext,
		//   'body_only' => $bodyOnly,
		//   'stash' => $stash,
		// ]
		// T267990: Stashing features are not implemented in zero-conf mode;
		// they will eventually be replaced by PET's ParserCache/stash mechanism
		$lang = $pageLanguage->getCode();
		$envOptions = [
			// $attribs['envOptions'] is created in ParsoidHandler::getRequestAttributes()
			'prefix' => WikiMap::getCurrentWikiId(),
			'pageName' => $title->getPrefixedDBkey(),
			'htmlVariantLanguage' => $lang,
			'outputContentVersion' => Parsoid::resolveContentVersion(
				self::PARSOID_VERSION
			) ?? Parsoid::defaultHTMLVersion(),
			'body_only' => $bodyOnly,
			// When VE does a fragment expansion (for example when
			// template arguments are edited and it wants an updated
			// render of the template) it's not going to want section
			// tags; in this case bodyOnly=true and wrapSections=false.
			// (T181226)
			// But on the other hand, VE doesn't do anything with section
			// tags right now other than strip them, so we'll just always
			// pass wrapSections=false for now.
			'wrapSections' => false,
		];
		// $pageConfig originally created in
		// ParsoidHandler::tryToCreatePageConfig
		$user = RequestContext::getMain()->getUser();
		// Note: Parsoid by design isn't supposed to use the user
		// context right now, and all user state is expected to be
		// introduced as a post-parse transform.  So although we pass a
		// User here, it only currently affects the output in obscure
		// corner cases; see PageConfigFactory::create() for more.

		// Create a mutable revision record and set to the desired wikitext.
		$tmpRevision = new MutableRevisionRecord( $title );
		$tmpRevision->setSlot(
			SlotRecord::newUnsaved(
				SlotRecord::MAIN,
				new WikitextContent( $wikitext )
			)
		);

		$pageConfig = $this->pageConfigFactory->create(
			$title, $user, $tmpRevision, null, $lang, $this->parsoidSettings
		);
		$parsoid = new Parsoid( $this->siteConfig, $this->dataAccess );
		$parserOutput = new ParserOutput();
		// Note that $headers is an out parameter
		$out = $parsoid->wikitext2html(
			$pageConfig, $envOptions, $headers, $parserOutput
		);
		// No etag generation in this pathway, and no caching
		// This is just used to update previews when you edit a template
		return [
			'body' => $out,
			'headers' => $headers,
		];
	}
}
