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

use ExtensionRegistry;

class OATHAuthModuleRegistry {

	/** @var array|null */
	private $modules = null;

	/**
	 * @param string $key
	 * @return IModule|null
	 */
	public function getModuleByKey( string $key ): ?IModule {
		$this->collectModules();
		if ( isset( $this->modules[$key] ) ) {
			$module = call_user_func_array( $this->modules[$key], [] );
			if ( !$module instanceof IModule ) {
				return null;
			}
			return $module;
		}

		return null;
	}

	/**
	 * Get all modules registered on the wiki
	 *
	 * @return IModule[]
	 */
	public function getAllModules(): array {
		$this->collectModules();

		$modules = [];
		foreach ( $this->modules as $key => $callback ) {
			$module = $this->getModuleByKey( $key );
			if ( !( $module instanceof IModule ) ) {
				continue;
			}
			$modules[$key] = $module;
		}
		return $modules;
	}

	private function collectModules() {
		if ( $this->modules !== null ) {
			return;
		}

		$this->modules = ExtensionRegistry::getInstance()->getAttribute( 'OATHAuthModules' );
	}
}
