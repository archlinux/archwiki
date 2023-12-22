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

use Exception;

class OATHAuthModuleRegistry {

	/** @var OATHAuthDatabase */
	private OATHAuthDatabase $database;

	/** @var array */
	private $modules;

	/** @var array|null */
	private $moduleIds;

	/**
	 * @param OATHAuthDatabase $database
	 * @param array $modules
	 */
	public function __construct(
		OATHAuthDatabase $database,
		array $modules
	) {
		$this->database = $database;
		$this->modules = $modules;
	}

	/**
	 * @param string $key
	 * @return IModule|null
	 */
	public function getModuleByKey( string $key ): ?IModule {
		if ( isset( $this->getModules()[$key] ) ) {
			$module = call_user_func_array( $this->getModules()[$key], [] );
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
		$modules = [];
		foreach ( $this->getModules() as $key => $callback ) {
			$module = $this->getModuleByKey( $key );
			if ( !( $module instanceof IModule ) ) {
				continue;
			}
			$modules[$key] = $module;
		}
		return $modules;
	}

	/**
	 * Returns the numerical ID for the module with the specified key.
	 * @param string $key
	 * @return int
	 */
	public function getModuleId( string $key ): int {
		$ids = $this->getModuleIds();
		if ( isset( $ids[$key] ) ) {
			return $ids[$key];
		}

		throw new Exception( "Module $key does not seem to exist" );
	}

	/**
	 * @return array
	 */
	public function getModuleIds(): array {
		if ( $this->moduleIds === null ) {
			$this->moduleIds = $this->getModuleIdsFromDatabase( DB_REPLICA );
		}

		$missing = array_diff(
			array_keys( $this->getModules() ),
			array_keys( $this->moduleIds )
		);

		if ( $missing ) {
			$rows = [];
			foreach ( $missing as $name ) {
				$rows[] = [ 'oat_name' => $name ];
			}

			$this->database
				->getDB( DB_PRIMARY )
				->insert( 'oathauth_types', $rows, __METHOD__ );
			$this->moduleIds = $this->getModuleIdsFromDatabase( DB_PRIMARY );
		}

		return $this->moduleIds;
	}

	private function getModuleIdsFromDatabase( int $index ): array {
		$ids = [];

		$rows = $this->database->getDB( $index )
			->newSelectQueryBuilder()
			->select( [ 'oat_id', 'oat_name' ] )
			->from( 'oathauth_types' )
			->caller( __METHOD__ )
			->fetchResultSet();

		foreach ( $rows as $row ) {
			$ids[$row->oat_name] = (int)$row->oat_id;
		}

		return $ids;
	}

	private function getModules(): array {
		return $this->modules;
	}
}
