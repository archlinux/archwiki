<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0+
 */

$dir = __DIR__;

$GLOBALS['wgAutoloadClasses']['LocalisationUpdate'] = "$dir/LocalisationUpdate.class.php";
$GLOBALS['wgAutoloadClasses']['LU_Updater'] = "$dir/Updater.php";
$GLOBALS['wgAutoloadClasses']['QuickArrayReader'] = "$dir/QuickArrayReader.php";

# fetcher
$GLOBALS['wgAutoloadClasses']['LU_Fetcher'] = "$dir/fetcher/Fetcher.php";
$GLOBALS['wgAutoloadClasses']['LU_FetcherFactory'] = "$dir/fetcher/FetcherFactory.php";
$GLOBALS['wgAutoloadClasses']['LU_FileSystemFetcher'] = "$dir/fetcher/FileSystemFetcher.php";
$GLOBALS['wgAutoloadClasses']['LU_GitHubFetcher'] = "$dir/fetcher/GitHubFetcher.php";
$GLOBALS['wgAutoloadClasses']['LU_HttpFetcher'] = "$dir/fetcher/HttpFetcher.php";

# finder
$GLOBALS['wgAutoloadClasses']['LU_Finder'] = "$dir/finder/Finder.php";

# reader
$GLOBALS['wgAutoloadClasses']['LU_JSONReader'] = "$dir/reader/JSONReader.php";
$GLOBALS['wgAutoloadClasses']['LU_PHPReader'] = "$dir/reader/PHPReader.php";
$GLOBALS['wgAutoloadClasses']['LU_Reader'] = "$dir/reader/Reader.php";
$GLOBALS['wgAutoloadClasses']['LU_ReaderFactory'] = "$dir/reader/ReaderFactory.php";
