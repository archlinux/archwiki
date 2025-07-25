<?php

namespace MediaWiki\CheckUser;

use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\MediaWikiServices;
use MediaWiki\RecentChanges\RecentChange;

class Hooks {

	/**
	 * Hook function for RecentChange_save. Saves data about the RecentChange object, along with private user data
	 * (such as their IP address and user agent string) from the main request, in the CheckUser result tables
	 * so that it can be queried by a CheckUser if they run a check.
	 *
	 * Note that other extensions (like AbuseFilter) may call this function directly
	 * if they want to send data to CU without creating a recentchanges entry
	 *
	 * @param RecentChange $rc
	 * @deprecated since 1.43. Use CheckUserInsert::updateCheckUserData instead.
	 */
	public static function updateCheckUserData( RecentChange $rc ) {
		/** @var CheckUserInsert $checkUserInsert */
		$checkUserInsert = MediaWikiServices::getInstance()->get( 'CheckUserInsert' );
		$checkUserInsert->updateCheckUserData( $rc );
	}
}
