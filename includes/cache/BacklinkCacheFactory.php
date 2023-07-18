<?php
/**
 * Remember the page that was previously loaded.
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
 * @ingroup Cache
 */

namespace MediaWiki\Cache;

use BacklinkCache;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Page\PageReference;
use WANObjectCache;

/**
 * @since 1.37
 */
class BacklinkCacheFactory {
	/** @var BacklinkCache */
	private $latestBacklinkCache;

	/** @var WANObjectCache */
	private $wanCache;

	/** @var HookContainer */
	private $hookContainer;

	/**
	 * @param WANObjectCache $wanCache
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		WANObjectCache $wanCache,
		HookContainer $hookContainer
	) {
		$this->wanCache = $wanCache;
		$this->hookContainer = $hookContainer;
	}

	/**
	 * Returns a BacklinkCache for $page. May re-use previously
	 * created instances.
	 *
	 * Currently, only one cache instance can exist; callers that
	 * need multiple backlink cache objects should keep them in scope.
	 *
	 * @param PageReference $page Page to get a backlink cache for
	 * @return BacklinkCache
	 */
	public function getBacklinkCache( PageReference $page ): BacklinkCache {
		if ( !$this->latestBacklinkCache || !$this->latestBacklinkCache->getPage()->isSamePageAs( $page ) ) {
			$this->latestBacklinkCache = new BacklinkCache(
				$this->wanCache,
				$this->hookContainer,
				$page
			);
		}
		return $this->latestBacklinkCache;
	}
}
