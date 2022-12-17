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

use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * A foreign repository with a MediaWiki database accessible via the configured LBFactory.
 *
 * @ingroup FileRepo
 */
class ForeignDBViaLBRepo extends LocalRepo {
	/** @var array */
	protected $fileFactory = [ ForeignDBFile::class, 'newFromTitle' ];

	/** @var array */
	protected $fileFromRowFactory = [ ForeignDBFile::class, 'newFromRow' ];

	/**
	 * @param array|null $info
	 */
	public function __construct( $info ) {
		parent::__construct( $info );
		'@phan-var array $info';
		$this->dbDomain = $info['wiki'];
		$this->hasAccessibleSharedCache = $info['hasSharedCache'];
	}

	public function getPrimaryDB() {
		return $this->getDBLoadBalancer()->getConnectionRef( DB_PRIMARY, [], $this->dbDomain );
	}

	public function getMasterDB() {
		wfDeprecated( __METHOD__, '1.37' );
		return $this->getPrimaryDB();
	}

	public function getReplicaDB() {
		return $this->getDBLoadBalancer()->getConnectionRef( DB_REPLICA, [], $this->dbDomain );
	}

	/**
	 * @return Closure
	 */
	protected function getDBFactory() {
		return function ( $index ) {
			return $this->getDBLoadBalancer()->getConnectionRef( $index, [], $this->dbDomain );
		};
	}

	/**
	 * @return ILoadBalancer
	 */
	protected function getDBLoadBalancer() {
		$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

		return $lbFactory->getMainLB( $this->dbDomain );
	}

	protected function assertWritableRepo() {
		throw new MWException( static::class . ': write operations are not supported.' );
	}

	public function getInfo() {
		return FileRepo::getInfo();
	}
}
