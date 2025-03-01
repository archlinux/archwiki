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

namespace MediaWiki\EditPage\Constraint;

use StatusValue;

/**
 * Do not allow the user to post an empty comment (only used for new section)
 *
 * @since 1.36
 * @internal
 * @author DannyS712
 */
class MissingCommentConstraint implements IEditConstraint {

	private string $section;
	private string $userComment;

	/**
	 * @param string $section
	 * @param string $userComment
	 */
	public function __construct( string $section, string $userComment ) {
		$this->section = $section;
		$this->userComment = $userComment;
	}

	public function checkConstraint(): string {
		if ( $this->section === 'new' && $this->userComment === '' ) {
			return self::CONSTRAINT_FAILED;
		}
		return self::CONSTRAINT_PASSED;
	}

	public function getLegacyStatus(): StatusValue {
		$statusValue = StatusValue::newGood();
		if ( $this->section === 'new' && $this->userComment === '' ) {
			$statusValue->fatal( 'missingcommenttext' );
			$statusValue->value = self::AS_TEXTBOX_EMPTY;
		}
		return $statusValue;
	}

}
