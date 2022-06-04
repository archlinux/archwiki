<?php

namespace MediaWiki\Extension\TemplateData;

use MediaWiki\MediaWikiServices;
use Status;
use stdClass;

class TemplateDataValidator {

	public const PREDEFINED_FORMATS = [
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
	 * @param mixed $data
	 *
	 * @return Status
	 */
	public function validate( $data ): Status {
		if ( $data === null ) {
			return Status::newFatal( 'templatedata-invalid-parse' );
		}

		if ( !( $data instanceof stdClass ) ) {
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
				return Status::newFatal( 'templatedata-invalid-type', 'description',
					'string|object' );
			}
			$data->description = $this->normaliseInterfaceText( $data->description );
		} else {
			$data->description = null;
		}

		// Root.format
		if ( isset( $data->format ) ) {
			// @phan-suppress-next-line PhanTypeMismatchDimFetchNullable
			$f = self::PREDEFINED_FORMATS[$data->format] ?? $data->format;
			if ( !is_string( $f ) ||
				!preg_match( '/^\n?\{\{ *_+\n? *\|\n? *_+ *= *_+\n? *\}\}\n?$/', $f )
			) {
				return Status::newFatal( 'templatedata-invalid-format', 'format' );
			}
		} else {
			$data->format = null;
		}

		// Root.params
		if ( !isset( $data->params ) ) {
			return Status::newFatal( 'templatedata-invalid-missing', 'params', 'object' );
		}

		if ( !( $data->params instanceof stdClass ) ) {
			return Status::newFatal( 'templatedata-invalid-type', 'params', 'object' );
		}

		// Deep clone
		// We need this to determine whether a property was originally set
		// to decide whether 'inherits' will add it or not.
		$unnormalizedParams = unserialize( serialize( $data->params ) );

		foreach ( $data->params as $paramName => $param ) {
			if ( !( $param instanceof stdClass ) ) {
				return Status::newFatal( 'templatedata-invalid-type', "params.{$paramName}",
					'object' );
			}

			foreach ( $param as $key => $value ) {
				if ( !in_array( $key, self::VALID_PARAM_KEYS ) ) {
					return Status::newFatal( 'templatedata-invalid-unknown',
						"params.{$paramName}.{$key}" );
				}
			}

			// Param.label
			if ( isset( $param->label ) ) {
				if ( !$this->isValidInterfaceText( $param->label ) ) {
					return Status::newFatal( 'templatedata-invalid-type',
						"params.{$paramName}.label", 'string|object' );
				}
				$param->label = $this->normaliseInterfaceText( $param->label );
			} else {
				$param->label = null;
			}

			// Param.required
			if ( isset( $param->required ) ) {
				if ( !is_bool( $param->required ) ) {
					return Status::newFatal( 'templatedata-invalid-type',
						"params.{$paramName}.required", 'boolean' );
				}
			} else {
				$param->required = false;
			}

			// Param.suggested
			if ( isset( $param->suggested ) ) {
				if ( !is_bool( $param->suggested ) ) {
					return Status::newFatal( 'templatedata-invalid-type',
						"params.{$paramName}.suggested", 'boolean' );
				}
			} else {
				$param->suggested = false;
			}

			// Param.description
			if ( isset( $param->description ) ) {
				if ( !$this->isValidInterfaceText( $param->description ) ) {
					return Status::newFatal( 'templatedata-invalid-type',
						"params.{$paramName}.description", 'string|object' );
				}
				$param->description = $this->normaliseInterfaceText( $param->description );
			} else {
				$param->description = null;
			}

			// Param.example
			if ( isset( $param->example ) ) {
				if ( !$this->isValidInterfaceText( $param->example ) ) {
					return Status::newFatal( 'templatedata-invalid-type',
						"params.{$paramName}.example", 'string|object' );
				}
				$param->example = $this->normaliseInterfaceText( $param->example );
			} else {
				$param->example = null;
			}

			// Param.deprecated
			if ( isset( $param->deprecated ) ) {
				if ( !is_bool( $param->deprecated ) && !is_string( $param->deprecated ) ) {
					return Status::newFatal( 'templatedata-invalid-type',
						"params.{$paramName}.deprecated", 'boolean|string' );
				}
			} else {
				$param->deprecated = false;
			}

			// Param.aliases
			if ( isset( $param->aliases ) ) {
				if ( !is_array( $param->aliases ) ) {
					return Status::newFatal( 'templatedata-invalid-type',
						"params.{$paramName}.aliases", 'array' );
				}
				foreach ( $param->aliases as $i => &$alias ) {
					if ( is_int( $alias ) ) {
						$alias = (string)$alias;
					} elseif ( !is_string( $alias ) ) {
						return Status::newFatal( 'templatedata-invalid-type',
							"params.{$paramName}.aliases[$i]", 'int|string' );
					}
				}
			} else {
				$param->aliases = [];
			}

			// Param.autovalue
			if ( isset( $param->autovalue ) ) {
				if ( !is_string( $param->autovalue ) ) {
					// TODO: Validate the autovalue values.
					return Status::newFatal( 'templatedata-invalid-type',
						"params.{$paramName}.autovalue", 'string' );
				}
			} else {
				$param->autovalue = null;
			}

			// Param.default
			if ( isset( $param->default ) ) {
				if ( !$this->isValidInterfaceText( $param->default ) ) {
					return Status::newFatal( 'templatedata-invalid-type',
						"params.{$paramName}.default", 'string|object' );
				}
				$param->default = $this->normaliseInterfaceText( $param->default );
			} else {
				$param->default = null;
			}

			// Param.type
			if ( isset( $param->type ) ) {
				if ( !is_string( $param->type ) ) {
					return Status::newFatal( 'templatedata-invalid-type',
						"params.{$paramName}.type", 'string' );
				}

				// Map deprecated types to newer versions
				if ( isset( self::DEPRECATED_TYPES_MAP[ $param->type ] ) ) {
					$param->type = self::DEPRECATED_TYPES_MAP[ $param->type ];
				}

				if ( !in_array( $param->type, self::VALID_TYPES ) ) {
					return Status::newFatal( 'templatedata-invalid-value',
						'params.' . $paramName . '.type' );
				}
			} else {
				$param->type = 'unknown';
			}

			// Param.suggestedvalues
			if ( isset( $param->suggestedvalues ) ) {
				if ( !is_array( $param->suggestedvalues ) ) {
					return Status::newFatal( 'templatedata-invalid-type',
						"params.{$paramName}.suggestedvalues", 'array' );
				}
				foreach ( $param->suggestedvalues as $i => $value ) {
					if ( !is_string( $value ) ) {
						return Status::newFatal( 'templatedata-invalid-type',
							"params.{$paramName}.suggestedvalues[$i]", 'string' );
					}
				}
			} else {
				$param->suggestedvalues = [];
			}
		}

		// Param.inherits
		// Done afterwards to avoid code duplication
		foreach ( $data->params as $paramName => $param ) {
			if ( isset( $param->inherits ) ) {
				if ( !isset( $data->params->{ $param->inherits } ) ) {
						return Status::newFatal( 'templatedata-invalid-missing',
							"params.{$param->inherits}" );
				}
				$parentParam = $data->params->{ $param->inherits };
				foreach ( $parentParam as $key => $value ) {
					if ( !isset( $unnormalizedParams->$paramName->$key ) ) {
						$param->$key = is_object( $value ) ? clone $value : $value;
					}
				}
				unset( $param->inherits );
			}
		}

		return $this->validateParameterOrder( $data->paramOrder ?? null, $data->params ) ??
			$this->validateSets( $data->sets, $data->params ) ??
			$this->validateMaps( $data->maps, $data->params ) ??
			Status::newGood();
	}

	/**
	 * @param mixed $paramOrder
	 * @param stdClass $params
	 *
	 * @return Status|null
	 */
	private function validateParameterOrder( $paramOrder, stdClass $params ): ?Status {
		if ( $paramOrder === null ) {
			return null;
		} elseif ( !is_array( $paramOrder ) ) {
			return Status::newFatal( 'templatedata-invalid-type', 'paramOrder', 'array' );
		} elseif ( count( $paramOrder ) < count( (array)$params ) ) {
			$firstMissing = count( $paramOrder );
			return Status::newFatal( 'templatedata-invalid-missing', "paramOrder[$firstMissing]" );
		}

		// Validate each of the values corresponds to a parameter and that there are no
		// duplicates
		$seen = [];
		foreach ( $paramOrder as $i => $param ) {
			if ( !isset( $params->$param ) ) {
				return Status::newFatal( 'templatedata-invalid-value', "paramOrder[$i]" );
			}
			if ( isset( $seen[$param] ) ) {
				return Status::newFatal( 'templatedata-invalid-duplicate-value',
					"paramOrder[$i]", "paramOrder[{$seen[$param]}]", $param );
			}
			$seen[$param] = $i;
		}

		return null;
	}

	/**
	 * @param mixed &$sets
	 * @param stdClass $params
	 *
	 * @return Status|null
	 */
	private function validateSets( &$sets, stdClass $params ): ?Status {
		if ( $sets === null ) {
			$sets = [];
			return null;
		} elseif ( !is_array( $sets ) ) {
			return Status::newFatal( 'templatedata-invalid-type', 'sets', 'array' );
		}

		foreach ( $sets as $setNr => $setObj ) {
			if ( !( $setObj instanceof stdClass ) ) {
				return Status::newFatal( 'templatedata-invalid-value', "sets.{$setNr}" );
			}

			if ( !isset( $setObj->label ) ) {
				return Status::newFatal( 'templatedata-invalid-missing', "sets.{$setNr}.label",
					'string|object' );
			}

			if ( !$this->isValidInterfaceText( $setObj->label ) ) {
				return Status::newFatal( 'templatedata-invalid-type', "sets.{$setNr}.label",
					'string|object' );
			}

			$setObj->label = $this->normaliseInterfaceText( $setObj->label );

			if ( !isset( $setObj->params ) ) {
				return Status::newFatal( 'templatedata-invalid-missing', "sets.{$setNr}.params",
					'array' );
			}

			if ( !is_array( $setObj->params ) ) {
				return Status::newFatal( 'templatedata-invalid-type', "sets.{$setNr}.params",
					'array' );
			}

			if ( !count( $setObj->params ) ) {
				return Status::newFatal( 'templatedata-invalid-empty-array',
					"sets.{$setNr}.params" );
			}

			foreach ( $setObj->params as $i => $param ) {
				if ( !isset( $params->$param ) ) {
					return Status::newFatal( 'templatedata-invalid-value',
						"sets.{$setNr}.params[$i]" );
				}
			}
		}

		return null;
	}

	/**
	 * @param mixed &$maps
	 * @param stdClass $params
	 *
	 * @return Status|null
	 */
	private function validateMaps( &$maps, stdClass $params ): ?Status {
		if ( $maps === null ) {
			$maps = (object)[];
			return null;
		} elseif ( !( $maps instanceof stdClass ) ) {
			return Status::newFatal( 'templatedata-invalid-type', 'maps', 'object' );
		}

		foreach ( $maps as $consumerId => $map ) {
			if ( !( $map instanceof stdClass ) ) {
				return Status::newFatal( 'templatedata-invalid-type', "maps.$consumerId",
					'object' );
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
									return Status::newFatal( 'templatedata-invalid-type',
										"maps.{$consumerId}.{$key}[$key2][$key3]", 'string' );
								}
								if ( !isset( $params->$value3 ) ) {
									return Status::newFatal( 'templatedata-invalid-param', $value3,
										"maps.$consumerId.{$key}[$key2][$key3]" );
								}
							}
						} elseif ( is_string( $value2 ) ) {
							if ( !isset( $params->$value2 ) ) {
								return Status::newFatal( 'templatedata-invalid-param', $value2,
									"maps.$consumerId.{$key}[$key2]" );
							}
						} else {
							return Status::newFatal( 'templatedata-invalid-type',
								"maps.{$consumerId}.{$key}[$key2]", 'string|array' );
						}
					}
				} elseif ( is_string( $value ) ) {
					if ( !isset( $params->$value ) ) {
						return Status::newFatal( 'templatedata-invalid-param', $value,
							"maps.{$consumerId}.{$key}" );
					}
				} else {
					return Status::newFatal( 'templatedata-invalid-type',
						"maps.{$consumerId}.{$key}", 'string|array' );
				}
			}
		}

		return null;
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
	private function normaliseInterfaceText( $text ): stdClass {
		if ( is_string( $text ) ) {
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			return (object)[ $contLang->getCode() => $text ];
		}
		return $text;
	}

}
