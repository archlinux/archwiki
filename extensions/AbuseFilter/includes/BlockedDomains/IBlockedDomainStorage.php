<?php

namespace MediaWiki\Extension\AbuseFilter\BlockedDomains;

use MediaWiki\Permissions\Authority;
use StatusValue;

interface IBlockedDomainStorage {
	public const SERVICE_NAME = 'AbuseFilterBlockedDomainStorage';

	/**
	 * Load the configuration page, with optional local-server caching.
	 *
	 * @param int $flags bit field, see IDBAccessObject::READ_XXX
	 * @return StatusValue The content of the configuration page (as JSON
	 *   data in PHP-native format), or a StatusValue on error.
	 */
	public function loadConfig( int $flags = 0 ): StatusValue;

	/**
	 * Load the computed domain blocklist
	 *
	 * @return array<string,true> Flipped for performance reasons
	 */
	public function loadComputed(): array;

	/**
	 * This doesn't do validation.
	 *
	 * @param string $domain domain to be blocked
	 * @param string $notes User provided notes
	 * @param Authority $authority Performer
	 *
	 * @return StatusValue
	 */
	public function addDomain( string $domain, string $notes, Authority $authority ): StatusValue;

	/**
	 * This doesn't do validation
	 *
	 * @param string $domain domain to be removed from the blocked list
	 * @param string $notes User provided notes
	 * @param Authority $authority Performer
	 *
	 * @return StatusValue
	 */
	public function removeDomain( string $domain, string $notes, Authority $authority ): StatusValue;
}
