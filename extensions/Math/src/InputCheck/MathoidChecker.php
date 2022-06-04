<?php

namespace MediaWiki\Extension\Math\InputCheck;

use MediaWiki\Http\HttpRequestFactory;
use Message;
use MWException;
use Psr\Log\LoggerInterface;
use WANObjectCache;

class MathoidChecker extends BaseChecker {

	private const EXPECTED_RETURN_CODES = [ 200, 400 ];
	public const VERSION = 1;
	/** @var string */
	private $url;
	/** @var int */
	private $timeout;
	/** @var WANObjectCache */
	private $cache;
	/** @var HttpRequestFactory */
	private $httpFactory;
	/** @var string */
	private $type;
	/** @var LoggerInterface */
	private $logger;
	/** @var int */
	private $statusCode;
	/** @var string */
	private $response;

	/**
	 * @param WANObjectCache $cache
	 * @param HttpRequestFactory $httpFactory
	 * @param LoggerInterface $logger
	 * @param string $url
	 * @param int $timeout
	 * @param string $input
	 * @param string $type
	 */
	public function __construct(
		WANObjectCache $cache,
		HttpRequestFactory $httpFactory,
		LoggerInterface $logger,
		$url,
		$timeout,
		string $input,
		string $type
	) {
		parent::__construct( $input );
		$this->url = $url;
		$this->timeout = $timeout;
		$this->cache = $cache;
		$this->httpFactory = $httpFactory;
		$this->type = $type;
		$this->logger = $logger;
	}

	/**
	 * @return array
	 */
	public function getCheckResponse(): array {
		if ( !isset( $this->statusCode ) ) {
			list( $this->statusCode, $this->response ) = $this->cache->getWithSetCallback(
				$this->getCacheKey(),
				WANObjectCache::TTL_INDEFINITE,
				[ $this, 'runCheck' ],
				[ 'version' => self::VERSION ]
			);
		}
		return [ $this->statusCode, $this->response ];
	}

	/**
	 * @return string
	 */
	public function getCacheKey(): string {
		return $this->cache->makeGlobalKey(
			self::class,
			md5( $this->type . '-' . $this->inputTeX )
		);
	}

	/**
	 * @return array
	 * @throws MWException
	 */
	public function runCheck(): array {
		$url = "{$this->url}/texvcinfo";
		$q = rawurlencode( $this->inputTeX );
		$postData = "type=$this->type&q=$q";
		$options = [
			'method' => 'POST',
			'postData' => $postData,
			'timeout' => $this->timeout,
		];
		$req = $this->httpFactory->create( $url, $options, __METHOD__ );
		$req->execute();
		$statusCode = $req->getStatus();
		if ( in_array( $statusCode, self::EXPECTED_RETURN_CODES ) ) {
			return [ $statusCode, $req->getContent() ];
		}
		$e = new MWException( 'Mathoid check returned unexpected error code.' );
		$this->logger->error( 'Mathoid check endpoint "{url}" returned ' .
			'HTTP status code "{statusCode}" for post data "{postData}": {exception}.',
			[
				'url' => $url,
				'statusCode' => $statusCode,
				'postData' => $postData,
				'exception' => $e,
			]
		);
		throw $e;
	}

	public function isValid() {
		[ $statusCode ] = $this->getCheckResponse();
		if ( $statusCode === 200 ) {
			return true;
		}
		return false;
	}

	public function getError(): ?Message {
		[ $statusCode, $content ] = $this->getCheckResponse();
		if ( $statusCode !== 200 ) {
			// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			$json = @json_decode( $content );
			if ( $json && isset( $json->detail ) ) {
				return $this->errorObjectToMessage( $json->detail, $this->url );
			}
			return $this->errorObjectToMessage( (object)[ 'error' => (object)[
				'message' => 'Math extension cannot connect to mathoid.' ] ], $this->url );
		}
		return null;
	}

	public function getValidTex() {
		[ $statusCode, $content ] = $this->getCheckResponse();
		if ( $statusCode === 200 ) {
			$json = json_decode( $content );
			return $json->checked;
		}
		return parent::getValidTex();
	}

}
