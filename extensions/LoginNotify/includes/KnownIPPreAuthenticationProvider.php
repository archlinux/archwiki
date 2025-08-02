<?php
namespace LoginNotify;

use MediaWiki\Api\ApiMessage;
use MediaWiki\Auth\AbstractPreAuthenticationProvider;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\User\UserFactory;
use StatusValue;

/**
 * A pre-authentication provider that optionally denies login attempts from unknown IPs.
 *
 * This check matches the behavior of the audit hook in that it only considers IPs that have been used on the
 * local wiki to be known if the request did not have a previous login cookie.
 */
class KnownIPPreAuthenticationProvider extends AbstractPreAuthenticationProvider {
	private LoginNotify $loginNotify;
	private UserFactory $userFactory;

	public function __construct(
		LoginNotify $loginNotify,
		UserFactory $userFactory
	) {
		$this->loginNotify = $loginNotify;
		$this->userFactory = $userFactory;
	}

	public function testForAuthentication( array $reqs ): StatusValue {
		if ( !$this->config->get( 'LoginNotifyDenyUnknownIPs' ) ) {
			return StatusValue::newGood();
		}

		$userName = AuthenticationRequest::getUsernameFromRequests( $reqs );

		$user = $this->userFactory->newFromName( $userName );
		if ( $user === null ) {
			return StatusValue::newGood();
		}

		$request = $this->manager->getRequest();

		$known = $this->loginNotify->isKnownSystemFast( $user, $request );

		if ( $known === LoginNotify::USER_KNOWN ) {
			return StatusValue::newGood();
		}

		LoggerFactory::getInstance( 'LoginNotify' )
			->warning( 'Rejected login attempt from unknown IP for {name}', [
				'name' => $user->getName(),
				'clientip' => $request->getIP(),
				'ua' => $request->getHeader( 'User-Agent' ),
				'xff' => $request->getHeader( 'X-Forwarded-For' ),
				'geocookie' => $request->getCookie( 'GeoIP', '' ),
			] );

		return StatusValue::newFatal( ApiMessage::create( 'loginnotify-unknown-ip', 'unknownip' ) );
	}
}
