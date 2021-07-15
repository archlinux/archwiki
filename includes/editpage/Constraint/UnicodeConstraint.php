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
 * Verify unicode constraint
 *
 * @since 1.36
 * @internal
 */
class UnicodeConstraint implements IEditConstraint {

	/**
	 * @var string
	 * Correct unicode
	 */
	public const VALID_UNICODE = 'ℳ𝒲♥𝓊𝓃𝒾𝒸ℴ𝒹ℯ';

	/**
	 * @var string
	 * Unicode string provided, to compare
	 */
	private $input;

	/**
	 * @param string $inputUnicode
	 */
	public function __construct( string $inputUnicode ) {
		$this->input = $inputUnicode;
	}

	public function checkConstraint() : string {
		if ( $this->input === self::VALID_UNICODE ) {
			return self::CONSTRAINT_PASSED;
		}
		return self::CONSTRAINT_FAILED;
	}

	public function getLegacyStatus() : StatusValue {
		$statusValue = StatusValue::newGood();
		if ( $this->input !== self::VALID_UNICODE ) {
			$statusValue->fatal( 'unicode-support-fail' );
			$statusValue->value = self::AS_UNICODE_NOT_SUPPORTED;
		}
		return $statusValue;
	}

}
