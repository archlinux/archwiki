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
 * @since 1.36
 */

namespace Vector\FeatureManagement\Requirements;

use Config;
use Vector\Constants;
use Vector\FeatureManagement\Requirement;

/**
 * Checks whether or not WVUI search should be used.
 *
 * @unstable
 *
 * @package Vector\FeatureManagement\Requirements
 * @internal
 */
final class WvuiSearchTreatmentRequirement implements Requirement {
	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var \User
	 */
	private $user;

	/**
	 * This constructor accepts all dependencies needed to determine
	 * whether wvui search is enabled for current user and config.
	 *
	 * @param \Config $config
	 * @param \User $user
	 */
	public function __construct( \Config $config, \User $user ) {
		$this->config = $config;
		$this->user = $user;
	}

	/**
	 * @inheritDoc
	 */
	public function getName() : string {
		return Constants::REQUIREMENT_USE_WVUI_SEARCH;
	}

	/**
	 * If A/B test is enabled check whether the user is logged in and bucketed.
	 * Fallback to `VectorUseWvuiSearch` config value.
	 *
	 * @inheritDoc
	 * @throws \ConfigException
	 */
	public function isMet() : bool {
		// Determine the search widget treatment to send to the user
		$shouldUseWvuiSearch = (bool)$this->config->get( Constants::CONFIG_KEY_USE_WVUI_SEARCH );

		if ( (bool)$this->config->get( Constants::CONFIG_SEARCH_TREATMENT_AB_TEST ) && $this->user->isRegistered() ) {
			$shouldUseWvuiSearch = $this->user->getID() % 2 === 0;
		}

		return $shouldUseWvuiSearch;
	}
}
