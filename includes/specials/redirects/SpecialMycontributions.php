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
 * @ingroup SpecialPage
 */

namespace MediaWiki\Specials\Redirects;

use MediaWiki\SpecialPage\RedirectSpecialPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\TempUser\TempUserConfig;

/**
 * Redirect to Special:Contributions for the current user's name or IP.
 *
 * @ingroup SpecialPage
 */
class SpecialMycontributions extends RedirectSpecialPage {

	private TempUserConfig $tempUserConfig;

	/**
	 * @param TempUserConfig $tempUserConfig
	 */
	public function __construct( TempUserConfig $tempUserConfig ) {
		parent::__construct( 'Mycontributions' );

		$this->tempUserConfig = $tempUserConfig;

		$this->mAllowedRedirectParams = [ 'limit', 'namespace', 'tagfilter',
			'offset', 'dir', 'year', 'month', 'feed', 'deletedOnly',
			'nsInvert', 'associated', 'newOnly', 'topOnly', 'start', 'end',
			'returnto' ];
	}

	/**
	 * @param string|null $subpage
	 * @return Title
	 */
	public function getRedirect( $subpage ) {
		// Redirect to login for anon users when temp accounts are enabled.
		if ( $this->tempUserConfig->isEnabled() && $this->getUser()->isAnon() ) {
			$this->requireLogin();
		}

		return SpecialPage::getTitleFor( 'Contributions', $this->getUser()->getName() );
	}

	/**
	 * Target identifies a specific User. See T109724.
	 *
	 * @since 1.27
	 * @return bool
	 */
	public function personallyIdentifiableTarget() {
		return true;
	}
}
/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.41
 */
class_alias( SpecialMycontributions::class, 'SpecialMycontributions' );
