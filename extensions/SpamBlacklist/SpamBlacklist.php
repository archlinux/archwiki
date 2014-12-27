<?php

# Loader for spam blacklist feature
# Include this from LocalSettings.php

if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

$wgExtensionCredits['antispam'][] = array(
	'path'           => __FILE__,
	'name'           => 'SpamBlacklist',
	'author'         => array( 'Tim Starling', 'John Du Hart', 'Daniel Kinzler' ),
	'url'            => 'https://www.mediawiki.org/wiki/Extension:SpamBlacklist',
	'descriptionmsg' => 'spam-blacklist-desc',
);

$dir = __DIR__ . '/';
$wgMessagesDirs['SpamBlackList'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['SpamBlackList'] = $dir . 'SpamBlacklist.i18n.php';

// Register the API method
$wgAutoloadClasses['ApiSpamBlacklist'] = "$dir/api/ApiSpamBlacklist.php";
$wgAPIModules['spamblacklist'] = 'ApiSpamBlacklist';

/**
 * Array of settings for blacklist classes
 */
$wgBlacklistSettings = array(
	'spam' => array(
		'files' => array( "https://meta.wikimedia.org/w/index.php?title=Spam_blacklist&action=raw&sb_ver=1" )
	)
);

/**
 * Log blacklist hits to Special:Log
 */
$wgLogSpamBlacklistHits = false;

/**
 * @deprecated
 */
$wgSpamBlacklistFiles =& $wgBlacklistSettings['spam']['files'];

/**
 * @deprecated
 */
$wgSpamBlacklistSettings =& $wgBlacklistSettings['spam'];

if ( !defined( 'MW_SUPPORTS_CONTENTHANDLER' ) ) {
	die( "This version of SpamBlacklist requires a version of MediaWiki that supports the ContentHandler facility (supported since MW 1.21)." );
}

// filter pages on save
$wgHooks['EditFilterMergedContent'][] = 'SpamBlacklistHooks::filterMergedContent';
$wgHooks['APIEditBeforeSave'][] = 'SpamBlacklistHooks::filterAPIEditBeforeSave';

// editing filter rules
$wgHooks['EditFilter'][] = 'SpamBlacklistHooks::validate';
$wgHooks['PageContentSaveComplete'][] = 'SpamBlacklistHooks::pageSaveContent';

// email filters
$wgHooks['UserCanSendEmail'][] = 'SpamBlacklistHooks::userCanSendEmail';
$wgHooks['AbortNewAccount'][] = 'SpamBlacklistHooks::abortNewAccount';

$wgAutoloadClasses['BaseBlacklist'] = $dir . 'BaseBlacklist.php';
$wgAutoloadClasses['EmailBlacklist'] = $dir . 'EmailBlacklist.php';
$wgAutoloadClasses['SpamBlacklistHooks'] = $dir . 'SpamBlacklistHooks.php';
$wgAutoloadClasses['SpamBlacklist'] = $dir . 'SpamBlacklist_body.php';
$wgAutoloadClasses['SpamRegexBatch'] = $dir . 'SpamRegexBatch.php';

$wgLogTypes[] = 'spamblacklist';
$wgLogActionsHandlers['spamblacklist/*'] = 'LogFormatter';
$wgLogRestrictions['spamblacklist'] = 'spamblacklistlog';
$wgGroupPermissions['sysop']['spamblacklistlog'] = true;

$wgAvailableRights[] = 'spamblacklistlog';
