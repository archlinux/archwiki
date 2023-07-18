<?php

namespace MediaWiki\Extension\VisualEditor;

use Language;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

/**
 * A decorator implementation of ParsoidClient that will delegate to the appropriate
 * implementation of ParsoidClient based on the incoming ETag.
 *
 * The purpose of this decorator is to ensure that VE sessions that loaded HTML from
 * one ParsoidClient implementation will use the same implementation when saving the HTML,
 * even when the preferred implementation was changed on the server while the editor was open.
 *
 * This avoids users losing edits at the time of the config change: if the HTML the user
 * submits when saving the page doesn't get handled by the same implementation that originally
 * provided the HTML for editing, the ETag will mismatch and the edit will fail.
 */
class DualParsoidClient implements ParsoidClient {

	/** @var VisualEditorParsoidClientFactory */
	private VisualEditorParsoidClientFactory $factory;

	/** @var string|string[]|null */
	private $cookiesToForward;

	/** @var Authority */
	private Authority $authority;

	/**
	 * @note Called by DiscussionTools, keep compatible!
	 *
	 * @param VisualEditorParsoidClientFactory $factory
	 * @param string|string[]|false $cookiesToForward
	 * @param Authority $authority
	 */
	public function __construct(
		VisualEditorParsoidClientFactory $factory,
		$cookiesToForward,
		Authority $authority
	) {
		$this->factory = $factory;
		$this->cookiesToForward = $cookiesToForward;
		$this->authority = $authority;
	}

	/**
	 * Detect the mode to use based on the given ETag
	 *
	 * @param string $etag
	 *
	 * @return string|null
	 */
	private static function detectMode( string $etag ): ?string {
		// Extract the mode from between the double-quote and the colon
		if ( preg_match( '/^(W\/)?"(\w+):/', $etag, $matches ) ) {
			return $matches[2];
		}

		return null;
	}

	/**
	 * Inject information about what ParsoidClient implementation was used
	 * into the ETag header.
	 *
	 * @param array &$result
	 * @param ParsoidClient $client
	 */
	private static function injectMode( array &$result, ParsoidClient $client ) {
		$mode = $client instanceof VRSParsoidClient ? 'vrs' : 'direct';

		if ( isset( $result['headers']['etag'] ) ) {
			$etag = $result['headers']['etag'];

			// Inject $mode after double-quote
			$result['headers']['etag'] = preg_replace( '/^(W\/)?"(.*)"$/', '$1"' . $mode . ':$2"', $etag );
		}
	}

	/**
	 * Strip information about what ParsoidClient implementation to use from the ETag,
	 * restoring it to the original ETag originally emitted by that ParsoidClient.
	 *
	 * @param string $etag
	 *
	 * @return string
	 */
	private static function stripMode( string $etag ): string {
		// Remove any prefix between double-quote and colon
		return preg_replace( '/"(\w+):/', '"', $etag );
	}

	/**
	 * Create a ParsoidClient based on information embedded in the given ETag.
	 *
	 * @param string|null $etag
	 *
	 * @return ParsoidClient
	 */
	private function createParsoidClient( ?string $etag = null ): ParsoidClient {
		$shouldUseVRS = null;

		if ( $etag ) {
			$mode = self::detectMode( $etag );
			if ( $mode === 'vrs' ) {
				$shouldUseVRS = true;
			} elseif ( $mode === 'direct' ) {
				$shouldUseVRS = false;
			}
		}

		return $this->factory->createParsoidClientInternal(
			$this->cookiesToForward,
			$this->authority,
			[ 'ShouldUseVRS' => $shouldUseVRS, 'NoDualClient' => true ]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function getPageHtml( RevisionRecord $revision, ?Language $targetLanguage ): array {
		$client = $this->createParsoidClient();
		$result = $client->getPageHtml( $revision, $targetLanguage );

		self::injectMode( $result, $client );
		return $result;
	}

	/**
	 * @inheritDoc
	 */
	public function transformHTML(
		PageIdentity $page,
		Language $targetLanguage,
		string $html,
		?int $oldid,
		?string $etag
	): array {
		$client = $this->createParsoidClient( $etag );

		if ( $etag ) {
			$etag = self::stripMode( $etag );
		}

		$result = $client->transformHTML( $page, $targetLanguage, $html, $oldid, $etag );

		self::injectMode( $result, $client );
		return $result;
	}

	/**
	 * @inheritDoc
	 */
	public function transformWikitext(
		PageIdentity $page,
		Language $targetLanguage,
		string $wikitext,
		bool $bodyOnly,
		?int $oldid,
		bool $stash
	): array {
		$client = $this->createParsoidClient();
		$result = $client->transformWikitext( $page, $targetLanguage, $wikitext, $bodyOnly, $oldid, $stash );

		self::injectMode( $result, $client );
		return $result;
	}
}
