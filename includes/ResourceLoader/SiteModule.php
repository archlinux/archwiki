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
 * @author Trevor Parscal
 * @author Roan Kattouw
 */

namespace MediaWiki\ResourceLoader;

use MediaWiki\MainConfigNames;

/**
 * Module for site customizations.
 *
 * @ingroup ResourceLoader
 * @internal
 */
class SiteModule extends WikiModule {
	/** @var string[] What client platforms the module targets (e.g. desktop, mobile) */
	protected $targets = [ 'desktop', 'mobile' ];

	/**
	 * Get list of pages used by this module
	 *
	 * @param Context $context
	 * @return array[]
	 */
	protected function getPages( Context $context ) {
		$pages = [];
		if ( $this->getConfig()->get( MainConfigNames::UseSiteJs ) ) {
			$skin = $context->getSkin();
			$pages['MediaWiki:Common.js'] = [ 'type' => 'script' ];
			$pages['MediaWiki:' . ucfirst( $skin ) . '.js'] = [ 'type' => 'script' ];
			$this->getHookRunner()->onResourceLoaderSiteModulePages( $skin, $pages );
		}
		return $pages;
	}

	/**
	 * @param Context|null $context
	 * @return array
	 */
	public function getDependencies( Context $context = null ) {
		return [ 'site.styles' ];
	}
}

/** @deprecated since 1.39 */
class_alias( SiteModule::class, 'ResourceLoaderSiteModule' );
