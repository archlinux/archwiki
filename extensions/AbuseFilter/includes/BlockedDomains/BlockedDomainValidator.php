<?php

namespace MediaWiki\Extension\AbuseFilter\BlockedDomains;

use MediaWiki\Utils\UrlUtils;

class BlockedDomainValidator {

	public const SERVICE_NAME = 'AbuseFilterBlockedDomainValidator';

	private UrlUtils $urlUtils;

	public function __construct( UrlUtils $urlUtils ) {
		$this->urlUtils = $urlUtils;
	}

	/**
	 * Validate an input domain
	 *
	 * @param string|null $domain Domain such as foo.wikipedia.org
	 * @return string|false Parsed domain, or false otherwise
	 */
	public function validateDomain( ?string $domain ) {
		if ( !$domain ) {
			return false;
		}

		$domain = trim( $domain );
		if ( !str_contains( $domain, '//' ) ) {
			$domain = 'https://' . $domain;
		}

		$parsedUrl = $this->urlUtils->parse( $domain );
		// Parse url returns a valid URL for "foo"
		if ( !$parsedUrl || !str_contains( $parsedUrl['host'], '.' ) ) {
			return false;
		}
		return $parsedUrl['host'];
	}
}
