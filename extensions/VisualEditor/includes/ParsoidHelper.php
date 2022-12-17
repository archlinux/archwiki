<?php
/**
 * Helper functions for contacting Parsoid/RESTBase.
 *
 * Because clearly we don't have enough APIs yet for accomplishing this Herculean task.
 *
 * @file
 * @ingroup Extensions
 * @copyright 2022 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

namespace MediaWiki\Extension\VisualEditor;

use Config;
use ConfigException;
use Language;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use ParsoidVirtualRESTService;
use Psr\Log\LoggerInterface;
use RestbaseVirtualRESTService;
use StatusValue;
use Title;
use VirtualRESTService;
use VirtualRESTServiceClient;
use Wikimedia\Assert\Assert;

class ParsoidHelper {

	/**
	 * A direct Parsoid client for zero-configuration mode.
	 * Initially `false`, then once we determine whether we're using zeroconf
	 * mode or not then it will be a ?VisualEditorParsoidClient.
	 * @var VisualEditorParsoidClient|null|false
	 */
	private $directClient = false;

	/** @var VirtualRESTServiceClient */
	private $serviceClient = null;

	/** @var Config */
	private $config;

	/** @var LoggerInterface */
	private $logger;

	/** @var string|false */
	private $forwardCookies;

	/**
	 * @param Config $config
	 * @param LoggerInterface $logger
	 * @param string|false $forwardCookies
	 */
	public function __construct( Config $config, LoggerInterface $logger, $forwardCookies ) {
		$this->config = $config;
		$this->logger = $logger;
		$this->forwardCookies = $forwardCookies;
	}

	/**
	 * Fetches the VisualEditorParsoidClient used for direct access to
	 * Parsoid.
	 * @return ?VisualEditorParsoidClient null if a VirtualRESTService is
	 *  to be used.
	 */
	private function getDirectClient(): ?VisualEditorParsoidClient {
		if ( $this->directClient === false ) {
			// We haven't checked configuration yet.
			// Check to see if any of the restbase-related configuration
			// variables are set, and bail if so:
			$vrs = $this->config->get( 'VirtualRestConfig' );
			if ( isset( $vrs['modules'] ) &&
				 ( isset( $vrs['modules']['restbase'] ) ||
				  isset( $vrs['modules']['parsoid'] ) )
			) {
				$this->directClient = null;
				return null;
			}
			// Eventually we'll do something fancy, but I'm hacking here...
			global $wgVisualEditorParsoidAutoConfig;
			if ( !$wgVisualEditorParsoidAutoConfig ) {
				// explicit opt out
				$this->directClient = null;
				return null;
			}
			// Default to using the direct client.
			$this->directClient = VisualEditorParsoidClient::factory();
		}
		return $this->directClient;
	}

	/**
	 * Creates the virtual REST service object to be used in VE's API calls. The
	 * method determines whether to instantiate a ParsoidVirtualRESTService or a
	 * RestbaseVirtualRESTService object based on configuration directives: if
	 * $wgVirtualRestConfig['modules']['restbase'] is defined, RESTBase is chosen,
	 * otherwise Parsoid is used (either by using the MW Core config, or the
	 * VE-local one).
	 *
	 * @return VirtualRESTService the VirtualRESTService object to use
	 */
	private function getVRSObject(): VirtualRESTService {
		Assert::precondition(
			!$this->getDirectClient(),
			"Direct Parsoid access is configured but the VirtualRESTService was used"
		);

		global $wgVisualEditorParsoidAutoConfig;
		// the params array to create the service object with
		$params = [];
		// the VRS class to use, defaults to Parsoid
		$class = ParsoidVirtualRESTService::class;
		// The global virtual rest service config object, if any
		$vrs = $this->config->get( 'VirtualRestConfig' );
		if ( isset( $vrs['modules'] ) && isset( $vrs['modules']['restbase'] ) ) {
			// if restbase is available, use it
			$params = $vrs['modules']['restbase'];
			// backward compatibility
			$params['parsoidCompat'] = false;
			$class = RestbaseVirtualRESTService::class;
		} elseif ( isset( $vrs['modules'] ) && isset( $vrs['modules']['parsoid'] ) ) {
			// there's a global parsoid config, use it next
			$params = $vrs['modules']['parsoid'];
			$params['restbaseCompat'] = true;
		} elseif ( $wgVisualEditorParsoidAutoConfig ) {
			$params = $vrs['modules']['parsoid'] ?? [];
			$params['restbaseCompat'] = true;
			// forward cookies on private wikis
			$params['forwardCookies'] = !MediaWikiServices::getInstance()
				->getPermissionManager()->isEveryoneAllowed( 'read' );
		} else {
			// No global modules defined, so no way to contact the document server.
			throw new ConfigException( "The VirtualRESTService for the document server is not defined;" .
				" see https://www.mediawiki.org/wiki/Extension:VisualEditor" );
		}
		// merge the global and service-specific params
		if ( isset( $vrs['global'] ) ) {
			$params = array_merge( $vrs['global'], $params );
		}
		// set up cookie forwarding
		if ( $params['forwardCookies'] ) {
			$params['forwardCookies'] = $this->forwardCookies;
		} else {
			$params['forwardCookies'] = false;
		}
		// create the VRS object and return it
		return new $class( $params );
	}

	/**
	 * Creates the object which directs queries to the virtual REST service, depending on the path.
	 *
	 * @return VirtualRESTServiceClient
	 */
	private function getVRSClient(): VirtualRESTServiceClient {
		if ( !$this->serviceClient ) {
			$this->serviceClient = new VirtualRESTServiceClient(
				MediaWikiServices::getInstance()->getHttpRequestFactory()->createMultiClient() );
			$this->serviceClient->mount( '/restbase/', $this->getVRSObject() );
		}
		return $this->serviceClient;
	}

	/**
	 * Accessor function for all RESTbase requests
	 *
	 * @param Title $title The title of the page to use as the parsing context
	 * @param string $method The HTTP method, either 'GET' or 'POST'
	 * @param string $path The RESTbase api path
	 * @param array $params Request parameters
	 * @param array $reqheaders Request headers
	 * @return StatusValue If successful, the value is the RESTbase server's response as an array
	 *   with keys 'code', 'reason', 'headers' and 'body'
	 */
	private function requestRestbase(
		Title $title, string $method, string $path, array $params, array $reqheaders = []
	): StatusValue {
		// Should be synchronised with requestParsoidData() in
		// modules/ve-mw/preinit/ve.init.mw.ArticleTargetLoader.js
		$profile = 'https://www.mediawiki.org/wiki/Specs/HTML/' .
			VisualEditorParsoidClient::PARSOID_VERSION;
		$reqheaders += [
			'Accept' =>
				"text/html; charset=utf-8; profile=\"$profile\"",
			'Accept-Language' => $title->getPageLanguage()->getCode(),
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
		$response = $this->getVRSClient()->run( $request );
		if ( $response['error'] !== '' ) {
			return StatusValue::newFatal( 'apierror-visualeditor-docserver-http-error',
				wfEscapeWikiText( $response['error'] ) );
		} elseif ( $response['code'] !== 200 ) {
			// error null, code not 200
			$json = json_decode( $response['body'], true );
			$message = $json['detail'] ?? '(no message)';
			return StatusValue::newFatal( 'apierror-visualeditor-docserver-http', $response['code'], $message );
		}
		return StatusValue::newGood( $response );
	}

	/**
	 * Request page HTML from RESTBase
	 *
	 * @param RevisionRecord $revision Page revision
	 * @param ?Language $pageLanguage Page language (default: `null`)
	 * @return StatusValue If successful, the value is the RESTbase server's response as an array
	 *   with keys 'code', 'reason', 'headers' and 'body'
	 */
	public function requestRestbasePageHtml( RevisionRecord $revision, ?Language $pageLanguage = null ): StatusValue {
		$title = Title::newFromLinkTarget( $revision->getPageAsLinkTarget() );
		$client = $this->getDirectClient();
		if ( $client ) {
			return StatusValue::newGood( $client->getPageHtml(
				$revision, $pageLanguage ?: $title->getPageLanguage()
			) );
		}
		return $this->requestRestbase(
			$title,
			'GET',
			'page/html/' . urlencode( $title->getPrefixedDBkey() ) .
				'/' . $revision->getId() .
				'?redirect=false&stash=true',
			[],
			[
				'Accept-Language' => ( $pageLanguage ?: $title->getPageLanguage() )->getCode(),
			]
		);
	}

	/**
	 * Transform HTML to wikitext via Parsoid through RESTbase. Wrapper for ::postData().
	 *
	 * @param Title $title The title of the page
	 * @param string $html The HTML of the page to be transformed
	 * @param int|null $oldid What oldid revision, if any, to base the request from (default: `null`)
	 * @param string|null $etag The ETag to set in the HTTP request header
	 * @param ?Language $pageLanguage Page language (default: `null`)
	 * @return StatusValue If successful, the value is the RESTbase server's response as an array
	 *   with keys 'code', 'reason', 'headers' and 'body'
	 */
	public function transformHTML(
		Title $title, string $html, int $oldid = null, string $etag = null, ?Language $pageLanguage = null
	): StatusValue {
		$client = $this->getDirectClient();
		if ( $client ) {
			return StatusValue::newGood( $client->transformHtml(
				$title, $pageLanguage ?: $title->getPageLanguage(), $html, $oldid, $etag
			) );
		}
		$data = [ 'html' => $html ];
		$path = 'transform/html/to/wikitext/' . urlencode( $title->getPrefixedDBkey() ) .
			( $oldid === null ? '' : '/' . $oldid );

		// Adapted from RESTBase mwUtil.parseETag()
		// ETag is not expected when:
		// * Doing anything on a non-RESTBase wiki
		// ETag is expected to be in a different format when:
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
			$title,
			'POST', $path, $data,
			[
				'If-Match' => $etag,
				'Accept-Language' => ( $pageLanguage ?: $title->getPageLanguage() )->getCode(),
			]
		);
	}

	/**
	 * Transform wikitext to HTML via Parsoid through RESTbase. Wrapper for ::postData().
	 *
	 * @param Title $title The title of the page to use as the parsing context
	 * @param string $wikitext The wikitext fragment to parse
	 * @param bool $bodyOnly Whether to provide only the contents of the `<body>` tag
	 * @param int|null $oldid What oldid revision, if any, to base the request from (default: `null`)
	 * @param bool $stash Whether to stash the result in the server-side cache (default: `false`)
	 * @param ?Language $pageLanguage Page language (default: `null`)
	 * @return StatusValue If successful, the value is the RESTbase server's response as an array
	 *   with keys 'code', 'reason', 'headers' and 'body'
	 */
	public function transformWikitext(
		Title $title, string $wikitext, bool $bodyOnly, int $oldid = null, bool $stash = false,
		?Language $pageLanguage = null
	): StatusValue {
		$client = $this->getDirectClient();
		if ( $client ) {
			return StatusValue::newGood( $client->transformWikitext(
				$title, $pageLanguage ?: $title->getPageLanguage(),
				$wikitext, $bodyOnly, $oldid, $stash
			) );
		}
		return $this->requestRestbase(
			$title,
			'POST',
			'transform/wikitext/to/html/' . urlencode( $title->getPrefixedDBkey() ) .
				( $oldid === null ? '' : '/' . $oldid ),
			[
				'wikitext' => $wikitext,
				'body_only' => $bodyOnly ? 1 : 0,
				'stash' => $stash ? 1 : 0
			],
			[
				'Accept-Language' => ( $pageLanguage ?: $title->getPageLanguage() )->getCode(),
			]
		);
	}

}
