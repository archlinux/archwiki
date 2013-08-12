<?php
if ( !defined( 'MEDIAWIKI' ) ) die();
/**
 * A Special Page extension to rename users, runnable by users with renameuser
 * rights
 *
 * @file
 * @ingroup Extensions
 * @author Ævar Arnfjörð Bjarmason <avarab@gmail.com>
 * @copyright Copyright © 2005, Ævar Arnfjörð Bjarmason
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

$wgAvailableRights[] = 'renameuser';
$wgGroupPermissions['bureaucrat']['renameuser'] = true;

$wgExtensionCredits['specialpage'][] = array(
	'path' => __FILE__,
	'name' => 'Renameuser',
	'author'         => array( 'Ævar Arnfjörð Bjarmason', 'Aaron Schulz' ),
	'url'            => 'https://www.mediawiki.org/wiki/Extension:Renameuser',
	'descriptionmsg' => 'renameuser-desc',
);

# Internationalisation files
$wgExtensionMessagesFiles['Renameuser'] = __DIR__ . '/Renameuser.i18n.php';
$wgExtensionMessagesFiles['RenameuserAliases'] = __DIR__ . '/Renameuser.alias.php';

/**
 * Users with more than this number of edits will have their rename operation
 * deferred via the job queue.
 */
define( 'RENAMEUSER_CONTRIBJOB', 5000 );

# Add a new log type
$wgLogTypes[] = 'renameuser';
$wgLogActionsHandlers['renameuser/renameuser'] = 'RenameuserLogFormatter';

$wgAutoloadClasses['RenameuserHooks'] = __DIR__ . '/Renameuser.hooks.php';
$wgAutoloadClasses['RenameUserJob'] = __DIR__ . '/RenameUserJob.php';
$wgAutoloadClasses['RenameuserLogFormatter'] = __DIR__ . '/RenameuserLogFormatter.php';
$wgAutoloadClasses['RenameuserSQL'] = __DIR__ . '/RenameuserSQL.php';
$wgAutoloadClasses['SpecialRenameuser'] = __DIR__ . '/specials/SpecialRenameuser.php';

$wgSpecialPages['Renameuser'] = 'SpecialRenameuser';
$wgSpecialPageGroups['Renameuser'] = 'users';
$wgJobClasses['renameUser'] = 'RenameUserJob';

$wgHooks['ShowMissingArticle'][] = 'RenameuserHooks::onShowMissingArticle';
$wgHooks['ContributionsToolLinks'][] = 'RenameuserHooks::onContributionsToolLinks';

