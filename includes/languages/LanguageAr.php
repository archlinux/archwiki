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
 * @author Niklas Laxström
 */

/**
 * Arabic (العربية) specific code.
 *
 * @ingroup Languages
 */
class LanguageAr extends Language {

	/**
	 * Replace Arabic presentation forms with their standard equivalents (T11413).
	 *
	 * Optimization: This is language-specific to reduce negative performance impact.
	 *
	 * @param string $s
	 * @return string
	 */
	public function normalize( $s ) {
		$s = parent::normalize( $s );
		$s = $this->transformUsingPairFile( MediaWiki\Languages\Data\NormalizeAr::class, $s );
		return $s;
	}
}
