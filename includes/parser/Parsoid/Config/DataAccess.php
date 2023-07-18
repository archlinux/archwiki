<?php
/**
 * Copyright (C) 2011-2022 Wikimedia Foundation and others.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace MediaWiki\Parser\Parsoid\Config;

use ContentHandler;
use File;
use MediaTransformError;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\Transform\ContentTransformer;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Linker\Linker;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\File\BadFileLookup;
use MediaWiki\Title\Title;
use Parser;
use ParserFactory;
use ReadOnlyMode;
use RepoGroup;
use Wikimedia\Parsoid\Config\DataAccess as IDataAccess;
use Wikimedia\Parsoid\Config\PageConfig as IPageConfig;
use Wikimedia\Parsoid\Config\PageContent as IPageContent;
use Wikimedia\Parsoid\Core\ContentMetadataCollector;

/**
 * Implement Parsoid's abstract class for data access.
 *
 * @since 1.39
 * @internal
 */
class DataAccess extends IDataAccess {

	/** @var RepoGroup */
	private $repoGroup;

	/** @var BadFileLookup */
	private $badFileLookup;

	/** @var HookContainer */
	private $hookContainer;

	/** @var HookRunner */
	private $hookRunner;

	/** @var ContentTransformer */
	private $contentTransformer;

	/** @var Parser */
	private $parser;

	/** @var \PPFrame */
	private $ppFrame;

	/** @var ?PageConfig */
	private $previousPageConfig;

	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::SVGMaxSize,
	];

	/** @var ServiceOptions */
	private $config;

	/** @var ReadOnlyMode */
	private $readOnlyMode;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/**
	 * @param ServiceOptions $config MediaWiki main configuration object
	 * @param RepoGroup $repoGroup
	 * @param BadFileLookup $badFileLookup
	 * @param HookContainer $hookContainer
	 * @param ContentTransformer $contentTransformer
	 * @param ReadOnlyMode $readOnlyMode used to disable linting when the
	 *   database is read-only.
	 * @param ParserFactory $parserFactory A legacy parser factory,
	 *   for PST/preprocessing/extension handling
	 * @param LinkBatchFactory $linkBatchFactory
	 */
	public function __construct(
		ServiceOptions $config,
		RepoGroup $repoGroup,
		BadFileLookup $badFileLookup,
		HookContainer $hookContainer,
		ContentTransformer $contentTransformer,
		ReadOnlyMode $readOnlyMode,
		ParserFactory $parserFactory,
		LinkBatchFactory $linkBatchFactory
	) {
		$config->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->config = $config;
		$this->repoGroup = $repoGroup;
		$this->badFileLookup = $badFileLookup;
		$this->hookContainer = $hookContainer;
		$this->contentTransformer = $contentTransformer;
		$this->readOnlyMode = $readOnlyMode;
		$this->linkBatchFactory = $linkBatchFactory;

		$this->hookRunner = new HookRunner( $hookContainer );

		// Use the same legacy parser object for all calls to extension tag
		// processing, for greater compatibility.
		$this->parser = $parserFactory->create();
		$this->previousPageConfig = null; // ensure we initialize parser options
	}

	/**
	 * @param IPageConfig $pageConfig
	 * @param File $file
	 * @param array $hp
	 * @return array
	 */
	private function makeTransformOptions( IPageConfig $pageConfig, $file, array $hp ): array {
		// Validate the input parameters like Parser::makeImage()
		$handler = $file->getHandler();
		if ( !$handler ) {
			return []; // will get iconThumb()
		}
		foreach ( $hp as $name => $value ) {
			if ( !$handler->validateParam( $name, $value ) ) {
				unset( $hp[$name] );
			}
		}

		// This part is similar to Linker::makeImageLink(). If there is no width,
		// set one based on the source file size.
		$page = $hp['page'] ?? 0;
		if ( !isset( $hp['width'] ) ) {
			if ( isset( $hp['height'] ) && $file->isVectorized() ) {
				// If it's a vector image, and user only specifies height
				// we don't want it to be limited by its "normal" width.
				$hp['width'] = $this->config->get( MainConfigNames::SVGMaxSize );
			} else {
				$hp['width'] = $file->getWidth( $page );
			}

			// We don't need to fill in a default thumbnail width here, since
			// that is done by Parsoid. Parsoid always sets the width parameter
			// for thumbnails.
		}

		// Parser::makeImage() always sets this
		$hp['targetlang'] = $pageConfig->getPageLanguage();

		return $hp;
	}

	/** @inheritDoc */
	public function getPageInfo( IPageConfig $pageConfig, array $titles ): array {
		$titleObjs = [];
		$pagemap = [];
		$classes = [];
		$ret = [];
		foreach ( $titles as $name ) {
			$t = Title::newFromText( $name );
			// Filter out invalid titles. Title::newFromText in core (not our bespoke
			// version in src/Utils/Title.php) can return null for invalid titles.
			if ( !$t ) {
				// FIXME: This is a bandaid to patch up the fact that Env::makeTitle treats
				// this as a valid title, but Title::newFromText treats it as invalid.
				// T237535
				// This matches what ApiQuery::outputGeneralPageInfo() would
				// return for an invalid title.
				$ret[$name] = [
					'pageId' => -1,
					'revId' => -1,
					'invalid' => true,
					'invalidreason' => 'The requested page title is invalid',
				];
			} else {
				$titleObjs[$name] = $t;
			}
		}
		$linkBatch = $this->linkBatchFactory->newLinkBatch( $titleObjs );
		$linkBatch->setCaller( __METHOD__ );
		$linkBatch->execute();

		foreach ( $titleObjs as $obj ) {
			$pdbk = $obj->getPrefixedDBkey();
			$pagemap[$obj->getArticleID()] = $pdbk;
			$classes[$pdbk] = $obj->isRedirect() ? 'mw-redirect' : '';
		}
		$context_title = Title::newFromText( $pageConfig->getTitle() );
		$this->hookRunner->onGetLinkColours(
			# $classes is passed by reference and mutated
			$pagemap, $classes, $context_title
		);

		foreach ( $titleObjs as $name => $obj ) {
			/** @var Title $obj */
			$pdbk = $obj->getPrefixedDBkey();
			$c = preg_split(
				'/\s+/', $classes[$pdbk] ?? '', -1, PREG_SPLIT_NO_EMPTY
			);
			$ret[$name] = [
				'pageId' => $obj->getArticleID(),
				'revId' => $obj->getLatestRevID(),
				'missing' => !$obj->exists(),
				'known' => $obj->isKnown(),
				'redirect' => $obj->isRedirect(),
				'linkclasses' => $c, # See ApiQueryInfo::getLinkClasses() in core
			];
		}
		return $ret;
	}

	/** @inheritDoc */
	public function getFileInfo( IPageConfig $pageConfig, array $files ): array {
		$page = Title::newFromText( $pageConfig->getTitle() );

		$keys = [];
		foreach ( $files as $f ) {
			$keys[] = $f[0];
		}
		$fileObjs = $this->repoGroup->findFiles( $keys );

		$ret = [];
		foreach ( $files as $f ) {
			$filename = $f[0];
			$dims = $f[1];

			/** @var File $file */
			$file = $fileObjs[$filename] ?? null;
			if ( !$file ) {
				$ret[] = null;
				continue;
			}

			// See Linker::makeImageLink; 'page' is a key in $handlerParams
			// core uses 'false' as the default then casts to (int) => 0
			$pageNum = $dims['page'] ?? 0;

			$result = [
				'width' => $file->getWidth( $pageNum ),
				'height' => $file->getHeight( $pageNum ),
				'size' => $file->getSize(),
				'mediatype' => $file->getMediaType(),
				'mime' => $file->getMimeType(),
				'url' => $file->getFullUrl(),
				'mustRender' => $file->mustRender(),
				'badFile' => $this->badFileLookup->isBadFile( $filename, $page ?: false ),
			];

			$length = $file->getLength();
			if ( $length ) {
				$result['duration'] = (float)$length;
			}

			if ( isset( $dims['seek'] ) ) {
				$dims['thumbtime'] = $dims['seek'];
			}

			$txopts = $this->makeTransformOptions( $pageConfig, $file, $dims );
			$mto = $file->transform( $txopts );
			if ( $mto ) {
				if ( $mto->isError() && $mto instanceof MediaTransformError ) {
					$result['thumberror'] = $mto->toText();
				} else {
					if ( $txopts ) {
						// Do srcset scaling
						Linker::processResponsiveImages( $file, $mto, $txopts );
						if ( count( $mto->responsiveUrls ) ) {
							$result['responsiveUrls'] = [];
							foreach ( $mto->responsiveUrls as $density => $url ) {
								$result['responsiveUrls'][$density] = $url;
							}
						}
					}

					// Proposed MediaTransformOutput serialization method for T51896 etc.
					// Note that getAPIData(['fullurl']) would return
					// wfExpandUrl(), which wouldn't respect the wiki's
					// protocol preferences -- instead it would use the
					// protocol used for the API request.
					if ( is_callable( [ $mto, 'getAPIData' ] ) ) {
						$result['thumbdata'] = $mto->getAPIData( [ 'withhash' ] );
					}

					$result['thumburl'] = $mto->getUrl();
					$result['thumbwidth'] = $mto->getWidth();
					$result['thumbheight'] = $mto->getHeight();
				}
			} else {
				$result['thumberror'] = "Presumably, invalid parameters, despite validation.";
			}

			$ret[] = $result;
		}

		return $ret;
	}

	/**
	 * Prepare MediaWiki's parser for preprocessing or extension tag parsing,
	 * clearing its state if necessary.
	 *
	 * @param IPageConfig $pageConfig
	 * @param int $outputType
	 * @return Parser
	 */
	private function prepareParser( IPageConfig $pageConfig, int $outputType ) {
		'@phan-var PageConfig $pageConfig'; // @var PageConfig $pageConfig
		// Clear the state only when the PageConfig changes, so that Parser's internal caches can
		// be retained. This should also provide better compatibility with extension tags.
		$clearState = $this->previousPageConfig !== $pageConfig;
		$this->previousPageConfig = $pageConfig;
		$this->parser->startExternalParse(
			Title::newFromText( $pageConfig->getTitle() ), $pageConfig->getParserOptions(),
			$outputType, $clearState, $pageConfig->getRevisionId() );
		$this->parser->resetOutput();

		// Retain a PPFrame object between preprocess requests since it contains
		// some useful caches.
		if ( $clearState ) {
			$this->ppFrame = $this->parser->getPreprocessor()->newFrame();
		}
		return $this->parser;
	}

	/** @inheritDoc */
	public function doPst( IPageConfig $pageConfig, string $wikitext ): string {
		'@phan-var PageConfig $pageConfig'; // @var PageConfig $pageConfig
		// This could use prepareParser(), but it's only called once per page,
		// so it's not essential.
		$titleObj = Title::newFromText( $pageConfig->getTitle() );
		$user = $pageConfig->getParserOptions()->getUserIdentity();
		$content = ContentHandler::makeContent( $wikitext, $titleObj, CONTENT_MODEL_WIKITEXT );
		return $this->contentTransformer->preSaveTransform(
			$content,
			$titleObj,
			$user,
			$pageConfig->getParserOptions()
		)->serialize();
	}

	/** @inheritDoc */
	public function parseWikitext(
		IPageConfig $pageConfig,
		ContentMetadataCollector $metadata,
		string $wikitext
	): string {
		$parser = $this->prepareParser( $pageConfig, Parser::OT_HTML );
		$html = $parser->parseExtensionTagAsTopLevelDoc( $wikitext );
		// XXX: Ideally we will eventually have the legacy parser use our
		// ContentMetadataCollector instead of having a new ParserOutput
		// created (implicitly in ::prepareParser()/Parser::resetOutput() )
		// which we then have to manually merge.
		$out = $parser->getOutput();
		$out->setText( $html );
		$out->collectMetadata( $metadata ); # merges $out into $metadata
		return $out->getText( [ 'unwrap' => true ] ); # HTML
	}

	/** @inheritDoc */
	public function preprocessWikitext(
		IPageConfig $pageConfig,
		ContentMetadataCollector $metadata,
		string $wikitext
	): string {
		$parser = $this->prepareParser( $pageConfig, Parser::OT_PREPROCESS );
		$this->hookRunner->onParserBeforePreprocess(
			# $wikitext is passed by reference and mutated
			$parser, $wikitext, $parser->getStripState()
		);
		$wikitext = $parser->replaceVariables( $wikitext, $this->ppFrame );
		// FIXME (T289545): StripState markers protect content that need to be protected from further
		// "wikitext processing". So, where the result has strip state markers, we actually
		// need to tunnel this content through rather than unwrap and let it go through the
		// rest of the parsoid pipeline. For example, some parser functions might return HTML
		// not wikitext, and where the content might contain wikitext characters, we are now
		// going to potentially mangle that output.
		$wikitext = $parser->getStripState()->unstripBoth( $wikitext );

		// XXX: Ideally we will eventually have the legacy parser use our
		// ContentMetadataCollector instead of having an new ParserOutput
		// created (implicitly in ::prepareParser()/Parser::resetOutput() )
		// which we then have to manually merge.
		$out = $parser->getOutput();
		$out->collectMetadata( $metadata ); # merges $out into $metadata
		return $wikitext;
	}

	/** @inheritDoc */
	public function fetchTemplateSource(
		IPageConfig $pageConfig, string $title
	): ?IPageContent {
		'@phan-var PageConfig $pageConfig'; // @var PageConfig $pageConfig
		$titleObj = Title::newFromText( $title );

		// Use the PageConfig to take advantage of custom template
		// fetch hooks like FlaggedRevisions, etc.
		$revRecord = $pageConfig->fetchRevisionRecordOfTemplate( $titleObj );

		return $revRecord ? new PageContent( $revRecord ) : null;
	}

	/** @inheritDoc */
	public function fetchTemplateData( IPageConfig $pageConfig, string $title ): ?array {
		$ret = [];
		// @todo: This hook needs some clean up: T304899
		$this->hookRunner->onParserFetchTemplateData(
			[ $title ],
			$ret # value returned by reference
		);

		// Cast value to array since the hook returns this as a stdclass
		$tplData = $ret[$title] ?? null;
		if ( $tplData ) {
			// Deep convert to associative array
			$tplData = json_decode( json_encode( $tplData ), true );
		}
		return $tplData;
	}

	/** @inheritDoc */
	public function logLinterData( IPageConfig $pageConfig, array $lints ): void {
		if ( $this->readOnlyMode->isReadOnly() ) {
			return;
		}

		$revId = $pageConfig->getRevisionId();
		$title = $pageConfig->getTitle();
		$pageInfo = $this->getPageInfo( $pageConfig, [ $title ] );
		$latest = $pageInfo[$title]['revId'];

		// Only send the request if it the latest revision
		if ( $revId !== null && $revId === $latest ) {
			$this->hookRunner->onParserLogLinterData(
				$title, $revId, $lints
			);
		}
	}

}
