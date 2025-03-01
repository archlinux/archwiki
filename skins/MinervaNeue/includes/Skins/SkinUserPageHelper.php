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

namespace MediaWiki\Minerva\Skins;

use MediaWiki\Context\IContextSource;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;

class SkinUserPageHelper {
	private UserFactory $userFactory;
	private UserNameUtils $userNameUtils;
	private IContextSource $context;
	private ?Title $title;
	private bool $fetchedData = false;
	private ?User $pageUser = null;

	public function __construct(
		UserFactory $userFactory,
		UserNameUtils $userNameUtils
	) {
		$this->userFactory = $userFactory;
		$this->userNameUtils = $userNameUtils;
	}

	public function setContext( IContextSource $context ): self {
		$this->context = $context;
		return $this;
	}

	public function setTitle( ?Title $title ): self {
		$this->title = $title;
		$this->fetchedData = false;
		$this->pageUser = null;
		return $this;
	}

	/**
	 * Fetch user data and store locally for performance improvement
	 */
	private function fetchData(): ?User {
		if ( !$this->fetchedData ) {
			if ( $this->title && $this->title->inNamespace( NS_USER ) && !$this->title->isSubpage() ) {
				$this->pageUser = $this->buildPageUserObject( $this->title );
			}
			$this->fetchedData = true;
		}
		return $this->pageUser;
	}

	/**
	 * Return new User object based on username or IP address.
	 */
	private function buildPageUserObject( Title $title ): ?User {
		$titleText = $title->getText();

		if ( $this->userNameUtils->isIP( $titleText ) ) {
			return $this->userFactory->newAnonymous( $titleText );
		}

		$user = $this->userFactory->newFromName( $titleText );
		if ( $user && $user->isRegistered() ) {
			return $user;
		}

		return null;
	}

	public function getPageUser(): ?User {
		return $this->fetchData();
	}

	public function isUserPage(): bool {
		return $this->fetchData() !== null;
	}

	public function isUserPageAccessibleToCurrentUser(): bool {
		$pageUser = $this->fetchData();
		$isHidden = $pageUser && $pageUser->isHidden();
		$canViewHidden = $this->context && $this->context->getAuthority()->isAllowed( 'hideuser' );
		return !$isHidden || $canViewHidden;
	}
}
