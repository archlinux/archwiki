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

namespace Wikimedia\DependencyStore;

use InvalidArgumentException;
use Wikimedia\Rdbms\DBConnRef;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * Track per-module file dependencies in the core module_deps table
 *
 * Wiki farms that are too big for maintenance/update.php, can clean up
 * unneeded data for modules that no longer exist after a MW upgrade,
 * by running maintenance/cleanupRemovedModules.php.
 *
 * To force a rebuild and incurr a small penalty in browser cache churn,
 * run maintenance/purgeModuleDeps.php instead.
 *
 * @internal For use by ResourceLoader\Module only
 * @since 1.35
 */
class SqlModuleDependencyStore extends DependencyStore {
	/** @var ILoadBalancer */
	private $lb;

	/**
	 * @param ILoadBalancer $lb Storage backend
	 */
	public function __construct( ILoadBalancer $lb ) {
		$this->lb = $lb;
	}

	public function retrieveMulti( $type, array $entities ) {
		$dbr = $this->getReplicaDb();

		$depsBlobByEntity = $this->fetchDependencyBlobs( $entities, $dbr );

		$storedPathsByEntity = [];
		foreach ( $depsBlobByEntity as $entity => $depsBlob ) {
			$storedPathsByEntity[$entity] = json_decode( $depsBlob, true );
		}

		$results = [];
		foreach ( $entities as $entity ) {
			$paths = $storedPathsByEntity[$entity] ?? [];
			$results[$entity] = $this->newEntityDependencies( $paths, null );
		}

		return $results;
	}

	public function storeMulti( $type, array $dataByEntity, $ttl ) {
		// Avoid opening a primary DB connection when it's not needed.
		// ResourceLoader::saveModuleDependenciesInternal calls this method unconditionally
		// with empty values most of the time.
		if ( !$dataByEntity ) {
			return;
		}

		$dbw = $this->getPrimaryDb();
		$depsBlobByEntity = $this->fetchDependencyBlobs( array_keys( $dataByEntity ), $dbw );

		$rows = [];
		foreach ( $dataByEntity as $entity => $data ) {
			[ $module, $variant ] = $this->getEntityNameComponents( $entity );
			if ( !is_array( $data[self::KEY_PATHS] ) ) {
				throw new InvalidArgumentException( "Invalid entry for '$entity'" );
			}

			// Normalize the list by removing duplicates and sortings
			$paths = array_values( array_unique( $data[self::KEY_PATHS] ) );
			sort( $paths, SORT_STRING );
			$blob = json_encode( $paths, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

			$existingBlob = $depsBlobByEntity[$entity] ?? null;
			if ( $blob !== $existingBlob ) {
				$rows[] = [
					'md_module' => $module,
					'md_skin' => $variant,
					'md_deps' => $blob
				];
			}
		}

		// @TODO: use a single query with VALUES()/aliases support in DB wrapper
		// See https://dev.mysql.com/doc/refman/8.0/en/insert-on-duplicate.html
		foreach ( $rows as $row ) {
			$dbw->upsert(
				'module_deps',
				$row,
				[ [ 'md_module', 'md_skin' ] ],
				[
					'md_deps' => $row['md_deps'],
				],
				__METHOD__
			);
		}
	}

	public function remove( $type, $entities ) {
		// Avoid opening a primary DB connection when it's not needed.
		// ResourceLoader::saveModuleDependenciesInternal calls this method unconditionally
		// with empty values most of the time.
		if ( !$entities ) {
			return;
		}

		$dbw = $this->getPrimaryDb();
		$disjunctionConds = [];
		foreach ( (array)$entities as $entity ) {
			[ $module, $variant ] = $this->getEntityNameComponents( $entity );
			$disjunctionConds[] = $dbw->makeList(
				[ 'md_skin' => $variant, 'md_module' => $module ],
				$dbw::LIST_AND
			);
		}

		if ( $disjunctionConds ) {
			$dbw->delete(
				'module_deps',
				$dbw->makeList( $disjunctionConds, $dbw::LIST_OR ),
				__METHOD__
			);
		}
	}

	/**
	 * @param string[] $entities
	 * @param IDatabase $db
	 * @return string[]
	 */
	private function fetchDependencyBlobs( array $entities, IDatabase $db ) {
		$modulesByVariant = [];
		foreach ( $entities as $entity ) {
			[ $module, $variant ] = $this->getEntityNameComponents( $entity );
			$modulesByVariant[$variant][] = $module;
		}

		$disjunctionConds = [];
		foreach ( $modulesByVariant as $variant => $modules ) {
			$disjunctionConds[] = $db->makeList(
				[ 'md_skin' => $variant, 'md_module' => $modules ],
				$db::LIST_AND
			);
		}

		$depsBlobByEntity = [];

		if ( $disjunctionConds ) {
			$res = $db->newSelectQueryBuilder()
				->select( [ 'md_module', 'md_skin', 'md_deps' ] )
				->from( 'module_deps' )
				->where( $db->makeList( $disjunctionConds, $db::LIST_OR ) )
				->caller( __METHOD__ )->fetchResultSet();

			foreach ( $res as $row ) {
				$entity = "{$row->md_module}|{$row->md_skin}";
				$depsBlobByEntity[$entity] = $row->md_deps;
			}
		}

		return $depsBlobByEntity;
	}

	/**
	 * @return DBConnRef
	 */
	private function getReplicaDb() {
		return $this->lb
			->getConnectionRef( DB_REPLICA, [], false, ( $this->lb )::CONN_TRX_AUTOCOMMIT );
	}

	/**
	 * @return DBConnRef
	 */
	private function getPrimaryDb() {
		return $this->lb
			->getConnectionRef( DB_PRIMARY, [], false, ( $this->lb )::CONN_TRX_AUTOCOMMIT );
	}

	/**
	 * @param string $entity
	 * @return string[]
	 */
	private function getEntityNameComponents( $entity ) {
		$parts = explode( '|', $entity, 2 );
		if ( count( $parts ) !== 2 ) {
			throw new InvalidArgumentException( "Invalid module entity '$entity'" );
		}

		return $parts;
	}
}
