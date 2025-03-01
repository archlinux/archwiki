<?php

namespace MediaWiki\Extension\Gadgets\Content;

use MediaWiki\Status\Status;

/**
 * Validate the content of a gadget definition page.
 *
 * @see MediaWikiGadgetsJsonRepo
 * @see GadgetDefinitionContent
 * @internal
 */
class GadgetDefinitionValidator {
	private const TYPE_ARRAY = [ 'callback' => 'is_array', 'expect' => 'array' ];
	private const TYPE_BOOL = [ 'callback' => 'is_bool', 'expect' => 'boolean' ];
	private const TYPE_INT = [ 'callback' => 'is_int', 'expect' => 'number' ];
	private const TYPE_STRING = [ 'callback' => 'is_string', 'expect' => 'string' ];
	private const TYPE_PAGE_SUFFIX = [
		'callback' => [ __CLASS__, 'isResourcePageSuffix' ], 'expect' => '.js, .css, .json'
	];
	private const TYPE_MODULE_TYPE = [
		'callback' => [ __CLASS__, 'isModuleType' ], 'expect' => '"", "general", "styles"',
	];

	/**
	 * @var array Schema for the definition.
	 *
	 * - callback: boolean check.
	 * - expect: human-readable description for the gadgets-validate-wrongtype message.
	 * - child: optional type+expect for each array item.
	 */
	protected static $schema = [
		'settings' => self::TYPE_ARRAY,
		'settings.actions' => self::TYPE_ARRAY + [ 'child' => self::TYPE_STRING ],
		'settings.categories' => self::TYPE_ARRAY + [ 'child' => self::TYPE_STRING ],
		'settings.category' => self::TYPE_STRING,
		'settings.contentModels' => self::TYPE_ARRAY + [ 'child' => self::TYPE_STRING ],
		'settings.default' => self::TYPE_BOOL,
		'settings.hidden' => self::TYPE_BOOL,
		'settings.namespaces' => self::TYPE_ARRAY + [ 'child' => self::TYPE_INT ],
		'settings.package' => self::TYPE_BOOL,
		'settings.requiresES6' => self::TYPE_BOOL,
		'settings.rights' => self::TYPE_ARRAY + [ 'child' => self::TYPE_STRING ],
		'settings.skins' => self::TYPE_ARRAY + [ 'child' => self::TYPE_STRING ],
		'settings.supportsUrlLoad' => self::TYPE_BOOL,

		'module' => self::TYPE_ARRAY,
		'module.dependencies' => self::TYPE_ARRAY + [ 'child' => self::TYPE_STRING ],
		'module.messages' => self::TYPE_ARRAY + [ 'child' => self::TYPE_STRING ],
		'module.pages' => self::TYPE_ARRAY + [ 'child' => self::TYPE_PAGE_SUFFIX ],
		'module.peers' => self::TYPE_ARRAY + [ 'child' => self::TYPE_STRING ],
		'module.type' => self::TYPE_MODULE_TYPE,
	];

	public static function isResourcePageSuffix( $title ): bool {
		return is_string( $title ) && (
			str_ends_with( $title, '.js' ) || str_ends_with( $title, '.css' ) || str_ends_with( $title, '.json' )
		);
	}

	public static function isModuleType( string $type ): bool {
		return $type === '' || $type === 'general' || $type === 'styles';
	}

	/**
	 * Check the validity of known properties in a gadget definition array.
	 *
	 * @param array $definition
	 * @param bool $tolerateMissing If true, don't complain about missing keys
	 * @return Status object with error message if applicable
	 */
	public function validate( array $definition, $tolerateMissing = false ) {
		foreach ( self::$schema as $property => $validation ) {
			// Access the property by walking from the root to the specified property
			$val = $definition;
			foreach ( explode( '.', $property ) as $propName ) {
				if ( !array_key_exists( $propName, $val ) ) {
					if ( $tolerateMissing ) {
						// Skip validation of this property altogether
						continue 2;
					}

					return Status::newFatal( 'gadgets-validate-notset', $property );
				}
				$val = $val[$propName];
			}

			// Validate this property
			$isValid = ( $validation['callback'] )( $val );
			if ( !$isValid ) {
				return Status::newFatal(
					'gadgets-validate-wrongtype', $property,
					$validation['expect']
				);
			}

			// Descend into the array and validate each array item
			if ( isset( $validation['child'] ) && is_array( $val ) ) {
				foreach ( $val as $key => $item ) {
					$isValid = ( $validation['child']['callback'] )( $item );
					if ( !$isValid ) {
						return Status::newFatal(
							'gadgets-validate-wrongtype',
							"{$property}[{$key}]",
							$validation['child']['expect']
						);
					}
				}
			}
		}

		return Status::newGood();
	}
}
