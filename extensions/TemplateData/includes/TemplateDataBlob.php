<?php
/**
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\TemplateData;

use MediaWiki\MediaWikiServices;
use Status;
use stdClass;
use Wikimedia\Rdbms\IDatabase;

/**
 * Represents the information about a template,
 * coming from the JSON blob in the <templatedata> tags
 * on wiki pages.
 */
class TemplateDataBlob {

	/**
	 * @var mixed
	 */
	private $data;

	/**
	 * @var string|null In-object cache for getJSON()
	 */
	private $json = null;

	/**
	 * @var Status
	 */
	private $status;

	/**
	 * Parse and validate passed JSON and create a blob handling
	 * instance.
	 * Accepts and handles user-provided data.
	 *
	 * @param IDatabase $db
	 * @param string $json
	 * @return TemplateDataBlob
	 */
	public static function newFromJSON( IDatabase $db, string $json ): TemplateDataBlob {
		if ( $db->getType() === 'mysql' ) {
			$tdb = new TemplateDataCompressedBlob( json_decode( $json ) );
		} else {
			$tdb = new TemplateDataBlob( json_decode( $json ) );
		}

		$status = $tdb->parse();

		if ( !$status->isOK() ) {
			// Reset in-object caches
			$tdb->json = null;
			$tdb->jsonDB = null;

			// If data is invalid, replace with the minimal valid blob.
			// This is to make sure that, if something forgets to check the status first,
			// we don't end up with invalid data in the database.
			$tdb->data = (object)[
				'description' => null,
				'params' => (object)[],
				'format' => null,
				'sets' => [],
				'maps' => (object)[],
			];
		}
		$tdb->status = $status;
		return $tdb;
	}

	/**
	 * Parse and validate passed JSON (possibly gzip-compressed) and create a blob handling
	 * instance.
	 *
	 * @param IDatabase $db
	 * @param string $json
	 * @return TemplateDataBlob
	 */
	public static function newFromDatabase( IDatabase $db, string $json ): TemplateDataBlob {
		// Handle GZIP compression. \037\213 is the header for GZIP files.
		if ( substr( $json, 0, 2 ) === "\037\213" ) {
			$json = gzdecode( $json );
		}
		return self::newFromJSON( $db, $json );
	}

	/**
	 * Parse the data, normalise it and validate it.
	 *
	 * See Specification.md for the expected format of the JSON object.
	 * @return Status
	 */
	protected function parse(): Status {
		$validator = new TemplateDataValidator();
		return $validator->validate( $this->data );
	}

	/**
	 * Get a single localized string from an InterfaceText object.
	 *
	 * Uses the preferred language passed to this function, or one of its fallbacks,
	 * or the site content language, or its fallbacks.
	 *
	 * @param stdClass $text An InterfaceText object
	 * @param string $langCode Preferred language
	 * @return null|string Text value from the InterfaceText object or null if no suitable
	 *  match was found
	 */
	private function getInterfaceTextInLanguage( stdClass $text, string $langCode ): ?string {
		if ( isset( $text->$langCode ) ) {
			return $text->$langCode;
		}

		list( $userlangs, $sitelangs ) = MediaWikiServices::getInstance()->getLanguageFallback()
			->getAllIncludingSiteLanguage( $langCode );

		foreach ( $userlangs as $lang ) {
			if ( isset( $text->$lang ) ) {
				return $text->$lang;
			}
		}

		foreach ( $sitelangs as $lang ) {
			if ( isset( $text->$lang ) ) {
				return $text->$lang;
			}
		}

		// If none of the languages are found fallback to null. Alternatively we could fallback to
		// reset( $text ) which will return whatever key there is, but we should't give the user a
		// "random" language with no context (e.g. could be RTL/Hebrew for an LTR/Japanese user).
		return null;
	}

	/**
	 * @return Status
	 */
	public function getStatus(): Status {
		return $this->status;
	}

	/**
	 * @return mixed
	 */
	public function getData() {
		// Return deep clone so callers can't modify data. Needed for getDataInLanguage().
		// Modification must clear 'json' and 'jsonDB' in-object cache.
		return unserialize( serialize( $this->data ) );
	}

	/**
	 * Get data with all InterfaceText objects resolved to a single string to the
	 * appropriate language.
	 *
	 * @param string $langCode Preferred language
	 * @return stdClass
	 */
	public function getDataInLanguage( string $langCode ): stdClass {
		$data = $this->getData();

		// Root.description
		if ( $data->description !== null ) {
			$data->description = $this->getInterfaceTextInLanguage( $data->description, $langCode );
		}

		foreach ( $data->params as $param ) {
			// Param.label
			if ( $param->label !== null ) {
				$param->label = $this->getInterfaceTextInLanguage( $param->label, $langCode );
			}

			// Param.description
			if ( $param->description !== null ) {
				$param->description = $this->getInterfaceTextInLanguage( $param->description, $langCode );
			}

			// Param.default
			if ( $param->default !== null ) {
				$param->default = $this->getInterfaceTextInLanguage( $param->default, $langCode );
			}

			// Param.example
			if ( $param->example !== null ) {
				$param->example = $this->getInterfaceTextInLanguage( $param->example, $langCode );
			}
		}

		foreach ( $data->sets as $setObj ) {
			$label = $this->getInterfaceTextInLanguage( $setObj->label, $langCode );
			if ( $label === null ) {
				// Contrary to other InterfaceTexts, set label is not optional. If we're here it
				// means the template data from the wiki doesn't contain either the user language,
				// site language or any of its fallbacks. Wikis should fix data that is in this
				// condition (TODO: Disallow during saving?). For now, fallback to whatever we can
				// get that does exist in the text object.
				$arr = (array)$setObj->label;
				$label = reset( $arr );
			}

			$setObj->label = $label;
		}

		return $data;
	}

	/**
	 * @return string JSON
	 */
	protected function getJSON(): string {
		if ( $this->json === null ) {
			// Cache for repeat calls
			$this->json = json_encode( $this->data );
		}
		return $this->json;
	}

	/**
	 * @return string JSON
	 */
	public function getJSONForDatabase(): string {
		return $this->getJSON();
	}

	/**
	 * @param mixed $data
	 */
	protected function __construct( $data ) {
		$this->data = $data;
	}

}
