<?php

/**
 * Class for localization updates.
 *
 * TODO: refactor code to remove duplication
 */
class LocalisationUpdate {

	private static $newHashes = null;
	private static $filecache = array();

	/**
	 * LocalisationCacheRecache hook handler.
	 *
	 * @param $lc LocalisationCache
	 * @param $langcode String
	 * @param $cache Array
	 *
	 * @return true
	 */
	public static function onRecache( LocalisationCache $lc, $langcode, array &$cache ) {
		// Handle fallback sequence and load all fallback messages from the cache
		$codeSequence = array_merge( array( $langcode ), $cache['fallbackSequence'] );
		// Iterate over the fallback sequence in reverse, otherwise the fallback
		// language will override the requested language
		foreach ( array_reverse( $codeSequence ) as $code ) {
			if ( $code == 'en' ) {
				// Skip English, otherwise we end up trying to read
				// the nonexistent cache file for en a couple hundred times
				continue;
			}

			$cache['messages'] = array_merge(
				$cache['messages'],
				self::readFile( $code )
			);

			$cache['deps'][] = new FileDependency(
				self::filename( $code )
			);
		}

		return true;
	}

	/**
	 * Called from the cronjob to fetch new messages from SVN.
	 *
	 * @param $options Array
	 *
	 * @return true
	 */
	public static function updateMessages( array $options ) {
		global $wgLocalisationUpdateDirectory, $wgLocalisationUpdateCoreURL,
			$wgLocalisationUpdateExtensionURL, $wgLocalisationUpdateSVNURL;

		$verbose = !isset( $options['quiet'] );
		$all = isset( $options['all'] );
		$skipCore = isset( $options['skip-core'] );
		$skipExtensions = isset( $options['skip-extensions'] );

		if( isset( $options['outdir'] ) ) {
			$wgLocalisationUpdateDirectory = $options['outdir'];
		}

		$coreUrl = $wgLocalisationUpdateCoreURL;
		$extUrl = $wgLocalisationUpdateExtensionURL;

		// Some ugly BC
		if ( $wgLocalisationUpdateSVNURL ) {
			$coreUrl = $wgLocalisationUpdateSVNURL . '/phase3/$2';
			$extUrl = $wgLocalisationUpdateSVNURL . '/extensions/$1/$2';
		}

		// Some more ugly BC
		if ( isset( $options['svnurl'] ) ) {
			$coreUrl = $options['svnurl'] . '/phase3/$2';
			$extUrl = $options['svnurl'] . '/extensions/$1/$2';
		}

		$result = 0;

		// Update all MW core messages.
		if( !$skipCore ) {
			$result = self::updateMediawikiMessages( $verbose, $coreUrl );
		}

		// Update all Extension messages.
		if( !$skipExtensions ) {
			if( $all ) {
				global $IP;
				$extFiles = array();

				// Look in extensions/ for all available items...
				// TODO: add support for $wgExtensionAssetsPath
				$dirs = new RecursiveDirectoryIterator( "$IP/extensions/" );

				// I ain't kidding... RecursiveIteratorIterator.
				foreach( new RecursiveIteratorIterator( $dirs ) as $pathname => $item ) {
					$filename = basename( $pathname );
					$matches = array();
					if( preg_match( '/^(.*)\.i18n\.php$/', $filename, $matches ) ) {
						$group = $matches[1];
						$extFiles[$group] = $pathname;
					}
				}
			} else {
				global $wgExtensionMessagesFiles;
				$extFiles = $wgExtensionMessagesFiles;
			}
			foreach ( $extFiles as $extension => $locFile ) {
				$result += self::updateExtensionMessages( $locFile, $extension, $verbose, $extUrl );
			}
		}

		self::writeHashes();

		// And output the result!
		self::myLog( "Updated {$result} messages in total" );
		self::myLog( "Done" );

		return true;
	}

	/**
	 * Update Extension Messages.
	 *
	 * @param $file String
	 * @param $extension String
	 * @param $verbose Boolean
	 *
	 * @return Integer: the amount of updated messages
	 */
	public static function updateExtensionMessages( $file, $extension, $verbose, $extUrl ) {
		$match = array();
		$ok = preg_match( '~^.*/extensions/([^/]+)/(.*)$~U', $file, $match );
		if ( !$ok ) {
			return null;
		}

		$ext = $match[1];
		$extFile = $match[2];

		// Create a full path.
		$svnfile = str_replace(
			array( '$1', '$2', '$3', '$4' ),
			array( $ext, $extFile, urlencode( $ext ), urlencode( $extFile ) ),
			$extUrl
		);

		// Compare the 2 files.
		$result = self::compareExtensionFiles( $extension, $svnfile, $file, $verbose );

		return $result;
	}

	/**
	 * Update the MediaWiki Core Messages.
	 *
	 * @param $verbose Boolean
	 *
	 * @return Integer: the amount of updated messages
	 */
	public static function updateMediawikiMessages( $verbose, $coreUrl ) {
		// Find the changed English strings (as these messages won't be updated in ANY language).
		$localUrl = Language::getMessagesFileName( 'en' );
		$repoUrl = str_replace(
			array( '$2', '$4' ),
			array( 'languages/messages/MessagesEn.php', 'languages%2Fmessages%2FMessagesEn.php' ),
			$coreUrl
		);
		$changedEnglishStrings = self::compareFiles( $repoUrl, $localUrl, $verbose );

		// Count the changes.
		$changedCount = 0;

		$languages = Language::fetchLanguageNames( null, 'mwfile' );
		foreach ( array_keys( $languages ) as $code ) {
			$localUrl = Language::getMessagesFileName( $code );
			// Not prefixed with $IP
			$filename = Language::getFilename( 'languages/messages/Messages', $code );
			$repoUrl = str_replace(
				array( '$2', '$4' ),
				array( $filename, urlencode( $filename ) ),
				$coreUrl
			);

			// Compare the files.
			$changedCount += self::compareFiles( $repoUrl, $localUrl, $verbose, $changedEnglishStrings, false, true );
		}

		// Log some nice info.
		self::myLog( "{$changedCount} MediaWiki messages are updated" );

		return $changedCount;
	}

	/**
	 * Removes all unneeded content from a file and returns it.
	 *
	 * @param $contents String
	 *
	 * @return String
	 */
	public static function cleanupFile( $contents ) {
		// We don't need any PHP tags.
		$contents = strtr( $contents,
			array(
				'<?php' => '',
				'?' . '>' => ''
			)
		);

		$results = array();

		// And we only want message arrays.
		preg_match_all( '/\$messages(.*\s)*?\);/', $contents, $results );

		// But we want them all in one string.
		if( !empty( $results[0] ) && is_array( $results[0] ) ) {
			$contents = implode( "\n\n", $results[0] );
		} else {
			$contents = '';
		}

		// And we hate the windows vs linux linebreaks.
		$contents = preg_replace( '/\r\n?/', "\n", $contents );

		return $contents;
	}

	/**
	 * Returns the contents of a file or false on failiure.
	 *
	 * @param $file String
	 *
	 * @return string or false
	 */
	public static function getFileContents( $file ) {
		global $wgLocalisationUpdateRetryAttempts;

		$attempts = 0;
		$filecontents = '';

		// Use cURL to get the SVN contents.
		if ( preg_match( "/^http/", $file ) ) {
			while( !$filecontents && $attempts <= $wgLocalisationUpdateRetryAttempts ) {
				if( $attempts > 0 ) {
					$delay = 1;
					self::myLog( 'Failed to download ' . $file . "; retrying in ${delay}s..." );
					sleep( $delay );
				}

				$filecontents = Http::get( $file );
				$attempts++;
			}
			if ( !$filecontents ) {
				self::myLog( 'Cannot get the contents of ' . $file . ' (curl)' );
				return false;
			}
		} else {// otherwise try file_get_contents
			if ( !( $filecontents = file_get_contents( $file ) ) ) {
				self::myLog( 'Cannot get the contents of ' . $file );
				return false;
			}
		}

		return $filecontents;
	}

	/**
	 * Returns a pair of arrays containing the messages from two files, or
	 * a pair of nulls if the files don't need to be checked.
	 *
	 * @param $tag String
	 * @param $file1 String
	 * @param $file2 String
	 * @param $verbose Boolean
	 * @param $alwaysGetResult Boolean
	 *
	 * @return array
	 */
	public static function loadFilesToCompare( $tag, $file1, $file2, $verbose, $alwaysGetResult = true ) {
		$file1contents = self::getFileContents( $file1 );
		if ( $file1contents === false || $file1contents === '' ) {
			self::myLog( "Failed to read $file1" );
			return array( null, null );
		}

		$file2contents = self::getFileContents( $file2 );
		if ( $file2contents === false || $file2contents === '' ) {
			self::myLog( "Failed to read $file2" );
			return array( null, null );
		}

		// Only get the part we need.
		$file1contents = self::cleanupFile( $file1contents );
		$file1hash = md5( $file1contents );

		$file2contents = self::cleanupFile( $file2contents );
		$file2hash = md5( $file2contents );

		// Check if the file has changed since our last update.
		if ( !$alwaysGetResult ) {
			if ( !self::checkHash( $file1, $file1hash ) && !self::checkHash( $file2, $file2hash ) ) {
				self::myLog( "Skipping {$tag} since the files haven't changed since our last update", $verbose );
				return array( null, null );
			}
		}

		// Get the array with messages.
		$messages1 = self::parsePHP( $file1contents, 'messages' );
		if ( !is_array( $messages1 ) ) {
			if ( strpos( $file1contents, '$messages' ) === false ) {
				// No $messages array. This happens for some languages that only have a fallback
				$messages1 = array();
			} else {
				// Broken file? Report and bail
				self::myLog( "Failed to parse $file1" );
				return array( null, null );
			}
		}

		$messages2 = self::parsePHP( $file2contents, 'messages' );
		if ( !is_array( $messages2 ) ) {
			// Broken file? Report and bail
			if ( strpos( $file2contents, '$messages' ) === false ) {
				// No $messages array. This happens for some languages that only have a fallback
				$messages2 = array();
			} else {
				self::myLog( "Failed to parse $file2" );
				return array( null, null );
			}
		}

		self::saveHash( $file1, $file1hash );
		self::saveHash( $file2, $file2hash );

		return array( $messages1, $messages2 );
	}

	/**
	 * Compare new and old messages lists, and optionally save the new
	 * messages if they've changed.
	 *
	 * @param $langcode String
	 * @param $old_messages Array
	 * @param $new_messages Array
	 * @param $verbose Boolean
	 * @param $forbiddenKeys Array
	 * @param $saveResults Boolean
	 *
	 * @return array|int
	 */
	private static function compareLanguageArrays( $langcode, $old_messages, $new_messages, $verbose, $forbiddenKeys, $saveResults ) {
		// Get the currently-cached messages, if any
		$cur_messages = self::readFile( $langcode );

		// Update the messages lists with the cached messages
		$old_messages = array_merge( $old_messages, $cur_messages );
		$new_messages = array_merge( $cur_messages, $new_messages );

		// Use the old/cached version for any forbidden keys
		if ( count( $forbiddenKeys ) ) {
			$new_messages = array_merge(
				array_diff_key( $new_messages, $forbiddenKeys ),
				array_intersect_key( $old_messages, $forbiddenKeys )
			);
		}


		if ( $saveResults ) {
			// If anything has changed from the saved version, save the new version
			if ( $new_messages != $cur_messages ) {
				// Count added, updated, and deleted messages:
				// diff( new, cur ) gives added + updated, and diff( cur, new )
				// gives deleted + updated.
				$changed = array_diff_assoc( $new_messages, $cur_messages ) +
					array_diff_assoc( $cur_messages, $new_messages );
				$updates = count( $changed );
				self::myLog( "{$updates} messages updated for {$langcode}.", $verbose );
				self::writeFile( $langcode, $new_messages );
			} else {
				$updates = 0;
			}
			return $updates;
		} else {
			// Find all deleted or changed messages
			$changedStrings = array_diff_assoc( $old_messages, $new_messages );
			return $changedStrings;
		}
	}

	/**
	 * Returns an array containing the differences between the files.
	 *
	 * @param $newfile String
	 * @param $oldfile String
	 * @param $verbose Boolean
	 * @param $forbiddenKeys Array
	 * @param $alwaysGetResult Boolean
	 * @param $saveResults Boolean
	 *
	 * @return array|int
	 */
	public static function compareFiles( $newfile, $oldfile, $verbose, array $forbiddenKeys = array(), $alwaysGetResult = true, $saveResults = false ) {
		// Get the languagecode.
		$langcode = Language::getCodeFromFileName( $newfile, 'Messages' );

		list( $new_messages, $old_messages ) = self::loadFilesToCompare(
			$langcode, $newfile, $oldfile, $verbose, $alwaysGetResult
		);
		if ( $new_messages === null || $old_messages === null ) {
			return $saveResults ? 0 : array();
		}

		return self::compareLanguageArrays( $langcode, $old_messages, $new_messages, $verbose, $forbiddenKeys, $saveResults );
	}

	/**
	 *
	 * @param $extension String
	 * @param $newfile String
	 * @param $oldfile String
	 * @param $verbose Boolean
	 * @param $alwaysGetResult Boolean
	 * @param $saveResults Boolean
	 *
	 * @return Integer: the amount of updated messages
	 */
	public static function compareExtensionFiles( $extension, $newfile, $oldfile, $verbose ) {
		list( $new_messages, $old_messages ) = self::loadFilesToCompare(
			$extension, $newfile, $oldfile, $verbose, false
		);
		if ( $new_messages === null || $old_messages === null ) {
			return 0;
		}

		// Update counter.
		$updates = 0;

		if ( empty( $new_messages['en'] ) ) {
			$new_messages['en'] = array();
		}

		if ( empty( $old_messages['en'] ) ) {
			$old_messages['en'] = array();
		}

		// Find the changed english strings.
		$forbiddenKeys = self::compareLanguageArrays( 'en', $old_messages['en'], $new_messages['en'], $verbose, array(), false );

		// Do an update for each language.
		foreach ( $new_messages as $language => $messages ) {
			if ( $language == 'en' ) { // Skip english.
				continue;
			}

			if ( !isset( $old_messages[$language] ) ) {
				$old_messages[$language] = array();
			}

			$updates += self::compareLanguageArrays( $language, $old_messages[$language], $messages, $verbose, $forbiddenKeys, true );
		}

		// And log some stuff.
		self::myLog( "Updated " . $updates . " messages for the '{$extension}' extension", $verbose );

		return $updates;
	}

	/**
	 * Checks whether a messages file has a certain hash.
	 *
	 * TODO: Swap return values, this is insane
	 *
	 * @param $file string Filename
	 * @param $hash string Hash
	 *
	 * @return bool True if $file does NOT have hash $hash, false if it does
	 */
	public static function checkHash( $file, $hash ) {
		$hashes = self::readFile( 'hashes' );
		return @$hashes[$file] !== $hash;
	}

	/**
	 * @param $file
	 * @param $hash
	 */
	public static function saveHash( $file, $hash ) {
		if ( is_null( self::$newHashes ) ) {
			self::$newHashes = self::readFile( 'hashes' );
		}

		self::$newHashes[$file] = $hash;
	}

	public static function writeHashes() {
		self::writeFile( 'hashes', self::$newHashes );
	}

	/**
	 * Logs a message.
	 *
	 * @param $log String
	 * @param bool $verbose
	 */
	public static function myLog( $log, $verbose = true ) {
		if ( !$verbose ) {
			return;
		}
		if ( isset( $_SERVER ) && array_key_exists( 'REQUEST_METHOD', $_SERVER ) ) {
			wfDebug( $log . "\n" );
		} else {
			print( $log . "\n" );
		}
	}

	/**
	 * @param $php
	 * @param $varname
	 * @return bool|array
	 */
	public static function parsePHP( $php, $varname ) {
		try {
			$reader = new QuickArrayReader("<?php $php");
			return $reader->getVar( $varname );
		} catch( Exception $e ) {
			self::myLog( "Failed to read file: " . $e );
			return false;
		}
	}

	/**
	 * @param $lang
	 * @return string
	 * @throws MWException
	 */
	public static function filename( $lang ) {
		global $wgLocalisationUpdateDirectory, $wgCacheDirectory;

		$dir = $wgLocalisationUpdateDirectory ?
			$wgLocalisationUpdateDirectory :
			$wgCacheDirectory;

		if ( !$dir ) {
			throw new MWException( 'No cache directory configured' );
		}

		return "$dir/l10nupdate-$lang.cache";
	}

	/**
	 * @param $lang
	 * @return mixed
	 */
	public static function readFile( $lang ) {
		if ( !isset( self::$filecache[$lang] ) ) {
			$file = self::filename( $lang );
			$contents = @file_get_contents( $file );

			if ( $contents === false ) {
				wfDebug( "Failed to read file '$file'\n" );
				$retval = array();
			} else {
				$retval = unserialize( $contents );

				if ( $retval === false ) {
					wfDebug( "Corrupted data in file '$file'\n" );
					$retval = array();
				}
			}
			self::$filecache[$lang] = $retval;
		}

		return self::$filecache[$lang];
	}

	/**
	 * @param $lang
	 * @param $var
	 * @throws MWException
	 */
	public static function writeFile( $lang, $var ) {
		$file = self::filename( $lang );

		if ( !@file_put_contents( $file, serialize( $var ) ) ) {
			throw new MWException( "Failed to write to file '$file'" );
		}

		self::$filecache[$lang] = $var;
	}

}
