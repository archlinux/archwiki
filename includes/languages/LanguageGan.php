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

/**
 * Gan Chinese
 *
 * This handles both Traditional and Simplified Chinese.
 * Right now, we only distinguish `gan_hans` and `gan_hant`.
 *
 * @ingroup Languages
 */
class LanguageGan extends LanguageZh {
	/**
	 * word segmentation
	 *
	 * @param string $string
	 * @param string $autoVariant
	 * @return string
	 */
	public function normalizeForSearch( $string, $autoVariant = 'gan-hans' ) {
		// LanguageZh::normalizeForSearch
		return parent::normalizeForSearch( $string, $autoVariant );
	}
}
