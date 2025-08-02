<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

namespace MediaWiki\SpecialPage;

use MediaWiki\Config\Config;
use MediaWiki\MainConfigNames;
use Wikimedia\IPUtils;

/**
 * Handle ranges for contributions special pages and APIs.
 *
 * @since 1.44
 */
trait ContributionsRangeTrait {
	/**
	 * Check whether the given IP is a range and within the contributions CIDR limit.
	 *
	 * @param string $target
	 * @param Config $config
	 * @return bool
	 */
	protected function isQueryableRange( string $target, Config $config ): bool {
		if ( !IPUtils::isValidRange( $target ) ) {
			return false;
		}

		$CIDRLimit = $this->getQueryableRangeLimit( $config );
		[ $ip, $range ] = explode( '/', $target, 2 );
		return (
			( IPUtils::isIPv4( $ip ) && $range >= $CIDRLimit['IPv4'] ) ||
			( IPUtils::isIPv6( $ip ) && $range >= $CIDRLimit['IPv6'] )
		);
	}

	/**
	 * Check whether the given target is either a valid IP address or a valid range within the
	 * contributions CIDR limit.
	 *
	 * @param string $target
	 * @param Config $config
	 * @return bool
	 */
	protected function isValidIPOrQueryableRange( string $target, Config $config ): bool {
		return IPUtils::isValid( $target ) ||
			$this->isQueryableRange( $target, $config );
	}

	/**
	 * @param Config $config
	 * @return int[]
	 */
	protected function getQueryableRangeLimit( Config $config ): array {
		return $config->get( MainConfigNames::RangeContributionsCIDRLimit );
	}
}
