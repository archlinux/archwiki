<?php

namespace MediaWiki\Extension\AbuseFilter\ChangeTags;

use MediaWiki\Extension\AbuseFilter\ActionSpecifier;
use MediaWiki\Extension\AbuseFilter\ServiceNames;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentityValue;

/**
 * Class that collects change tags to be later applied
 * @internal This interface should be improved and is not ready for external use
 */
class ChangeTagger {
	public const SERVICE_NAME = ServiceNames::ChangeTagger;

	/** @var array<string,string[]> (Persistent) map of (action ID => string[]) */
	private static $tagsToSet = [];

	public function __construct( private readonly ChangeTagsManager $changeTagsManager ) {
	}

	/**
	 * Clear any buffered tag
	 */
	public function clearBuffer(): void {
		self::$tagsToSet = [];
	}

	public function addConditionsLimitTag( ActionSpecifier $specifier ): void {
		$this->addTags( $specifier, [ $this->changeTagsManager->getCondsLimitTag() ] );
	}

	public function addTags( ActionSpecifier $specifier, array $tags ): void {
		$id = $this->getActionID( $specifier );
		$this->bufferTagsToSetByAction( [ $id => $tags ] );
	}

	/**
	 * @param string[][] $tagsByAction Map of (string => string[])
	 */
	private function bufferTagsToSetByAction( array $tagsByAction ): void {
		foreach ( $tagsByAction as $actionID => $tags ) {
			self::$tagsToSet[ $actionID ] = array_unique(
				array_merge( self::$tagsToSet[ $actionID ] ?? [], $tags )
			);
		}
	}

	/**
	 * @param string $id
	 * @param bool $clear
	 * @return string[]
	 */
	private function getTagsForID( string $id, bool $clear = true ): array {
		$val = self::$tagsToSet[$id] ?? [];
		if ( $clear ) {
			unset( self::$tagsToSet[$id] );
		}
		return $val;
	}

	/**
	 * @param RecentChange $recentChange
	 * @param bool $clear
	 * @return string[]
	 */
	public function getTagsForRecentChange( RecentChange $recentChange, bool $clear = true ): array {
		$id = $this->getIDFromRecentChange( $recentChange );
		return $this->getTagsForID( $id, $clear );
	}

	private function getIDFromRecentChange( RecentChange $recentChange ): string {
		$title = new TitleValue(
			$recentChange->getAttribute( 'rc_namespace' ),
			$recentChange->getAttribute( 'rc_title' )
		);

		$logType = $recentChange->getAttribute( 'rc_log_type' ) ?: 'edit';
		if ( $logType === 'newusers' ) {
			// XXX: as of 1.43, the following is never true
			$action = $recentChange->getAttribute( 'rc_log_action' ) === 'autocreate' ?
				'autocreateaccount' :
				'createaccount';
		} else {
			$action = $logType;
		}
		$user = new UserIdentityValue(
			$recentChange->getAttribute( 'rc_user' ),
			$recentChange->getAttribute( 'rc_user_text' )
		);
		$specifier = new ActionSpecifier(
			$action,
			$title,
			$user,
			$recentChange->getAttribute( 'rc_ip' ) ?? '',
			$user->getName()
		);
		return $this->getActionID( $specifier );
	}

	/**
	 * Get a unique identifier for the given action
	 *
	 * @param ActionSpecifier $specifier
	 * @return string
	 */
	private function getActionID( ActionSpecifier $specifier ): string {
		$username = $specifier->getUser()->getName();
		$title = $specifier->getTitle();
		if ( str_contains( $specifier->getAction(), 'createaccount' ) ) {
			// TODO Move this to ActionSpecifier?
			$username = $specifier->getAccountName();
			if ( $username === null ) {
				throw new \UnexpectedValueException(
					'Expected string from ActionSpecifier::getAccountName()'
				);
			}
			$title = new TitleValue( NS_USER, $username );
		}

		// Use a character that's not allowed in titles and usernames
		$glue = '|';
		return implode(
			$glue,
			[
				$title->getNamespace() . ':' . $title->getText(),
				$username,
				$specifier->getAction()
			]
		);
	}
}
