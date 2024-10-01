<?php

/**
 * Parse and compile Less files into CSS
 */
class Less_Parser {

	/**
	 * Default parser options
	 * @var array<string,mixed>
	 */
	public static $default_options = [
		'compress'				=> false, // option - whether to compress
		'strictUnits'			=> false, // whether units need to evaluate correctly
		'strictMath'			=> false, // whether math has to be within parenthesis
		'relativeUrls'			=> true, // option - whether to adjust URL's to be relative
		'urlArgs'				=> '', // whether to add args into url tokens
		'numPrecision'			=> 8,

		'import_dirs'			=> [],

		// Override how imported file names are resolved.
		//
		// This legacy calllback exposes internal objects and their implementation
		// details and is therefore deprecated. Use Less_Parser::SetImportDirs instead
		// to override the resolution of imported file names.
		//
		// Example:
		//
		//     $parser = new Less_Parser( [
		//       'import_callback' => function ( $importNode ) {
		//            $path = $importNode->getPath();
		//            if ( $path === 'special.less' ) {
		//                return [ $mySpecialFilePath, null ];
		//            }
		//       }
		//     ] );
		//
		// @since 1.5.1
		// @deprecated since 4.3.0
		// @see Less_Environment::callImportCallback
		// @see Less_Parser::SetImportDirs
		//
		'import_callback'		=> null,
		'cache_dir'				=> null,
		'cache_method'			=> 'serialize', // false, 'serialize', 'callback';
		'cache_callback_get'	=> null,
		'cache_callback_set'	=> null,

		'sourceMap'				=> false, // whether to output a source map
		'sourceMapBasepath'		=> null,
		'sourceMapWriteTo'		=> null,
		'sourceMapURL'			=> null,

		'indentation' 			=> '  ',

		'plugins'				=> [],
		'functions'             => [],

	];

	/** @var array{compress:bool,strictUnits:bool,strictMath:bool,relativeUrls:bool,urlArgs:string,numPrecision:int,import_dirs:array,import_callback:null|callable,indentation:string} */
	public static $options = [];

	/** @var Less_Environment */
	private static $envCompat;

	private $input;					// Less input string
	private $input_len;				// input string length
	private $pos;					// current index in `input`
	private $saveStack = [];	// holds state for backtracking
	private $furthest;
	private $mb_internal_encoding = ''; // for remember exists value of mbstring.internal_encoding

	private $autoCommentAbsorb = true;
	/**
	 * @var array<array{index:int,text:string,isLineComment:bool}>
	 */
	private $commentStore = [];

	/**
	 * @var Less_Environment
	 */
	private $env;

	protected $rules = [];

	/**
	 * Evaluated ruleset created by `getCss()`. Stored for potential use in `getVariables()`
	 * @var Less_Tree[]|null
	 */
	private $cachedEvaldRules;

	public static $has_extends = false;

	public static $next_id = 0;

	/**
	 * Filename to contents of all parsed the files
	 *
	 * @var array
	 */
	public static $contentsMap = [];

	/**
	 * @param Less_Environment|array|null $env
	 */
	public function __construct( $env = null ) {
		// Top parser on an import tree must be sure there is one "env"
		// which will then be passed around by reference.
		if ( $env instanceof Less_Environment ) {
			$this->env = $env;
			self::$envCompat = $this->env;
		} else {
			$this->Reset( $env );
		}

		// mbstring.func_overload > 1 bugfix
		// The encoding value must be set for each source file,
		// therefore, to conserve resources and improve the speed of this design is taken here
		if ( ini_get( 'mbstring.func_overload' ) ) {
			$this->mb_internal_encoding = ini_get( 'mbstring.internal_encoding' );
			@ini_set( 'mbstring.internal_encoding', 'ascii' );
		}
	}

	/**
	 * Reset the parser state completely
	 */
	public function Reset( $options = null ) {
		$this->rules = [];
		$this->cachedEvaldRules = null;
		self::$has_extends = false;
		self::$contentsMap = [];

		$this->env = new Less_Environment();
		self::$envCompat = $this->env;

		// set new options
		$this->SetOptions( self::$default_options );
		if ( is_array( $options ) ) {
			$this->SetOptions( $options );
		}

		$this->env->Init();
	}

	/**
	 * Set one or more compiler options
	 *  options: import_dirs, cache_dir, cache_method
	 */
	public function SetOptions( $options ) {
		foreach ( $options as $option => $value ) {
			$this->SetOption( $option, $value );
		}
	}

	/**
	 * Set one compiler option
	 */
	public function SetOption( $option, $value ) {
		switch ( $option ) {
			case 'strictMath':
				$this->env->strictMath = (bool)$value;
				self::$options[$option] = $value;
				return;

			case 'import_dirs':
				$this->SetImportDirs( $value );
				return;

			case 'import_callback':
				$this->env->importCallback = $value;
				return;

			case 'cache_dir':
				if ( is_string( $value ) ) {
					Less_Cache::SetCacheDir( $value );
					Less_Cache::CheckCacheDir();
				}
				return;
			case 'functions':
				foreach ( $value as $key => $function ) {
					$this->registerFunction( $key, $function );
				}
				return;
		}

		self::$options[$option] = $value;
	}

	/**
	 * Registers a new custom function
	 *
	 * @param string $name function name
	 * @param callable $callback callback
	 */
	public function registerFunction( $name, $callback ) {
		$this->env->functions[$name] = $callback;
	}

	/**
	 * Removed an already registered function
	 *
	 * @param string $name function name
	 */
	public function unregisterFunction( $name ) {
		if ( isset( $this->env->functions[$name] ) ) {
			unset( $this->env->functions[$name] );
		}
	}

	/**
	 * Get the current css buffer
	 *
	 * @return string
	 */
	public function getCss() {
		$precision = ini_get( 'precision' );
		@ini_set( 'precision', '16' );
		$locale = setlocale( LC_NUMERIC, 0 );
		setlocale( LC_NUMERIC, "C" );

		try {
			$root = new Less_Tree_Ruleset( null, $this->rules );
			$root->root = true;
			$root->firstRoot = true;

			$importVisitor = new Less_ImportVisitor( $this->env );
			$importVisitor->run( $root );

			$this->PreVisitors( $root );

			self::$has_extends = false;
			$evaldRoot = $root->compile( $this->env );

			$this->cachedEvaldRules = $evaldRoot->rules;

			$this->PostVisitors( $evaldRoot );

			if ( self::$options['sourceMap'] ) {
				$generator = new Less_SourceMap_Generator( $evaldRoot, self::$contentsMap, self::$options );
				// will also save file
				// FIXME: should happen somewhere else?
				$css = $generator->generateCSS();
			} else {
				$css = $evaldRoot->toCSS();
			}

			if ( self::$options['compress'] ) {
				$css = preg_replace( '/(^(\s)+)|((\s)+$)/', '', $css );
			}

		} catch ( Exception $exc ) {
			// Intentional fall-through so we can reset environment
		}

		// reset php settings
		@ini_set( 'precision', $precision );
		setlocale( LC_NUMERIC, $locale );

		// If you previously defined $this->mb_internal_encoding
		// is required to return the encoding as it was before
		if ( $this->mb_internal_encoding != '' ) {
			@ini_set( "mbstring.internal_encoding", $this->mb_internal_encoding );
			$this->mb_internal_encoding = '';
		}

		// Rethrow exception after we handled resetting the environment
		if ( !empty( $exc ) ) {
			throw $exc;
		}

		return $css;
	}

	public function findValueOf( $varName ) {
		$rules = $this->cachedEvaldRules ?? $this->rules;

		foreach ( $rules as $rule ) {
			if ( isset( $rule->variable ) && ( $rule->variable == true ) && ( str_replace( "@", "", $rule->name ) == $varName ) ) {
				return $this->getVariableValue( $rule );
			}
		}
		return null;
	}

	/**
	 * Gets the private rules variable and returns an array of the found variables
	 * it uses a helper method getVariableValue() that contains the logic ot fetch the value
	 * from the rule object
	 *
	 * @return array
	 */
	public function getVariables() {
		$variables = [];

		$not_variable_type = [
			Less_Tree_Comment::class, // this include less comments ( // ) and css comments (/* */)
			Less_Tree_Import::class, // do not search variables in included files @import
			Less_Tree_Ruleset::class, // selectors (.someclass, #someid, …)
			Less_Tree_Operation::class,
		];

		$rules = $this->cachedEvaldRules ?? $this->rules;

		foreach ( $rules as $key => $rule ) {
			if ( in_array( get_class( $rule ), $not_variable_type ) ) {
				continue;
			}

			// Note: it seems $rule is always Less_Tree_Rule when variable = true
			if ( $rule instanceof Less_Tree_Rule && $rule->variable ) {
				$variables[$rule->name] = $this->getVariableValue( $rule );
			} else {
				if ( $rule instanceof Less_Tree_Comment ) {
					$variables[] = $this->getVariableValue( $rule );
				}
			}
		}
		return $variables;
	}

	public function findVarByName( $var_name ) {
		$rules = $this->cachedEvaldRules ?? $this->rules;

		foreach ( $rules as $rule ) {
			if ( isset( $rule->variable ) && ( $rule->variable == true ) ) {
				if ( $rule->name == $var_name ) {
					return $this->getVariableValue( $rule );
				}
			}
		}
		return null;
	}

	/**
	 * This method gets the value of the less variable from the rules object.
	 * Since the objects vary here we add the logic for extracting the css/less value.
	 *
	 * @param Less_Tree $var
	 * @return string
	 */
	private function getVariableValue( Less_Tree $var ) {
		switch ( get_class( $var ) ) {
			case Less_Tree_Color::class:
				return $this->rgb2html( $var->rgb );
			case Less_Tree_Variable::class:
				return $this->findVarByName( $var->name );
			case Less_Tree_Keyword::class:
				return $var->value;
			case Less_Tree_Url::class:
				// Based on Less_Tree_Url::genCSS()
				// Recurse to serialize the Less_Tree_Quoted value
				return 'url(' . $this->getVariableValue( $var->value ) . ')';
			case Less_Tree_Rule::class:
				return $this->getVariableValue( $var->value );
			case Less_Tree_Value::class:
				$values = [];
				foreach ( $var->value as $sub_value ) {
					$values[] = $this->getVariableValue( $sub_value );
				}
				return implode( ' ', $values );
			case Less_Tree_Quoted::class:
				return $var->quote . $var->value . $var->quote;
			case Less_Tree_Dimension::class:
				$value = $var->value;
				if ( $var->unit && $var->unit->numerator ) {
					$value .= $var->unit->numerator[0];
				}
				return $value;
			case Less_Tree_Expression::class:
				$values = [];
				foreach ( $var->value as $item ) {
					$values[] = $this->getVariableValue( $item );
				}
				return implode( ' ', $values );
			case Less_Tree_Operation::class:
				throw new Exception( 'getVariables() require Less to be compiled. please use $parser->getCss() before calling getVariables()' );
			case Less_Tree_Unit::class:
			case Less_Tree_Comment::class:
			case Less_Tree_Import::class:
			case Less_Tree_Ruleset::class:
			default:
				throw new Exception( "type missing in switch/case getVariableValue for " . get_class( $var ) );
		}
	}

	private function rgb2html( $r, $g = -1, $b = -1 ) {
		if ( is_array( $r ) && count( $r ) == 3 ) {
			[ $r, $g, $b ] = $r;
		}

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	/**
	 * Run pre-compile visitors
	 */
	private function PreVisitors( $root ) {
		if ( self::$options['plugins'] ) {
			foreach ( self::$options['plugins'] as $plugin ) {
				if ( !empty( $plugin->isPreEvalVisitor ) ) {
					$plugin->run( $root );
				}
			}
		}
	}

	/**
	 * Run post-compile visitors
	 */
	private function PostVisitors( $evaldRoot ) {
		$visitors = [];
		$visitors[] = new Less_Visitor_joinSelector();
		if ( self::$has_extends ) {
			$visitors[] = new Less_Visitor_processExtends();
		}
		$visitors[] = new Less_Visitor_toCSS();

		if ( self::$options['plugins'] ) {
			foreach ( self::$options['plugins'] as $plugin ) {
				if ( property_exists( $plugin, 'isPreEvalVisitor' ) && $plugin->isPreEvalVisitor ) {
					continue;
				}

				if ( property_exists( $plugin, 'isPreVisitor' ) && $plugin->isPreVisitor ) {
					array_unshift( $visitors, $plugin );
				} else {
					$visitors[] = $plugin;
				}
			}
		}

		for ( $i = 0; $i < count( $visitors ); $i++ ) {
			$visitors[$i]->run( $evaldRoot );
		}
	}

	/**
	 * Parse a Less string
	 *
	 * @throws Less_Exception_Parser If the compiler encounters invalid syntax
	 * @param string $str The string to convert
	 * @param string|null $file_uri The url of the file
	 * @return $this
	 */
	public function parse( $str, $file_uri = null ) {
		if ( !$file_uri ) {
			$uri_root = '';
			$filename = 'anonymous-file-' . self::$next_id++ . '.less';
		} else {
			$file_uri = self::WinPath( $file_uri );
			$filename = $file_uri;
			$uri_root = dirname( $file_uri );
		}

		$previousFileInfo = $this->env->currentFileInfo;
		$uri_root = self::WinPath( $uri_root );
		$this->SetFileInfo( $filename, $uri_root );

		$this->input = $str;
		$this->_parse();

		if ( $previousFileInfo ) {
			$this->env->currentFileInfo = $previousFileInfo;
		}

		return $this;
	}

	/**
	 * Parse a Less string from a given file
	 *
	 * @throws Less_Exception_Parser If the compiler encounters invalid syntax
	 * @param string $filename The file to parse
	 * @param string $uri_root The url of the file
	 * @param bool $returnRoot Indicates whether the return value should be a css string a root node
	 * @return Less_Tree_Ruleset|$this
	 */
	public function parseFile( $filename, $uri_root = '', $returnRoot = false ) {
		if ( !file_exists( $filename ) ) {
			$this->Error( sprintf( 'File `%s` not found.', $filename ) );
		}

		// fix uri_root?
		// Instead of The mixture of file path for the first argument and directory path for the second argument has bee
		if ( !$returnRoot && !empty( $uri_root ) && basename( $uri_root ) == basename( $filename ) ) {
			$uri_root = dirname( $uri_root );
		}

		$previousFileInfo = $this->env->currentFileInfo;

		if ( $filename ) {
			$filename = self::AbsPath( $filename, true );
		}
		$uri_root = self::WinPath( $uri_root );

		$this->SetFileInfo( $filename, $uri_root );

		$this->env->addParsedFile( $filename );

		if ( $returnRoot ) {
			$rules = $this->GetRules( $filename );
			$return = new Less_Tree_Ruleset( null, $rules );
		} else {
			$this->_parse( $filename );
			$return = $this;
		}

		if ( $previousFileInfo ) {
			$this->env->currentFileInfo = $previousFileInfo;
		}

		return $return;
	}

	/**
	 * Allows a user to set variables values
	 * @param array $vars
	 * @return $this
	 */
	public function ModifyVars( $vars ) {
		$this->input = self::serializeVars( $vars );
		$this->_parse();

		return $this;
	}

	/**
	 * @param string $filename
	 * @param string $uri_root
	 */
	public function SetFileInfo( $filename, $uri_root = '' ) {
		$filename = Less_Environment::normalizePath( $filename );
		$dirname = preg_replace( '/[^\/\\\\]*$/', '', $filename );

		if ( !empty( $uri_root ) ) {
			$uri_root = rtrim( $uri_root, '/' ) . '/';
		}

		$currentFileInfo = [];

		// entry info
		if ( isset( $this->env->currentFileInfo ) ) {
			$currentFileInfo['entryPath'] = $this->env->currentFileInfo['entryPath'];
			$currentFileInfo['entryUri'] = $this->env->currentFileInfo['entryUri'];
			$currentFileInfo['rootpath'] = $this->env->currentFileInfo['rootpath'];

		} else {
			$currentFileInfo['entryPath'] = $dirname;
			$currentFileInfo['entryUri'] = $uri_root;
			$currentFileInfo['rootpath'] = $dirname;
		}

		$currentFileInfo['currentDirectory'] = $dirname;
		$currentFileInfo['currentUri'] = $uri_root . basename( $filename );
		$currentFileInfo['filename'] = $filename;
		$currentFileInfo['uri_root'] = $uri_root;

		// inherit reference
		if ( isset( $this->env->currentFileInfo['reference'] ) && $this->env->currentFileInfo['reference'] ) {
			$currentFileInfo['reference'] = true;
		}

		$this->env->currentFileInfo = $currentFileInfo;
	}

	/**
	 * @deprecated 1.5.1.2
	 */
	public function SetCacheDir( $dir ) {
		if ( !file_exists( $dir ) ) {
			if ( mkdir( $dir ) ) {
				return true;
			}
			throw new Less_Exception_Parser( 'Less.php cache directory couldn\'t be created: ' . $dir );

		} elseif ( !is_dir( $dir ) ) {
			throw new Less_Exception_Parser( 'Less.php cache directory doesn\'t exist: ' . $dir );

		} elseif ( !is_writable( $dir ) ) {
			throw new Less_Exception_Parser( 'Less.php cache directory isn\'t writable: ' . $dir );

		} else {
			$dir = self::WinPath( $dir );
			Less_Cache::$cache_dir = rtrim( $dir, '/' ) . '/';
			return true;
		}
	}

	/**
	 * Set a list of directories or callbacks the parser should use for determining import paths
	 *
	 * Import closures are called with a single `$path` argument containing the unquoted `@import`
	 * string an input LESS file. The string is unchanged, except for a statically appended ".less"
	 * suffix if the basename does not yet contain a dot. If a dot is present in the filename, you
	 * are responsible for choosing whether to expand "foo.bar" to "foo.bar.less". If your callback
	 * can handle this import statement, return an array with an absolute file path and an optional
	 * URI path, or return void/null to indicate that your callback does not handle this import
	 * statement.
	 *
	 * Example:
	 *
	 *     function ( $path ) {
	 *         if ( $path === 'virtual/something.less' ) {
	 *             return [ '/srv/elsewhere/thing.less', null ];
	 *         }
	 *     }
	 *
	 *
	 * @param array<string|callable> $dirs The key should be a server directory from which LESS
	 * files may be imported. The value is an optional public URL or URL base path that corresponds to
	 * the same directory (use empty string otherwise). The value may also be a closure, in
	 * which case the key is ignored.
	 */
	public function SetImportDirs( $dirs ) {
		self::$options['import_dirs'] = [];

		foreach ( $dirs as $path => $uri_root ) {

			$path = self::WinPath( $path );
			if ( !empty( $path ) ) {
				$path = rtrim( $path, '/' ) . '/';
			}

			if ( !is_callable( $uri_root ) ) {
				$uri_root = self::WinPath( $uri_root );
				if ( !empty( $uri_root ) ) {
					$uri_root = rtrim( $uri_root, '/' ) . '/';
				}
			}

			self::$options['import_dirs'][$path] = $uri_root;
		}
	}

	/**
	 * @param string|null $file_path
	 */
	private function _parse( $file_path = null ) {
		$this->rules = array_merge( $this->rules, $this->GetRules( $file_path ) );
	}

	/**
	 * Return the results of parsePrimary for $file_path
	 * Use cache and save cached results if possible
	 *
	 * @param string|null $file_path
	 */
	private function GetRules( $file_path ) {
		$this->SetInput( $file_path );

		$cache_file = $this->CacheFile( $file_path );
		if ( $cache_file ) {
			if ( self::$options['cache_method'] == 'callback' ) {
				$callback = self::$options['cache_callback_get'];
				if ( is_callable( $callback ) ) {
					$cache = $callback( $this, $file_path, $cache_file );

					if ( $cache ) {
						$this->UnsetInput();
						return $cache;
					}
				}

			} elseif ( file_exists( $cache_file ) ) {
				switch ( self::$options['cache_method'] ) {

					// Using serialize
					case 'serialize':
						$cache = unserialize( file_get_contents( $cache_file ) );
						if ( $cache ) {
							touch( $cache_file );
							$this->UnsetInput();
							return $cache;
						}
						break;
				}
			}
		}
		$this->skipWhitespace( 0 );
		$rules = $this->parsePrimary();

		if ( $this->pos < $this->input_len ) {
			throw new Less_Exception_Chunk( $this->input, null, $this->furthest, $this->env->currentFileInfo );
		}

		$this->UnsetInput();

		// save the cache
		if ( $cache_file ) {
			if ( self::$options['cache_method'] == 'callback' ) {
				$callback = self::$options['cache_callback_set'];
				if ( is_callable( $callback ) ) {
					$callback( $this, $file_path, $cache_file, $rules );
				}
			} else {
				switch ( self::$options['cache_method'] ) {
					case 'serialize':
						file_put_contents( $cache_file, serialize( $rules ) );
						break;
				}

				Less_Cache::CleanCache();
			}
		}

		return $rules;
	}

	/**
	 * @internal since 4.3.0 No longer a public API.
	 */
	public function SetInput( $file_path ) {
		// Set up the input buffer
		if ( $file_path ) {
			$this->input = file_get_contents( $file_path );
		}

		$this->pos = $this->furthest = 0;

		// Remove potential UTF Byte Order Mark
		$this->input = preg_replace( '/\\G\xEF\xBB\xBF/', '', $this->input );
		$this->input_len = strlen( $this->input );

		if ( self::$options['sourceMap'] && $this->env->currentFileInfo ) {
			$uri = $this->env->currentFileInfo['currentUri'];
			self::$contentsMap[$uri] = $this->input;
		}
	}

	/**
	 * @internal since 4.3.0 No longer a public API.
	 */
	public function UnsetInput() {
		// Free up some memory
		$this->input = $this->pos = $this->input_len = $this->furthest = null;
		$this->saveStack = [];
	}

	/**
	 * @internal since 4.3.0 Use Less_Cache instead.
	 */
	public function CacheFile( $file_path ) {
		if ( $file_path && $this->CacheEnabled() ) {

			$env = get_object_vars( $this->env );
			unset( $env['frames'] );

			$parts = [];
			$parts[] = $file_path;
			$parts[] = filesize( $file_path );
			$parts[] = filemtime( $file_path );
			$parts[] = $env;
			$parts[] = Less_Version::cache_version;
			$parts[] = self::$options['cache_method'];
			return Less_Cache::$cache_dir . Less_Cache::$prefix . base_convert( sha1( json_encode( $parts ) ), 16, 36 ) . '.lesscache';
		}
	}

	/**
	 * @deprecated since 4.3.0 Use $parser->getParsedFiles() instead.
	 * @return string[]
	 */
	public static function AllParsedFiles() {
		return self::$envCompat->imports;
	}

	/**
	 * @since 4.3.0
	 * @return string[]
	 */
	public function getParsedFiles() {
		return $this->env->imports;
	}

	/**
	 * @internal since 4.3.0 No longer a public API.
	 */
	public function save() {
		$this->saveStack[] = $this->pos;
	}

	private function restore() {
		if ( $this->pos > $this->furthest ) {
			$this->furthest = $this->pos;
		}
		$this->pos = array_pop( $this->saveStack );
	}

	private function forget() {
		array_pop( $this->saveStack );
	}

	/**
	 * Determine if the character at the specified offset from the current position is a white space.
	 *
	 * @param int $offset
	 * @return bool
	 */
	private function isWhitespace( $offset = 0 ) {
		// @phan-suppress-next-line PhanParamSuspiciousOrder False positive
		return strpos( " \t\n\r\v\f", $this->input[$this->pos + $offset] ) !== false;
	}

	/**
	 * Match a single character in the input.
	 *
	 * @param string $tok
	 * @return string|null
	 * @see less-2.5.3.js#parserInput.$char
	 */
	private function matchChar( $tok ) {
		if ( ( $this->pos < $this->input_len ) && ( $this->input[$this->pos] === $tok ) ) {
			$this->skipWhitespace( 1 );
			return $tok;
		}
	}

	/**
	 * Match a regexp from the current start point
	 *
	 * @return string|array|null
	 * @see less-2.5.3.js#parserInput.$re
	 */
	private function matchReg( $tok ) {
		if ( preg_match( $tok, $this->input, $match, 0, $this->pos ) ) {
			$this->skipWhitespace( strlen( $match[0] ) );
			return count( $match ) === 1 ? $match[0] : $match;
		}
	}

	/**
	 * Match an exact string of characters.
	 *
	 * @param string $tok
	 * @return string|null
	 * @see less-2.5.3.js#parserInput.$str
	 */
	private function matchStr( $tok ) {
		$tokLength = strlen( $tok );
		if (
			( $this->pos < $this->input_len ) &&
			substr( $this->input, $this->pos, $tokLength ) === $tok
		) {
			$this->skipWhitespace( $tokLength );
			return $tok;
		}
	}

	/**
	 * Same as match(), but don't change the state of the parser,
	 * just return the match.
	 *
	 * @param string $tok
	 * @return int|false
	 */
	private function peekReg( $tok ) {
		return preg_match( $tok, $this->input, $match, 0, $this->pos );
	}

	/**
	 * @param string $tok
	 */
	private function peekChar( $tok ) {
		return ( $this->pos < $this->input_len ) && ( $this->input[$this->pos] === $tok );
	}

	/**
	 * @param int $length
	 * @see less-2.5.3.js#skipWhitespace
	 */
	private function skipWhitespace( $length ) {
		$this->pos += $length;

		for ( ; $this->pos < $this->input_len; $this->pos++ ) {
			$currentChar = $this->input[$this->pos];

			if ( $this->autoCommentAbsorb && $currentChar === '/' ) {
				$nextChar = $this->input[$this->pos + 1] ?? '';
				if ( $nextChar === '/' ) {
					$comment = [ 'index' => $this->pos, 'isLineComment' => true ];
					$nextNewLine = strpos( $this->input, "\n", $this->pos + 2 );
					if ( $nextNewLine === false ) {
						$nextNewLine = $this->input_len ?? 0;
					}
					$this->pos = $nextNewLine;
					$comment['text'] = substr( $this->input, $this->pos, $nextNewLine - $this->pos );
					$this->commentStore[] = $comment;
					continue;
				} elseif ( $nextChar === '*' ) {
					$nextStarSlash = strpos( $this->input, "*/", $this->pos + 2 );
					if ( $nextStarSlash !== false ) {
						$comment = [
							'index' => $this->pos,
							'text' => substr( $this->input, $this->pos, $nextStarSlash + 2 -
								$this->pos ),
							'isLineComment' => false,
						];
						$this->pos += strlen( $comment['text'] ) - 1;
						$this->commentStore[] = $comment;
						continue;
					}
				}
				break;
			}

			// Optimization: Skip over irrelevant chars without slow loop
			$skip = strspn( $this->input, " \n\t\r", $this->pos );
			if ( $skip ) {
				$this->pos += $skip - 1;
			}
			if ( !$skip && $this->pos < $this->input_len ) {
				break;
			}
		}
	}

	/**
	 * Parse a token from a regexp or method name string
	 *
	 * @param string $tok
	 * @param string|null $msg
	 * @see less-2.5.3.js#Parser.expect
	 */
	private function expect( $tok, $msg = null ) {
		if ( $tok[0] === '/' ) {
			$result = $this->matchReg( $tok );
		} else {
			$result = $this->$tok();
		}
		if ( $result !== null ) {
			return $result;
		}
		$this->Error( $msg ? "Expected '" . $tok . "' got '" . $this->input[$this->pos] . "'" : $msg );
	}

	/**
	 * @param string $tok
	 * @param string|null $msg
	 */
	private function expectChar( $tok, $msg = null ) {
		$result = $this->matchChar( $tok );
		if ( !$result ) {
			$msg = $msg ?: "Expected '" . $tok . "' got '" . $this->input[$this->pos] . "'";
			$this->Error( $msg );
		} else {
			return $result;
		}
	}

	//
	// Here in, the parsing rules/functions
	//
	// The basic structure of the syntax tree generated is as follows:
	//
	//   Ruleset ->  Rule -> Value -> Expression -> Entity
	//
	// Here's some LESS code:
	//
	//	.class {
	//	  color: #fff;
	//	  border: 1px solid #000;
	//	  width: @w + 4px;
	//	  > .child {...}
	//	}
	//
	// And here's what the parse tree might look like:
	//
	//	 Ruleset (Selector '.class', [
	//		 Rule ("color",  Value ([Expression [Color #fff]]))
	//		 Rule ("border", Value ([Expression [Dimension 1px][Keyword "solid"][Color #000]]))
	//		 Rule ("width",  Value ([Expression [Operation "+" [Variable "@w"][Dimension 4px]]]))
	//		 Ruleset (Selector [Element '>', '.child'], [...])
	//	 ])
	//
	//  In general, most rules will try to parse a token with the `$()` function, and if the return
	//  value is truly, will return a new node, of the relevant type. Sometimes, we need to check
	//  first, before parsing, that's when we use `peek()`.
	//

	//
	// The `primary` rule is the *entry* and *exit* point of the parser.
	// The rules here can appear at any level of the parse tree.
	//
	// The recursive nature of the grammar is an interplay between the `block`
	// rule, which represents `{ ... }`, the `ruleset` rule, and this `primary` rule,
	// as represented by this simplified grammar:
	//
	//	 primary  →  (ruleset | rule)+
	//	 ruleset  →  selector+ block
	//	 block	→  '{' primary '}'
	//
	// Only at one point is the primary rule not called from the
	// block rule: at the root level.
	//
	// @see less-2.5.3.js#parsers.primary
	private function parsePrimary() {
		$root = [];

		while ( true ) {

			while ( true ) {
				$node = $this->parseComment();
				if ( !$node ) {
					break;
				}
				$root[] = $node;
			}

			// always process comments before deciding if finished
			if ( $this->pos >= $this->input_len ) {
				break;
			}

			if ( $this->peekChar( '}' ) ) {
				break;
			}

			$node = $this->parseExtend( true );
			if ( $node ) {
				$root = array_merge( $root, $node );
				continue;
			}

			$node = $this->parseMixinDefinition()
				// Optimisation: NameValue is specific to less.php
				?? $this->parseNameValue()
				?? $this->parseRule()
				?? $this->parseRuleset()
				?? $this->parseMixinCall()
				?? $this->parseRulesetCall()
				?? $this->parseDirective();

			if ( $node ) {
				$root[] = $node;
			} elseif ( !$this->matchReg( '/\\G[\s\n;]+/' ) ) {
				break;
			}

		}

		return $root;
	}

	/**
	 * comments are collected by the main parsing mechanism and then assigned to nodes
	 * where the current structure allows it
	 *
	 * @return Less_Tree_Comment|void
	 * @see less-2.5.3.js#parsers.comment
	 */
	private function parseComment() {
		$comment = array_shift( $this->commentStore );
		if ( $comment ) {
			return new Less_Tree_Comment(
				$comment['text'],
				$comment['isLineComment'],
				$comment['index'],
				$this->env->currentFileInfo
			);
		}
	}

	/**
	 * A string, which supports escaping " and '
	 *
	 *	 "milky way" 'he\'s the one!'
	 *
	 * @return Less_Tree_Quoted|null
	 * @see less-2.5.3.js#entities.quoted
	 */
	private function parseEntitiesQuoted() {
		// Optimization: Determine match potential without save()/restore() overhead
		// Optimization: Inline matchChar() here, with its skipWhitespace(1) call below
		$startChar = $this->input[$this->pos] ?? null;
		$isEscaped = $startChar === '~';
		if ( !$isEscaped && $startChar !== "'" && $startChar !== '"' ) {
			return;
		}

		$index = $this->pos;
		$this->save();

		if ( $isEscaped ) {
			$this->skipWhitespace( 1 );
			$startChar = $this->input[$this->pos] ?? null;
			if ( $startChar !== "'" && $startChar !== '"' ) {
				$this->restore();
				return;
			}
		}

		// Optimization: Inline matching of quotes for 8% overall speed up
		// on large LESS files. https://gerrit.wikimedia.org/r/939727
		// @see less-2.5.3.js#parserInput.$quoted
		$i = 1;
		while ( $this->pos + $i < $this->input_len ) {
			// Optimization: Skip over irrelevant chars without slow loop
			$i += strcspn( $this->input, "\n\r$startChar\\", $this->pos + $i );
			switch ( $this->input[$this->pos + $i++] ) {
				case "\\":
					$i++;
					break;
				case "\r":
				case "\n":
					break 2;
				case $startChar:
					$str = substr( $this->input, $this->pos, $i );
					$this->skipWhitespace( $i );
					$this->forget();
					return new Less_Tree_Quoted( $str[0], substr( $str, 1, -1 ), $isEscaped, $index, $this->env->currentFileInfo );
			}
		}

		$this->restore();
	}

	/**
	 * A catch-all word, such as:
	 *
	 *	 black border-collapse
	 *
	 * @return Less_Tree_Keyword|Less_Tree_Color|null
	 */
	private function parseEntitiesKeyword() {
		// $k = $this->matchReg('/\\G[_A-Za-z-][_A-Za-z0-9-]*/');
		$k = $this->matchReg( '/\\G%|\\G[_A-Za-z-][_A-Za-z0-9-]*/' );
		if ( $k ) {
			$color = Less_Tree_Color::fromKeyword( $k );
			if ( $color ) {
				return $color;
			}
			return new Less_Tree_Keyword( $k );
		}
	}

	//
	// A function call
	//
	//	 rgb(255, 0, 255)
	//
	// We also try to catch IE's `alpha()`, but let the `alpha` parser
	// deal with the details.
	//
	// The arguments are parsed with the `entities.arguments` parser.
	//
	// @see less-2.5.3.js#parsers.entities.call
	private function parseEntitiesCall() {
		$index = $this->pos;

		if ( $this->peekReg( '/\\Gurl\(/i' ) ) {
			return;
		}

		$this->save();

		$name = $this->matchReg( '/\\G([\w-]+|%|progid:[\w\.]+)\(/' );
		if ( !$name ) {
			$this->forget();
			return;
		}

		$name = $name[1];
		$nameLC = strtolower( $name );

		if ( $nameLC === 'alpha' ) {
			$alpha_ret = $this->parseAlpha();
			if ( $alpha_ret ) {
				return $alpha_ret;
			}
		}

		$args = $this->parseEntitiesArguments();

		if ( !$this->matchChar( ')' ) ) {
			$this->restore();
			return;
		}

		$this->forget();
		return new Less_Tree_Call( $name, $args, $index, $this->env->currentFileInfo );
	}

	/**
	 * Parse a list of arguments
	 *
	 * @return array<Less_Tree_Assignment|Less_Tree_Expression>
	 */
	private function parseEntitiesArguments() {
		$args = [];
		while ( true ) {
			$arg = $this->parseEntitiesAssignment() ?? $this->parseExpression();
			if ( !$arg ) {
				break;
			}

			$args[] = $arg;
			if ( !$this->matchChar( ',' ) ) {
				break;
			}
		}
		return $args;
	}

	/** @return Less_Tree_Dimension|Less_Tree_Color|Less_Tree_Quoted|Less_Tree_UnicodeDescriptor|null */
	private function parseEntitiesLiteral() {
		return $this->parseEntitiesDimension() ?? $this->parseEntitiesColor() ?? $this->parseEntitiesQuoted() ?? $this->parseUnicodeDescriptor();
	}

	/**
	 * Assignments are argument entities for calls.
	 *
	 * They are present in IE filter properties as shown below.
	 *
	 *	 filter: progid:DXImageTransform.Microsoft.Alpha( *opacity=50* )
	 *
	 * @return Less_Tree_Assignment|null
	 * @see less-2.5.3.js#parsers.entities.assignment
	 */
	private function parseEntitiesAssignment() {
		$key = $this->matchReg( '/\\G\w+(?=\s?=)/' );
		if ( !$key ) {
			return;
		}

		if ( !$this->matchChar( '=' ) ) {
			return;
		}

		$value = $this->parseEntity();
		if ( $value ) {
			return new Less_Tree_Assignment( $key, $value );
		}
	}

	//
	// Parse url() tokens
	//
	// We use a specific rule for urls, because they don't really behave like
	// standard function calls. The difference is that the argument doesn't have
	// to be enclosed within a string, so it can't be parsed as an Expression.
	//
	private function parseEntitiesUrl() {
		$char = $this->input[$this->pos] ?? null;

		$this->autoCommentAbsorb = false;
		// Optimisation: 'u' check is specific to less.php
		if ( $char !== 'u' || !$this->matchReg( '/\\Gurl\(/' ) ) {
			$this->autoCommentAbsorb = true;
			return;
		}

		$value = $this->parseEntitiesQuoted() ?? $this->parseEntitiesVariable() ?? $this->matchReg( '/\\Gdata\:.*?[^\)]+/' ) ?? $this->matchReg( '/\\G(?:(?:\\\\[\(\)\'"])|[^\(\)\'"])+/' ) ?? null;
		if ( !$value ) {
			$value = '';
		}
		$this->autoCommentAbsorb = true;
		$this->expectChar( ')' );

		if ( $value instanceof Less_Tree_Quoted || $value instanceof Less_Tree_Variable ) {
			return new Less_Tree_Url( $value, $this->env->currentFileInfo );
		}

		return new Less_Tree_Url( new Less_Tree_Anonymous( $value ), $this->env->currentFileInfo );
	}

	/**
	 * A Variable entity, such as `@fink`, in
	 *
	 *	 width: @fink + 2px
	 *
	 * We use a different parser for variable definitions,
	 * see `parsers.variable`.
	 *
	 * @return Less_Tree_Variable|null
	 * @see less-2.5.3.js#parsers.entities.variable
	 */
	private function parseEntitiesVariable() {
		$index = $this->pos;
		if ( $this->peekChar( '@' ) ) {
			$name = $this->matchReg( '/\\G@@?[\w-]+/' );
			if ( $name ) {
				return new Less_Tree_Variable( $name, $index, $this->env->currentFileInfo );
			}
		}
	}

	/**
	 * A variable entity using the protective `{}` e.g. `@{var}`.
	 *
	 * @return Less_Tree_Variable|null
	 */
	private function parseEntitiesVariableCurly() {
		$index = $this->pos;

		if ( $this->input_len > ( $this->pos + 1 ) && $this->input[$this->pos] === '@' ) {
			$curly = $this->matchReg( '/\\G@\{([\w-]+)\}/' );
			if ( $curly ) {
				return new Less_Tree_Variable( '@' . $curly[1], $index, $this->env->currentFileInfo );
			}
		}
	}

	/**
	 * A Hexadecimal color
	 *
	 *	 #4F3C2F
	 *
	 * `rgb` and `hsl` colors are parsed through the `entities.call` parser.
	 *
	 * @return Less_Tree_Color|null
	 */
	private function parseEntitiesColor() {
		if ( $this->peekChar( '#' ) ) {
			$rgb = $this->matchReg( '/\\G#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})/' );
			if ( $rgb ) {
				return new Less_Tree_Color( $rgb[1], 1, null, $rgb[0] );
			}
		}
	}

	/**
	 * A Dimension, that is, a number and a unit
	 *
	 *	 0.5em 95%
	 *
	 * @return Less_Tree_Dimension|null
	 */
	private function parseEntitiesDimension() {
		$c = @ord( $this->input[$this->pos] );

		// Is the first char of the dimension 0-9, '.', '+' or '-'
		if ( ( $c > 57 || $c < 43 ) || $c === 47 || $c == 44 ) {
			return;
		}

		$value = $this->matchReg( '/\\G([+-]?\d*\.?\d+)(%|[a-z]+)?/i' );
		if ( $value ) {
			if ( isset( $value[2] ) ) {
				return new Less_Tree_Dimension( $value[1], $value[2] );
			}
			return new Less_Tree_Dimension( $value[1] );
		}
	}

	/**
	 * A unicode descriptor, as is used in unicode-range
	 *
	 * U+0?? or U+00A1-00A9
	 *
	 * @return Less_Tree_UnicodeDescriptor|null
	 */
	private function parseUnicodeDescriptor() {
		// Optimization: Hardcode first char, to avoid matchReg() cost for common case
		$char = $this->input[$this->pos] ?? null;
		if ( $char !== 'U' ) {
			return;
		}

		$ud = $this->matchReg( '/\\G(U\+[0-9a-fA-F?]+)(\-[0-9a-fA-F?]+)?/' );
		if ( $ud ) {
			return new Less_Tree_UnicodeDescriptor( $ud[0] );
		}
	}

	/**
	 * JavaScript code to be evaluated
	 *
	 *	 `window.location.href`
	 *
	 * @return Less_Tree_JavaScript|null
	 * @see less-2.5.3.js#parsers.entities.javascript
	 */
	private function parseEntitiesJavascript() {
		// Optimization: Hardcode first char, to avoid save()/restore() overhead
		// Optimization: Inline matchChar(), with skipWhitespace(1) below
		$char = $this->input[$this->pos] ?? null;
		$isEscaped = $char === '~';
		if ( !$isEscaped && $char !== '`' ) {
			return;
		}

		$index = $this->pos;
		$this->save();

		if ( $isEscaped ) {
			$this->skipWhitespace( 1 );
			$char = $this->input[$this->pos] ?? null;
			if ( $char !== '`' ) {
				$this->restore();
				return;
			}
		}

		$this->skipWhitespace( 1 );
		$js = $this->matchReg( '/\\G[^`]*`/' );
		if ( $js ) {
			$this->forget();
			return new Less_Tree_JavaScript( substr( $js, 0, -1 ), $index, $isEscaped );
		}
		$this->restore();
	}

	//
	// The variable part of a variable definition. Used in the `rule` parser
	//
	//	 @fink:
	//
	// @see less-2.5.3.js#parsers.variable
	private function parseVariable() {
		if ( $this->peekChar( '@' ) ) {
			$name = $this->matchReg( '/\\G(@[\w-]+)\s*:/' );
			if ( $name ) {
				return $name[1];
			}
		}
	}

	//
	// The variable part of a variable definition. Used in the `rule` parser
	//
	// @fink();
	//
	// @see less-2.5.3.js#parsers.rulesetCall
	private function parseRulesetCall() {
		if ( $this->peekChar( '@' ) ) {
			$name = $this->matchReg( '/\\G(@[\w-]+)\s*\(\s*\)\s*;/' );
			if ( $name ) {
				return new Less_Tree_RulesetCall( $name[1] );
			}
		}
	}

	//
	// extend syntax - used to extend selectors
	//
	// @see less-2.5.3.js#parsers.extend
	private function parseExtend( $isRule = false ) {
		$index = $this->pos;
		$extendList = [];

		if ( !$this->matchStr( $isRule ? '&:extend(' : ':extend(' ) ) {
			return;
		}

		do {
			$option = null;
			$elements = [];
			while ( true ) {
				$option = $this->matchReg( '/\\G(all)(?=\s*(\)|,))/' );
				if ( $option ) {
					break;
				}
				$e = $this->parseElement();
				if ( !$e ) {
					break;
				}
				$elements[] = $e;
			}

			if ( $option ) {
				$option = $option[1];
			}

			$extendList[] = new Less_Tree_Extend( new Less_Tree_Selector( $elements ), $option, $index );

		} while ( $this->matchChar( "," ) );

		$this->expect( '/\\G\)/' );

		if ( $isRule ) {
			$this->expect( '/\\G;/' );
		}

		return $extendList;
	}

	//
	// A Mixin call, with an optional argument list
	//
	//	 #mixins > .square(#fff);
	//	 .rounded(4px, black);
	//	 .button;
	//
	// The `while` loop is there because mixins can be
	// namespaced, but we only support the child and descendant
	// selector for now.
	//
	private function parseMixinCall() {
		$char = $this->input[$this->pos] ?? null;
		if ( $char !== '.' && $char !== '#' ) {
			return;
		}

		$index = $this->pos;
		$this->save(); // stop us absorbing part of an invalid selector

		$elements = $this->parseMixinCallElements();

		if ( $elements ) {

			if ( $this->matchChar( '(' ) ) {
				$returned = $this->parseMixinArgs( true );
				$args = $returned['args'];
				$this->expectChar( ')' );
			} else {
				$args = [];
			}

			$important = $this->parseImportant();

			if ( $this->parseEnd() ) {
				$this->forget();
				return new Less_Tree_Mixin_Call( $elements, $args, $index, $this->env->currentFileInfo, $important );
			}
		}

		$this->restore();
	}

	private function parseMixinCallElements() {
		$elements = [];
		$c = null;

		while ( true ) {
			$elemIndex = $this->pos;
			$e = $this->matchReg( '/\\G[#.](?:[\w-]|\\\\(?:[A-Fa-f0-9]{1,6} ?|[^A-Fa-f0-9]))+/' );
			if ( !$e ) {
				break;
			}
			$elements[] = new Less_Tree_Element( $c, $e, $elemIndex, $this->env->currentFileInfo );
			$c = $this->matchChar( '>' );
		}

		return $elements;
	}

	/**
	 * @param bool $isCall
	 * @see less-2.5.3.js#parsers.mixin.args
	 */
	private function parseMixinArgs( $isCall ) {
		$expressions = [];
		$argsSemiColon = [];
		$isSemiColonSeperated = null;
		$argsComma = [];
		$expressionContainsNamed = null;
		$name = null;
		$returner = [ 'args' => [], 'variadic' => false ];
		$expand = false;

		$this->save();

		while ( true ) {
			if ( $isCall ) {
				$arg = $this->parseDetachedRuleset() ?? $this->parseExpression();
			} else {
				$this->commentStore = [];
				if ( $this->input[ $this->pos ] === '.' && $this->matchStr( '...' ) ) {
					$returner['variadic'] = true;
					if ( $this->matchChar( ";" ) && !$isSemiColonSeperated ) {
						$isSemiColonSeperated = true;
					}

					if ( $isSemiColonSeperated ) {
						$argsSemiColon[] = [ 'variadic' => true ];
					} else {
						$argsComma[] = [ 'variadic' => true ];
					}
					break;
				}
				$arg = $this->parseEntitiesVariable() ?? $this->parseEntitiesLiteral() ?? $this->parseEntitiesKeyword();
			}

			if ( !$arg ) {
				break;
			}

			$nameLoop = null;
			if ( $arg instanceof Less_Tree_Expression ) {
				$arg->throwAwayComments();
			}
			$value = $arg;
			$val = null;

			if ( $isCall ) {
				// Variable
				if ( $value instanceof Less_Tree_Expression && count( $arg->value ) == 1 ) {
					$val = $arg->value[0];
				}
			} else {
				$val = $arg;
			}

			if ( $val instanceof Less_Tree_Variable ) {

				if ( $this->matchChar( ':' ) ) {
					if ( $expressions ) {
						if ( $isSemiColonSeperated ) {
							$this->Error( 'Cannot mix ; and , as delimiter types' );
						}
						$expressionContainsNamed = true;
					}

					// we do not support setting a ruleset as a default variable - it doesn't make sense
					// However if we do want to add it, there is nothing blocking it, just don't error
					// and remove isCall dependency below
					$value = $this->parseDetachedRuleset() ?? $this->parseExpression();

					if ( !$value ) {
						if ( $isCall ) {
							$this->Error( 'could not understand value for named argument' );
						} else {
							$this->restore();
							$returner['args'] = [];
							return $returner;
						}
					}

					$nameLoop = ( $name = $val->name );
				} elseif ( $this->matchStr( '...' ) ) {
					if ( !$isCall ) {
						$returner['variadic'] = true;
						if ( $this->matchChar( ";" ) && !$isSemiColonSeperated ) {
							$isSemiColonSeperated = true;
						}
						if ( $isSemiColonSeperated ) {
							$argsSemiColon[] = [ 'name' => $arg->name, 'variadic' => true ];
						} else {
							$argsComma[] = [ 'name' => $arg->name, 'variadic' => true ];
						}
						break;
					} else {
						$expand = true;
					}
				} elseif ( !$isCall ) {
					$name = $nameLoop = $val->name;
					$value = null;
				}
			}

			if ( $value ) {
				$expressions[] = $value;
			}

			$argsComma[] = [ 'name' => $nameLoop, 'value' => $value, 'expand' => $expand ];

			if ( $this->matchChar( ',' ) ) {
				continue;
			}

			if ( $this->matchChar( ';' ) || $isSemiColonSeperated ) {

				if ( $expressionContainsNamed ) {
					$this->Error( 'Cannot mix ; and , as delimiter types' );
				}

				$isSemiColonSeperated = true;

				if ( count( $expressions ) > 1 ) {
					$value = new Less_Tree_Value( $expressions );
				}
				$argsSemiColon[] = [ 'name' => $name, 'value' => $value, 'expand' => $expand ];

				$name = null;
				$expressions = [];
				$expressionContainsNamed = false;
			}
		}

		$this->forget();
		$returner['args'] = ( $isSemiColonSeperated ? $argsSemiColon : $argsComma );
		return $returner;
	}

	//
	// A Mixin definition, with a list of parameters
	//
	//	 .rounded (@radius: 2px, @color) {
	//		...
	//	 }
	//
	// Until we have a finer grained state-machine, we have to
	// do a look-ahead, to make sure we don't have a mixin call.
	// See the `rule` function for more information.
	//
	// We start by matching `.rounded (`, and then proceed on to
	// the argument list, which has optional default values.
	// We store the parameters in `params`, with a `value` key,
	// if there is a value, such as in the case of `@radius`.
	//
	// Once we've got our params list, and a closing `)`, we parse
	// the `{...}` block.
	//
	// @see less-2.5.3.js#parsers.mixin.definition
	private function parseMixinDefinition() {
		$cond = null;

		$char = $this->input[$this->pos] ?? null;
		// TODO: Less.js doesn't limit this to $char == '{'.
		if ( ( $char !== '.' && $char !== '#' ) || ( $char === '{' && $this->peekReg( '/\\G[^{]*\}/' ) ) ) {
			return;
		}

		$this->save();

		$match = $this->matchReg( '/\\G([#.](?:[\w-]|\\\(?:[A-Fa-f0-9]{1,6} ?|[^A-Fa-f0-9]))+)\s*\(/' );
		if ( $match ) {
			$name = $match[1];

			$argInfo = $this->parseMixinArgs( false );
			$params = $argInfo['args'];
			$variadic = $argInfo['variadic'];

			// .mixincall("@{a}");
			// looks a bit like a mixin definition..
			// also
			// .mixincall(@a: {rule: set;});
			// so we have to be nice and restore
			if ( !$this->matchChar( ')' ) ) {
				$this->restore();
				return;
			}

			$this->commentStore = [];

			if ( $this->matchStr( 'when' ) ) { // Guard
				$cond = $this->expect( 'parseConditions', 'Expected conditions' );
			}

			$ruleset = $this->parseBlock();

			if ( $ruleset !== null ) {
				$this->forget();
				return new Less_Tree_Mixin_Definition( $name, $params, $ruleset, $cond, $variadic );
			}

			$this->restore();
		} else {
			$this->forget();
		}
	}

	//
	// Entities are the smallest recognized token,
	// and can be found inside a rule's value.
	//
	private function parseEntity() {
		return $this->parseComment() ??
			$this->parseEntitiesLiteral() ??
			$this->parseEntitiesVariable() ??
			$this->parseEntitiesUrl() ??
			$this->parseEntitiesCall() ??
			$this->parseEntitiesKeyword() ??
			$this->parseEntitiesJavascript();
	}

	//
	// A Rule terminator. Note that we use `peek()` to check for '}',
	// because the `block` rule will be expecting it, but we still need to make sure
	// it's there, if ';' was omitted.
	//
	private function parseEnd() {
		return $this->matchChar( ';' ) || $this->peekChar( '}' );
	}

	//
	// IE's alpha function
	//
	//	 alpha(opacity=88)
	//
	// @see less-2.5.3.js#parsers.alpha
	private function parseAlpha() {
		if ( !$this->matchReg( '/\\Gopacity=/i' ) ) {
			return;
		}

		$value = $this->matchReg( '/\\G[0-9]+/' );
		if ( !$value ) {
			$value = $this->expect( 'parseEntitiesVariable', 'Could not parse alpha' );
		}

		$this->expectChar( ')' );
		return new Less_Tree_Alpha( $value );
	}

	/**
	 * A Selector Element
	 *
	 *	 div
	 *	 + h1
	 *	 #socks
	 *	 input[type="text"]
	 *
	 * Elements are the building blocks for Selectors,
	 * they are made out of a `Combinator` (see combinator rule),
	 * and an element name, such as a tag a class, or `*`.
	 *
	 * @return Less_Tree_Element|null
	 * @see less-2.5.3.js#parsers.element
	 */
	private function parseElement() {
		$c = $this->parseCombinator();
		$index = $this->pos;

		$e = $this->matchReg( '/\\G(?:\d+\.\d+|\d+)%/' )
			?? $this->matchReg( '/\\G(?:[.#]?|:*)(?:[\w-]|[^\x00-\x9f]|\\\\(?:[A-Fa-f0-9]{1,6} ?|[^A-Fa-f0-9]))+/' )
			?? $this->matchChar( '*' )
			?? $this->matchChar( '&' )
			?? $this->parseAttribute()
			?? $this->matchReg( '/\\G\([^&()@]+\)/' )
			?? $this->matchReg( '/\\G[\.#:](?=@)/' )
			?? $this->parseEntitiesVariableCurly();

		if ( $e === null ) {
			$this->save();
			if ( $this->matchChar( '(' ) ) {
				$v = $this->parseSelector();
				if ( $v && $this->matchChar( ')' ) ) {
					$e = new Less_Tree_Paren( $v );
					$this->forget();
				} else {
					$this->restore();
				}
			} else {
				$this->forget();
			}
		}

		if ( $e !== null ) {
			return new Less_Tree_Element( $c, $e, $index, $this->env->currentFileInfo );
		}
	}

	//
	// Combinators combine elements together, in a Selector.
	//
	// Because our parser isn't white-space sensitive, special care
	// has to be taken, when parsing the descendant combinator, ` `,
	// as it's an empty space. We have to check the previous character
	// in the input, to see if it's a ` ` character.
	//
	// @see less-2.5.3.js#parsers.combinator
	private function parseCombinator() {
		if ( $this->pos < $this->input_len ) {
			$c = $this->input[$this->pos];
			if ( $c === '/' ) {
				$this->save();
				$slashedCombinator = $this->matchReg( '/\\G\/[a-z]+\//i' );
				if ( $slashedCombinator ) {
					$this->forget();
					return $slashedCombinator;
				}
				$this->restore();
			}

			// TODO: Figure out why less.js also handles '/' here, and implement with regression test.
			if ( $c === '>' || $c === '+' || $c === '~' || $c === '|' || $c === '^' ) {

				$this->pos++;
				if ( $c === '^' && $this->input[$this->pos] === '^' ) {
					$c = '^^';
					$this->pos++;
				}

				$this->skipWhitespace( 0 );

				return $c;
			}

			if ( $this->pos > 0 && $this->isWhitespace( -1 ) ) {
				return ' ';
			}
		}
	}

	/**
	 * A CSS selector (see selector below)
	 * with less extensions e.g. the ability to extend and guard
	 *
	 * @return Less_Tree_Selector|null
	 * @see less-2.5.3.js#parsers.lessSelector
	 */
	private function parseLessSelector() {
		return $this->parseSelector( true );
	}

	/**
	 * A CSS Selector
	 *
	 *	 .class > div + h1
	 *	 li a:hover
	 *
	 * Selectors are made out of one or more Elements, see ::parseElement.
	 *
	 * @return Less_Tree_Selector|null
	 * @see less-2.5.3.js#parsers.selector
	 */
	private function parseSelector( $isLess = false ) {
		$elements = [];
		$extendList = [];
		$condition = null;
		$when = false;
		$extend = false;
		$e = null;
		$c = null;
		$index = $this->pos;

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition
		while ( ( $isLess && ( $extend = $this->parseExtend() ) ) || ( $isLess && ( $when = $this->matchStr( 'when' ) ) ) || ( $e = $this->parseElement() ) ) {
			if ( $when ) {
				$condition = $this->expect( 'parseConditions', 'expected condition' );
			} elseif ( $condition ) {
				// error("CSS guard can only be used at the end of selector");
			} elseif ( $extend ) {
				$extendList = array_merge( $extendList, $extend );
			} else {
				// if( count($extendList) ){
				//error("Extend can only be used at the end of selector");
				//}
				if ( $this->pos < $this->input_len ) {
					$c = $this->input[ $this->pos ];
				}
				$elements[] = $e;
				$e = null;
			}

			if ( $c === '{' || $c === '}' || $c === ';' || $c === ',' || $c === ')' ) {
				break;
			}
		}

		if ( $elements ) {
			return new Less_Tree_Selector( $elements, $extendList, $condition, $index, $this->env->currentFileInfo );
		}
		if ( $extendList ) {
			$this->Error( 'Extend must be used to extend a selector, it cannot be used on its own' );
		}
	}

	/**
	 * @return Less_Tree_Attribute|null
	 * @see less-2.5.3.js#parsers.attribute
	 */
	private function parseAttribute() {
		$val = null;

		if ( !$this->matchChar( '[' ) ) {
			return;
		}

		$key = $this->parseEntitiesVariableCurly();
		if ( !$key ) {
			$key = $this->expect( '/\\G(?:[_A-Za-z0-9-\*]*\|)?(?:[_A-Za-z0-9-]|\\\\.)+/' );
		}

		$op = $this->matchReg( '/\\G[|~*$^]?=/' );
		if ( $op ) {
			$val = $this->parseEntitiesQuoted() ?? $this->matchReg( '/\\G[0-9]+%/' ) ?? $this->matchReg( '/\\G[\w-]+/' ) ?? $this->parseEntitiesVariableCurly();
		}

		$this->expectChar( ']' );

		return new Less_Tree_Attribute( $key, $op, $val );
	}

	/**
	 * The `block` rule is used by `ruleset` and `mixin.definition`.
	 * It's a wrapper around the `primary` rule, with added `{}`.
	 *
	 * @return array<Less_Tree>|null
	 * @see less-2.5.3.js#parsers.block
	 */
	private function parseBlock() {
		if ( $this->matchChar( '{' ) ) {
			$content = $this->parsePrimary();
			if ( $this->matchChar( '}' ) ) {
				return $content;
			}
		}
	}

	private function parseBlockRuleset() {
		$block = $this->parseBlock();
		if ( $block !== null ) {
			return new Less_Tree_Ruleset( null, $block );
		}
	}

	/** @return Less_Tree_DetachedRuleset|null */
	private function parseDetachedRuleset() {
		$blockRuleset = $this->parseBlockRuleset();
		if ( $blockRuleset ) {
			return new Less_Tree_DetachedRuleset( $blockRuleset );
		}
	}

	/**
	 * Ruleset such as:
	 *
	 *     div, .class, body > p {
	 *     }
	 *
	 * @return Less_Tree_Ruleset|null
	 * @see less-2.5.3.js#parsers.ruleset
	 */
	private function parseRuleset() {
		$selectors = [];

		$this->save();

		while ( true ) {
			$s = $this->parseLessSelector();
			if ( !$s ) {
				break;
			}
			$selectors[] = $s;
			$this->commentStore = [];

			if ( $s->condition && count( $selectors ) > 1 ) {
				$this->Error( 'Guards are only currently allowed on a single selector.' );
			}

			if ( !$this->matchChar( ',' ) ) {
				break;
			}
			if ( $s->condition ) {
				$this->Error( 'Guards are only currently allowed on a single selector.' );
			}
			$this->commentStore = [];
		}

		if ( $selectors ) {
			$rules = $this->parseBlock();
			if ( is_array( $rules ) ) {
				$this->forget();
				// TODO: Less_Environment::$strictImports is not yet ported
				// It is passed here by less.js
				return new Less_Tree_Ruleset( $selectors, $rules );
			}
		}

		// Backtrack
		$this->restore();
	}

	/**
	 * Custom less.php parse function for finding simple name-value css pairs
	 * ex: width:100px;
	 */
	private function parseNameValue() {
		$index = $this->pos;
		$this->save();

		$match = $this->matchReg( '/\\G([a-zA-Z\-]+)\s*:\s*([\'"]?[#a-zA-Z0-9\-%\.,]+?[\'"]?\s*) *(! *important)?\s*([;}])/' );
		if ( $match ) {

			if ( $match[4] == '}' ) {
				// because we will parse all comments after closing }, we need to reset the store as
				// we're going to reset the position to closing }
				$this->commentStore = [];
				$this->pos = $index + strlen( $match[0] ) - 1;
				$match[2] = rtrim( $match[2] );
			}

			if ( $match[3] ) {
				$match[2] .= $match[3];
			}
			$this->forget();
			return new Less_Tree_NameValue( $match[1], $match[2], $index, $this->env->currentFileInfo );
		}

		$this->restore();
	}

	// @see less-2.5.3.js#parsers.rule
	private function parseRule( $tryAnonymous = null ) {
		$value = null;
		$startOfRule = $this->pos;
		$c = $this->input[$this->pos] ?? null;
		$important = null;
		$merge = false;

		// TODO: Figure out why less.js also handles ':' here, and implement with regression test.
		if ( $c === '.' || $c === '#' || $c === '&' ) {
			return;
		}

		$this->save();
		$name = $this->parseVariable() ?? $this->parseRuleProperty();

		if ( $name ) {
			$isVariable = is_string( $name );

			if ( $isVariable ) {
				$value = $this->parseDetachedRuleset();
			}
			$this->commentStore = [];
			if ( !$value ) {
				// a name returned by this.ruleProperty() is always an array of the form:
				// [string-1, ..., string-n, ""] or [string-1, ..., string-n, "+"]
				// where each item is a tree.Keyword or tree.Variable
				if ( !$isVariable && count( $name ) > 1 ) {
					$merge = array_pop( $name )->value;
				}

				// prefer to try to parse first if its a variable or we are compressing
				// but always fallback on the other one
				$tryValueFirst = ( !$tryAnonymous && ( self::$options['compress'] || $isVariable ) );
				if ( $tryValueFirst ) {
					$value = $this->parseValue();
				}
				if ( !$value ) {
					$value = $this->parseAnonymousValue();
					if ( $value ) {
						$this->forget();
						// anonymous values absorb the end ';' which is required for them to work
						return new Less_Tree_Rule( $name, $value, false, $merge, $startOfRule, $this->env->currentFileInfo );
					}
				}
				if ( !$tryValueFirst && !$value ) {
					$value = $this->parseValue();
				}

				$important = $this->parseImportant();
			}

			if ( $value && $this->parseEnd() ) {
				$this->forget();
				return new Less_Tree_Rule( $name, $value, $important, $merge, $startOfRule, $this->env->currentFileInfo );
			} else {
				$this->restore();
				if ( $value && !$tryAnonymous ) {
					return $this->parseRule( true );
				}
			}
		} else {
			$this->forget();
		}
	}

	private function parseAnonymousValue() {
		$match = $this->matchReg( '/\\G([^@+\/\'"*`(;{}-]*);/' );
		if ( $match ) {
			return new Less_Tree_Anonymous( $match[1] );
		}
	}

	//
	// An @import directive
	//
	//	 @import "lib";
	//
	// Depending on our environment, importing is done differently:
	// In the browser, it's an XHR request, in Node, it would be a
	// file-system operation. The function used for importing is
	// stored in `import`, which we pass to the Import constructor.
	//
	private function parseImport() {
		$this->save();

		$dir = $this->matchReg( '/\\G@import?\s+/' );

		if ( $dir ) {
			$options = $this->parseImportOptions();
			$path = $this->parseEntitiesQuoted() ?? $this->parseEntitiesUrl();

			if ( $path ) {
				$features = $this->parseMediaFeatures();
				if ( $this->matchChar( ';' ) ) {
					if ( $features ) {
						$features = new Less_Tree_Value( $features );
					}

					$this->forget();
					return new Less_Tree_Import( $path, $features, $options, $this->pos, $this->env->currentFileInfo );
				}
			}
		}

		$this->restore();
	}

	private function parseImportOptions() {
		$options = [];

		// list of options, surrounded by parens
		if ( !$this->matchChar( '(' ) ) {
			return $options;
		}
		do {
			$optionName = $this->parseImportOption();
			if ( $optionName ) {
				$value = true;
				switch ( $optionName ) {
					case "css":
						$optionName = "less";
						$value = false;
						break;
					case "once":
						$optionName = "multiple";
						$value = false;
						break;
				}
				$options[$optionName] = $value;
				if ( !$this->matchChar( ',' ) ) {
					break;
				}
			}
		} while ( $optionName );
		$this->expectChar( ')' );
		return $options;
	}

	private function parseImportOption() {
		$opt = $this->matchReg( '/\\G(less|css|multiple|once|inline|reference|optional)/' );
		if ( $opt ) {
			return $opt[1];
		}
	}

	private function parseMediaFeature() {
		$nodes = [];

		do {
			$e = $this->parseEntitiesKeyword() ?? $this->parseEntitiesVariable();
			if ( $e ) {
				$nodes[] = $e;
			} elseif ( $this->matchChar( '(' ) ) {
				$p = $this->parseProperty();
				$e = $this->parseValue();
				if ( $this->matchChar( ')' ) ) {
					if ( $p && $e ) {
						$r = new Less_Tree_Rule( $p, $e, null, null, $this->pos, $this->env->currentFileInfo, true );
						$nodes[] = new Less_Tree_Paren( $r );
					} elseif ( $e ) {
						$nodes[] = new Less_Tree_Paren( $e );
					} else {
						return null;
					}
				} else {
					return null;
				}
			}
		} while ( $e );

		if ( $nodes ) {
			return new Less_Tree_Expression( $nodes );
		}
	}

	private function parseMediaFeatures() {
		$features = [];

		do {
			$e = $this->parseMediaFeature();
			if ( $e ) {
				$features[] = $e;
				if ( !$this->matchChar( ',' ) ) {
					break;
				}
			} else {
				$e = $this->parseEntitiesVariable();
				if ( $e ) {
					$features[] = $e;
					if ( !$this->matchChar( ',' ) ) {
						break;
					}
				}
			}
		} while ( $e );

		return $features ?: null;
	}

	/**
	 * @see less-2.5.3.js#parsers.media
	 */
	private function parseMedia() {
		if ( $this->matchStr( '@media' ) ) {
			$this->save();

			$features = $this->parseMediaFeatures();
			$rules = $this->parseBlock();

			if ( $rules === null ) {
				$this->restore();
				return;
			}

			$this->forget();
			return new Less_Tree_Media( $rules, $features, $this->pos, $this->env->currentFileInfo );
		}
	}

	/**
	 * A CSS Directive like `@charset "utf-8";`
	 *
	 * @return Less_Tree_Import|Less_Tree_Media|Less_Tree_Directive|null
	 * @see less-2.5.3.js#parsers.directive
	 */
	private function parseDirective() {
		if ( !$this->peekChar( '@' ) ) {
			return;
		}

		$rules = null;
		$index = $this->pos;
		$hasBlock = true;
		$hasIdentifier = false;
		$hasExpression = false;
		$hasUnknown = false;
		$isRooted = true;

		$value = $this->parseImport() ?? $this->parseMedia();
		if ( $value ) {
			return $value;
		}

		$this->save();

		$name = $this->matchReg( '/\\G@[a-z-]+/' );

		if ( !$name ) {
			return;
		}

		$nonVendorSpecificName = $name;
		$pos = strpos( $name, '-', 2 );
		if ( $name[1] == '-' && $pos > 0 ) {
			$nonVendorSpecificName = "@" . substr( $name, $pos + 1 );
		}

		switch ( $nonVendorSpecificName ) {
			/*
			case "@font-face":
			case "@viewport":
			case "@top-left":
			case "@top-left-corner":
			case "@top-center":
			case "@top-right":
			case "@top-right-corner":
			case "@bottom-left":
			case "@bottom-left-corner":
			case "@bottom-center":
			case "@bottom-right":
			case "@bottom-right-corner":
			case "@left-top":
			case "@left-middle":
			case "@left-bottom":
			case "@right-top":
			case "@right-middle":
			case "@right-bottom":
			hasBlock = true;
			isRooted = true;
			break;
			*/
			case "@counter-style":
				$hasIdentifier = true;
				break;
			case "@charset":
				$hasIdentifier = true;
				$hasBlock = false;
				break;
			case "@namespace":
				$hasExpression = true;
				$hasBlock = false;
				break;
			case "@keyframes":
				$hasIdentifier = true;
				break;
			case "@host":
			case "@page":
				$hasUnknown = true;
				break;
			case "@document":
			case "@supports":
				$hasUnknown = true;
				$isRooted = false;
				break;
		}

		$this->commentStore = [];

		if ( $hasIdentifier ) {
			$value = $this->parseEntity();
			if ( !$value ) {
				$this->error( "expected " . $name . " identifier" );
			}
		} elseif ( $hasExpression ) {
			$value = $this->parseExpression();
			if ( !$value ) {
				$this->error( "expected " . $name . " expression" );
			}
		} elseif ( $hasUnknown ) {

			$value = $this->matchReg( '/\\G[^{;]+/' );
			if ( $value ) {
				$value = new Less_Tree_Anonymous( trim( $value ) );
			}
		}

		if ( $hasBlock ) {
			$rules = $this->parseBlockRuleset();
		}

		if ( $rules || ( !$hasBlock && $value && $this->matchChar( ';' ) ) ) {
			$this->forget();
			return new Less_Tree_Directive( $name, $value, $rules, $index, $isRooted, $this->env->currentFileInfo );
		}

		$this->restore();
	}

	//
	// A Value is a comma-delimited list of Expressions
	//
	//	 font-family: Baskerville, Georgia, serif;
	//
	// In a Rule, a Value represents everything after the `:`,
	// and before the `;`.
	//
	private function parseValue() {
		$expressions = [];

		do {
			$e = $this->parseExpression();
			if ( $e ) {
				$expressions[] = $e;
				if ( !$this->matchChar( ',' ) ) {
					break;
				}
			}
		} while ( $e );

		if ( $expressions ) {
			return new Less_Tree_Value( $expressions );
		}
	}

	private function parseImportant() {
		if ( $this->peekChar( '!' ) && $this->matchReg( '/\\G! *important/' ) ) {
			return ' !important';
		}
	}

	private function parseSub() {
		$this->save();
		if ( $this->matchChar( '(' ) ) {
			$a = $this->parseAddition();
			if ( $a && $this->matchChar( ')' ) ) {
				$this->forget();
				return new Less_Tree_Expression( [ $a ], true );
			}
		}
		$this->restore();
	}

	/**
	 * Parses multiplication operation
	 *
	 * @return Less_Tree_Operation|null
	 */
	private function parseMultiplication() {
		$return = $m = $this->parseOperand();
		if ( $return ) {
			while ( true ) {

				$isSpaced = $this->isWhitespace( -1 );

				if ( $this->peekReg( '/\\G\/[*\/]/' ) ) {
					break;
				}
				$this->save();

				$op = $this->matchChar( '/' );
				if ( !$op ) {
					$op = $this->matchChar( '*' );
					if ( !$op ) {
						$this->forget();
						break;
					}
				}

				$a = $this->parseOperand();

				if ( !$a ) {
					$this->restore();
					break;
				}
				$this->forget();

				$m->parensInOp = true;
				$a->parensInOp = true;
				$return = new Less_Tree_Operation( $op, [ $return, $a ], $isSpaced );
			}
		}
		return $return;
	}

	/**
	 * Parses an addition operation
	 *
	 * @return Less_Tree_Operation|null
	 */
	private function parseAddition() {
		$return = $m = $this->parseMultiplication();
		if ( $return ) {
			while ( true ) {

				$isSpaced = $this->isWhitespace( -1 );

				$op = $this->matchReg( '/\\G[-+]\s+/' );
				if ( !$op ) {
					if ( !$isSpaced ) {
						$op = $this->matchChar( '+' ) ?? $this->matchChar( '-' );
					}
					if ( !$op ) {
						break;
					}
				}

				$a = $this->parseMultiplication();
				if ( !$a ) {
					break;
				}

				$m->parensInOp = true;
				$a->parensInOp = true;
				$return = new Less_Tree_Operation( $op, [ $return, $a ], $isSpaced );
			}
		}

		return $return;
	}

	/**
	 * Parses the conditions
	 *
	 * @return Less_Tree_Condition|null
	 */
	private function parseConditions() {
		$index = $this->pos;
		$return = $a = $this->parseCondition();
		if ( $a ) {
			while ( true ) {
				if ( !$this->peekReg( '/\\G,\s*(not\s*)?\(/' ) || !$this->matchChar( ',' ) ) {
					break;
				}
				$b = $this->parseCondition();
				if ( !$b ) {
					break;
				}

				$return = new Less_Tree_Condition( 'or', $return, $b, $index );
			}
			return $return;
		}
	}

	/**
	 * @see less-2.5.3.js#parsers.condition
	 */
	private function parseCondition() {
		$index = $this->pos;
		$negate = false;
		$c = null;

		if ( $this->matchStr( 'not' ) ) {
			$negate = true;
		}
		$this->expectChar( '(' );
		$a = $this->parseAddition() ?? $this->parseEntitiesKeyword() ?? $this->parseEntitiesQuoted();

		if ( $a ) {
			$op = $this->matchReg( '/\\G(?:>=|<=|=<|[<=>])/' );
			if ( $op ) {
				$b = $this->parseAddition() ?? $this->parseEntitiesKeyword() ?? $this->parseEntitiesQuoted();
				if ( $b ) {
					$c = new Less_Tree_Condition( $op, $a, $b, $index, $negate );
				} else {
					$this->Error( 'Unexpected expression' );
				}
			} else {
				$k = new Less_Tree_Keyword( 'true' );
				$c = new Less_Tree_Condition( '=', $a, $k, $index, $negate );
			}
			$this->expectChar( ')' );
			// @phan-suppress-next-line PhanPossiblyInfiniteRecursionSameParams
			return $this->matchStr( 'and' ) ? new Less_Tree_Condition( 'and', $c, $this->parseCondition() ) : $c;
		}
	}

	/**
	 * An operand is anything that can be part of an operation,
	 * such as a Color, or a Variable
	 */
	private function parseOperand() {
		$negate = false;
		$offset = $this->pos + 1;
		if ( $offset >= $this->input_len ) {
			return;
		}
		$char = $this->input[$offset];
		if ( $char === '@' || $char === '(' ) {
			$negate = $this->matchChar( '-' );
		}

		$o = $this->parseSub() ?? $this->parseEntitiesDimension() ?? $this->parseEntitiesColor() ?? $this->parseEntitiesVariable() ?? $this->parseEntitiesCall();

		if ( $negate ) {
			$o->parensInOp = true;
			$o = new Less_Tree_Negative( $o );
		}

		return $o;
	}

	/**
	 * Expressions either represent mathematical operations,
	 * or white-space delimited Entities.
	 *
	 * @return Less_Tree_Expression|null
	 */
	private function parseExpression() {
		$entities = [];

		do {
			$e = $this->parseComment();
			if ( $e ) {
				$entities[] = $e;
				continue;
			}
			$e = $this->parseAddition() ?? $this->parseEntity();
			if ( $e ) {
				$entities[] = $e;
				// operations do not allow keyword "/" dimension (e.g. small/20px) so we support that here
				if ( !$this->peekReg( '/\\G\/[\/*]/' ) ) {
					$delim = $this->matchChar( '/' );
					if ( $delim ) {
						$entities[] = new Less_Tree_Anonymous( $delim );
					}
				}
			}
		} while ( $e );

		if ( $entities ) {
			return new Less_Tree_Expression( $entities );
		}
	}

	/**
	 * Parse a property
	 * eg: 'min-width', 'orientation', etc
	 *
	 * @return string
	 */
	private function parseProperty() {
		$name = $this->matchReg( '/\\G(\*?-?[_a-zA-Z0-9-]+)\s*:/' );
		if ( $name ) {
			return $name[1];
		}
	}

	/**
	 * Parse a rule property
	 * eg: 'color', 'width', 'height', etc
	 *
	 * @return array<Less_Tree_Keyword|Less_Tree_Variable>
	 * @see less-2.5.3.js#parsers.ruleProperty
	 */
	private function parseRuleProperty() {
		$name = [];
		$index = [];

		$this->save();

		$simpleProperty = $this->matchReg( '/\\G([_a-zA-Z0-9-]+)\s*:/' );
		if ( $simpleProperty ) {
			$name[] = new Less_Tree_Keyword( $simpleProperty[1] );
			$this->forget();
			return $name;
		}

		$this->rulePropertyMatch( '/\\G(\*?)/', $index, $name );

		// Consume!
		// @phan-suppress-next-line PhanPluginEmptyStatementWhileLoop
		while ( $this->rulePropertyMatch( '/\\G((?:[\w-]+)|(?:@\{[\w-]+\}))/', $index, $name ) );

		if ( ( count( $name ) > 1 ) && $this->rulePropertyMatch( '/\\G((?:\+_|\+)?)\s*:/', $index, $name ) ) {
			$this->forget();

			// at last, we have the complete match now. move forward,
			// convert name particles to tree objects and return:
			if ( $name[0] === '' ) {
				array_shift( $name );
				array_shift( $index );
			}
			foreach ( $name as $k => $s ) {
				if ( !$s || $s[0] !== '@' ) {
					$name[$k] = new Less_Tree_Keyword( $s );
				} else {
					$name[$k] = new Less_Tree_Variable( '@' . substr( $s, 2, -1 ), $index[$k], $this->env->currentFileInfo );
				}
			}
			return $name;
		} else {
			$this->restore();
		}
	}

	private function rulePropertyMatch( $re, &$index, &$name ) {
		$i = $this->pos;
		$chunk = $this->matchReg( $re );
		if ( $chunk ) {
			$index[] = $i;
			$name[] = $chunk[1];
			return true;
		}
	}

	public static function serializeVars( $vars ) {
		$s = '';

		foreach ( $vars as $name => $value ) {
			$s .= ( ( $name[0] === '@' ) ? '' : '@' ) . $name . ': ' . $value . ( ( substr( $value, -1 ) === ';' ) ? '' : ';' );
		}

		return $s;
	}

	/**
	 * Some versions of PHP have trouble with method_exists($a,$b) if $a is not an object
	 *
	 * @param mixed $a
	 * @param string $b
	 */
	public static function is_method( $a, $b ) {
		return is_object( $a ) && method_exists( $a, $b );
	}

	/**
	 * Round numbers similarly to javascript
	 * eg: 1.499999 to 1 instead of 2
	 */
	public static function round( $input, $precision = 0 ) {
		$precision = pow( 10, $precision );
		$i = $input * $precision;

		$ceil = ceil( $i );
		$floor = floor( $i );
		if ( ( $ceil - $i ) <= ( $i - $floor ) ) {
			return $ceil / $precision;
		} else {
			return $floor / $precision;
		}
	}

	/** @return never */
	public function Error( $msg ) {
		throw new Less_Exception_Parser( $msg, null, $this->furthest, $this->env->currentFileInfo );
	}

	public static function WinPath( $path ) {
		return str_replace( '\\', '/', $path );
	}

	public static function AbsPath( $path, $winPath = false ) {
		if ( strpos( $path, '//' ) !== false && preg_match( '/^(https?:)?\/\//i', $path ) ) {
			return $winPath ? '' : false;
		} else {
			$path = realpath( $path );
			if ( $winPath ) {
				$path = self::WinPath( $path );
			}
			return $path;
		}
	}

	public function CacheEnabled() {
		return ( self::$options['cache_method'] && ( Less_Cache::$cache_dir || ( self::$options['cache_method'] == 'callback' ) ) );
	}

}
