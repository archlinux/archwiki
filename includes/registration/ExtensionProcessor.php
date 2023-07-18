<?php

use MediaWiki\MainConfigNames;
use MediaWiki\ResourceLoader\FilePath;

/**
 * Utility class for loading extension manifests and aggregating
 * their contents.
 *
 * @newable since 1.39
 */
class ExtensionProcessor implements Processor {

	/**
	 * Keys that should be set to $GLOBALS
	 *
	 * @var array
	 */
	protected static $globalSettings = [
		MainConfigNames::ActionFilteredLogs,
		MainConfigNames::Actions,
		MainConfigNames::AddGroups,
		MainConfigNames::APIFormatModules,
		MainConfigNames::APIListModules,
		MainConfigNames::APIMetaModules,
		MainConfigNames::APIModules,
		MainConfigNames::APIPropModules,
		MainConfigNames::AuthManagerAutoConfig,
		MainConfigNames::AvailableRights,
		MainConfigNames::CentralIdLookupProviders,
		MainConfigNames::ChangeCredentialsBlacklist,
		MainConfigNames::ConfigRegistry,
		MainConfigNames::ContentHandlers,
		MainConfigNames::DefaultUserOptions,
		MainConfigNames::ExtensionEntryPointListFiles,
		MainConfigNames::ExtensionFunctions,
		MainConfigNames::FeedClasses,
		MainConfigNames::FileExtensions,
		MainConfigNames::FilterLogTypes,
		MainConfigNames::GrantPermissionGroups,
		MainConfigNames::GrantPermissions,
		MainConfigNames::GroupPermissions,
		MainConfigNames::GroupsAddToSelf,
		MainConfigNames::GroupsRemoveFromSelf,
		MainConfigNames::HiddenPrefs,
		MainConfigNames::ImplicitGroups,
		MainConfigNames::JobClasses,
		MainConfigNames::LogActions,
		MainConfigNames::LogActionsHandlers,
		MainConfigNames::LogHeaders,
		MainConfigNames::LogNames,
		MainConfigNames::LogRestrictions,
		MainConfigNames::LogTypes,
		MainConfigNames::MediaHandlers,
		MainConfigNames::PasswordPolicy,
		MainConfigNames::RateLimits,
		MainConfigNames::RawHtmlMessages,
		MainConfigNames::ReauthenticateTime,
		MainConfigNames::RecentChangesFlags,
		MainConfigNames::RemoveCredentialsBlacklist,
		MainConfigNames::RemoveGroups,
		MainConfigNames::ResourceLoaderSources,
		MainConfigNames::RevokePermissions,
		MainConfigNames::SessionProviders,
		MainConfigNames::SpecialPages
	];

	/**
	 * Top-level attributes that come from MW core
	 *
	 * @var string[]
	 */
	protected const CORE_ATTRIBS = [
		'ParsoidModules',
		'RestRoutes',
		'SkinOOUIThemes',
		'SkinCodexThemes',
		'SearchMappings',
		'TrackingCategories',
		'LateJSConfigVarNames',
		'TempUserSerialProviders',
		'TempUserSerialMappings',
	];

	/**
	 * Mapping of global settings to their specific merge strategies.
	 *
	 * @see ExtensionRegistry::exportExtractedData
	 * @see getExtractedInfo
	 * @var array
	 */
	protected const MERGE_STRATEGIES = [
		'wgAuthManagerAutoConfig' => 'array_plus_2d',
		'wgCapitalLinkOverrides' => 'array_plus',
		'wgExtraGenderNamespaces' => 'array_plus',
		'wgGrantPermissions' => 'array_plus_2d',
		'wgGroupPermissions' => 'array_plus_2d',
		'wgHooks' => 'array_merge_recursive',
		'wgNamespaceContentModels' => 'array_plus',
		'wgNamespaceProtection' => 'array_plus',
		'wgNamespacesWithSubpages' => 'array_plus',
		'wgPasswordPolicy' => 'array_merge_recursive',
		'wgRateLimits' => 'array_plus_2d',
		'wgRevokePermissions' => 'array_plus_2d',
	];

	/**
	 * Keys that are part of the extension credits
	 *
	 * @var array
	 */
	protected const CREDIT_ATTRIBS = [
		'type',
		'author',
		'description',
		'descriptionmsg',
		'license-name',
		'name',
		'namemsg',
		'url',
		'version',
	];

	/**
	 * Things that are not 'attributes', and are not in
	 * $globalSettings or CREDIT_ATTRIBS.
	 *
	 * @var array
	 */
	protected const NOT_ATTRIBS = [
		'callback',
		'config',
		'config_prefix',
		'load_composer_autoloader',
		'manifest_version',
		'namespaces',
		'requires',
		'AutoloadClasses',
		'AutoloadNamespaces',
		'ExtensionMessagesFiles',
		'Hooks',
		'MessagePosterModule',
		'MessagesDirs',
		'OOUIThemePaths',
		'QUnitTestModule',
		'ResourceFileModulePaths',
		'ResourceModuleSkinStyles',
		'ResourceModules',
		'ServiceWiringFiles',
	];

	/**
	 * Stuff that is going to be set to $GLOBALS
	 *
	 * Some keys are pre-set to arrays so we can += to them
	 *
	 * @var array
	 */
	protected $globals = [
		'wgExtensionMessagesFiles' => [],
		'wgMessagesDirs' => [],
	];

	/**
	 * Things that should be define()'d
	 *
	 * @var array
	 */
	protected $defines = [];

	/**
	 * Things to be called once registration of these extensions are done
	 * keyed by the name of the extension that it belongs to
	 *
	 * @var callable[]
	 */
	protected $callbacks = [];

	/**
	 * @var array
	 */
	protected $credits = [];

	/**
	 * Autoloader information.
	 * Each element is an array of strings.
	 * 'files' is just a list, 'classes' and 'namespaces' are associative.
	 *
	 * @var string[][]
	 */
	protected $autoload = [
		'files' => [],
		'classes' => [],
		'namespaces' => [],
	];

	/**
	 * Autoloader information for development.
	 * Same structure as $autoload.
	 *
	 * @var string[][]
	 */
	protected $autoloadDev = [
		'files' => [],
		'classes' => [],
		'namespaces' => [],
	];

	/**
	 * Any thing else in the $info that hasn't
	 * already been processed
	 *
	 * @var array
	 */
	protected $attributes = [];

	/**
	 * Extension attributes, keyed by name =>
	 *  settings.
	 *
	 * @var array
	 */
	protected $extAttributes = [];

	/**
	 * Extracts extension info from the given JSON file.
	 *
	 * @param string $path
	 *
	 * @return void
	 */
	public function extractInfoFromFile( string $path ) {
		$json = file_get_contents( $path );
		$info = json_decode( $json, true );

		if ( !$info ) {
			throw new RuntimeException( "Failed to load JSON data from $path" );
		}

		if ( !isset( $info['manifest_version'] ) ) {
			wfDeprecatedMsg(
				"{$info['name']}'s extension.json or skin.json does not have manifest_version, " .
				'this is deprecated since MediaWiki 1.29',
				'1.29', false, false
			);
			$info['manifest_version'] = 1;
		}

		$this->extractInfo( $path, $info, $info['manifest_version'] );
	}

	/**
	 * @param string $path
	 * @param array $info
	 * @param int $version manifest_version for info
	 */
	public function extractInfo( $path, array $info, $version ) {
		$dir = dirname( $path );
		$this->extractHooks( $info, $path );
		$this->extractExtensionMessagesFiles( $dir, $info );
		$this->extractMessagesDirs( $dir, $info );
		$this->extractSkins( $dir, $info );
		$this->extractSkinImportPaths( $dir, $info );
		$this->extractNamespaces( $info );
		$this->extractResourceLoaderModules( $dir, $info );
		if ( isset( $info['ServiceWiringFiles'] ) ) {
			$this->extractPathBasedGlobal(
				'wgServiceWiringFiles',
				$dir,
				$info['ServiceWiringFiles']
			);
		}
		$name = $this->extractCredits( $path, $info );
		if ( isset( $info['callback'] ) ) {
			$this->callbacks[$name] = $info['callback'];
		}

		$this->extractAutoload( $info, $dir );

		// config should be after all core globals are extracted,
		// so duplicate setting detection will work fully
		if ( $version >= 2 ) {
			$this->extractConfig2( $info, $dir );
		} else {
			// $version === 1
			$this->extractConfig1( $info );
		}

		// Record the extension name in the ParsoidModules property
		if ( isset( $info['ParsoidModules'] ) ) {
			foreach ( $info['ParsoidModules'] as &$module ) {
				if ( is_string( $module ) ) {
					$className = $module;
					$module = [
						'class' => $className,
					];
				}
				$module['name'] = $name;
			}
		}

		if ( $version >= 2 ) {
			$this->extractAttributes( $path, $info );
		}

		foreach ( $info as $key => $val ) {
			// If it's a global setting,
			if ( in_array( $key, self::$globalSettings ) ) {
				$this->storeToArrayRecursive( $path, "wg$key", $val, $this->globals );
				continue;
			}
			// Ignore anything that starts with a @
			if ( $key[0] === '@' ) {
				continue;
			}

			if ( $version >= 2 ) {
				// Only allowed attributes are set
				if ( in_array( $key, self::CORE_ATTRIBS ) ) {
					$this->storeToArray( $path, $key, $val, $this->attributes );
				}
			} else {
				// version === 1
				if ( !in_array( $key, self::NOT_ATTRIBS )
					&& !in_array( $key, self::CREDIT_ATTRIBS )
				) {
					// If it's not disallowed, it's an attribute
					$this->storeToArrayRecursive( $path, $key, $val, $this->attributes );
				}
			}
		}
	}

	/**
	 * @param string $path
	 * @param array $info
	 */
	protected function extractAttributes( $path, array $info ) {
		if ( isset( $info['attributes'] ) ) {
			foreach ( $info['attributes'] as $extName => $value ) {
				$this->storeToArrayRecursive( $path, $extName, $value, $this->extAttributes );
			}
		}
	}

	public function getExtractedInfo( bool $includeDev = false ) {
		// Make sure the merge strategies are set
		foreach ( $this->globals as $key => $val ) {
			if ( isset( self::MERGE_STRATEGIES[$key] ) ) {
				$this->globals[$key][ExtensionRegistry::MERGE_STRATEGY] = self::MERGE_STRATEGIES[$key];
			}
		}

		// Merge $this->extAttributes into $this->attributes depending on what is loaded
		foreach ( $this->extAttributes as $extName => $value ) {
			// Only set the attribute if $extName is loaded (and hence present in credits)
			if ( isset( $this->credits[$extName] ) ) {
				foreach ( $value as $attrName => $attrValue ) {
					$this->storeToArrayRecursive(
						'', // Don't provide a path since it's impossible to generate an error here
						$extName . $attrName,
						$attrValue,
						$this->attributes
					);
				}
				unset( $this->extAttributes[$extName] );
			}
		}

		$autoload = $this->getExtractedAutoloadInfo( $includeDev );
		$info = [
			'globals' => $this->globals,
			'defines' => $this->defines,
			'callbacks' => $this->callbacks,
			'credits' => $this->credits,
			'attributes' => $this->attributes,
			'autoloaderPaths' => $autoload['files'],
			'autoloaderClasses' => $autoload['classes'],
			'autoloaderNS' => $autoload['namespaces'],
		];
		return $info;
	}

	public function getRequirements( array $info, $includeDev ) {
		// Quick shortcuts
		if ( !$includeDev || !isset( $info['dev-requires'] ) ) {
			return $info['requires'] ?? [];
		}

		if ( !isset( $info['requires'] ) ) {
			return $info['dev-requires'] ?? [];
		}

		// OK, we actually have to merge everything
		$merged = [];

		// Helper that combines version requirements by
		// picking the non-null if one is, or combines
		// the two. Note that it is not possible for
		// both inputs to be null.
		$pick = static function ( $a, $b ) {
			if ( $a === null ) {
				return $b;
			} elseif ( $b === null ) {
				return $a;
			} else {
				return "$a $b";
			}
		};

		$req = $info['requires'];
		$dev = $info['dev-requires'];
		if ( isset( $req['MediaWiki'] ) || isset( $dev['MediaWiki'] ) ) {
			$merged['MediaWiki'] = $pick(
				$req['MediaWiki'] ?? null,
				$dev['MediaWiki'] ?? null
			);
		}

		$platform = array_merge(
			array_keys( $req['platform'] ?? [] ),
			array_keys( $dev['platform'] ?? [] )
		);
		if ( $platform ) {
			foreach ( $platform as $pkey ) {
				if ( $pkey === 'php' ) {
					$value = $pick(
						$req['platform']['php'] ?? null,
						$dev['platform']['php'] ?? null
					);
				} else {
					// Prefer dev value, but these should be constant
					// anyways (ext-* and ability-*)
					$value = $dev['platform'][$pkey] ?? $req['platform'][$pkey];
				}
				$merged['platform'][$pkey] = $value;
			}
		}

		foreach ( [ 'extensions', 'skins' ] as $thing ) {
			$things = array_merge(
				array_keys( $req[$thing] ?? [] ),
				array_keys( $dev[$thing] ?? [] )
			);
			foreach ( $things as $name ) {
				$merged[$thing][$name] = $pick(
					$req[$thing][$name] ?? null,
					$dev[$thing][$name] ?? null
				);
			}
		}
		return $merged;
	}

	/**
	 * When handler value is an array, set $wgHooks or Hooks attribute
	 * Could be legacy hook e.g. 'GlobalFunctionName' or non-legacy hook
	 * referencing a handler definition from 'HookHandler' attribute
	 *
	 * @param array $callback Handler
	 * @param array $hookHandlersAttr handler definitions from 'HookHandler' attribute
	 * @param string $name
	 * @param string $path extension.json file path
	 * @throws UnexpectedValueException
	 */
	private function setArrayHookHandler(
		array $callback,
		array $hookHandlersAttr,
		string $name,
		string $path
	) {
		if ( isset( $callback['handler'] ) ) {
			$handlerName = $callback['handler'];
			$handlerDefinition = $hookHandlersAttr[$handlerName] ?? false;
			if ( !$handlerDefinition ) {
				throw new UnexpectedValueException(
					"Missing handler definition for $name in HookHandlers attribute in $path"
				);
			}
			$callback['handler'] = $handlerDefinition;
			$callback['extensionPath'] = $path;
			$this->attributes['Hooks'][$name][] = $callback;
		} else {
			foreach ( $callback as $callable ) {
				if ( is_array( $callable ) ) {
					if ( isset( $callable['handler'] ) ) { // Non-legacy style handler
						$this->setArrayHookHandler( $callable, $hookHandlersAttr, $name, $path );
					} else { // Legacy style handler array
						$this->globals['wgHooks'][$name][] = $callable;
					}
				} elseif ( is_string( $callable ) ) {
					$this->setStringHookHandler( $callable, $hookHandlersAttr, $name, $path );
				}
			}
		}
	}

	/**
	 * When handler value is a string, set $wgHooks or Hooks attribute.
	 * Could be legacy hook e.g. 'GlobalFunctionName' or non-legacy hook
	 * referencing a handler definition from 'HookHandler' attribute
	 *
	 * @param string $callback Handler
	 * @param array $hookHandlersAttr handler definitions from 'HookHandler' attribute
	 * @param string $name
	 * @param string $path
	 */
	private function setStringHookHandler(
		string $callback,
		array $hookHandlersAttr,
		string $name,
		string $path
	) {
		if ( isset( $hookHandlersAttr[$callback] ) ) {
			$handler = [
				'handler' => $hookHandlersAttr[$callback],
				'extensionPath' => $path
			];
			$this->attributes['Hooks'][$name][] = $handler;
		} else { // legacy style handler
			$this->globals['wgHooks'][$name][] = $callback;
		}
	}

	/**
	 * Extract hook information from Hooks and HookHandler attributes.
	 * Store hook in $wgHooks if a legacy style handler or the 'Hooks' attribute if
	 * a non-legacy handler
	 *
	 * @param array $info attributes and associated values from extension.json
	 * @param string $path path to extension.json
	 */
	protected function extractHooks( array $info, string $path ) {
		$extName = $info['name'];
		if ( isset( $info['Hooks'] ) ) {
			$hookHandlersAttr = [];
			foreach ( $info['HookHandlers'] ?? [] as $name => $def ) {
				$hookHandlersAttr[$name] = [ 'name' => "$extName-$name" ] + $def;
			}
			foreach ( $info['Hooks'] as $name => $callback ) {
				if ( is_string( $callback ) ) {
					$this->setStringHookHandler( $callback, $hookHandlersAttr, $name, $path );
				} elseif ( is_array( $callback ) ) {
					$this->setArrayHookHandler( $callback, $hookHandlersAttr, $name, $path );
				}
			}
		}
		if ( isset( $info['DeprecatedHooks'] ) ) {
			$deprecatedHooks = [];
			foreach ( $info['DeprecatedHooks'] as $name => $deprecatedHookInfo ) {
				$deprecatedHookInfo += [ 'component' => $extName ];
				$deprecatedHooks[$name] = $deprecatedHookInfo;
			}
			if ( isset( $this->attributes['DeprecatedHooks'] ) ) {
				$this->attributes['DeprecatedHooks'] += $deprecatedHooks;
			} else {
				$this->attributes['DeprecatedHooks'] = $deprecatedHooks;
			}
		}
	}

	/**
	 * Register namespaces with the appropriate global settings
	 *
	 * @param array $info
	 */
	protected function extractNamespaces( array $info ) {
		if ( isset( $info['namespaces'] ) ) {
			foreach ( $info['namespaces'] as $ns ) {
				if ( defined( $ns['constant'] ) ) {
					// If the namespace constant is already defined, use it.
					// This allows namespace IDs to be overwritten locally.
					$id = constant( $ns['constant'] );
				} else {
					$id = $ns['id'];
				}
				$this->defines[ $ns['constant'] ] = $id;

				if ( !( isset( $ns['conditional'] ) && $ns['conditional'] ) ) {
					// If it is not conditional, register it
					$this->attributes['ExtensionNamespaces'][$id] = $ns['name'];
				}
				if ( isset( $ns['movable'] ) && !$ns['movable'] ) {
					$this->attributes['ImmovableNamespaces'][] = $id;
				}
				if ( isset( $ns['gender'] ) ) {
					$this->globals['wgExtraGenderNamespaces'][$id] = $ns['gender'];
				}
				if ( isset( $ns['subpages'] ) && $ns['subpages'] ) {
					$this->globals['wgNamespacesWithSubpages'][$id] = true;
				}
				if ( isset( $ns['content'] ) && $ns['content'] ) {
					$this->globals['wgContentNamespaces'][] = $id;
				}
				if ( isset( $ns['defaultcontentmodel'] ) ) {
					$this->globals['wgNamespaceContentModels'][$id] = $ns['defaultcontentmodel'];
				}
				if ( isset( $ns['protection'] ) ) {
					$this->globals['wgNamespaceProtection'][$id] = $ns['protection'];
				}
				if ( isset( $ns['capitallinkoverride'] ) ) {
					$this->globals['wgCapitalLinkOverrides'][$id] = $ns['capitallinkoverride'];
				}
				if ( isset( $ns['includable'] ) && !$ns['includable'] ) {
					$this->globals['wgNonincludableNamespaces'][] = $id;
				}
			}
		}
	}

	protected function extractResourceLoaderModules( $dir, array $info ) {
		$defaultPaths = $info['ResourceFileModulePaths'] ?? false;
		if ( isset( $defaultPaths['localBasePath'] ) ) {
			if ( $defaultPaths['localBasePath'] === '' ) {
				// Avoid double slashes (e.g. /extensions/Example//path)
				$defaultPaths['localBasePath'] = $dir;
			} else {
				$defaultPaths['localBasePath'] = "$dir/{$defaultPaths['localBasePath']}";
			}
		}

		foreach ( [ 'ResourceModules', 'ResourceModuleSkinStyles', 'OOUIThemePaths' ] as $setting ) {
			if ( isset( $info[$setting] ) ) {
				foreach ( $info[$setting] as $name => $data ) {
					if ( isset( $data['localBasePath'] ) ) {
						if ( $data['localBasePath'] === '' ) {
							// Avoid double slashes (e.g. /extensions/Example//path)
							$data['localBasePath'] = $dir;
						} else {
							$data['localBasePath'] = "$dir/{$data['localBasePath']}";
						}
					}
					if ( $defaultPaths ) {
						$data += $defaultPaths;
					}
					$this->attributes[$setting][$name] = $data;
				}
			}
		}

		if ( isset( $info['QUnitTestModule'] ) ) {
			$data = $info['QUnitTestModule'];
			if ( isset( $data['localBasePath'] ) ) {
				if ( $data['localBasePath'] === '' ) {
					// Avoid double slashes (e.g. /extensions/Example//path)
					$data['localBasePath'] = $dir;
				} else {
					$data['localBasePath'] = "$dir/{$data['localBasePath']}";
				}
			}
			// Satisfy PHPUnit ResourcesTest::testUnsatisfiableDependencies
			$data['targets'] = [ 'test' ];
			$this->attributes['QUnitTestModules']["test.{$info['name']}"] = $data;
		}

		if ( isset( $info['MessagePosterModule'] ) ) {
			$data = $info['MessagePosterModule'];
			$basePath = $data['localBasePath'] ?? '';
			$baseDir = $basePath === '' ? $dir : "$dir/$basePath";
			foreach ( $data['scripts'] ?? [] as $scripts ) {
				$this->attributes['MessagePosterModule']['scripts'][] =
					new FilePath( $scripts, $baseDir );
			}
			foreach ( $data['dependencies'] ?? [] as $dependency ) {
				$this->attributes['MessagePosterModule']['dependencies'][] = $dependency;
			}
		}
	}

	protected function extractExtensionMessagesFiles( $dir, array $info ) {
		if ( isset( $info['ExtensionMessagesFiles'] ) ) {
			foreach ( $info['ExtensionMessagesFiles'] as &$file ) {
				$file = "$dir/$file";
			}
			$this->globals["wgExtensionMessagesFiles"] += $info['ExtensionMessagesFiles'];
		}
	}

	/**
	 * Set message-related settings, which need to be expanded to use
	 * absolute paths
	 *
	 * @param string $dir
	 * @param array $info
	 */
	protected function extractMessagesDirs( $dir, array $info ) {
		if ( isset( $info['MessagesDirs'] ) ) {
			foreach ( $info['MessagesDirs'] as $name => $files ) {
				foreach ( (array)$files as $file ) {
					$this->globals["wgMessagesDirs"][$name][] = "$dir/$file";
				}
			}
		}
	}

	/**
	 * Extract skins and handle path correction for templateDirectory.
	 *
	 * @param string $dir
	 * @param array $info
	 */
	protected function extractSkins( $dir, array $info ) {
		if ( isset( $info['ValidSkinNames'] ) ) {
			foreach ( $info['ValidSkinNames'] as $skinKey => $data ) {
				if ( isset( $data['args'][0]['templateDirectory'] ) ) {
					$templateDirectory = $data['args'][0]['templateDirectory'];
					$correctedPath = $dir . '/' . $templateDirectory;
					// Historically the template directory was relative to core
					// but it really should've been relative to the skin directory.
					// If the path exists relative to the skin directory, assume that
					// is what was intended. Otherwise fall back on the previous behavior
					// of having it relative to core.
					if ( is_dir( $correctedPath ) ) {
						$data['args'][0]['templateDirectory'] = $correctedPath;
					} else {
						$data['args'][0]['templateDirectory'] = $templateDirectory;
						wfDeprecatedMsg(
							'Template directory should be relative to skin or omitted for skin ' . $skinKey,
							'1.37'
						);
					}
				} elseif ( isset( $data['args'][0] ) ) {
					// If not set, we set a sensible default.
					$data['args'][0]['templateDirectory'] = $dir . '/templates';
				}
				$this->globals['wgValidSkinNames'][$skinKey] = $data;
			}
		}
	}

	/**
	 * @param string $dir
	 * @param array $info
	 */
	protected function extractSkinImportPaths( $dir, array $info ) {
		if ( isset( $info['SkinLessImportPaths'] ) ) {
			foreach ( $info['SkinLessImportPaths'] as $skin => $subpath ) {
				$this->attributes['SkinLessImportPaths'][$skin] = "$dir/$subpath";
			}
		}
	}

	/**
	 * @param string $path
	 * @param array $info
	 * @return string Name of thing
	 * @throws Exception
	 */
	protected function extractCredits( $path, array $info ) {
		$credits = [
			'path' => $path,
			'type' => 'other',
		];
		foreach ( self::CREDIT_ATTRIBS as $attr ) {
			if ( isset( $info[$attr] ) ) {
				$credits[$attr] = $info[$attr];
			}
		}

		$name = $credits['name'];

		// If someone is loading the same thing twice, throw
		// a nice error (T121493)
		if ( isset( $this->credits[$name] ) ) {
			$firstPath = $this->credits[$name]['path'];
			$secondPath = $credits['path'];
			throw new Exception( "It was attempted to load $name twice, from $firstPath and $secondPath." );
		}

		$this->credits[$name] = $credits;

		return $name;
	}

	/**
	 * Set configuration settings for manifest_version == 1
	 * @todo In the future, this should be done via Config interfaces
	 *
	 * @param array $info
	 */
	protected function extractConfig1( array $info ) {
		if ( isset( $info['config'] ) ) {
			if ( isset( $info['config']['_prefix'] ) ) {
				$prefix = $info['config']['_prefix'];
				unset( $info['config']['_prefix'] );
			} else {
				$prefix = 'wg';
			}
			foreach ( $info['config'] as $key => $val ) {
				if ( $key[0] !== '@' ) {
					$this->addConfigGlobal( "$prefix$key", $val, $info['name'] );
				}
			}
		}
	}

	/**
	 * Applies a base path to the given string or string array.
	 *
	 * @param string[] $value
	 * @param string $dir
	 *
	 * @return string[]
	 */
	private function applyPath( array $value, string $dir ): array {
		$result = [];

		foreach ( $value as $k => $v ) {
			$result[$k] = $dir . '/' . $v;
		}
		return $result;
	}

	/**
	 * Set configuration settings for manifest_version == 2
	 * @todo In the future, this should be done via Config interfaces
	 *
	 * @param array $info
	 * @param string $dir
	 */
	protected function extractConfig2( array $info, $dir ) {
		$prefix = $info['config_prefix'] ?? 'wg';
		if ( isset( $info['config'] ) ) {
			foreach ( $info['config'] as $key => $data ) {
				if ( !array_key_exists( 'value', $data ) ) {
					throw new UnexpectedValueException( "Missing value for config $key" );
				}

				$value = $data['value'];
				if ( isset( $data['path'] ) && $data['path'] ) {
					if ( is_array( $value ) ) {
						$value = $this->applyPath( $value, $dir );
					} else {
						$value = "$dir/$value";
					}
				}
				if ( isset( $data['merge_strategy'] ) ) {
					$value[ExtensionRegistry::MERGE_STRATEGY] = $data['merge_strategy'];
				}
				$this->addConfigGlobal( "$prefix$key", $value, $info['name'] );
				$data['providedby'] = $info['name'];
				if ( isset( $info['ConfigRegistry'][0] ) ) {
					$data['configregistry'] = array_keys( $info['ConfigRegistry'] )[0];
				}
			}
		}
	}

	/**
	 * Helper function to set a value to a specific global, if it isn't set already.
	 *
	 * @param string $key The config key with the prefix and anything
	 * @param mixed $value The value of the config
	 * @param string $extName Name of the extension
	 */
	private function addConfigGlobal( $key, $value, $extName ) {
		if ( array_key_exists( $key, $this->globals ) ) {
			throw new RuntimeException(
				"The configuration setting '$key' was already set by MediaWiki core or"
				. " another extension, and cannot be set again by $extName." );
		}
		$this->globals[$key] = $value;
	}

	protected function extractPathBasedGlobal( $global, $dir, $paths ) {
		foreach ( $paths as $path ) {
			$this->globals[$global][] = "$dir/$path";
		}
	}

	/**
	 * Stores $value to $array; using array_merge_recursive() if $array already contains $name
	 *
	 * @param string $path
	 * @param string $name
	 * @param array $value
	 * @param array &$array
	 * @throws InvalidArgumentException
	 */
	protected function storeToArrayRecursive( $path, $name, $value, &$array ) {
		if ( !is_array( $value ) ) {
			throw new InvalidArgumentException( "The value for '$name' should be an array (from $path)" );
		}
		if ( isset( $array[$name] ) ) {
			$array[$name] = array_merge_recursive( $array[$name], $value );
		} else {
			$array[$name] = $value;
		}
	}

	/**
	 * Stores $value to $array; using array_merge() if $array already contains $name
	 *
	 * @param string $path
	 * @param string $name
	 * @param array $value
	 * @param array &$array
	 * @throws InvalidArgumentException
	 */
	protected function storeToArray( $path, $name, $value, &$array ) {
		if ( !is_array( $value ) ) {
			throw new InvalidArgumentException( "The value for '$name' should be an array (from $path)" );
		}
		if ( isset( $array[$name] ) ) {
			$array[$name] = array_merge( $array[$name], $value );
		} else {
			$array[$name] = $value;
		}
	}

	/**
	 * @deprecated since 1.39, use getExtractedAutoloadInfo instead
	 *
	 * @param string $dir
	 * @param array $info
	 *
	 * @return array
	 */
	public function getExtraAutoloaderPaths( $dir, array $info ) {
		wfDeprecated( __METHOD__, '1.39' );
		$paths = [];
		if ( isset( $info['load_composer_autoloader'] ) && $info['load_composer_autoloader'] === true ) {
			$paths[] = "$dir/vendor/autoload.php";
		}
		return $paths;
	}

	/**
	 * Returns the extracted autoload info.
	 * The autoload info is returned as an associative array with three keys:
	 * - files: a list of files to load, for use with Autoloader::loadFile()
	 * - classes: a map of class names to files, for use with Autoloader::registerClass()
	 * - namespaces: a map of namespace names to directories, for use
	 *   with Autoloader::registerNamespace()
	 *
	 * @since 1.39
	 *
	 * @param bool $includeDev
	 *
	 * @return array[] The autoload info.
	 */
	public function getExtractedAutoloadInfo( bool $includeDev = false ): array {
		$autoload = $this->autoload;

		if ( $includeDev ) {
			$autoload['classes'] += $this->autoloadDev['classes'];
			$autoload['namespaces'] += $this->autoloadDev['namespaces'];

			// NOTE: This is here for completeness. Per MW 1.39,
			//       $this->autoloadDev['files'] is always empty.
			//       So avoid the performance hit of array_merge().
			if ( !empty( $this->autoloadDev['files'] ) ) {
				// NOTE: Don't use += with numeric keys!
				//       Could use PHPUtils::pushArray.
				$autoload['files'] = array_merge(
					$autoload['files'],
					$this->autoloadDev['files']
				);
			}
		}

		return $autoload;
	}

	/**
	 * @param array $info
	 * @param string $dir
	 */
	private function extractAutoload( array $info, string $dir ) {
		if ( isset( $info['load_composer_autoloader'] ) && $info['load_composer_autoloader'] === true ) {
			$file = "$dir/vendor/autoload.php";
			if ( file_exists( $file ) ) {
				$this->autoload['files'][] = $file;
			}
		}

		if ( isset( $info['AutoloadClasses'] ) ) {
			$paths = $this->applyPath( $info['AutoloadClasses'], $dir );
			$this->autoload['classes'] += $paths;
		}

		if ( isset( $info['AutoloadNamespaces'] ) ) {
			$paths = $this->applyPath( $info['AutoloadNamespaces'], $dir );
			$this->autoload['namespaces'] += $paths;
		}

		if ( isset( $info['TestAutoloadClasses'] ) ) {
			$paths = $this->applyPath( $info['TestAutoloadClasses'], $dir );
			$this->autoloadDev['classes'] += $paths;
		}

		if ( isset( $info['TestAutoloadNamespaces'] ) ) {
			$paths = $this->applyPath( $info['TestAutoloadNamespaces'], $dir );
			$this->autoloadDev['namespaces'] += $paths;
		}
	}
}
