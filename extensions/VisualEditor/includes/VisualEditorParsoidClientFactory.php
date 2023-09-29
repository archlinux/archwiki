<?php

namespace MediaWiki\Extension\VisualEditor;

use ConfigException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\Handler\Helper\PageRestHelperFactory;
use ParsoidVirtualRESTService;
use Psr\Log\LoggerInterface;
use RequestContext;
use RestbaseVirtualRESTService;
use VirtualRESTService;
use VirtualRESTServiceClient;

/**
 * @since 1.40
 */
class VisualEditorParsoidClientFactory {

	/**
	 * @internal For use by ServiceWiring.php only or when locating the service
	 * @var string
	 */
	public const SERVICE_NAME = 'VisualEditor.ParsoidClientFactory';

	/** @var bool */
	public const ENABLE_COOKIE_FORWARDING = 'EnableCookieForwarding';

	/**
	 * @internal For used by ServiceWiring.php
	 *
	 * @var array
	 */
	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::VirtualRestConfig,
		self::ENABLE_COOKIE_FORWARDING,
		self::DEFAULT_PARSOID_CLIENT_SETTING,
	];

	/** @var string */
	public const DEFAULT_PARSOID_CLIENT_SETTING = 'VisualEditorDefaultParsoidClient';

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var VirtualRESTServiceClient */
	private $serviceClient = null;

	/** @var ServiceOptions */
	private $options;

	/** @var LoggerInterface */
	private $logger;

	/** @var PageRestHelperFactory */
	private $pageRestHelperFactory;

	/**
	 * @param ServiceOptions $options
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param LoggerInterface $logger
	 * @param PageRestHelperFactory $pageRestHelperFactory
	 */
	public function __construct(
		ServiceOptions $options,
		HttpRequestFactory $httpRequestFactory,
		LoggerInterface $logger,
		PageRestHelperFactory $pageRestHelperFactory
	) {
		$this->options = $options;
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->httpRequestFactory = $httpRequestFactory;
		$this->logger = $logger;
		$this->pageRestHelperFactory = $pageRestHelperFactory;
	}

	/**
	 * Create a ParsoidClient for accessing Parsoid.
	 *
	 * @param string|string[]|false $cookiesToForward
	 * @param Authority|null $performer
	 *
	 * @return ParsoidClient
	 */
	public function createParsoidClient(
		$cookiesToForward,
		?Authority $performer = null
	): ParsoidClient {
		if ( $performer === null ) {
			$performer = RequestContext::getMain()->getAuthority();
		}

		return new DualParsoidClient( $this, $cookiesToForward, $performer );
	}

	/**
	 * Create a ParsoidClient for accessing Parsoid.
	 *
	 * @internal For use by DualParsoidClient only.
	 *
	 * @param string|string[]|false $cookiesToForward
	 * @param Authority $performer
	 * @param array $hints An associative array of hints for client creation.
	 *
	 * @return ParsoidClient
	 */
	public function createParsoidClientInternal(
		$cookiesToForward,
		Authority $performer,
		array $hints = []
	): ParsoidClient {
		// TODO: Delete when we no longer support VRS
		$shouldUseVRS = $hints['ShouldUseVRS'] ?? null;
		if ( $shouldUseVRS === null ) {
			$shouldUseVRS = ( $this->options->get( self::DEFAULT_PARSOID_CLIENT_SETTING ) === 'vrs' );
		}

		if ( $shouldUseVRS && $this->canUseParsoidOverHTTP() ) {
			$client = new VRSParsoidClient(
				$this->getVRSClient( $cookiesToForward ),
				$this->logger
			);
		} else {
			$client = $this->createDirectClient( $performer );
		}

		return $client;
	}

	/**
	 * Whether Parsoid should be used over HTTP, according to the configuration.
	 * Note that we may still end up using direct mode, depending on information
	 * from the request.
	 *
	 * @return bool
	 */
	public function useParsoidOverHTTP(): bool {
		$shouldUseVRS = ( $this->options->get( self::DEFAULT_PARSOID_CLIENT_SETTING ) === 'vrs' );
		return $this->canUseParsoidOverHTTP() && $shouldUseVRS;
	}

	/**
	 * Whether Parsoid could be used over HTTP, based on the configuration provided.
	 * @return bool
	 */
	private function canUseParsoidOverHTTP(): bool {
		// If we have VRS modules configured, use them
		$vrs = $this->options->get( MainConfigNames::VirtualRestConfig );
		if ( isset( $vrs['modules'] ) &&
			( isset( $vrs['modules']['restbase'] ) ||
				isset( $vrs['modules']['parsoid'] ) )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Create a ParsoidClient for accessing Parsoid.
	 *
	 * @param Authority $performer
	 *
	 * @return ParsoidClient
	 */
	private function createDirectClient( Authority $performer ): ParsoidClient {
		return new DirectParsoidClient(
			$this->pageRestHelperFactory,
			$performer
		);
	}

	/**
	 * Creates the virtual REST service object to be used in VE's API calls. The
	 * method determines whether to instantiate a ParsoidVirtualRESTService or a
	 * RestbaseVirtualRESTService object based on configuration directives: if
	 * $wgVirtualRestConfig['modules']['restbase'] is defined, RESTBase is chosen,
	 * otherwise Parsoid is used (either by using the MW Core config, or the
	 * VE-local one).
	 *
	 * @param string|string[]|false $forwardCookies False if header is unset; otherwise the
	 *  header value(s) as either a string (the default) or an array, if
	 *  WebRequest::GETHEADER_LIST flag was set.
	 *
	 * @return VirtualRESTService the VirtualRESTService object to use
	 */
	private function createVRSObject( $forwardCookies ): VirtualRESTService {
		// the params array to create the service object with
		$params = [];
		// the VRS class to use, defaults to Parsoid
		$class = ParsoidVirtualRESTService::class;
		// The global virtual rest service config object, if any
		$vrs = $this->options->get( MainConfigNames::VirtualRestConfig );
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
		if ( isset( $params['forwardCookies'] ) && $params['forwardCookies'] ) {
			$params['forwardCookies'] = $forwardCookies;
		} else {
			$params['forwardCookies'] = false;
		}
		// create the VRS object and return it
		return new $class( $params );
	}

	/**
	 * Creates the object which directs queries to the virtual REST service, depending on the path.
	 *
	 * @param string|string[]|false $cookiesToForward False if header is unset; otherwise the
	 *  header value(s) as either a string (the default) or an array, if
	 *  WebRequest::GETHEADER_LIST flag was set.
	 *
	 * @return VirtualRESTServiceClient
	 */
	private function getVRSClient( $cookiesToForward ): VirtualRESTServiceClient {
		if ( !$this->serviceClient ) {
			$this->serviceClient = new VirtualRESTServiceClient( $this->httpRequestFactory->createMultiClient() );
			$this->serviceClient->mount( '/restbase/', $this->createVRSObject( $cookiesToForward ) );
		}
		return $this->serviceClient;
	}

}
