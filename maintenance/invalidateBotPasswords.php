<?php
/**
 * Invalidates the bot passwords of a given user
 *
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
 * @ingroup Maintenance
 */

use MediaWiki\User\BotPassword;

require_once __DIR__ . '/Maintenance.php';

/**
 * Maintenance script to invalidate the bot passwords of a given user.
 *
 * @ingroup Maintenance
 */
class InvalidateBotPasswords extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addOption( "user", "The username to operate on", false, true );
		$this->addOption( "userid", "The user id to operate on", false, true );
		$this->addDescription( "Invalidate a user's bot passwords" );
	}

	public function execute() {
		$user = $this->validateUserOption( "A \"user\" or \"userid\" must be set to invalidate the bot passwords for" );

		$res = BotPassword::invalidateAllPasswordsForUser( $user->getName() );

		if ( $res ) {
			$this->output( "Bot passwords invalidated for " . $user->getName() . "\n" );
		} else {
			$this->output( "No bot passwords invalidated for " . $user->getName() . "\n" );
		}
	}
}

$maintClass = InvalidateBotPasswords::class;
require_once RUN_MAINTENANCE_IF_MAIN;
