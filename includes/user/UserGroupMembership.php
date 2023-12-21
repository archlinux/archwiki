<?php
/**
 * Represents the membership of a user to a user group.
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
 */

namespace MediaWiki\User;

use IContextSource;
use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Message;
use RequestContext;

/**
 * Represents a "user group membership" -- a specific instance of a user belonging
 * to a group. For example, the fact that user Mary belongs to the sysop group is a
 * user group membership.
 *
 * The class is a pure value object. Use UserGroupManager to modify user group memberships.
 *
 * @since 1.29
 */
class UserGroupMembership {

	/** @var int The ID of the user who belongs to the group */
	private $userId;

	/** @var string */
	private $group;

	/** @var string|null Timestamp of expiry in TS_MW format, or null if no expiry */
	private $expiry;

	/** @var bool Expiration flag */
	private $expired;

	/**
	 * @param int $userId The ID of the user who belongs to the group
	 * @param string|null $group The internal group name
	 * @param string|null $expiry Timestamp of expiry in TS_MW format, or null if no expiry
	 */
	public function __construct( int $userId = 0, ?string $group = null, ?string $expiry = null ) {
		$this->userId = $userId;
		$this->group = $group;
		$this->expiry = $expiry ?: null;
		$this->expired = $expiry && wfTimestampNow() > $expiry;
	}

	/**
	 * @return int
	 */
	public function getUserId() {
		return $this->userId;
	}

	/**
	 * @return string
	 */
	public function getGroup() {
		return $this->group;
	}

	/**
	 * @return string|null Timestamp of expiry in TS_MW format, or null if no expiry
	 */
	public function getExpiry() {
		return $this->expiry;
	}

	/**
	 * Has the membership expired?
	 *
	 * @return bool
	 */
	public function isExpired() {
		return $this->expired;
	}

	/**
	 * Gets a link for a user group, possibly including the expiry date if relevant.
	 *
	 * @deprecated since 1.41 use getLinkWiki or getLinkHTML directly
	 *
	 * @param string|UserGroupMembership $ugm Either a group name as a string, or
	 *   a UserGroupMembership object
	 * @param IContextSource $context
	 * @param string $format Either 'wiki' or 'html'
	 * @param string|null $userName If you want to use the group member message
	 *   ("administrator"), pass the name of the user who belongs to the group; it
	 *   is used for GENDER of the group member message. If you instead want the
	 *   group name message ("Administrators"), omit this parameter.
	 * @return string
	 */
	public static function getLink( $ugm, IContextSource $context, string $format, $userName = null ) {
		switch ( $format ) {
			case 'wiki':
				return self::getLinkWiki( $ugm, $context, $userName );
			case 'html':
				return self::getLinkHTML( $ugm, $context, $userName );
			default:
				throw new InvalidArgumentException( 'UserGroupMembership::getLink() $format parameter should be ' .
					"'wiki' or 'html'" );
		}
	}

	/**
	 * Gets a link for a user group, possibly including the expiry date if relevant.
	 * @since 1.41
	 *
	 * @param string|UserGroupMembership $ugm Either a group name as a string, or
	 *   a UserGroupMembership object
	 * @param IContextSource $context
	 * @param string|null $userName If you want to use the group member message
	 *   ("administrator"), pass the name of the user who belongs to the group; it
	 *   is used for GENDER of the group member message. If you instead want the
	 *   group name message ("Administrators"), omit this parameter.
	 * @return string
	 */
	public static function getLinkHTML( $ugm, IContextSource $context, $userName = null ): string {
		[
			'expiry' => $expiry,
			'linkTitle' => $linkTitle,
			'groupName' => $groupName
		] = self::getLinkInfo( $ugm, $context, $userName );

		// link to the group description page, if it exists
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
		if ( $linkTitle ) {
			$groupLink = $linkRenderer->makeLink( $linkTitle, $groupName );
		} else {
			$groupLink = htmlspecialchars( $groupName );
		}

		if ( $expiry ) {
			[
				'expiryDT' => $expiryDT,
				'expiryD' => $expiryD,
				'expiryT' => $expiryT
			] = self::getLinkExpiryParams( $context, $expiry );
			$groupLink = Message::rawParam( $groupLink );
			return $context->msg( 'group-membership-link-with-expiry' )
				->params( $groupLink, $expiryDT, $expiryD, $expiryT )->escaped();
		}
		return $groupLink;
	}

	/**
	 * Gets a link for a user group, possibly including the expiry date if relevant.
	 * @since 1.41
	 *
	 * @param string|UserGroupMembership $ugm Either a group name as a string, or
	 *   a UserGroupMembership object
	 * @param IContextSource $context
	 * @param string|null $userName If you want to use the group member message
	 *   ("administrator"), pass the name of the user who belongs to the group; it
	 *   is used for GENDER of the group member message. If you instead want the
	 *   group name message ("Administrators"), omit this parameter.
	 * @return string
	 */
	public static function getLinkWiki( $ugm, IContextSource $context, $userName = null ): string {
		[
			'expiry' => $expiry,
			'linkTitle' => $linkTitle,
			'groupName' => $groupName
		] = self::getLinkInfo( $ugm, $context, $userName );

		// link to the group description page, if it exists
		if ( $linkTitle ) {
			$linkPage = $linkTitle->getFullText();
			$groupLink = "[[$linkPage|$groupName]]";
		} else {
			$groupLink = $groupName;
		}

		if ( $expiry ) {
			[
				'expiryDT' => $expiryDT,
				'expiryD' => $expiryD,
				'expiryT' => $expiryT
			] = self::getLinkExpiryParams( $context, $expiry );
			return $context->msg( 'group-membership-link-with-expiry' )
				->params( $groupLink, $expiryDT, $expiryD, $expiryT )->text();
		}
		return $groupLink;
	}

	/**
	 * @param self|string $ugm
	 * @param IContextSource $context
	 * @param string|null $userName
	 * @return array
	 */
	private static function getLinkInfo( $ugm, $context, $userName = null ): array {
		if ( $ugm instanceof UserGroupMembership ) {
			$expiry = $ugm->getExpiry();
			$group = $ugm->getGroup();
		} else {
			$expiry = null;
			$group = $ugm;
		}

		$uiLanguage = $context->getLanguage();
		if ( $userName !== null ) {
			$groupName = $uiLanguage->getGroupMemberName( $group, $userName );
		} else {
			$groupName = $uiLanguage->getGroupName( $group );
		}
		$linkTitle = self::getGroupPage( $group );
		return [ 'expiry' => $expiry, 'linkTitle' => $linkTitle, 'groupName' => $groupName ];
	}

	/**
	 * @param IContextSource $context
	 * @param string $expiry
	 * @return array
	 */
	private static function getLinkExpiryParams( IContextSource $context, string $expiry ): array {
		// format the expiry to a nice string
		$uiLanguage = $context->getLanguage();
		$uiUser = $context->getUser();
		$expiryDT = $uiLanguage->userTimeAndDate( $expiry, $uiUser );
		$expiryD = $uiLanguage->userDate( $expiry, $uiUser );
		$expiryT = $uiLanguage->userTime( $expiry, $uiUser );
		return [ 'expiryDT' => $expiryDT, 'expiryD' => $expiryD, 'expiryT' => $expiryT ];
	}

	/**
	 * Gets the localized friendly name for a group, if it exists. For example,
	 * "Administrators" or "Bureaucrats"
	 *
	 * @param string $group Internal group name
	 * @return string Localized friendly group name
	 * @deprecated since 1.38, use Language::getGroupName or Message::userGroupParams, hard-deprecated in 1.41
	 */
	public static function getGroupName( $group ) {
		wfDeprecated( __METHOD__, '1.41' );
		return RequestContext::getMain()->getLanguage()->getGroupName( $group );
	}

	/**
	 * Gets the localized name for a member of a group, if it exists. For example,
	 * "administrator" or "bureaucrat"
	 *
	 * @param string $group Internal group name
	 * @param string|UserIdentity $member Username or UserIdentity of member for gender
	 * @return string Localized name for group member
	 * @deprecated since 1.40, use Language::getGroupMemberName or
	 *   Message::objectParm with instance of UserGroupMembershipParam, hard-deprecated in 1.41
	 */
	public static function getGroupMemberName( $group, $member ) {
		wfDeprecated( __METHOD__, '1.41' );
		return RequestContext::getMain()->getLanguage()->getGroupMemberName( $group, $member );
	}

	/**
	 * Gets the title of a page describing a particular user group. When the name
	 * of the group appears in the UI, it can link to this page.
	 *
	 * @param string $group Internal group name
	 * @return Title|false Title of the page if it exists, false otherwise
	 */
	public static function getGroupPage( $group ) {
		$msg = wfMessage( "grouppage-$group" )->inContentLanguage();
		if ( $msg->exists() ) {
			$title = Title::newFromText( $msg->text() );
			if ( is_object( $title ) ) {
				return $title;
			}
		}
		return false;
	}

	/**
	 * Compares two pure value objects
	 *
	 * @param UserGroupMembership $ugm
	 * @return bool
	 *
	 * @since 1.35
	 */
	public function equals( UserGroupMembership $ugm ) {
		return (
			$ugm->getUserId() === $this->userId
			&& $ugm->getGroup() === $this->group
		);
	}

}

/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.41
 */
class_alias( UserGroupMembership::class, 'UserGroupMembership' );
