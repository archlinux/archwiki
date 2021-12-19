<?php
/**
 * @file
 * @ingroup Extensions
 */
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\IDatabase;

/**
 * Represents the information about a template,
 * coming from the JSON blob in the <templatedata> tags
 * on wiki pages.
 */
class TemplateDataBlob {
	/**
	 * Predefined formats for TemplateData to check against
	 */
	private const FORMATS = [
		'block' => "{{_\n| _ = _\n}}",
		'inline' => '{{_|_=_}}',
	];

	private const VALID_ROOT_KEYS = [
		'description',
		'params',
		'paramOrder',
		'sets',
		'maps',
		'format',
	];

	private const VALID_PARAM_KEYS = [
		'label',
		'required',
		'suggested',
		'description',
		'example',
		'deprecated',
		'aliases',
		'autovalue',
		'default',
		'inherits',
		'type',
		'suggestedvalues',
	];

	private const VALID_TYPES = [
		'content',
		'line',
		'number',
		'boolean',
		'string',
		'date',
		'unbalanced-wikitext',
		'unknown',
		'url',
		'wiki-page-name',
		'wiki-user-name',
		'wiki-file-name',
		'wiki-template-name',
	];

	private const DEPRECATED_TYPES_MAP = [
		'string/line' => 'line',
		'string/wiki-page-name' => 'wiki-page-name',
		'string/wiki-user-name' => 'wiki-user-name',
		'string/wiki-file-name' => 'wiki-file-name',
	];

	/**
	 * @var stdClass
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
		$data = $this->data;

		if ( $data === null ) {
			return Status::newFatal( 'templatedata-invalid-parse' );
		}

		if ( !is_object( $data ) ) {
			return Status::newFatal( 'templatedata-invalid-type', 'templatedata', 'object' );
		}

		foreach ( $data as $key => $value ) {
			if ( !in_array( $key, self::VALID_ROOT_KEYS ) ) {
				return Status::newFatal( 'templatedata-invalid-unknown', $key );
			}
		}

		// Root.description
		if ( isset( $data->description ) ) {
			if ( !$this->isValidInterfaceText( $data->description ) ) {
				return Status::newFatal( 'templatedata-invalid-type', 'description', 'string|object' );
			}
			$data->description = $this->normaliseInterfaceText( $data->description );
		} else {
			$data->description = null;
		}

		// Root.format
		if ( isset( $data->format ) ) {
			// @phan-suppress-next-line PhanTypeMismatchDimFetchNullable isset makes this non-null
			$f = self::FORMATS[$data->format] ?? $data->format;
			if (
				!is_string( $f ) ||
				!preg_match( '/^\n?\{\{ *_+\n? *\|\n? *_+ *= *_+\n? *\}\}\n?$/', $f )
			) {
				return Status::newFatal(
					'templatedata-invalid-format',
					'format'
				);
			}
		} else {
			$data->format = null;
		}

		// Root.params
		if ( !isset( $data->params ) ) {
			return Status::newFatal( 'templatedata-invalid-missing', 'params', 'object' );
		}

		if ( !is_object( $data->params ) ) {
			return Status::newFatal( 'templatedata-invalid-type', 'params', 'object' );
		}

		// Deep clone
		// We need this to determine whether a property was originally set
		// to decide whether 'inherits' will add it or not.
		$unnormalizedParams = unserialize( serialize( $data->params ) );
		$paramNames = [];

		foreach ( $data->params as $paramName => $paramObj ) {
			if ( !is_object( $paramObj ) ) {
				return Status::newFatal(
					'templatedata-invalid-type',
					"params.{$paramName}",
					'object'
				);
			}

			foreach ( $paramObj as $key => $value ) {
				if ( !in_array( $key, self::VALID_PARAM_KEYS ) ) {
					return Status::newFatal(
						'templatedata-invalid-unknown',
						"params.{$paramName}.{$key}"
					);
				}
			}

			// Param.label
			if ( isset( $paramObj->label ) ) {
				if ( !$this->isValidInterfaceText( $paramObj->label ) ) {
					return Status::newFatal(
						'templatedata-invalid-type',
						"params.{$paramName}.label",
						'string|object'
					);
				}
				$paramObj->label = $this->normaliseInterfaceText( $paramObj->label );
			} else {
				$paramObj->label = null;
			}

			// Param.required
			if ( isset( $paramObj->required ) ) {
				if ( !is_bool( $paramObj->required ) ) {
					return Status::newFatal(
						'templatedata-invalid-type',
						"params.{$paramName}.required",
						'boolean'
					);
				}
			} else {
				$paramObj->required = false;
			}

			// Param.suggested
			if ( isset( $paramObj->suggested ) ) {
				if ( !is_bool( $paramObj->suggested ) ) {
					return Status::newFatal(
						'templatedata-invalid-type',
						"params.{$paramName}.suggested",
						'boolean'
					);
				}
			} else {
				$paramObj->suggested = false;
			}

			// Param.description
			if ( isset( $paramObj->description ) ) {
				if ( !$this->isValidInterfaceText( $paramObj->description ) ) {
					return Status::newFatal(
						'templatedata-invalid-type',
						"params.{$paramName}.description",
						'string|object'
					);
				}
				$paramObj->description = $this->normaliseInterfaceText( $paramObj->description );
			} else {
				$paramObj->description = null;
			}

			// Param.example
			if ( isset( $paramObj->example ) ) {
				if ( !$this->isValidInterfaceText( $paramObj->example ) ) {
					return Status::newFatal(
						'templatedata-invalid-type',
						"params.{$paramName}.example",
						'string|object'
					);
				}
				$paramObj->example = $this->normaliseInterfaceText( $paramObj->example );
			} else {
				$paramObj->example = null;
			}

			// Param.deprecated
			if ( isset( $paramObj->deprecated ) ) {
				if ( !is_bool( $paramObj->deprecated ) && !is_string( $paramObj->deprecated ) ) {
					return Status::newFatal(
						'templatedata-invalid-type',
						"params.{$paramName}.deprecated",
						'boolean|string'
					);
				}
			} else {
				$paramObj->deprecated = false;
			}

			// Param.aliases
			if ( isset( $paramObj->aliases ) ) {
				if ( !is_array( $paramObj->aliases ) ) {
					// TODO: Validate the array values.
					return Status::newFatal(
						'templatedata-invalid-type',
						"params.{$paramName}.aliases",
						'array'
					);
				}
			} else {
				$paramObj->aliases = [];
			}

			// Param.autovalue
			if ( isset( $paramObj->autovalue ) ) {
				if ( !is_string( $paramObj->autovalue ) ) {
					// TODO: Validate the autovalue values.
					return Status::newFatal(
						'templatedata-invalid-type',
						"params.{$paramName}.autovalue",
						'string'
					);
				}
			} else {
				$paramObj->autovalue = null;
			}

			// Param.default
			if ( isset( $paramObj->default ) ) {
				if ( !$this->isValidInterfaceText( $paramObj->default ) ) {
					return Status::newFatal(
						'templatedata-invalid-type',
						"params.{$paramName}.default",
						'string|object'
					);
				}
				$paramObj->default = $this->normaliseInterfaceText( $paramObj->default );
			} else {
				$paramObj->default = null;
			}

			// Param.type
			if ( isset( $paramObj->type ) ) {
				if ( !is_string( $paramObj->type ) ) {
					return Status::newFatal(
						'templatedata-invalid-type',
						"params.{$paramName}.type",
						'string'
					);
				}

				// Map deprecated types to newer versions
				if ( isset( self::DEPRECATED_TYPES_MAP[ $paramObj->type ] ) ) {
					$paramObj->type = self::DEPRECATED_TYPES_MAP[ $paramObj->type ];
				}

				if ( !in_array( $paramObj->type, self::VALID_TYPES ) ) {
					return Status::newFatal(
						'templatedata-invalid-value',
						'params.' . $paramName . '.type'
					);
				}
			} else {
				$paramObj->type = 'unknown';
			}

			// Param.suggestedvalues
			if ( isset( $paramObj->suggestedvalues ) ) {
				if ( !is_array( $paramObj->suggestedvalues ) ) {
					return Status::newFatal(
						'templatedata-invalid-type',
						"params.{$paramName}.suggestedvalues",
						'array'
					);
				}
			} else {
				$paramObj->suggestedvalues = [];
			}

			$paramNames[] = $paramName;
		}

		// Param.inherits
		// Done afterwards to avoid code duplication
		foreach ( $data->params as $paramName => $paramObj ) {
			if ( isset( $paramObj->inherits ) ) {
				if ( !isset( $data->params->{ $paramObj->inherits } ) ) {
						return Status::newFatal(
							'templatedata-invalid-missing',
							"params.{$paramObj->inherits}"
						);
				}
				$parentParamObj = $data->params->{ $paramObj->inherits };
				foreach ( $parentParamObj as $key => $value ) {
					if ( !in_array( $key, self::VALID_PARAM_KEYS ) ) {
						return Status::newFatal( 'templatedata-invalid-unknown', $key );
					}
					if ( !isset( $unnormalizedParams->$paramName->$key ) ) {
						$paramObj->$key = is_object( $parentParamObj->$key ) ?
							clone $parentParamObj->$key :
							$parentParamObj->$key;
					}
				}
				unset( $paramObj->inherits );
			}
		}

		// Root.paramOrder
		if ( isset( $data->paramOrder ) ) {
			if ( !is_array( $data->paramOrder ) ) {
				return Status::newFatal( 'templatedata-invalid-type', 'paramOrder', 'array' );
			}

			if ( count( $data->paramOrder ) < count( $paramNames ) ) {
				$i = count( $data->paramOrder );
				return Status::newFatal( 'templatedata-invalid-missing', "paramOrder[$i]" );
			}

			// Validate each of the values corresponds to a parameter and that there are no
			// duplicates
			$seen = [];
			foreach ( $data->paramOrder as $i => $param ) {
				if ( !isset( $data->params->$param ) ) {
					return Status::newFatal( 'templatedata-invalid-value', "paramOrder[$i]" );
				}
				if ( isset( $seen[$param] ) ) {
					return Status::newFatal(
						'templatedata-invalid-duplicate-value',
						"paramOrder[$i]",
						"paramOrder[{$seen[$param]}]",
						$param
					);
				}
				$seen[$param] = $i;
			}
		}

		// Root.sets
		if ( isset( $data->sets ) ) {
			if ( !is_array( $data->sets ) ) {
				return Status::newFatal( 'templatedata-invalid-type', 'sets', 'array' );
			}
		} else {
			$data->sets = [];
		}

		foreach ( $data->sets as $setNr => $setObj ) {
			if ( !is_object( $setObj ) ) {
				return Status::newFatal( 'templatedata-invalid-value', "sets.{$setNr}" );
			}

			if ( !isset( $setObj->label ) ) {
				return Status::newFatal(
					'templatedata-invalid-missing',
					"sets.{$setNr}.label",
					'string|object'
				);
			}

			if ( !$this->isValidInterfaceText( $setObj->label ) ) {
				return Status::newFatal(
					'templatedata-invalid-type',
					"sets.{$setNr}.label",
					'string|object'
				);
			}

			$setObj->label = $this->normaliseInterfaceText( $setObj->label );

			if ( !isset( $setObj->params ) ) {
				return Status::newFatal( 'templatedata-invalid-missing', "sets.{$setNr}.params", 'array' );
			}

			if ( !is_array( $setObj->params ) ) {
				return Status::newFatal( 'templatedata-invalid-type', "sets.{$setNr}.params", 'array' );
			}

			if ( !count( $setObj->params ) ) {
				return Status::newFatal( 'templatedata-invalid-empty-array', "sets.{$setNr}.params" );
			}

			foreach ( $setObj->params as $i => $param ) {
				if ( !isset( $data->params->$param ) ) {
					return Status::newFatal( 'templatedata-invalid-value', "sets.{$setNr}.params[$i]" );
				}
			}
		}

		// Root.maps
		if ( isset( $data->maps ) ) {
			if ( !is_object( $data->maps ) ) {
				return Status::newFatal( 'templatedata-invalid-type', 'maps', 'object' );
			}
		} else {
			$data->maps = (object)[];
		}

		foreach ( $data->maps as $consumerId => $map ) {
			if ( !is_object( $map ) ) {
				return Status::newFatal( 'templatedata-invalid-type', 'maps', 'object' );
			}

			foreach ( $map as $key => $value ) {
				// Key is not validated as this is used by a third-party application
				// Value must be 2d array of parameter names, 1d array of parameter names, or valid
				// parameter name
				if ( is_array( $value ) ) {
					foreach ( $value as $key2 => $value2 ) {
						if ( is_array( $value2 ) ) {
							foreach ( $value2 as $key3 => $value3 ) {
								if ( !is_string( $value3 ) ) {
									return Status::newFatal(
										'templatedata-invalid-type',
										"maps.{$consumerId}.{$key}[$key2][$key3]",
										'string'
									);
								}
								if ( !isset( $data->params->$value3 ) ) {
									return Status::newFatal(
										'templatedata-invalid-param',
										$value3,
										"maps.{$consumerId}.{$key}"
									);
								}
							}
						} elseif ( is_string( $value2 ) ) {
							if ( !isset( $data->params->$value2 ) ) {
								return Status::newFatal(
									'templatedata-invalid-param',
									$value2,
									"maps.{$consumerId}.{$key}"
								);
							}
						} else {
							return Status::newFatal(
								'templatedata-invalid-type',
								"maps.{$consumerId}.{$key}[$key2]",
								'string|array'
							);
						}
					}
				} elseif ( is_string( $value ) ) {
					if ( !isset( $data->params->$value ) ) {
						return Status::newFatal(
							'templatedata-invalid-param',
							$value,
							"maps.{$consumerId}.{$key}"
						);
					}
				} else {
					return Status::newFatal(
						'templatedata-invalid-type',
						"maps.{$consumerId}.{$key}",
						'string|array'
					);
				}
			}
		}
		return Status::newGood();
	}

	/**
	 * @param mixed $text
	 * @return bool
	 */
	private function isValidInterfaceText( $text ): bool {
		if ( $text instanceof stdClass ) {
			$isEmpty = true;
			// An (array) cast would return private/protected properties as well
			foreach ( get_object_vars( $text ) as $languageCode => $string ) {
				// TODO: Do we need to validate if these are known interface language codes?
				if ( !is_string( $languageCode ) ||
					ltrim( $languageCode ) === '' ||
					!is_string( $string )
				) {
					return false;
				}
				$isEmpty = false;
			}
			return !$isEmpty;
		}

		return is_string( $text );
	}

	/**
	 * Normalise a InterfaceText field in the TemplateData blob.
	 * @param stdClass|string $text
	 * @return stdClass
	 */
	private function normaliseInterfaceText( $text ) {
		if ( is_string( $text ) ) {
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			return (object)[ $contLang->getCode() => $text ];
		}
		return $text;
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
	protected static function getInterfaceTextInLanguage( stdClass $text, string $langCode ): ?string {
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
	 * @return stdClass
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
	public function getDataInLanguage( string $langCode ) {
		$data = $this->getData();

		// Root.description
		if ( $data->description !== null ) {
			$data->description = self::getInterfaceTextInLanguage( $data->description, $langCode );
		}

		foreach ( $data->params as $paramObj ) {
			// Param.label
			if ( $paramObj->label !== null ) {
				$paramObj->label = self::getInterfaceTextInLanguage( $paramObj->label, $langCode );
			}

			// Param.description
			if ( $paramObj->description !== null ) {
				$paramObj->description = self::getInterfaceTextInLanguage( $paramObj->description, $langCode );
			}

			// Param.default
			if ( $paramObj->default !== null ) {
				$paramObj->default = self::getInterfaceTextInLanguage( $paramObj->default, $langCode );
			}

			// Param.example
			if ( $paramObj->example !== null ) {
				$paramObj->example = self::getInterfaceTextInLanguage( $paramObj->example, $langCode );
			}
		}

		foreach ( $data->sets as $setObj ) {
			$label = self::getInterfaceTextInLanguage( $setObj->label, $langCode );
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
	public function getJSON(): string {
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
	 * @param Language $lang
	 *
	 * @return string
	 */
	public function getHtml( Language $lang ): string {
		$data = $this->getDataInLanguage( $lang->getCode() );
		$icon = 'settings';
		if ( $data->format === null ) {
			$formatMsg = null;
		} elseif ( isset( self::FORMATS[$data->format] ) ) {
			$formatMsg = $data->format;
			'@phan-var string $formatMsg';
			$icon = 'template-format-' . $formatMsg;
		} else {
			$formatMsg = 'custom';
		}
		$sorting = count( (array)$data->params ) > 1 ? " sortable" : "";
		$html = '<header>'
			. Html::element(
				'p',
				[
					'class' => [
						'mw-templatedata-doc-desc',
						'mw-templatedata-doc-muted' => $data->description === null,
					]
				],
				$data->description ??
					wfMessage( 'templatedata-doc-desc-empty' )->inLanguage( $lang )->text()
			)
			. '</header>'
			. '<table class="wikitable mw-templatedata-doc-params' . $sorting . '">'
			. Html::rawElement(
				'caption',
				[],
				Html::element(
					'p',
					[],
					wfMessage( 'templatedata-doc-params' )->inLanguage( $lang )->text()
				)
				. ( $formatMsg !== null ?
					Html::rawElement(
						'p',
						[],
						new OOUI\IconWidget( [ 'icon' => $icon ] )
						. Html::element(
							'span',
							[ 'class' => 'mw-templatedata-format' ],
							// Messages that can be used here:
							// * templatedata-doc-format-block
							// * templatedata-doc-format-custom
							// * templatedata-doc-format-inline
							wfMessage( 'templatedata-doc-format-' . $formatMsg )->inLanguage( $lang )->text()
						)
					) :
					'' )
			)
			. '<thead><tr>'
			. Html::element(
				'th',
				[ 'colspan' => 2 ],
				wfMessage( 'templatedata-doc-param-name' )->inLanguage( $lang )->text()
			)
			. Html::element(
				'th',
				[],
				wfMessage( 'templatedata-doc-param-desc' )->inLanguage( $lang )->text()
			)
			. Html::element(
				'th',
				[],
				wfMessage( 'templatedata-doc-param-type' )->inLanguage( $lang )->text()
			)
			. Html::element(
				'th',
				[],
				wfMessage( 'templatedata-doc-param-status' )->inLanguage( $lang )->text()
			)
			. '</tr></thead>'
			. '<tbody>';

		if ( count( (array)$data->params ) === 0 ) {
			// Display no parameters message
			$html .= '<tr>'
			. Html::element( 'td',
				[
					'class' => 'mw-templatedata-doc-muted',
					'colspan' => 7
				],
				wfMessage( 'templatedata-doc-no-params-set' )->inLanguage( $lang )->text()
			)
			. '</tr>';
		}

		$paramNames = $data->paramOrder ?? array_keys( (array)$data->params );
		foreach ( $paramNames as $paramName ) {
			$paramObj = $data->params->$paramName;

			$aliases = '';
			if ( count( $paramObj->aliases ) ) {
				foreach ( $paramObj->aliases as $alias ) {
					$aliases .= wfMessage( 'word-separator' )->inLanguage( $lang )->escaped()
					. Html::element( 'code', [
						'class' => 'mw-templatedata-doc-param-alias'
					], $alias );
				}
			}

			$suggestedValuesLine = '';
			if ( count( $paramObj->suggestedvalues ) ) {
				$suggestedValues = '';
				foreach ( $paramObj->suggestedvalues as $suggestedValue ) {
					$suggestedValues .= wfMessage( 'word-separator' )->inLanguage( $lang )->escaped()
						. Html::element( 'code', [
							'class' => 'mw-templatedata-doc-param-alias'
						], $suggestedValue );
				}
				$suggestedValuesLine .= Html::element( 'dt', [],
						wfMessage( 'templatedata-doc-param-suggestedvalues' )->inLanguage( $lang )->text()
					) . Html::rawElement( 'dd', [], $suggestedValues );
			}

			if ( $paramObj->deprecated ) {
				$status = 'deprecated';
			} elseif ( $paramObj->required ) {
				$status = 'required';
			} elseif ( $paramObj->suggested ) {
				$status = 'suggested';
			} else {
				$status = 'optional';
			}

			$html .= '<tr>'
			// Label
			. Html::element( 'th', [], $paramObj->label ?? $paramName )
			// Parameters and aliases
			. Html::rawElement( 'td', [ 'class' => 'mw-templatedata-doc-param-name' ],
				Html::element( 'code', [], $paramName ) . $aliases
			)
			// Description
			. Html::rawElement( 'td', [
					'class' => [
						'mw-templatedata-doc-muted' => ( $paramObj->description === null )
					]
				],
				Html::element( 'p', [],
					$paramObj->description ??
						wfMessage( 'templatedata-doc-param-desc-empty' )->inLanguage( $lang )->text()
				)
				. Html::rawElement( 'dl', [],
					// Suggested Values
					$suggestedValuesLine .
					// Default
					( $paramObj->default !== null ? ( Html::element( 'dt', [],
						wfMessage( 'templatedata-doc-param-default' )->inLanguage( $lang )->text()
					)
					. Html::element( 'dd', [],
						$paramObj->default
					) ) : '' )
					// Example
					. ( $paramObj->example !== null ? ( Html::element( 'dt', [],
						wfMessage( 'templatedata-doc-param-example' )->inLanguage( $lang )->text()
					)
					. Html::element( 'dd', [],
						$paramObj->example
					) ) : '' )
					// Auto value
					. ( $paramObj->autovalue !== null ? ( Html::element( 'dt', [],
						wfMessage( 'templatedata-doc-param-autovalue' )->inLanguage( $lang )->text()
					)
					. Html::rawElement( 'dd', [],
						Html::element( 'code', [], $paramObj->autovalue )
					) ) : '' )
				)
			)
			// Type
			. Html::element( 'td', [
					'class' => [
						'mw-templatedata-doc-param-type',
						'mw-templatedata-doc-muted' => $paramObj->type === 'unknown'
					]
				],

				// Known messages, for grepping:
				// templatedata-doc-param-type-boolean, templatedata-doc-param-type-content,
				// templatedata-doc-param-type-date, templatedata-doc-param-type-line,
				// templatedata-doc-param-type-number, templatedata-doc-param-type-string,
				// templatedata-doc-param-type-unbalanced-wikitext, templatedata-doc-param-type-unknown,
				// templatedata-doc-param-type-url, templatedata-doc-param-type-wiki-file-name,
				// templatedata-doc-param-type-wiki-page-name, templatedata-doc-param-type-wiki-template-name,
				// templatedata-doc-param-type-wiki-user-name
				wfMessage( 'templatedata-doc-param-type-' . $paramObj->type )->inLanguage( $lang )->text()
			)
			// Status
			. Html::element(
				'td',
				[
					// CSS class names that can be used here:
					// mw-templatedata-doc-param-status-deprecated
					// mw-templatedata-doc-param-status-optional
					// mw-templatedata-doc-param-status-required
					// mw-templatedata-doc-param-status-suggested
					'class' => "mw-templatedata-doc-param-status-$status",
					'data-sort-value' => [
						'deprecated' => -1,
						'suggested' => 1,
						'required' => 2,
					][$status] ?? 0,
				],
				// Messages that can be used here:
				// templatedata-doc-param-status-deprecated
				// templatedata-doc-param-status-optional
				// templatedata-doc-param-status-required
				// templatedata-doc-param-status-suggested
				wfMessage( "templatedata-doc-param-status-$status" )->inLanguage( $lang )->text()
			)
			. '</tr>';
		}
		$html .= '</tbody></table>';

		return Html::rawElement( 'section', [ 'class' => 'mw-templatedata-doc-wrap' ], $html );
	}

	/**
	 * Get parameter descriptions from raw wikitext (used for templates that have no templatedata).
	 * @param string $wikitext The text to extract parameters from.
	 * @return string[] Parameter info in the same format as the templatedata 'params' key.
	 */
	public static function getRawParams( string $wikitext ): array {
		// Ignore wikitext within nowiki tags and comments
		$wikitext = preg_replace( '/<!--.*?-->/s', '', $wikitext );
		$wikitext = preg_replace( '/<nowiki\s*>.*?<\/nowiki\s*>/s', '', $wikitext );

		// This regex matches the one in ext.TemplateDataGenerator.sourceHandler.js
		preg_match_all( '/{{3,}([^#]*?)([<|]|}{3,})/m', $wikitext, $rawParams );
		$params = [];
		$normalizedParams = [];
		if ( isset( $rawParams[1] ) ) {
			foreach ( $rawParams[1] as $rawParam ) {
				// This normalization process is repeated in JS in ext.TemplateDataGenerator.sourceHandler.js
				$normalizedParam = strtolower( trim( preg_replace( '/[-_ ]+/', ' ', $rawParam ) ) );
				if ( !$normalizedParam || in_array( $normalizedParam, $normalizedParams ) ) {
					// This or a similarly-named parameter has already been found.
					continue;
				}
				$normalizedParams[] = $normalizedParam;
				$params[ trim( $rawParam ) ] = [];
			}
		}
		return $params;
	}

	/**
	 * @param stdClass $data
	 */
	protected function __construct( $data ) {
		$this->data = $data;
	}

}
