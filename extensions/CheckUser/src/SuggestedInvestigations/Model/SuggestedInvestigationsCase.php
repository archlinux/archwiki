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

namespace MediaWiki\CheckUser\SuggestedInvestigations\Model;

/**
 * This class represents a Suggested Investigations case and its properties.
 */
class SuggestedInvestigationsCase {

	public function __construct(
		private readonly int $id,
		private readonly CaseStatus $status,
		private readonly string $reason = ''
	) {
	}

	public function getId(): int {
		return $this->id;
	}

	public function getStatus(): CaseStatus {
		return $this->status;
	}

	public function getReason(): string {
		return $this->reason;
	}
}
