<?php
/**
 * We want to make this whole thing as seamless as possible to the
 * end-user. Unfortunately, we can't do _all_ of the work in the class
 * because A) included files are not in global scope, but in the scope
 * of their caller, and B) MediaWiki has way too many globals. So instead
 * we'll kinda fake it, and do the requires() inline. <3 PHP
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
 * @ingroup Maintenance
 */
use MediaWiki\MediaWikiServices;
use MediaWiki\Settings\SettingsBuilder;

if ( !defined( 'RUN_MAINTENANCE_IF_MAIN' ) ) {
	echo "This file must be included after Maintenance.php\n";
	exit( 1 );
}

// Wasn't included from the file scope, halt execution (probably wanted the class)
// If a class is using CommandLineInc (old school maintenance), they definitely
// cannot be included and will proceed with execution
// @phan-suppress-next-line PhanSuspiciousValueComparisonInGlobalScope
if ( !Maintenance::shouldExecute() && $maintClass != CommandLineInc::class ) {
	return;
}

// @phan-suppress-next-line PhanImpossibleConditionInGlobalScope
if ( !$maintClass || !class_exists( $maintClass ) ) {
	echo "\$maintClass is not set or is set to a non-existent class.\n";
	exit( 1 );
}

// Define the MediaWiki entrypoint
define( 'MEDIAWIKI', true );

// This environment variable is ensured present by Maintenance.php.
$IP = getenv( 'MW_INSTALL_PATH' );

// Get an object to start us off
/** @var Maintenance $maintenance */
$maintenance = new $maintClass();

// Basic checks and such
$maintenance->setup();

// We used to call this variable $self, but it was moved
// to $maintenance->mSelf. Keep that here for b/c
$self = $maintenance->getName();

// Define how settings are loaded (e.g. LocalSettings.php)
if ( !defined( 'MW_CONFIG_CALLBACK' ) ) {
	$maintenance->loadSettings();
}

// Custom setup for Maintenance entry point
if ( !defined( 'MW_SETUP_CALLBACK' ) ) {

	function wfMaintenanceSetup( SettingsBuilder $settingsBuilder ) {
		global $maintenance;
		$config = $settingsBuilder->getConfig();

		if ( $maintenance->getDbType() === Maintenance::DB_NONE ) {
			$cacheConf = $config->get( 'LocalisationCacheConf' );
			if ( $cacheConf['storeClass'] === false
				&& ( $cacheConf['store'] == 'db'
					|| ( $cacheConf['store'] == 'detect'
						&& !$config->get( 'CacheDirectory' ) ) )
			) {
				$cacheConf['storeClass'] = LCStoreNull::class;
				$settingsBuilder->putConfigValue( 'LocalisationCacheConf', $cacheConf );
			}
		}

		$maintenance->finalSetup( $settingsBuilder );
	}

	define( 'MW_SETUP_CALLBACK', 'wfMaintenanceSetup' );
}

require_once "$IP/includes/Setup.php";

// Initialize main config instance
$maintenance->setConfig( MediaWikiServices::getInstance()->getMainConfig() );

// Double check required extensions are installed
$maintenance->checkRequiredExtensions();

if ( $maintenance->getDbType() == Maintenance::DB_NONE ) {
	// Be strict with maintenance tasks that claim to not need a database by
	// disabling the storage backend.
	MediaWikiServices::disableStorageBackend();
}

$maintenance->validateParamsAndArgs();

// Do the work
try {
	$success = $maintenance->execute();
} catch ( Exception $ex ) {
	$success = false;
	$exReportMessage = '';
	while ( $ex ) {
		$cls = get_class( $ex );
		$exReportMessage .= "$cls from line {$ex->getLine()} of {$ex->getFile()}: {$ex->getMessage()}\n";
		$exReportMessage .= $ex->getTraceAsString() . "\n";
		$ex = $ex->getPrevious();
	}
	// Print the exception to stderr if possible, don't mix it in
	// with stdout output.
	if ( defined( 'STDERR' ) ) {
		fwrite( STDERR, $exReportMessage );
	} else {
		echo $exReportMessage;
	}
}

// Potentially debug globals
$maintenance->globals();

$maintenance->shutdown();

// Exit with an error status if execute() returned false
if ( $success === false ) {
	exit( 1 );
}
