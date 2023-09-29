<?php

namespace MediaWiki\Parser\Parsoid;

use Composer\Semver\Semver;
use Content;
use ContentHandler;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use LogicException;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Parser\Parsoid\Config\PageConfigFactory;
use MediaWiki\Rest\HttpException;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use Wikimedia\Bcp47Code\Bcp47Code;
use Wikimedia\Parsoid\Config\PageConfig;
use Wikimedia\Parsoid\Core\ClientError;
use Wikimedia\Parsoid\Core\PageBundle;
use Wikimedia\Parsoid\Core\ResourceLimitExceededException;
use Wikimedia\Parsoid\Core\SelserData;
use Wikimedia\Parsoid\DOM\Document;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Parsoid;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\Timing;

/**
 * This class allows HTML to be transformed to a page content source format such as wikitext.
 *
 * @since 1.40
 * @unstable should be stable before 1.40 release
 */
class HtmlToContentTransform {
	/** @var array */
	private $options = [];

	/** @var ?int */
	private $oldid = null;

	/** @var ?Bcp47Code */
	private $contentLanguage = null;

	/** @var ?Content */
	private $originalContent = null;

	/** @var ?RevisionRecord */
	private $originalRevision = null;

	/**
	 * Whether $this->doc has had any necessary processing applied,
	 * such as injecting data-parsoid attributes from a PageBundle.
	 * @var bool
	 */
	private $docHasBeenProcessed = false;

	/** @var ?Document */
	private $doc = null;

	/** @var ?Element */
	private $originalBody = null;

	/** @var ?StatsdDataFactoryInterface A statistics aggregator */
	protected $metrics = null;

	/** @var PageBundle */
	private $modifiedPageBundle;

	/** @var PageBundle */
	private $originalPageBundle;

	/** @var ?PageConfig */
	private $pageConfig = null;

	/** @var Parsoid */
	private $parsoid;

	/** @var array */
	private $parsoidSettings;

	/** @var PageIdentity */
	private $page;

	/** @var PageConfigFactory */
	private $pageConfigFactory;

	/** @var IContentHandlerFactory */
	private $contentHandlerFactory;

	/**
	 * @param string $modifiedHTML
	 * @param PageIdentity $page
	 * @param Parsoid $parsoid
	 * @param array $parsoidSettings
	 * @param PageConfigFactory $pageConfigFactory
	 * @param IContentHandlerFactory $contentHandlerFactory
	 */
	public function __construct(
		string $modifiedHTML,
		PageIdentity $page,
		Parsoid $parsoid,
		array $parsoidSettings,
		PageConfigFactory $pageConfigFactory,
		IContentHandlerFactory $contentHandlerFactory
	) {
		$this->parsoid = $parsoid;
		$this->parsoidSettings = $parsoidSettings;
		$this->modifiedPageBundle = new PageBundle( $modifiedHTML );
		$this->originalPageBundle = new PageBundle( '' );
		$this->page = $page;
		$this->pageConfigFactory = $pageConfigFactory;
		$this->contentHandlerFactory = $contentHandlerFactory;
	}

	/**
	 * @param StatsdDataFactoryInterface $metrics
	 */
	public function setMetrics( StatsdDataFactoryInterface $metrics ): void {
		$this->metrics = $metrics;
	}

	private function startTiming(): Timing {
		return Timing::start( $this->metrics );
	}

	private function incrementMetrics( string $key ) {
		if ( $this->metrics ) {
			$this->metrics->increment( $key );
		}
	}

	private function timingMetrics( string $key, $value ) {
		if ( $this->metrics ) {
			$this->metrics->timing( $key, $value );
		}
	}

	public function setOptions( array $options ) {
		$this->options = $options;
	}

	/**
	 * @param RevisionRecord $rev
	 */
	public function setOriginalRevision( RevisionRecord $rev ): void {
		if ( $this->pageConfig ) {
			throw new LogicException( 'Cannot set revision after using the PageConfig' );
		}
		if ( $this->originalRevision ) {
			throw new LogicException( 'Cannot set revision again' );
		}

		$this->originalRevision = $rev;
		$this->oldid = $rev->getId();
	}

	/**
	 * @param int $oldid
	 */
	public function setOriginalRevisionId( int $oldid ): void {
		if ( $this->pageConfig ) {
			throw new LogicException( 'Cannot set revision ID after using the PageConfig' );
		}
		if ( $this->originalRevision ) {
			throw new LogicException( 'Cannot set revision again' );
		}

		$this->oldid = $oldid;
	}

	/**
	 * @param Bcp47Code $lang
	 */
	public function setContentLanguage( Bcp47Code $lang ): void {
		if ( $this->pageConfig ) {
			throw new LogicException( 'Cannot set content language after using the PageConfig' );
		}

		$this->contentLanguage = $lang;
	}

	/**
	 * Sets the original source text (usually wikitext).
	 *
	 * @param string $text
	 */
	public function setOriginalText( string $text ): void {
		$content = $this->getContentHandler()->unserializeContent( $text );
		$this->setOriginalContent( $content );
	}

	/**
	 * Sets the original content (such as wikitext).
	 *
	 * @param Content $content
	 */
	public function setOriginalContent( Content $content ): void {
		if ( $this->pageConfig ) {
			throw new LogicException( 'Cannot set text after using the PageConfig' );
		}
		if ( $this->originalRevision ) {
			throw new LogicException( 'Cannot set wikitext after using the PageConfig' );
		}

		$this->options['contentmodel'] = $content->getModel();
		$this->originalContent = $content;
	}

	private function validatePageBundle( PageBundle $pb ) {
		if ( !$pb->version ) {
			return;
		}

		$errorMessage = '';
		if ( !$pb->validate( $pb->version, $errorMessage ) ) {
			throw new ClientError( $errorMessage );
		}
	}

	/**
	 * @note Call this after all original data has been set!
	 *
	 * @param array $modifiedDataMW
	 */
	public function setModifiedDataMW( array $modifiedDataMW ): void {
		// Relies on setOriginalSchemaVersion having been called already.
		if ( !Semver::satisfies( $this->getSchemaVersion(), '^999.0.0' ) ) {
			throw new ClientError( 'Modified data-mw is not supported by schema version '
				. $this->getSchemaVersion() );
		}

		$this->modifiedPageBundle->mw = $modifiedDataMW;
	}

	/**
	 * @param string $originalSchemaVeraion
	 */
	public function setOriginalSchemaVersion( string $originalSchemaVeraion ): void {
		$this->originalPageBundle->version = $originalSchemaVeraion;
	}

	/**
	 * @param string $originalHtml
	 */
	public function setOriginalHtml( string $originalHtml ): void {
		if ( $this->doc ) {
			throw new LogicException( __FUNCTION__ . ' cannot be called after' .
				' getModifiedDocument()' );
		}

		$this->originalPageBundle->html = $originalHtml;
	}

	/**
	 * @param array $originalDataMW
	 */
	public function setOriginalDataMW( array $originalDataMW ): void {
		if ( $this->doc ) {
			throw new LogicException( __FUNCTION__ . ' cannot be called after getModifiedDocument()' );
		}

		$this->originalPageBundle->mw = $originalDataMW;

		// Modified data-mw is going to be the same as original data-mw,
		// unless specified otherwise.
		if ( $this->modifiedPageBundle->mw === null ) {
			$this->modifiedPageBundle->mw = $originalDataMW;
		}
	}

	/**
	 * @param array $originalDataParsoid
	 */
	public function setOriginalDataParsoid( array $originalDataParsoid ): void {
		if ( $this->doc ) {
			throw new LogicException( __FUNCTION__ . ' cannot be called after getModifiedDocument()' );
		}

		// data-parsoid is going to be the same for original and modified.
		$this->originalPageBundle->parsoid = $originalDataParsoid;
		$this->modifiedPageBundle->parsoid = $originalDataParsoid;
	}

	/**
	 * @return PageConfig
	 */
	private function getPageConfig(): PageConfig {
		if ( !$this->pageConfig ) {

			// XXX: do we even have to support wikitext overrides? What's the use case?
			if ( $this->originalContent !== null ) {
				// Create a mutable revision record point to the same revision
				// and set to the desired content.
				$revision = new MutableRevisionRecord( $this->page );
				if ( $this->oldid ) {
					$revision->setId( $this->oldid );
				}

				$revision->setSlot(
					SlotRecord::newUnsaved(
						SlotRecord::MAIN,
						$this->originalContent
					)
				);
			} else {
				// NOTE: PageConfigFactory allows $revision to be an int ID or a RevisionRecord.
				$revision = $this->originalRevision ?: $this->oldid;
			}

			try {
				$this->pageConfig = $this->pageConfigFactory->create(
					$this->page,
					null,
					$revision,
					null,
					$this->contentLanguage,
					$this->parsoidSettings
				);
			} catch ( RevisionAccessException $exception ) {
				// TODO: Throw a different exception, this class should not know
				//       about HTTP status codes.
				throw new HttpException( 'The specified revision is deleted or suppressed.', 404 );
			}
		}

		return $this->pageConfig;
	}

	/**
	 * The size of the modified HTML in characters.
	 *
	 * @return int
	 */
	public function getModifiedHtmlSize(): int {
		return mb_strlen( $this->modifiedPageBundle->html );
	}

	private function getModifiedDocumentRaw(): Document {
		if ( !$this->doc ) {
			$this->doc = $this->parseHTML( $this->modifiedPageBundle->html, true );
			$this->modifiedPageBundle->version = DOMUtils::extractInlinedContentVersion( $this->doc );
		}

		return $this->doc;
	}

	public function getModifiedDocument(): Document {
		$doc = $this->getModifiedDocumentRaw();

		if ( !$this->docHasBeenProcessed ) {
			$this->applyPageBundle( $this->doc, $this->modifiedPageBundle );

			$this->docHasBeenProcessed = true;
		}

		return $doc;
	}

	/**
	 * NOTE: The return value of this method depends on
	 *    setOriginalData() having been called first.
	 *
	 * @return bool
	 */
	public function hasOriginalHtml(): bool {
		return $this->originalPageBundle->html !== null && $this->originalPageBundle->html !== '';
	}

	/**
	 * NOTE: The return value of this method depends on
	 *    setOriginalData() having been called first.
	 *
	 * @return bool
	 */
	public function hasOriginalDataParsoid(): bool {
		return $this->originalPageBundle->parsoid !== null;
	}

	/**
	 * Returns the original HTML, with any necessary processing applied.
	 *
	 * @todo Make this method redundant, nothing should operate on HTML strings.
	 *
	 * @return string
	 */
	public function getOriginalHtml(): string {
		// NOTE: Schema version should have been set explicitly,
		//       so don't call getOriginalSchemaVersion,
		//       which will silently fall back to the default.
		if ( !$this->originalPageBundle->version ) {
			throw new ClientError(
				'Content-type of original html is missing.'
			);
		}

		if ( !$this->originalBody ) {
			// NOTE: Make sure we called getOriginalBody() at least once before we
			//       return the original HTML, so downgrades can be applied,
			//       data-parsoid can be injected, and $this->originalPageBundle->html
			//       is updated accordingly.

			if ( $this->hasOriginalDataParsoid() || $this->needsDowngrade( $this->originalPageBundle ) ) {
				$this->getOriginalBody();
			}
		}

		return $this->originalPageBundle->html ?: '';
	}

	/**
	 * @param string $html
	 * @param bool $validateXMLNames
	 *
	 * @return Document
	 * @throws ClientError
	 */
	protected function parseHTML( string $html, bool $validateXMLNames = false ): Document {
		return DOMUtils::parseHTML( $html, $validateXMLNames );
	}

	/**
	 * NOTE: The return value of this method depends on
	 *    setOriginalData() having been called first.
	 *
	 * @return Element
	 * @throws ClientError
	 */
	public function getOriginalBody(): Element {
		if ( !$this->hasOriginalHtml() ) {
			throw new LogicException(
				'No original data supplied, call hasOriginalHtml() first.'
			);
		}

		if ( $this->originalBody ) {
			return $this->originalBody;
		}

		// NOTE: Schema version should have been set explicitly,
		//       so don't call getOriginalSchemaVersion,
		//       which will silently fall back to the default.
		if ( !$this->originalPageBundle->version ) {
			throw new ClientError(
				'Content-type of original html is missing.'
			);
		}

		if ( $this->needsDowngrade( $this->originalPageBundle ) ) {
			$this->downgradeOriginalData( $this->originalPageBundle, $this->getSchemaVersion() );
		}

		$doc = $this->parseHTML( $this->originalPageBundle->html );

		$this->applyPageBundle( $doc, $this->originalPageBundle );

		$this->originalBody = DOMCompat::getBody( $doc );

		// XXX: use a separate field??
		$this->originalPageBundle->html = ContentUtils::toXML( $this->originalBody );

		return $this->originalBody;
	}

	public function getOriginalSchemaVersion(): string {
		return $this->originalPageBundle->version ?: $this->getSchemaVersion();
	}

	/**
	 * NOTE: The return value of this method depends on
	 *    setOriginalData() having been called first.
	 *
	 * @return string
	 */
	public function getSchemaVersion(): string {
		// Get the content version of the edited doc, if available.
		// Make sure $this->modifiedPageBundle->version is initialized.
		$this->getModifiedDocumentRaw();
		$inputContentVersion = $this->modifiedPageBundle->version;

		if ( !$inputContentVersion ) {
			$this->incrementMetrics( 'html2wt.original.version.notinline' );
			$inputContentVersion = $this->originalPageBundle->version ?: Parsoid::defaultHTMLVersion();
		}

		return $inputContentVersion;
	}

	public function getOriginalRevisionId(): ?int {
		return $this->oldid;
	}

	public function knowsOriginalContent(): bool {
		return $this->originalRevision || $this->oldid || $this->originalContent !== null;
	}

	public function getContentModel(): ?string {
		return $this->options['contentmodel'] ?? null;
	}

	public function getOffsetType(): string {
		return $this->options['offsetType'];
	}

	private function needsDowngrade( PageBundle $pb ): bool {
		$vOriginal = $pb->version;
		$vEdited = $this->getSchemaVersion();

		// Downgrades are only expected to be between major version
		//
		// RESTBase was only expected to store latest version.  If a client asked for a version
		// not satisfied by the latest version, it would downgrade the stored version where
		// possible.  So, it's the original version that needs to satisfy the edited version,
		// otherwise it needs downgrading.
		//
		// There's also the case where an old version is not stored and a re-parse must occur.
		// Here again the original version generated will be the latest, either satisfying
		// the edited or needing downgrading.
		return $vOriginal !== null && !Semver::satisfies( $vOriginal, "^{$vEdited}" );
	}

	private function downgradeOriginalData( PageBundle $pb, string $targetSchemaVersion ) {
		if ( $pb->version === null ) {
			throw new ClientError( 'Missing schema version' );
		}

		if ( $targetSchemaVersion === $pb->version ) {
			// nothing to do.
			return;
		}

		if ( !$pb->parsoid ) {
			// XXX: Should we also support downgrades if $pb->html has everything inlined?
			// XXX: The downgrade should really be an operation on the DOM.
			return;
		}

		// We need to downgrade the original to match the edited doc's version.
		$downgrade = Parsoid::findDowngrade( $pb->version, $targetSchemaVersion );

		if ( !$downgrade ) {
			throw new ClientError(
				"No downgrade possible from schema version {$pb->version} to {$targetSchemaVersion}."
			);
		}

		$this->incrementMetrics(
			"downgrade.from.{$downgrade['from']}.to.{$downgrade['to']}"
		);
		$downgradeTiming = $this->startTiming();
		Parsoid::downgrade( $downgrade, $pb );
		$downgradeTiming->end( 'downgrade.time' );

		// NOTE: Set $this->originalBody to null so getOriginalBody() will re-generate it.
		// XXX: Parsoid::downgrade operates on the parsed Document, would be nice
		//      if we could get that instead of getting back HTML which we have to
		//      parse again!
		$this->originalBody = null;
	}

	/**
	 * @param Document $doc
	 * @param PageBundle $pb
	 *
	 * @throws ClientError
	 */
	private function applyPageBundle( Document $doc, PageBundle $pb ): void {
		if ( $pb->parsoid === null && $pb->mw === null ) {
			return;
		}

		// Verify that the top-level parsoid object either doesn't contain
		// offsetType, or that it matches the conversion that has been
		// explicitly requested.
		if ( isset( $pb->parsoid['offsetType'] ) ) {
			$offsetType = $this->getOffsetType();
			$origOffsetType = $pb->parsoid['offsetType'] ?? $offsetType;
			if ( $origOffsetType !== $offsetType ) {
				throw new ClientError(
					'DSR offsetType mismatch: ' . $origOffsetType . ' vs ' . $offsetType
				);
			}
		}

		$this->validatePageBundle( $pb );

		// TODO: HACK! Remove as soon as change I41e6c741a3e2e is in the version of Parsoid used by MW.
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		if ( isset( $pb->parsoid['ids'] ) && is_object( $pb->parsoid['ids'] ) ) {
			// recursively convert stdClass objects to associative arrays.
			$pb->parsoid = json_decode( json_encode( $pb->parsoid ), true );
		}

		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		if ( isset( $pb->mw['ids'] ) && is_object( $pb->mw['ids'] ) ) {
			// recursively convert stdClass objects to associative arrays.
			$pb->mw = json_decode( json_encode( $pb->mw ), true );
		}

		PageBundle::apply( $doc, $pb );
	}

	/**
	 * Get a selective serialization (selser) data object. This
	 * can be null if selser is not enabled or oldid is not available.
	 *
	 * @return SelserData|null
	 * @throws HttpException
	 */
	private function getSelserData(): ?SelserData {
		$oldhtml = $this->hasOriginalHtml() ? $this->getOriginalHtml() : null;

		// Selser requires knowledge of the original wikitext.
		$knowsOriginal = $this->knowsOriginalContent();

		if ( $knowsOriginal && !empty( $this->parsoidSettings['useSelser'] ) ) {
			if ( !$this->getPageConfig()->getRevisionContent() ) {
				throw new HttpException( 'Could not find previous revision. Has the page been locked / deleted?',
					409 );
			}

			// TODO: T234548/T234549 - $pageConfig->getPageMainContent() is deprecated:
			//       should use $env->topFrame->getSrcText()
			$selserData = new SelserData( $this->getPageConfig()->getPageMainContent(),
				$oldhtml );
		} else {
			$selserData = null;
		}

		return $selserData;
	}

	private function getContentHandler(): ContentHandler {
		$model = $this->getContentModel() ?: CONTENT_MODEL_WIKITEXT;

		return $this->contentHandlerFactory
			->getContentHandler( $model );
	}

	/**
	 * Returns a Content object derived from the supplied HTML.
	 *
	 * @return Content
	 */
	public function htmlToContent(): Content {
		$text = $this->htmlToText();
		$content = $this->getContentHandler()->unserializeContent( $text );

		return $content;
	}

	/**
	 * Converts the input HTML to source format, typically wikitext.
	 *
	 * @see Parsoid::dom2wikitext
	 *
	 * @return string
	 */
	private function htmlToText(): string {
		// Performance Timing options
		$timing = $this->startTiming();

		$doc = $this->getModifiedDocument();
		$htmlSize = $this->getModifiedHtmlSize();

		// Send input size to statsd/Graphite
		$this->timingMetrics( 'html2wt.size.input', $htmlSize );

		$inputContentVersion = $this->getSchemaVersion();

		$this->incrementMetrics(
			'html2wt.original.version.' . $inputContentVersion
		);

		$selserData = $this->getSelserData();

		$timing->end( 'html2wt.init' );

		try {
			$text = $this->parsoid->dom2wikitext( $this->getPageConfig(), $doc, [
				'inputContentVersion' => $inputContentVersion,
				'offsetType' => $this->getOffsetType(),
				'contentmodel' => $this->getContentModel(),
				'htmlSize' => $htmlSize, // used to trigger status 413 if the input is too big
			], $selserData );
		} catch ( ClientError $e ) {
			throw new HttpException( $e->getMessage(), 400 );
		} catch ( ResourceLimitExceededException $e ) {
			throw new HttpException( $e->getMessage(), 413 );
		}

		$total = $timing->end( 'html2wt.total' );
		$this->timingMetrics( 'html2wt.size.output', strlen( $text ) );

		if ( $htmlSize ) {  // Avoid division by zero
			// NOTE: the name timePerInputKB is misleading, since $htmlSize is
			//       in characters, not bytes.
			$this->timingMetrics( 'html2wt.timePerInputKB', $total * 1024 / $htmlSize );
		}

		return $text;
	}

}
