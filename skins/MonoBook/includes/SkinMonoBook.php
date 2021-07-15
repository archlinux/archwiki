<?php
/**
 * MonoBook nouveau.
 *
 * Translated from gwicke's previous TAL template version to remove
 * dependency on PHPTAL.
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
 * @ingroup Skins
 */

/**
 * Inherit main code from SkinTemplate, set the CSS and template filter.
 * @ingroup Skins
 */
class SkinMonoBook extends SkinTemplate {
	/** Using MonoBook. */
	public $skinname = 'monobook';
	public $stylename = 'MonoBook';
	public $template = 'MonoBookTemplate';

	/**
	 * @inheritDoc
	 * @return bool
	 */
	public function isResponsive() {
		return $this->getUser()->getOption( 'monobook-responsive' );
	}

	/**
	 * @inheritDoc
	 * @param OutputPage $out
	 */
	public function initPage( OutputPage $out ) {
		parent::initPage( $out );

		if ( $this->isResponsive() ) {
			$styleModule = 'skins.monobook.responsive';
			$out->addModules( [
				'skins.monobook.mobile'
			] );
		} else {
			$styleModule = 'skins.monobook.styles';
		}

		$out->addModuleStyles( [
			$styleModule
		] );
	}

	/**
	 * @param User $user
	 * @param array &$preferences
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		$preferences['monobook-responsive'] = [
			'type' => 'toggle',
			'label-message' => 'monobook-responsive-label',
			'section' => 'rendering/skin/skin-prefs',
			// Only show this section when the Monobook skin is checked. The JavaScript client also uses
			// this state to determine whether to show or hide the whole section.
			'hide-if' => [ '!==', 'wpskin', 'monobook' ],
		];
	}
}
