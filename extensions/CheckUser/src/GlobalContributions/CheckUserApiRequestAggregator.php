<?php

namespace MediaWiki\CheckUser\GlobalContributions;

use CentralAuthSessionProvider;
use Exception;
use LogicException;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\SessionManager;
use MediaWiki\Site\MediaWikiSite;
use MediaWiki\Site\SiteLookup;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\WikiMap\WikiMap;
use Psr\Log\LoggerInterface;

/**
 * Perform multiple API requests to external wikis. Much of this class is copied from ForeignWikiRequest
 * in the Echo extension.
 *
 * This is used by GlobalContributionsPager to look up permissions at external wikis, as decided in
 * T356294#10272663. Looking this up via API calls is not ideal, since it can be slow, susceptible to
 * failures, and difficult to test. However, there is no other way to look up permissions until T380867.
 */
class CheckUserApiRequestAggregator {
	private HttpRequestFactory $httpRequestFactory;
	private CentralIdLookup $centralIdLookup;
	private ExtensionRegistry $extensionRegistry;
	private SiteLookup $siteLookup;
	private LoggerInterface $logger;
	private User $user;
	private array $params;
	private array $wikis;
	private int $authenticate;

	public const AUTHENTICATE_NONE = 0;
	public const AUTHENTICATE_CENTRAL_AUTH = 1;

	public function __construct(
		HttpRequestFactory $httpRequestFactory,
		CentralIdLookup $centralIdLookup,
		ExtensionRegistry $extensionRegistry,
		SiteLookup $siteLookup,
		LoggerInterface $logger
	) {
		$this->httpRequestFactory = $httpRequestFactory;
		$this->centralIdLookup = $centralIdLookup;
		$this->extensionRegistry = $extensionRegistry;
		$this->siteLookup = $siteLookup;
		$this->logger = $logger;
	}

	/**
	 * Execute the request.
	 *
	 * @internal For use by GlobalContributionsPager.
	 * @param User $user
	 * @param array $params API request parameters
	 * @param string[] $wikis Wikis to send the request to
	 * @param WebRequest $originalRequest Original request data to be sent with these requests
	 * @param int $authenticate Authentication level needed - one of the self::AUTHENTICATE_* constants
	 * @return array[] [ wiki => result ] for all the wikis that returned results
	 */
	public function execute(
		User $user,
		array $params,
		array $wikis,
		WebRequest $originalRequest,
		int $authenticate = self::AUTHENTICATE_NONE
	) {
		$this->user = $user;
		$this->params = $params;
		$this->wikis = $wikis;
		$this->authenticate = $authenticate;

		if ( count( $this->wikis ) === 0 ) {
			return [];
		}

		if ( $this->authenticate === self::AUTHENTICATE_CENTRAL_AUTH && !$this->canUseCentralAuth() ) {
			throw new LogicException(
				"CentralAuth authentication is needed but not available"
			);
		}

		$reqs = $this->getRequestParams( $originalRequest );
		return $this->doRequests( $reqs );
	}

	/**
	 * @return int
	 */
	private function getCentralId() {
		return $this->centralIdLookup->centralIdFromLocalUser( $this->user, CentralIdLookup::AUDIENCE_RAW );
	}

	/**
	 * Check whether the user has a central ID via the CentralAuth extension
	 *
	 * Protected function for mocking in tests.
	 *
	 * @return bool
	 */
	protected function canUseCentralAuth() {
		return $this->extensionRegistry->isLoaded( 'CentralAuth' ) &&
			SessionManager::getGlobalSession()->getProvider() instanceof CentralAuthSessionProvider &&
			$this->getCentralId() !== 0;
	}

	/**
	 * Returns CentralAuth token. Should only be called if ::canUseCentralAuth returns true.
	 *
	 * @return string
	 */
	private function getCentralAuthToken() {
		return CentralAuthServices::getApiTokenGenerator()->getToken(
			$this->user,
			SessionManager::getGlobalSession()->getId(),
			WikiMap::getCurrentWikiId()
		);
	}

	/**
	 * @param WebRequest $originalRequest Original request data to be sent with these requests
	 * @return array[] Array of request parameters to pass to doRequests(), keyed by wiki name
	 */
	private function getRequestParams( WebRequest $originalRequest ) {
		$urls = [];
		foreach ( $this->wikis as $wiki ) {
			$site = $this->siteLookup->getSite( $wiki );
			if ( $site instanceof MediaWikiSite ) {
				$urls[$wiki] = $site->getFileUrl( 'api.php' );
			} else {
				$this->logger->error(
					'Site {wiki} was not recognized.',
					[
						'wiki' => $wiki,
					]
				);
			}
		}

		if ( !$urls ) {
			return [];
		}

		$reqs = [];
		foreach ( $urls as $wiki => $url ) {
			$params = [
				'format' => 'json',
				'formatversion' => '2',
				'errorformat' => 'bc',
			];

			// Use a new CentralAuth token for each request since they are one-time use (T384717).
			if ( $this->authenticate === self::AUTHENTICATE_CENTRAL_AUTH ) {
				$params['centralauthtoken'] = $this->getCentralAuthToken();
			}

			$reqs[$wiki] = [
				'method' => 'GET',
				'url' => $url,
				'query' => $params + $this->params,
			];

			$reqs[$wiki]['headers'] = [
				'X-Forwarded-For' => $originalRequest->getIP(),
				'User-Agent' => (
					$originalRequest->getHeader( 'User-Agent' )
					. ' (via CheckUserApiRequestAggregator MediaWiki/' . MW_VERSION . ')'
				),
			];
		}

		return $reqs;
	}

	/**
	 * @param array $reqs API request params
	 * @return array[]
	 * @throws Exception
	 */
	private function doRequests( array $reqs ) {
		if ( count( $reqs ) === 0 ) {
			return [];
		}

		$http = $this->httpRequestFactory->createMultiClient();
		$responses = $http->runMulti( $reqs );

		$results = [];
		foreach ( $responses as $wiki => $response ) {
			$statusCode = $response['response']['code'];

			if ( $statusCode >= 200 && $statusCode <= 299 ) {
				$parsed = json_decode( $response['response']['body'], true );
				if ( $parsed ) {
					$results[$wiki] = $parsed;
				}
			}

			if ( !isset( $results[$wiki]['query']['pages'][0]['actions'] ) ) {
				$this->logger->error(
					'Failed to fetch API response from {wiki}. Error: {error}',
					[
						'wiki' => $wiki,
						'error' => $response['response']['error'] ?? 'unknown',
						'statusCode' => $statusCode,
						'response' => $response['response'],
						'request' => $reqs[$wiki],
					]
				);
			}
		}

		return $results;
	}
}
