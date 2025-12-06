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

namespace MediaWiki\Extension\OATHAuth;

use MediaWiki\Config\Config;
use MediaWiki\Extension\OATHAuth\Key\EncryptionHelper;
use MediaWiki\MediaWikiServices;

/**
 * Type-safe wrapper for accessing OATHAuth services.
 *
 * @author Taavi Väänänen <hi@taavi.wtf>
 */
class OATHAuthServices {
	public function __construct( private readonly MediaWikiServices $services ) {
	}

	public static function getInstance( ?MediaWikiServices $services = null ): self {
		return new self(
			$services ?? MediaWikiServices::getInstance(),
		);
	}

	public function getModuleRegistry(): OATHAuthModuleRegistry {
		return $this->services->getService( 'OATHAuthModuleRegistry' );
	}

	public function getUserRepository(): OATHUserRepository {
		return $this->services->getService( 'OATHUserRepository' );
	}

	public function getEncryptionHelper(): EncryptionHelper {
		return $this->services->getService( 'OATHAuth.EncryptionHelper' );
	}

	public function getConfig(): Config {
		return $this->services->getMainConfig();
	}
}
