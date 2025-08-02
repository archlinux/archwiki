<?php
/**
 * Helper functions for contacting Parsoid/RESTBase from the action API.
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

namespace MediaWiki\Extension\VisualEditor;

use MediaWiki\Api\ApiUsageException;
use MediaWiki\Language\Language;
use MediaWiki\MediaWikiServices;
use MediaWiki\Request\WebRequest;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Wikimedia\Message\MessageSpecifier;
use Wikimedia\Stats\StatsFactory;

trait ApiParsoidTrait {

	private ?LoggerInterface $logger = null;
	/** @var StatsFactory|null */
	private $stats = null;

	protected function getLogger(): LoggerInterface {
		return $this->logger ?: new NullLogger();
	}

	protected function setLogger( LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	protected function getStatsFactory(): StatsFactory {
		return $this->stats ?: StatsFactory::newNull();
	}

	protected function setStatsFactory( StatsFactory $statsFactory ): void {
		$this->stats = $statsFactory;
	}

	/**
	 * @return float Return a start time for use with statsRecordTiming()
	 */
	private function statsGetStartTime(): float {
		return microtime( true );
	}

	/**
	 * @param string $key
	 * @param float $startTime from statsGetStartTime()
	 */
	private function statsRecordTiming( string $key, float $startTime ): void {
		$duration = ( microtime( true ) - $startTime ) * 1000;
		$this->getStatsFactory()->getTiming( $key )->observe( $duration );
	}

	/**
	 * @return never
	 * @throws ApiUsageException
	 */
	private function dieWithRestHttpException( HttpException $ex ): void {
		if ( $ex instanceof LocalizedHttpException ) {
			$this->dieWithError( $ex->getMessageValue(), null, $ex->getErrorData() );
		} else {
			$this->dieWithException( $ex, [ 'data' => $ex->getErrorData() ] );
		}
	}

	/**
	 * Request page HTML from Parsoid.
	 *
	 * @param RevisionRecord $revision Page revision
	 * @return array An array mimicking a RESTbase server's response, with keys: 'headers' and 'body'
	 * @phan-return array{body:string,headers:array<string,string>}
	 * @throws ApiUsageException
	 */
	protected function requestRestbasePageHtml( RevisionRecord $revision ): array {
		$title = Title::newFromLinkTarget( $revision->getPageAsLinkTarget() );
		$lang = self::getPageLanguage( $title );

		$startTime = $this->statsGetStartTime();
		try {
			$response = $this->getParsoidClient()->getPageHtml( $revision, $lang );
		} catch ( HttpException $ex ) {
			$this->dieWithRestHttpException( $ex );
		}
		$this->statsRecordTiming( 'ApiVisualEditor_ParsoidClient_getPageHtml_seconds', $startTime );

		return $response;
	}

	/**
	 * Transform HTML to wikitext with Parsoid.
	 *
	 * @param Title $title The title of the page
	 * @param string $html The HTML of the page to be transformed
	 * @param int|null $oldid What oldid revision, if any, to base the request from (default: `null`)
	 * @param string|null $etag The ETag to set in the HTTP request header
	 * @return array An array mimicking a RESTbase server's response, with keys: 'headers' and 'body'
	 * @phan-return array{body:string,headers:array<string,string>}
	 * @throws ApiUsageException
	 */
	protected function transformHTML(
		Title $title, string $html, ?int $oldid = null, ?string $etag = null
	): array {
		$lang = self::getPageLanguage( $title );

		$startTime = $this->statsGetStartTime();
		try {
			$response = $this->getParsoidClient()->transformHTML( $title, $lang, $html, $oldid, $etag );
		} catch ( HttpException $ex ) {
			$this->dieWithRestHttpException( $ex );
		}
		$this->statsRecordTiming( 'ApiVisualEditor_ParsoidClient_transformHTML_seconds', $startTime );

		return $response;
	}

	/**
	 * Transform wikitext to HTML with Parsoid.
	 *
	 * @param Title $title The title of the page to use as the parsing context
	 * @param string $wikitext The wikitext fragment to parse
	 * @param bool $bodyOnly Whether to provide only the contents of the `<body>` tag
	 * @param int|null $oldid What oldid revision, if any, to base the request from (default: `null`)
	 * @param bool $stash Whether to stash the result in the server-side cache (default: `false`)
	 * @return array An array mimicking a RESTbase server's response, with keys: 'headers' and 'body'
	 * @phan-return array{body:string,headers:array<string,string>}
	 * @throws ApiUsageException
	 */
	protected function transformWikitext(
		Title $title, string $wikitext, bool $bodyOnly, ?int $oldid = null, bool $stash = false
	): array {
		$lang = self::getPageLanguage( $title );

		$startTime = $this->statsGetStartTime();
		try {
			$response = $this->getParsoidClient()->transformWikitext(
				$title,
				$lang,
				$wikitext,
				$bodyOnly,
				$oldid,
				$stash
			);
		} catch ( HttpException $ex ) {
			$this->dieWithRestHttpException( $ex );
		}
		$this->statsRecordTiming( 'ApiVisualEditor_ParsoidClient_transformWikitext_seconds', $startTime );

		return $response;
	}

	/**
	 * Get the page language from a title, using the content language as fallback on special pages
	 *
	 * @param Title $title
	 * @return Language Content language
	 */
	public static function getPageLanguage( Title $title ): Language {
		if ( $title->isSpecial( 'CollabPad' ) ) {
			// Use the site language for CollabPad, as getPageLanguage just
			// returns the interface language for special pages.
			// TODO: Let the user change the document language on multi-lingual sites.
			return MediaWikiServices::getInstance()->getContentLanguage();
		} else {
			return $title->getPageLanguage();
		}
	}

	/**
	 * @see VisualEditorParsoidClientFactory
	 */
	abstract protected function getParsoidClient(): ParsoidClient;

	/**
	 * @see ApiBase
	 * @param string|array|MessageSpecifier $msg See ApiErrorFormatter::addError()
	 * @param string|null $code See ApiErrorFormatter::addError()
	 * @param array|null $data See ApiErrorFormatter::addError()
	 * @param int $httpCode HTTP error code to use
	 * @throws ApiUsageException always
	 * @return never
	 */
	abstract public function dieWithError( $msg, $code = null, $data = null, $httpCode = 0 );

	/**
	 * @see ApiBase
	 * @param Throwable $exception See ApiErrorFormatter::getMessageFromException()
	 * @param array $options See ApiErrorFormatter::getMessageFromException()
	 * @throws ApiUsageException always
	 * @return never
	 */
	abstract public function dieWithException( Throwable $exception, array $options = [] );

	/**
	 * @see ContextSource
	 * @return WebRequest
	 */
	abstract public function getRequest();
}
