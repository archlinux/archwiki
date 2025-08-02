<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\Request\ProxyLookup;
use MediaWiki\Request\WebRequest;
use Wikimedia\IPUtils;

class CheckUserUtilityService {

	private ProxyLookup $proxyLookup;

	private bool $usePrivateIPs;

	/**
	 * @param ProxyLookup $proxyLookup
	 * @param bool $usePrivateIPs
	 */
	public function __construct( ProxyLookup $proxyLookup, bool $usePrivateIPs ) {
		$this->proxyLookup = $proxyLookup;
		$this->usePrivateIPs = $usePrivateIPs;
	}

	/**
	 * Locates the client IP within a given XFF string.
	 * Unlike the XFF checking to determine a user IP in WebRequest,
	 * this simply follows the chain and does not account for server trust.
	 *
	 * This returns an array containing:
	 *   - The best guess of the client IP
	 *   - Whether all the proxies are just squid/varnish
	 *   - The XFF value, converted to a empty string if false
	 *
	 * @param string|bool $xff XFF header value
	 * @return array (string|null, bool, string)
	 */
	public function getClientIPfromXFF( $xff ) {
		if ( $xff === false || !strlen( $xff ) ) {
			// If the XFF is empty or not a string return with a
			// XFF of the empty string and no results
			return [ null, false, '' ];
		}

		# Get the list in the form of <PROXY N, ... PROXY 1, CLIENT>
		$ipchain = array_map( 'trim', explode( ',', $xff ) );
		$ipchain = array_reverse( $ipchain );

		// best guess of the client IP
		$client = null;

		// all proxy servers where site Squid/Varnish servers?
		$isSquidOnly = false;
		# Step through XFF list and find the last address in the list which is a
		# sensible proxy server. Set $ip to the IP address given by that proxy server,
		# unless the address is not sensible (e.g. private). However, prefer private
		# IP addresses over proxy servers controlled by this site (more sensible).
		foreach ( $ipchain as $i => $curIP ) {
			$curIP = IPUtils::canonicalize(
				WebRequest::canonicalizeIPv6LoopbackAddress( $curIP )
			);
			if ( $curIP === null ) {
				// not a valid IP address
				break;
			}
			$curIsSquid = $this->proxyLookup->isConfiguredProxy( $curIP );
			if ( $client === null ) {
				$client = $curIP;
				$isSquidOnly = $curIsSquid;
			}
			if (
				isset( $ipchain[$i + 1] ) &&
				IPUtils::isIPAddress( $ipchain[$i + 1] ) &&
				(
					IPUtils::isPublic( $ipchain[$i + 1] ) ||
					$this->usePrivateIPs ||
					// T50919
					$curIsSquid
				)
			) {
				$client = IPUtils::canonicalize(
					WebRequest::canonicalizeIPv6LoopbackAddress( $ipchain[$i + 1] )
				);
				$isSquidOnly = ( $isSquidOnly && $curIsSquid );
				continue;
			}
			break;
		}

		return [ $client, $isSquidOnly, $xff ];
	}
}

/**
 * Retain the old namespace for backwards compatibility.
 * @deprecated since 1.41
 */
class_alias( CheckUserUtilityService::class, 'MediaWiki\CheckUser\CheckUserUtilityService' );
