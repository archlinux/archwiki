<?php
/**
 * Contains a utility class to query the language data.
 * @file
 * @license GPL-2.0-or-later
 */

namespace Wikimedia\LanguageData;

use stdClass;

/**
 * A singleton utility class to query the language data.
 */
class LanguageUtil {
	/**
	 * Instance of the class.
	 * @var self
	 */
	private static $instance;

	/**
	 * If language does not belong to a script group, this is returned instead.
	 * @var string
	 */
	public const OTHER_SCRIPT_GROUP = 'Other';

	/**
	 * Path of the language data file
	 * @var string
	 */
	private const LANGUAGE_DATA_PATH = '../data/language-data.json';

	/**
	 * Cached language data object
	 * @var stdClass
	 */
	private $data;

	/**
	 * Returns an instance of the class that can be used to then call the other methods in the
	 * class.
	 * @return self
	 */
	public static function get(): LanguageUtil {
		if ( self::$instance === null ) {
			self::$instance = new LanguageUtil();
			self::$instance->loadData();
		}

		return self::$instance;
	}

	private function loadData() {
		$path = realpath( __DIR__ . '/' . self::LANGUAGE_DATA_PATH );
		if ( $path && str_starts_with( $path, dirname( __DIR__ ) ) ) {
			$this->data = json_decode( file_get_contents( $path ) );
		} else {
			throw new \RuntimeException( 'Invalid language data file path' );
		}
	}

	/**
	 * Checks if a language code is valid
	 * @param string $languageCode Language code
	 * @return bool
	 */
	public function isKnown( string $languageCode ): bool {
		return isset( $this->data->languages->$languageCode );
	}

	/**
	 * Checks if the language is a redirect and returns the target language code
	 * @param string $languageCode Language code
	 * @return string|bool Target language code if it's a redirect or false if it's not
	 */
	public function isRedirect( string $languageCode ) {
		if (
			$this->isKnown( $languageCode ) &&
			count( $this->getLanguage( $languageCode ) ) === 1
		) {
			return $this->getLanguage( $languageCode )[0];
		}

		return false;
	}

	/**
	 * Get all the languages. The properties in the returned object are ISO 639 language codes
	 * The value of each property is an array that has,
	 * [writing system code, [regions list], autonym]
	 * @return stdClass
	 */
	public function getLanguages() {
		return $this->data->languages;
	}

	/**
	 * Returns the script of the language
	 * @param string $languageCode Language code
	 * @return string|bool Language script or false if the language is unknown
	 */
	public function getScript( string $languageCode ) {
		if ( !$this->isKnown( $languageCode ) ) {
			return false;
		}

		$targetCode = $this->isRedirect( $languageCode );
		if ( $targetCode ) {
			return $this->getScript( $targetCode );
		}

		return $this->getLanguage( $languageCode )[0];
	}

	/**
	 * Returns the regions in which a language is spoken
	 * @param string $languageCode Language code
	 * @return array|bool List of regions or false if language is unknown
	 */
	public function getRegions( string $languageCode ) {
		if ( !$this->isKnown( $languageCode ) ) {
			return false;
		}

		$targetCode = $this->isRedirect( $languageCode );
		if ( $targetCode ) {
			return $this->getRegions( $targetCode );
		}

		return $this->getLanguage( $languageCode )[1];
	}

	/**
	 * Returns the autonym of the language
	 * @param string $languageCode Language code
	 * @return string|bool Autonym of the language or false if the language is unknown
	 */
	public function getAutonym( string $languageCode ) {
		if ( !$this->isKnown( $languageCode ) ) {
			return false;
		}

		$targetCode = $this->isRedirect( $languageCode );
		if ( $targetCode ) {
			return $this->getAutonym( $targetCode );
		}

		$language = $this->getLanguage( $languageCode );
		return count( $language ) >= 2 ? $language[2] : $languageCode;
	}

	/**
	 * Returns all language codes and corresponding autonyms
	 * @return array The key is the language code, and the values are corresponding
	 * autonym
	 */
	public function getAutonyms(): array {
		$languages = $this->getLanguages();
		$languageAutonyms = [];
		foreach ( $languages as $languageCode => $languageData ) {
			if ( $this->isRedirect( $languageCode ) ) {
				continue;
			}
			$languageAutonyms[$languageCode] = $this->getAutonym( $languageCode );
		}

		return $languageAutonyms;
	}

	/**
	 * Returns the version of the language data.
	 * @return string|bool Version string, or "UNKNOWN" if no version is declared
	 */
	public function getVersion() {
		return $this->data->version ?? 'UNKNOWN';
	}

	/**
	 * Returns all languages written in the given scripts
	 * @param array $scripts List of strings, each being the name of a script
	 * @return array
	 */
	public function getLanguagesInScripts( array $scripts ): array {
		$languages = $this->getLanguages();
		$languagesInScripts = [];
		foreach ( $languages as $languageCode => $languageData ) {
			if ( $this->isRedirect( $languageCode ) ) {
				continue;
			}

			$script = $this->getScript( $languageCode );
			if ( in_array( $script, $scripts ) ) {
				$languagesInScripts[] = $languageCode;
			}
		}

		return $languagesInScripts;
	}

	/**
	 * Returns all languages written in the given script
	 * @param string $script Name of the script
	 * @return array
	 */
	public function getLanguagesInScript( string $script ): array {
		return $this->getLanguagesInScripts( [ $script ] );
	}

	/**
	 * Returns the script group of a script or "Other" if it doesn't belong to any group
	 * @param string $script Name of the script
	 * @return string Script group name or "Other" if the script doesn't belong to any group
	 */
	public function getGroupOfScript( string $script ): string {
		$scriptGroups = $this->data->scriptgroups;
		foreach ( $scriptGroups as $scriptGroup => $scriptGroupData ) {
			if ( in_array( $script, $scriptGroupData ) ) {
				return $scriptGroup;
			}
		}

		return self::OTHER_SCRIPT_GROUP;
	}

	/**
	 * Returns the script group of a language. Language belongs to a script, and the script
	 * belongs to a script group
	 * @param string $languageCode Language code
	 * @return string script group name
	 */
	public function getScriptGroupOfLanguage( string $languageCode ): string {
		return $this->getGroupOfScript( $this->getScript( $languageCode ) );
	}

	/**
	 * Return the list of languages passed, grouped by their script group
	 * @param array $languageCodes List of language codes to group
	 * @return array List of language codes grouped by script group
	 */
	public function getLanguagesByScriptGroup( array $languageCodes ): array {
		$languagesByScriptGroup = [];

		foreach ( $languageCodes as $languageCode ) {
			if ( !$this->isKnown( $languageCode ) ) {
				continue;
			}

			$targetLanguageCode = $this->isRedirect( $languageCode );
			if ( $targetLanguageCode === false ) {
				$targetLanguageCode = $languageCode;
			}
			$langScriptGroup = $this->getScriptGroupOfLanguage( $targetLanguageCode );

			if ( !isset( $languagesByScriptGroup[$langScriptGroup] ) ) {
				$languagesByScriptGroup[$langScriptGroup] = [];
			}

			$languagesByScriptGroup[$langScriptGroup][] = $languageCode;
		}

		return $languagesByScriptGroup;
	}

	/**
	 * Returns an associative array of languages in several regions,
	 * grouped by script group
	 * @param array $regions List of strings representing region codes
	 * @return array Returns an associative array. They key is the script group name,
	 * and the value is a list of language codes in that region.
	 */
	public function getLanguagesByScriptGroupInRegions( array $regions ): array {
		$languagesByScriptGroupInRegions = [];
		$languages = $this->getLanguages();

		foreach ( $languages as $languageCode => $languageData ) {
			if ( $this->isRedirect( $languageCode ) ) {
				continue;
			}

			$languageRegions = $this->getRegions( $languageCode );
			foreach ( $regions as $region ) {
				if ( !in_array( $region, $languageRegions ) ) {
					continue;
				}

				$langScriptGroup = $this->getScriptGroupOfLanguage( $languageCode );
				if ( !isset( $languagesByScriptGroupInRegions[$langScriptGroup] ) ) {
					$languagesByScriptGroupInRegions[$langScriptGroup] = [];
				}

				$languagesByScriptGroupInRegions[$langScriptGroup][] = $languageCode;
			}
		}

		return $languagesByScriptGroupInRegions;
	}

	/**
	 * Returns an associative array of languages in a region, grouped by their script
	 * @see LanguageUtil#getLanguagesByScriptGroupInRegions
	 * @param string $region Region code
	 * @return array
	 */
	public function getLanguagesByScriptGroupInRegion( string $region ): array {
		return $this->getLanguagesByScriptGroupInRegions( [ $region ] );
	}

	/**
	 * Return the list of languages sorted by their script groups
	 * @param array $languageCodes List of language codes to sort
	 * @return array Sorted list of strings containing language codes
	 */
	public function sortByScriptGroup( array $languageCodes ): array {
		$groupedLanguageData = $this->getLanguagesByScriptGroup( $languageCodes );
		ksort( $groupedLanguageData, SORT_STRING | SORT_FLAG_CASE );

		$sortedLanguageData = [];
		foreach ( $groupedLanguageData as $languageData ) {
			$sortedLanguageData = array_merge( $sortedLanguageData, $languageData );
		}

		return $sortedLanguageData;
	}

	/**
	 * Sort languages by their autonym
	 * @param array $languageCodes List of language codes to sort
	 * @return array List of sorted language codes returned by their autonym
	 */
	public function sortByAutonym( array $languageCodes ): array {
		$sortedLanguages = [];
		foreach ( $languageCodes as $languageCode ) {
			$autonym = $this->getAutonym( $languageCode );
			if ( $autonym !== false ) {
				$sortedLanguages[$languageCode] = $autonym;
			}
		}

		asort( $sortedLanguages, SORT_STRING | SORT_FLAG_CASE );

		return array_keys( $sortedLanguages );
	}

	/**
	 * Check if a language is right-to-left
	 * @param string $languageCode Language code
	 * @return bool true if it is an RTL language, else false. Returns false if an
	 * unknown language code is passed.
	 */
	public function isRtl( string $languageCode ): bool {
		$script = $this->getScript( $languageCode );
		return in_array( $script, $this->data->rtlscripts );
	}

	/**
	 * Return the direction of the language
	 * @param string $languageCode Language code
	 * @return string|bool Returns 'rtl' or 'ltr'. If the language code is unknown,
	 * returns false.
	 */
	public function getDir( string $languageCode ) {
		if ( $this->isKnown( $languageCode ) ) {
			return $this->isRtl( $languageCode ) ? 'rtl' : 'ltr';
		}

		return false;
	}

	/**
	 * Returns all territories and the languages spoken in each.
	 * @return stdClass An object whose keys are territory codes and whose values
	 * are arrays of language codes spoken in that territory.
	 */
	public function getTerritoriesWithLanguages(): stdClass {
		return $this->data->territories;
	}

	/**
	 * Returns the languages spoken in a territory
	 * @param string $territory Territory code
	 * @return array|bool List of language codes in the territory, or else false if invalid
	 * territory is passed
	 */
	public function getLanguagesInTerritory( string $territory ) {
		if ( isset( $this->data->territories->$territory ) ) {
			return $this->data->territories->$territory;
		}

		return false;
	}

	/**
	 * Adds a language in run time and sets its options as provided.
	 * If the target option is provided, the language is defined as a redirect.
	 * Other possible options are `script` (string), `regions` (array) and `autonym` (string).
	 * @param string $languageCode New language code.
	 * @param array $options Language properties.
	 */
	public function addLanguage( string $languageCode, array $options ): void {
		$languages = $this->getLanguages();
		if ( isset( $options['target'] ) ) {
			$languages->$languageCode = [ $options['target'] ];
		} else {
			$languages->$languageCode =
				[ $options['script'], $options['regions'], $options['autonym'] ];
		}
	}

	/**
	 * Return the language data based on language code. Performs no check, meant for
	 * internal use only
	 * @param string $languageCode
	 * @return array
	 */
	private function getLanguage( string $languageCode ): array {
		return $this->data->languages->$languageCode;
	}
}
