<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Extension\AbuseFilter\Filter\Flags;

/**
 * @internal
 */
class FilterUtils {
	/**
	 * Given a bitmask of privacy levels, return if the hidden flag is set
	 *
	 * @param int $privacyLevel
	 * @return bool
	 */
	public static function isHidden( int $privacyLevel ) {
		return (bool)( Flags::FILTER_HIDDEN & $privacyLevel );
	}

	/**
	 * Given a bitmask, return if the protected flag is set
	 *
	 * @param int $privacyLevel
	 * @return bool
	 */
	public static function isProtected( int $privacyLevel ) {
		return (bool)( Flags::FILTER_USES_PROTECTED_VARS & $privacyLevel );
	}
}
