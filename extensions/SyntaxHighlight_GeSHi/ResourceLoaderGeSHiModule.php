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
 */

class ResourceLoaderGeSHiModule extends ResourceLoaderModule {

	/**
	 * @var string
	 */
	protected $lang;

	/**
	 * @param array $info Module definition.
	 */
	function __construct( $info ) {
		$this->lang = $info['lang'];
	}

	/**
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	public function getStyles( ResourceLoaderContext $context ) {
		$geshi = SyntaxHighlight_GeSHi::prepare( '', $this->lang );
		if ( !$geshi->error ) {
			$css = SyntaxHighlight_GeSHi::getCSS( $geshi );
		} else {
			$css = ResourceLoader::makeComment( $geshi->error() );
		}

		return array( 'all' => $css );
	}

	/**
	 * @param ResourceLoaderContext $context
	 * @return int
	 */
	public function getModifiedTime( ResourceLoaderContext $context ) {
		static $selfmtime = null;
		if ( $selfmtime === null ) {
			// Cache this since there are 100s of instances of this module
			// See also T93025, T85794.
			$selfmtime = self::safeFilemtime( __FILE__ );
		}

		return max( array(
			$this->getDefinitionMtime( $context ),
			$selfmtime,
			self::safeFilemtime( GESHI_LANG_ROOT . "/{$this->lang}.php" ),
		) );
	}

	/**
	 * @param $context ResourceLoaderContext
	 * @return array
	 */
	public function getDefinitionSummary( ResourceLoaderContext $context ) {
		return array(
			'class' => get_class( $this ),
			'lang' => $this->lang,
			'geshi' => GESHI_VERSION,
		);
	}
}
