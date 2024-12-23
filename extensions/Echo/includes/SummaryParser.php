<?php

namespace MediaWiki\Extension\Notifications;

use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

class SummaryParser {
	/** @var callable */
	private $userLookup;

	/**
	 * @param callable|null $userLookup An alternative filter function that receives a User object
	 *  (see the caller in {@see parse}) and returns the user id or 0 if the user doesn't exist.
	 *  Only tests should modify this.
	 */
	public function __construct( ?callable $userLookup = null ) {
		$this->userLookup = $userLookup ?? static fn ( UserIdentity $user ) => $user->getId();
	}

	/**
	 * Returns a list of registered users linked in an edit summary
	 *
	 * @param string $summary
	 * @return User[] Array of username => User object
	 */
	public function parse( $summary ) {
		// Remove section autocomments. Replace with characters that can't be in titles,
		// to prevent fun stuff like "[[foo /* section */ bar]]".
		$summary = preg_replace( '#/\*.*?\*/#', ' [] ', $summary );

		$users = [];
		$regex = '/\[\[([' . Title::legalChars() . ']++)(?:\|.*?)?\]\]/';
		if ( preg_match_all( $regex, $summary, $matches ) ) {
			foreach ( $matches[1] as $match ) {
				if ( $match[0] === ':' ) {
					continue;
				}

				$title = Title::newFromText( $match );
				if ( $title
					 && $title->isLocal()
					 && $title->getNamespace() === NS_USER
				) {
					$user = User::newFromName( $title->getText() );
					if ( $user && ( $this->userLookup )( $user ) > 0 ) {
						$users[$user->getName()] = $user;
					}
				}
			}
		}

		return $users;
	}
}
