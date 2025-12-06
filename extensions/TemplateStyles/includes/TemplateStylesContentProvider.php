<?php

namespace MediaWiki\Extension\TemplateStyles;

/**
 * @file
 * @license GPL-2.0-or-later
 */

use ContentHandler;
use MapCacheLRU;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Parser\Parser;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\TitleFormatter;
use Status;

/**
 * Provide the styles for the parser
 */
class TemplateStylesContentProvider {

	private const CACHE_LIMIT = 100;

	private MapCacheLRU $cache;

	private IContentHandlerFactory $contentHandlerFactory;
	private TitleFormatter $titleFormatter;

	public function __construct(
		IContentHandlerFactory $contentHandlerFactory,
		TitleFormatter $titleFormatter
	) {
		$this->cache = new MapCacheLRU( self::CACHE_LIMIT );

		$this->contentHandlerFactory = $contentHandlerFactory;
		$this->titleFormatter = $titleFormatter;
	}

	public function getStyle(
		RevisionRecord $revRecord,
		Parser $parser,
		?string $extraWrapper
	): Status {
		$content = $revRecord->getContent( SlotRecord::MAIN );
		if ( !$content ) {
			$titleText = $this->titleFormatter->getPrefixedText( $revRecord->getPage() );
			return Status::newFatal(
				'templatestyles-bad-src-missing',
				$titleText,
				wfEscapeWikiText( $titleText )
			);
		}
		if ( !$content instanceof TemplateStylesContent ) {
			$titleText = $this->titleFormatter->getPrefixedText( $revRecord->getPage() );
			return Status::newFatal(
				'templatestyles-bad-src',
				$titleText,
				wfEscapeWikiText( $titleText ),
				ContentHandler::getLocalizedName( $content->getModel() )
			);
		}

		$targetDir = $parser->getTargetLanguage()->getDir();
		$contentDir = $parser->getContentLanguage()->getDir();
		$flip = $targetDir !== $contentDir;

		$wrapOutputClass = $parser->getOptions()->getWrapOutputClass();

		$cacheKey = $this->buildCacheKey(
			$revRecord, [
				'flip' => $flip,
				'class' => $wrapOutputClass,
				'extraWrapper' => $extraWrapper,
			]
		);

		if ( $this->cache->has( $cacheKey ) ) {
			return Status::newGood( [ $cacheKey, $this->cache->get( $cacheKey ) ] );
		}

		$contentHandler = $this->contentHandlerFactory->getContentHandler( $content->getModel() );
		'@phan-var TemplateStylesContentHandler $contentHandler';
		$status = $contentHandler->sanitize(
			$content,
			[
				// Any option depending on arguments should be part of the cache key
				'flip' => $flip,
				'minify' => true,
				'class' => $wrapOutputClass,
				'extraWrapper' => $extraWrapper,
			]
		);
		$style = $status->isOk() ? $status->getValue() : '/* Fatal error, no CSS will be output */';

		// Prepend errors. This should normally never happen, but might if an
		// update or configuration change causes something that was formerly
		// valid to become invalid or something like that.
		if ( !$status->isGood() ) {
			$comment = wfMessage(
				'templatestyles-errorcomment',
				$this->titleFormatter->getPrefixedText( $revRecord->getPage() ),
				$revRecord->getId(),
				$status->getWikiText( false, 'rawmessage' )
			)->text();
			$comment = trim( strtr( $comment, [
				// Use some lookalike unicode characters to avoid things that might
				// otherwise confuse browsers.
				'*' => '•', '-' => '‐', '<' => '⧼', '>' => '⧽',
			] ) );
			$style = "/*\n$comment\n*/\n$style";
		}

		$this->cache->set( $cacheKey, $style );

		return Status::newGood( [ $cacheKey, $style ] );
	}

	private function buildCacheKey( RevisionRecord $revRecord, array $options ): string {
		// If the revision actually has an ID, cache based on that.
		// Otherwise, cache by hash.
		if ( $revRecord->getId() ) {
			$cacheKey = 'r' . $revRecord->getId();
		} else {
			$content = $revRecord->getContent( SlotRecord::MAIN );
			'@phan-var TemplateStylesContent $content';
			$cacheKey = sha1( $content->getText() );
		}

		if ( $options['flip'] ) {
			$cacheKey .= ':flip';
		}

		// Include any non-default wrapper class in the cache key too
		$wrapClass = $options['class'];
		$extraWrapper = $options['extraWrapper'];
		if ( $wrapClass === false ) {
			// deprecated
			$wrapClass = 'mw-parser-output';
		}
		if ( $wrapClass !== 'mw-parser-output' || $extraWrapper !== null ) {
			$cacheKey .= '/' . $wrapClass;
			if ( $extraWrapper !== null ) {
				$cacheKey .= '/' . $extraWrapper;
			}
		}

		return $cacheKey;
	}
}
