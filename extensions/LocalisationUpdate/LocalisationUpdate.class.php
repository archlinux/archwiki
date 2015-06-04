<?php

/**
 * Class for localization update hooks and static methods.
 */
class LocalisationUpdate {
	/** @todo Remove this once pre-1.24 versions of MW are no longer supported. */
	private static $onRecacheFallbackCalled = false;

	/**
	 * Hook: LocalisationCacheRecacheFallback
	 */
	public static function onRecacheFallback( LocalisationCache $lc, $code, array &$cache ) {
		self::$onRecacheFallbackCalled = true;

		$dir = LocalisationUpdate::getDirectory();
		if ( !$dir ) {
			return true;
		}

		$fileName = "$dir/" . self::getFilename( $code );
		if ( is_readable( $fileName ) ) {
			$data = FormatJson::decode( file_get_contents( $fileName ), true );
			$cache['messages'] = array_merge( $cache['messages'], $data );
		}

		return true;
	}

	/**
	 * Hook: LocalisationCacheRecache
	 */
	public static function onRecache( LocalisationCache $lc, $code, array &$cache ) {
		$dir = LocalisationUpdate::getDirectory();
		if ( !$dir ) {
			return true;
		}

		$codeSequence = array_merge( array( $code ), $cache['fallbackSequence'] );
		foreach ( $codeSequence as $csCode ) {
			$fileName = "$dir/" . self::getFilename( $csCode );
			if ( !self::$onRecacheFallbackCalled && is_readable( $fileName ) ) {
				// We're on an old version of MW that doesn't have the hook
				// needed to do things correctly. L10n will be broken here in
				// certain reasonably-common situations (see bug 68781), but
				// there's nothing we can do about it.
				$data = FormatJson::decode( file_get_contents( $fileName ), true );
				$cache['messages'] = array_merge( $cache['messages'], $data );
			}
			$cache['deps'][] = new FileDependency( $fileName );
		}

		return true;
	}

	/**
	 * Returns a directory where updated translations are stored.
	 *
	 * @return string|false False if not configured.
	 * @since 1.1
	 */
	public static function getDirectory() {
		global $wgLocalisationUpdateDirectory, $wgCacheDirectory;

		// ?: can be used once we drop support for MW 1.19
		return $wgLocalisationUpdateDirectory ?
			$wgLocalisationUpdateDirectory :
			$wgCacheDirectory;
	}

	/**
	 * Returns a filename where updated translations are stored.
	 *
	 * @param string $language Language tag
	 * @return string
	 * @since 1.1
	 */
	public static function getFilename( $language ) {
		return "l10nupdate-$language.json";
	}

	/**
	 * Hook: UnitTestsList
	 */
	public static function setupUnitTests( array &$files ) {
		$dir = __DIR__ . '/tests/phpunit';
		$directoryIterator = new RecursiveDirectoryIterator( $dir );
		$fileIterator = new RecursiveIteratorIterator( $directoryIterator );

		/// @var SplFileInfo $fileInfo
		foreach ( $fileIterator as $fileInfo ) {
			if ( substr( $fileInfo->getFilename(), -8 ) === 'Test.php' ) {
				$files[] = $fileInfo->getPathname();
			}
		}

		return true;
	}
}
