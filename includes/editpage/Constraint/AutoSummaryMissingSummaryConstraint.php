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

use Content;
use StatusValue;

/**
 * For an edit to an existing page but not with a new section, do not allow the user to post with
 * a summary that matches the automatic summary if
 *   - the content has changed (to allow null edits without a summary, see T7365),
 *   - the new content is not a redirect (since redirecting a page has an informative automatic
 *       edit summary, see T9889), and
 *   - the user has not explicitly chosen to allow the automatic summary to be used
 *
 * For most edits, the automatic summary is blank, so checking against the automatic summary means
 * checking that any summary was given.
 *
 * @since 1.36
 * @internal
 * @author DannyS712
 */
class AutoSummaryMissingSummaryConstraint implements IEditConstraint {

	/** @var string */
	private $userSummary;

	/** @var string */
	private $autoSummary;

	/** @var bool */
	private $allowBlankSummary;

	/** @var Content */
	private $newContent;

	/** @var Content */
	private $originalContent;

	/** @var string|null */
	private $result;

	/**
	 * @param string $userSummary
	 * @param string $autoSummary
	 * @param bool $allowBlankSummary
	 * @param Content $newContent
	 * @param Content $originalContent
	 */
	public function __construct(
		string $userSummary,
		string $autoSummary,
		bool $allowBlankSummary,
		Content $newContent,
		Content $originalContent
	) {
		$this->userSummary = $userSummary;
		$this->autoSummary = $autoSummary;
		$this->allowBlankSummary = $allowBlankSummary;
		$this->newContent = $newContent;
		$this->originalContent = $originalContent;
	}

	public function checkConstraint(): string {
		if (
			!$this->allowBlankSummary &&
			!$this->newContent->equals( $this->originalContent ) &&
			!$this->newContent->isRedirect() &&
			md5( $this->userSummary ) == $this->autoSummary
		) {
			// TODO this was == in EditPage, can it be === ?
			$this->result = self::CONSTRAINT_FAILED;
		} else {
			$this->result = self::CONSTRAINT_PASSED;
		}
		return $this->result;
	}

	public function getLegacyStatus(): StatusValue {
		$statusValue = StatusValue::newGood();
		if ( $this->result === self::CONSTRAINT_FAILED ) {
			$statusValue->fatal( 'missingsummary' );
			$statusValue->value = self::AS_SUMMARY_NEEDED;
		}
		return $statusValue;
	}

}
