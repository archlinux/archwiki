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
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use Psr\Log\LoggerInterface;
use Title;
use VirtualRESTServiceClient;

class VRSParsoidClient implements ParsoidClient {
	/**
	 * @var VirtualRESTServiceClient
	 */
	private $vrsClient;
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @param VirtualRESTServiceClient $vrsClient
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		VirtualRESTServiceClient $vrsClient,
		LoggerInterface $logger
	) {
		$this->vrsClient = $vrsClient;
		$this->logger = $logger;
	}

	/**
	 * Accessor function for all RESTbase requests
	 *
	 * @param string $method The HTTP method, either 'GET' or 'POST'
	 * @param string $path The RESTbase api path
	 * @param array $params Request parameters
	 * @param array $reqheaders Request headers
	 *
	 * @return array If successful, the value is the RESTbase server's response as an array
	 *   with keys 'code', 'error', 'headers' and 'body'
	 */
	private function requestRestbase(
		string $method, string $path, array $params, array $reqheaders = []
	): array {
		// Should be synchronised with requestParsoidData() in
		// modules/ve-mw/preinit/ve.init.mw.ArticleTargetLoader.js
		$profile = 'https://www.mediawiki.org/wiki/Specs/HTML/' .
			DirectParsoidClient::PARSOID_VERSION;
		$reqheaders += [
			'Accept' =>
				"text/html; charset=utf-8; profile=\"$profile\"",
			'User-Agent' => 'VisualEditor-MediaWiki/' . MW_VERSION,
			'Api-User-Agent' => 'VisualEditor-MediaWiki/' . MW_VERSION,
			'Promise-Non-Write-API-Action' => 'true',
		];
		$request = [
			'method' => $method,
			'url' => '/restbase/local/v1/' . $path,
			( $method === 'GET' ? 'query' : 'body' ) => $params,
			'headers' => $reqheaders,
		];
		$response = $this->vrsClient->run( $request );

		if ( !empty( $response['error'] ) ) {
			$response['error'] = [
				'apierror-visualeditor-docserver-http-error',
				wfEscapeWikiText( $response['error'] )
			];
		} elseif ( $response['code'] >= 400 ) {
			// no error message, but code indicates an error
			$json = json_decode( $response['body'], true );
			$text = $json['detail'] ?? '(no message)';
			$response['error'] = [
				'apierror-visualeditor-docserver-http',
				$response['code'],
				wfEscapeWikiText( $text )
			];
		} else {
			// Needed because $response['error'] may be '' on success!
			$response['error'] = null;
		}

		return $response;
	}

	/**
	 * Request page HTML
	 *
	 * @param RevisionRecord $revision Page revision
	 * @param ?Language $targetLanguage The desired output language
	 *
	 * @return array The response
	 */
	public function getPageHtml( RevisionRecord $revision, ?Language $targetLanguage ): array {
		$title = Title::castFromPageIdentity( $revision->getPage() );
		$targetLanguage = $targetLanguage ?: $title->getPageLanguage();

		return $this->requestRestbase(
			'GET',
			'page/html/' . urlencode( $title->getPrefixedDBkey() ) .
			'/' . $revision->getId() .
			'?redirect=false&stash=true',
			[],
			[
				'Accept-Language' => $targetLanguage->getCode(),
			]
		);
	}

	/**
	 * Transform HTML to wikitext via Parsoid
	 *
	 * @param PageIdentity $page The page the content belongs to
	 * @param Language $targetLanguage The desired output language
	 * @param string $html The HTML of the page to be transformed
	 * @param ?int $oldid What oldid revision, if any, to base the request from (default: `null`)
	 * @param ?string $etag The ETag to set in the HTTP request header
	 *
	 * @return array The response, 'code', 'error', 'headers' and 'body'
	 */
	public function transformHTML(
		PageIdentity $page, Language $targetLanguage, string $html, ?int $oldid, ?string $etag
	): array {
		$title = Title::castFromPageIdentity( $page );

		$data = [ 'html' => $html ];
		$path = 'transform/html/to/wikitext/' . urlencode( $title->getPrefixedDBkey() ) .
			( $oldid === null ? '' : '/' . $oldid );

		// Adapted from RESTBase mwUtil.parseETag()
		// ETag is not expected when:
		// * Doing anything on a non-RESTBase wiki
		// * Creating a new page on a RESTBase wiki (oldid=0)
		if ( $etag !== null && $oldid && !( preg_match( '/
			^(?:W\\/)?"?
			' . preg_quote( "$oldid", '/' ) . '
			(?:\\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}))
			(?:\\/([^"]+))?
			"?$
		/x', $etag ) ) ) {
			$this->logger->info(
				__METHOD__ . ": Received funny ETag from client: '{etag}'",
				[
					'etag' => $etag,
					'oldid' => $oldid,
					'requestPath' => $path,
				]
			);
		}
		return $this->requestRestbase(
			'POST', $path, $data,
			[
				'If-Match' => $etag,
				'Accept-Language' => $targetLanguage->getCode(),
			]
		);
	}

	/**
	 * Transform wikitext to HTML via Parsoid.
	 *
	 * @param PageIdentity $page The page the content belongs to
	 * @param Language $targetLanguage The desired output language
	 * @param string $wikitext The wikitext fragment to parse
	 * @param bool $bodyOnly Whether to provide only the contents of the `<body>` tag
	 * @param ?int $oldid What oldid revision, if any, to base the request from (default: `null`)
	 * @param bool $stash Whether to stash the result in the server-side cache (default: `false`)
	 *
	 * @return array The response, 'code', 'reason', 'headers' and 'body'
	 */
	public function transformWikitext(
		PageIdentity $page, Language $targetLanguage, string $wikitext,
		bool $bodyOnly, ?int $oldid, bool $stash
	): array {
		$title = Title::castFromPageIdentity( $page );

		return $this->requestRestbase(
			'POST',
			'transform/wikitext/to/html/' . urlencode( $title->getPrefixedDBkey() ) .
			( $oldid === null ? '' : '/' . $oldid ),
			[
				'wikitext' => $wikitext,
				'body_only' => $bodyOnly ? 1 : 0,
				'stash' => $stash ? 1 : 0
			],
			[
				'Accept-Language' => $targetLanguage->getCode(),
			]
		);
	}
}
