#!/usr/bin/env php
<?php

/**
 * WARNING: Hic sunt dracones!
 *
 * This script is used in the PHPUnit bootstrap to get a list of extensions and skins to autoload,
 * without having the bootstrap file itself load any settings. It is a HUGE but unavoidable hack,
 * if we want to avoid loading settings in unit tests, and at the same time only load the extensions
 * enabled in LocalSettings.php without triggering any other side effects of Setup.php and the other
 * files it includes.
 * One day this may become unnecessary if we enforce YAML settings with a static list of extensions
 * and skins (https://www.mediawiki.org/wiki/Manual:YAML_settings_file_format).
 * The script was introduced for T227900, the idea being to have a single config file that can be used
 * with both unit and integration tests.
 * @internal This script should only be invoked by bootstrap.php, as part of the PHPUnit bootstrap process
 */

require_once __DIR__ . '/bootstrap.common.php';

TestSetup::loadSettingsFiles();

$extensionsAndSkins = ExtensionRegistry::getInstance()->getQueue();

echo implode( "\n", array_keys( $extensionsAndSkins ) );
