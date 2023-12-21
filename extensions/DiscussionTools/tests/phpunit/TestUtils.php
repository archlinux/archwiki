<?php

namespace MediaWiki\Extension\DiscussionTools\Tests;

use FormatJson;
use MediaWiki\Extension\DiscussionTools\CommentParser;
use MediaWiki\MediaWikiServices;
use Wikimedia\Parsoid\DOM\Document;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

trait TestUtils {

	/**
	 * Create a Document from a string.
	 *
	 * @param string $html
	 * @return Document
	 */
	protected static function createDocument( string $html ): Document {
		return DOMUtils::parseHTML( $html );
	}

	/**
	 * Return the node that is expected to contain thread items.
	 *
	 * @param Document $doc
	 * @return Element
	 */
	protected static function getThreadContainer( Document $doc ): Element {
		// In tests created from Parsoid output, comments are contained directly in <body>.
		// In tests created from old parser output, comments are contained in <div class="mw-parser-output">.
		$body = DOMCompat::getBody( $doc );
		$wrapper = DOMCompat::querySelector( $body, 'div.mw-parser-output' );
		return $wrapper ?: $body;
	}

	/**
	 * Get text from path
	 *
	 * @param string $relativePath
	 * @return string
	 */
	protected static function getText( string $relativePath ): string {
		return file_get_contents( __DIR__ . '/../' . $relativePath );
	}

	/**
	 * Write text to path
	 *
	 * @param string $relativePath
	 * @param string $text
	 */
	protected static function overwriteTextFile( string $relativePath, string $text ): void {
		file_put_contents( __DIR__ . '/../' . $relativePath, $text );
	}

	/**
	 * Get parsed JSON from path
	 *
	 * @param string $relativePath
	 * @param bool $assoc See json_decode()
	 * @return array
	 */
	protected static function getJson( string $relativePath, bool $assoc = true ): array {
		$json = json_decode(
			file_get_contents( __DIR__ . '/' . $relativePath ),
			$assoc
		);
		return $json;
	}

	/**
	 * Write JSON to path
	 *
	 * @param string $relativePath
	 * @param array $data
	 */
	protected static function overwriteJsonFile( string $relativePath, array $data ): void {
		$json = FormatJson::encode( $data, "\t", FormatJson::ALL_OK );
		file_put_contents( __DIR__ . '/' . $relativePath, $json . "\n" );
	}

	/**
	 * Get HTML from path
	 *
	 * @param string $relativePath
	 * @return string
	 */
	protected static function getHtml( string $relativePath ): string {
		return file_get_contents( __DIR__ . '/../' . $relativePath );
	}

	/**
	 * Write HTML to path
	 *
	 * @param string $relPath
	 * @param Element $container
	 * @param string $origRelPath
	 */
	protected static function overwriteHtmlFile( string $relPath, Element $container, string $origRelPath ): void {
		// Do not use $doc->saveHtml(), it outputs an awful soup of HTML entities for documents with
		// non-ASCII characters
		$html = file_get_contents( __DIR__ . '/../' . $origRelPath );

		$newInnerHtml = DOMCompat::getInnerHTML( $container );

		if ( strtolower( $container->tagName ) === 'body' ) {
			// Apparently <body> innerHTML always has a trailing newline, even if the source HTML did not,
			// and we need to preserve whatever whitespace was there to avoid test failures
			preg_match( '`(\s*)(</body>|\z)`s', $html, $matches );
			$newInnerHtml = rtrim( $newInnerHtml ) . $matches[1];
		}

		// Quote \ and $ in the replacement text
		$quotedNewInnerHtml = strtr( $newInnerHtml, [ '\\' => '\\\\', '$' => '\\$' ] );

		if ( strtolower( $container->tagName ) === 'body' ) {
			if ( str_contains( $html, '<body' ) ) {
				$html = preg_replace(
					'`(<body[^>]*>)(.*)(</body>)`s',
					'$1' . $quotedNewInnerHtml . '$3',
					$html
				);
			} else {
				$html = $newInnerHtml;
			}
		} else {
			$html = preg_replace(
				'`(<div class="mw-parser-output">)(.*)(</div>)`s',
				'$1' . $quotedNewInnerHtml . '$3',
				$html
			);
		}

		file_put_contents( __DIR__ . '/../' . $relPath, $html );
	}

	/**
	 * Create a comment parser
	 *
	 * @param array $data
	 * @return CommentParser
	 */
	public static function createParser( array $data ): CommentParser {
		$services = MediaWikiServices::getInstance();
		return new CommentParser(
			$services->getMainConfig(),
			$services->getContentLanguage(),
			$services->getLanguageConverterFactory(),
			new MockLanguageData( $data ),
			$services->getTitleParser()
		);
	}
}
