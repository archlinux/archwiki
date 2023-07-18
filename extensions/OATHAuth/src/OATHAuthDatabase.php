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

use MediaWiki\Config\ServiceOptions;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\LBFactory;

/**
 * Helper class to access the OATHAuth database.
 *
 * @author Taavi Väänänen <hi@taavi.wtf>
 */
class OATHAuthDatabase {
	/** @internal Only public for service wiring use */
	public const CONSTRUCTOR_OPTIONS = [
		'OATHAuthDatabase',
	];

	/** @var ServiceOptions */
	private $options;

	/** @var LBFactory */
	private $lbFactory;

	/**
	 * @param ServiceOptions $options
	 * @param LBFactory $lbFactory
	 */
	public function __construct( ServiceOptions $options, LBFactory $lbFactory ) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;
		$this->lbFactory = $lbFactory;
	}

	/**
	 * @param int $index DB_PRIMARY/DB_REPLICA
	 * @return IDatabase
	 */
	public function getDB( int $index ): IDatabase {
		$db = $this->options->get( 'OATHAuthDatabase' );
		return $this->lbFactory->getMainLB( $db )->getConnectionRef( $index, [], $db );
	}
}
