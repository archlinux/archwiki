<?php
/**
 * MediaWiki math extension
 *
 * @copyright 2002-2015 various MediaWiki contributors
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\Math;

use Exception;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MWException;
use Psr\Log\LoggerInterface;
use RestbaseVirtualRESTService;
use stdClass;
use VirtualRESTServiceClient;

class MathRestbaseInterface {
	/** @var string|false */
	private $hash = false;
	/** @var string */
	private $tex;
	/** @var string */
	private $type;
	/** @var string|null */
	private $checkedTex;
	/** @var bool|null */
	private $success;
	/** @var array */
	private $identifiers;
	/** @var stdClass|null */
	private $error;
	/** @var string|null */
	private $mathoidStyle;
	/** @var string|null */
	private $mml;
	/** @var array */
	private $warnings = [];
	/** @var bool is there a request to purge the existing mathematical content */
	private $purge = false;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param string $tex
	 * @param string $type
	 */
	public function __construct( $tex = '', $type = 'tex' ) {
		$this->tex = $tex;
		$this->type = $type;
		$this->logger = LoggerFactory::getInstance( 'Math' );
	}

	/**
	 * Bundles several requests for fetching MathML.
	 * Does not send requests, if the input TeX is invalid.
	 * @param MathRestbaseInterface[] $rbis
	 * @param VirtualRESTServiceClient $serviceClient
	 */
	private static function batchGetMathML( array $rbis, VirtualRESTServiceClient $serviceClient ) {
		$requests = [];
		$skips = [];
		$i = 0;
		foreach ( $rbis as $rbi ) {
			/** @var MathRestbaseInterface $rbi */
			if ( $rbi->getSuccess() ) {
				$requests[] = $rbi->getContentRequest( 'mml' );
			} else {
				$skips[] = $i;
			}
			$i++;
		}
		$results = $serviceClient->runMulti( $requests );
		$lenRbis = count( $rbis );
		$j = 0;
		for ( $i = 0; $i < $lenRbis; $i++ ) {
			if ( !in_array( $i, $skips ) ) {
				/** @var MathRestbaseInterface $rbi */
				$rbi = $rbis[$i];
				try {
					$mml = $rbi->evaluateContentResponse( 'mml', $results[$j], $requests[$j] );
					$rbi->mml = $mml;
				}
				catch ( Exception $e ) {
				}
				$j++;
			}
		}
	}

	/**
	 * Lets this instance know if this is a purge request. When set to true,
	 * it will cause the object to issue the first content request with a
	 * 'Cache-Control: no-cache' header to prompt the regeneration of the
	 * renders.
	 *
	 * @param bool $purge whether this is a purge request
	 */
	public function setPurge( $purge = true ) {
		$this->purge = $purge;
	}

	/**
	 * @return string MathML code
	 * @throws MWException
	 */
	public function getMathML() {
		if ( !$this->mml ) {
			$this->mml = $this->getContent( 'mml' );
		}
		return $this->mml;
	}

	private function getContent( $type ) {
		$request = $this->getContentRequest( $type );
		$serviceClient = $this->getServiceClient();
		$response = $serviceClient->run( $request );
		return $this->evaluateContentResponse( $type, $response, $request );
	}

	private function calculateHash() {
		if ( !$this->hash ) {
			if ( !$this->checkTeX() ) {
				throw new MWException( "TeX input is invalid." );
			}
		}
	}

	public function checkTeX() {
		$request = $this->getCheckRequest();
		$requestResult = $this->executeRestbaseCheckRequest( $request );
		return $this->evaluateRestbaseCheckResponse( $requestResult );
	}

	/**
	 * Performs a service request
	 * Generates error messages on failure
	 * @see Http::post()
	 *
	 * @param array $request
	 * @return array
	 */
	private function executeRestbaseCheckRequest( $request ) {
		$res = null;
		$serviceClient = $this->getServiceClient();
		$response = $serviceClient->run( $request );
		if ( $response['code'] !== 200 ) {
			$this->log()->info( 'Tex check failed', [
				'post'  => $request['body'],
				'error' => $response['error'],
				'urlparams'   => $request['url']
			] );
		}
		return $response;
	}

	/**
	 * @param MathRestbaseInterface[] $rbis
	 */
	public static function batchEvaluate( array $rbis ) {
		if ( count( $rbis ) == 0 ) {
			return;
		}
		$requests = [];
		/** @var MathRestbaseInterface $first */
		$first = $rbis[0];
		$serviceClient = $first->getServiceClient();
		foreach ( $rbis as $rbi ) {
			/** @var MathRestbaseInterface $rbi */
			$requests[] = $rbi->getCheckRequest();
		}
		$results = $serviceClient->runMulti( $requests );
		$i = 0;
		foreach ( $results as $response ) {
			/** @var MathRestbaseInterface $rbi */
			$rbi = $rbis[$i++];
			try {
				$rbi->evaluateRestbaseCheckResponse( $response );
			} catch ( Exception $e ) {
			}
		}
		self::batchGetMathML( $rbis, $serviceClient );
	}

	/**
	 * @return VirtualRESTServiceClient
	 */
	private function getServiceClient() {
		global $wgVirtualRestConfig, $wgMathConcurrentReqs;
		$http = MediaWikiServices::getInstance()->getHttpRequestFactory()->createMultiClient(
			[ 'maxConnsPerHost' => $wgMathConcurrentReqs ] );
		$serviceClient = new VirtualRESTServiceClient( $http );
		if ( isset( $wgVirtualRestConfig['modules']['restbase'] ) ) {
			$cfg = $wgVirtualRestConfig['modules']['restbase'];
			$cfg['parsoidCompat'] = false;
			$vrsObject = new RestbaseVirtualRESTService( $cfg );
			$serviceClient->mount( '/mathoid/', $vrsObject );
		}
		return $serviceClient;
	}

	/**
	 * The URL is generated accoding to the following logic:
	 *
	 * Case A: <code>$internal = false</code>, which means one needs an URL that is accessible from
	 * outside:
	 *
	 * --> If <code>$wgMathFullRestbaseURL</code> is configured use it, otherwise fall back try to
	 * <code>$wgVisualEditorFullRestbaseURL</code>. (Note, that this is not be worse than failing
	 * immediately.)
	 *
	 * Case B: <code> $internal= true</code>, which means one needs to access content from Restbase
	 * which does not need to be accessible from outside:
	 *
	 * --> Use the mount point whenever possible. If the mount point is not available, use
	 * <code>$wgMathFullRestbaseURL</code> with fallback to <code>wgVisualEditorFullRestbaseURL</code>
	 *
	 * @param string $path
	 * @param bool|true $internal
	 * @return string
	 * @throws MWException
	 */
	public function getUrl( $path, $internal = true ) {
		global $wgVirtualRestConfig, $wgMathFullRestbaseURL, $wgVisualEditorFullRestbaseURL;
		if ( $internal && isset( $wgVirtualRestConfig['modules']['restbase'] ) ) {
			return "/mathoid/local/v1/$path";
		}
		if ( $wgMathFullRestbaseURL ) {
			return "{$wgMathFullRestbaseURL}v1/$path";
		}
		if ( $wgVisualEditorFullRestbaseURL ) {
			return "{$wgVisualEditorFullRestbaseURL}v1/$path";
		}
		$msg = 'Math extension can not find Restbase URL. Please specify $wgMathFullRestbaseURL.';
		$this->setErrorMessage( $msg );
		throw new MWException( $msg );
	}

	/**
	 * @return \Psr\Log\LoggerInterface
	 */
	private function log() {
		return $this->logger;
	}

	public function getSvg() {
		return $this->getContent( 'svg' );
	}

	/**
	 * Generates a unique TeX string, renders it and gets it via a public URL.
	 * The method fails, if the public URL does not point to the same server, who did render
	 * the unique TeX input in the first place.
	 * @return bool
	 */
	private function checkConfig() {
		// Generates a TeX string that probably has not been generated before
		$uniqueTeX = uniqid( 't=', true );
		$testInterface = new MathRestbaseInterface( $uniqueTeX );
		if ( !$testInterface->checkTeX() ) {
			$this->log()->warning( 'Config check failed, since test expression was considered as invalid.',
				[ 'uniqueTeX' => $uniqueTeX ] );
			return false;
		}

		try {
			$url = $testInterface->getFullSvgUrl();
			$req = MediaWikiServices::getInstance()->getHttpRequestFactory()->create( $url, [], __METHOD__ );
			$status = $req->execute();
			if ( $status->isOK() ) {
				return true;
			}

			$this->log()->warning( 'Config check failed, due to an invalid response code.',
				[ 'responseCode' => $status ] );
		} catch ( Exception $e ) {
			$this->log()->warning( 'Config check failed, due to an exception.', [ $e ] );
		}

		return false;
	}

	/**
	 * Gets a publicly accessible link to the generated SVG image.
	 * @return string
	 * @throws MWException
	 */
	public function getFullSvgUrl() {
		$this->calculateHash();
		return $this->getUrl( "media/math/render/svg/{$this->hash}", false );
	}

	/**
	 * Gets a publicly accessible link to the generated SVG image.
	 * @return string
	 * @throws MWException
	 */
	public function getFullPngUrl() {
		$this->calculateHash();
		return $this->getUrl( "media/math/render/png/{$this->hash}", false );
	}

	/**
	 * @return string
	 */
	public function getCheckedTex() {
		return $this->checkedTex;
	}

	/**
	 * @return bool
	 */
	public function getSuccess() {
		if ( $this->success === null ) {
			$this->checkTeX();
		}
		return $this->success;
	}

	/**
	 * @return array
	 */
	public function getIdentifiers() {
		return $this->identifiers;
	}

	/**
	 * @return stdClass
	 */
	public function getError() {
		return $this->error;
	}

	/**
	 * @return string
	 */
	public function getTex() {
		return $this->tex;
	}

	/**
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	private function setErrorMessage( $msg ) {
		$this->error = (object)[ 'error' => (object)[ 'message' => $msg ] ];
	}

	/**
	 * @return array
	 */
	public function getWarnings() {
		return $this->warnings;
	}

	/**
	 * @return array
	 * @throws MWException
	 */
	public function getCheckRequest() {
		return [
			'method' => 'POST',
			'body'   => [
				'type' => $this->type,
				'q'    => $this->tex
			],
			'url'    => $this->getUrl( "media/math/check/{$this->type}" )
		];
	}

	/**
	 * @param array $response
	 * @return bool
	 */
	public function evaluateRestbaseCheckResponse( $response ) {
		$json = json_decode( $response['body'] );
		if ( $response['code'] === 200 ) {
			$headers = $response['headers'];
			$this->hash = $headers['x-resource-location'];
			$this->success = $json->success;
			$this->checkedTex = $json->checked;
			$this->identifiers = $json->identifiers;
			if ( isset( $json->warnings ) ) {
				$this->warnings = $json->warnings;
			}
			return true;
		} else {
			if ( isset( $json->detail ) && isset( $json->detail->success ) ) {
				$this->success = $json->detail->success;
				$this->error = $json->detail;
			} else {
				$this->success = false;
				$this->setErrorMessage( 'Math extension cannot connect to Restbase.' );
			}
			return false;
		}
	}

	/**
	 * @return string
	 */
	public function getMathoidStyle() {
		return $this->mathoidStyle;
	}

	/**
	 * @param string $type
	 * @return array
	 * @throws MWException
	 */
	private function getContentRequest( $type ) {
		$this->calculateHash();
		$request = [
			'method' => 'GET',
			'url' => $this->getUrl( "media/math/render/$type/{$this->hash}" )
		];
		if ( $this->purge ) {
			$request['headers'] = [
				'Cache-Control' => 'no-cache'
			];
			$this->purge = false;
		}
		return $request;
	}

	/**
	 * @param string $type
	 * @param array $response
	 * @param array $request
	 * @return string
	 * @throws MWException
	 */
	private function evaluateContentResponse( $type, array $response, array $request ) {
		if ( $response['code'] === 200 ) {
			if ( array_key_exists( 'x-mathoid-style', $response['headers'] ) ) {
				$this->mathoidStyle = $response['headers']['x-mathoid-style'];
			}
			return $response['body'];
		}
		// Remove "convenience" duplicate keys put in place by MultiHttpClient
		unset( $response[0], $response[1], $response[2], $response[3], $response[4] );
		$this->log()->error( 'Restbase math server problem', [
			'urlparams' => $request['url'],
			'response' => [ 'code' => $response['code'], 'body' => $response['body'] ],
			'math_type' => $type,
			'tex' => $this->tex
		] );
		self::throwContentError( $type, $response['body'] );
	}

	/**
	 * @param string $type
	 * @param string $body
	 * @throws MWException
	 * @return never
	 */
	public static function throwContentError( $type, $body ) {
		$detail = 'Server problem.';
		$json = json_decode( $body );
		if ( isset( $json->detail ) ) {
			if ( is_array( $json->detail ) ) {
				$detail = $json->detail[0];
			} elseif ( is_string( $json->detail ) ) {
				$detail = $json->detail;
			}
		}
		throw new MWException( "Cannot get $type. $detail" );
	}
}
