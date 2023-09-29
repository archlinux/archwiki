<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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

/**
 * Job that initializes an user's edit count.
 *
 * This is used by UserEditTracker when a user's editcount isn't yet set.
 *
 * The following job parameters are required:
 *   - userId: the user ID
 *   - editCount: new edit count to set
 *
 * @internal For use by \MediaWiki\User\UserEditTracker
 * @since 1.36
 */
class UserEditCountInitJob extends Job implements GenericParameterJob {

	public function __construct( array $params ) {
		parent::__construct( 'userEditCountInit', $params );
		$this->removeDuplicates = true;
	}

	public function run() {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbw = $lb->getConnectionRef( DB_PRIMARY );

		$dbw->update(
			'user',
			// SET
			[ 'user_editcount' => $this->params['editCount'] ],
			// WHERE
			[
				'user_id' => $this->params['userId'],
				'user_editcount IS NULL OR user_editcount < ' . $dbw->addQuotes( $this->params['editCount'] )
			],
			__METHOD__
		);

		return true;
	}
}
