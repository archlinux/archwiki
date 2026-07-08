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

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Model;

/**
 * Lists the different statuses a SuggestedInvestigations case can be
 * in as a typed enum associating the status names with the integer
 * value used when storing the case in the database.
 */
enum CaseStatus: int {
	case Open = 0;
	case Resolved = 1;
	case Invalid = 2;

	/**
	 * Given a string representation of the name of a status, return the associated
	 * {@link CaseStatus} enum value or null if it did not match.
	 */
	public static function newFromStringName( string $status ): ?CaseStatus {
		return match ( strtolower( $status ) ) {
			'open' => CaseStatus::Open,
			'invalid' => CaseStatus::Invalid,
			'resolved', 'closed' => CaseStatus::Resolved,
			default => null,
		};
	}
}

// @codeCoverageIgnoreStart
/**
 * @deprecated since 1.46
 */
class_alias(
	CaseStatus::class,
	'MediaWiki\\CheckUser\\SuggestedInvestigations\\Model\\CaseStatus'
);
// @codeCoverageIgnoreEnd
