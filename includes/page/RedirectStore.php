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
 * @author Derick Alangi
 */

namespace MediaWiki\Page;

use MediaWiki\Linker\LinkTarget;

/**
 * Service for storing and retreiving page redirect information.
 *
 * @note This is not yet ready. https://phabricator.wikimedia.org/T290639
 * To manage redirects today, use WikiPage via WikiPageFactory instead.
 *
 * @unstable
 * @since 1.38
 */
class RedirectStore implements RedirectLookup {
	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/**
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct( WikiPageFactory $wikiPageFactory ) {
		$this->wikiPageFactory = $wikiPageFactory;
	}

	public function getRedirectTarget( PageIdentity $page ): ?LinkTarget {
		return $this->wikiPageFactory->newFromTitle( $page )->getRedirectTarget();
	}
}
