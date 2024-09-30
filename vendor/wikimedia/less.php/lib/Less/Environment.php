<?php
/**
 * @private
 */
class Less_Environment {

	/**
	 * Information about the current file - for error reporting and importing and making urls relative etc.
	 *
	 * - rootpath: rootpath to append to URLs
	 *
	 * @var array|null
	 */
	public $currentFileInfo;

	/** @var bool Whether we are currently importing multiple copies */
	public $importMultiple = false;

	/**
	 * @var array
	 */
	public $frames = [];

	/** @var Less_Tree_Media[] */
	public $mediaBlocks = [];
	/** @var Less_Tree_Media[] */
	public $mediaPath = [];

	/** @var string[] */
	public $imports = [];

	/** @var array */
	public $importantScope = [];

	/**
	 * This is the equivalent of `importVisitor.onceFileDetectionMap`
	 * as used by the dynamic `importNode.skip` function.
	 *
	 * @see less-2.5.3.js#ImportVisitor.prototype.onImported
	 * @var array<string,true>
	 */
	public $importVisitorOnceMap = [];

	public static $parensStack = 0;

	public static $tabLevel = 0;

	public static $lastRule = false;

	public static $_noSpaceCombinators;

	public static $mixin_stack = 0;

	public $strictMath = false;

	public $importCallback = null;

	/**
	 * @var array
	 */
	public $functions = [];

	public function Init() {
		self::$parensStack = 0;
		self::$tabLevel = 0;
		self::$lastRule = false;
		self::$mixin_stack = 0;

		self::$_noSpaceCombinators = [
			'' => true,
			' ' => true,
			'|' => true
		];
	}

	/**
	 * @param string $file
	 * @return void
	 */
	public function addParsedFile( $file ) {
		$this->imports[] = $file;
	}

	public function clone() {
		$new_env = clone $this;
		// NOTE: Match JavaScript by-ref behaviour for arrays
		$new_env->imports =& $this->imports;
		$new_env->importVisitorOnceMap =& $this->importVisitorOnceMap;
		return $new_env;
	}

	/**
	 * @param string $file
	 * @return bool
	 */
	public function isFileParsed( $file ) {
		return in_array( $file, $this->imports );
	}

	public function copyEvalEnv( $frames = [] ) {
		$new_env = new self();
		$new_env->frames = $frames;
		$new_env->importantScope = $this->importantScope;
		$new_env->strictMath = $this->strictMath;
		return $new_env;
	}

	/**
	 * @return bool
	 * @see Eval.prototype.isMathOn in less.js 3.0.0 https://github.com/less/less.js/blob/v3.0.0/dist/less.js#L1007
	 */
	public function isMathOn() {
		return $this->strictMath ? (bool)self::$parensStack : true;
	}

	/**
	 * @param string $path
	 * @return bool
	 * @see less-2.5.3.js#Eval.isPathRelative
	 */
	public static function isPathRelative( $path ) {
		return !preg_match( '/^(?:[a-z-]+:|\/|#)/', $path );
	}

	/**
	 * Apply legacy 'import_callback' option.
	 *
	 * See Less_Parser::$default_options to learn more about the 'import_callback' option.
	 * This option is deprecated in favour of Less_Parser::SetImportDirs.
	 *
	 * @param Less_Tree_Import $importNode
	 * @return array{0:string,1:string|null}|null Array containing path and (optional) uri or null
	 */
	public function callImportCallback( Less_Tree_Import $importNode ) {
		if ( is_callable( $this->importCallback ) ) {
			return ( $this->importCallback )( $importNode );
		}
	}

	/**
	 * Canonicalize a path by resolving references to '/./', '/../'
	 * Does not remove leading "../"
	 * @param string $path or url
	 * @return string Canonicalized path
	 */
	public static function normalizePath( $path ) {
		$segments = explode( '/', $path );
		$segments = array_reverse( $segments );

		$path = [];
		$path_len = 0;

		while ( $segments ) {
			$segment = array_pop( $segments );
			switch ( $segment ) {

				case '.':
					break;

				case '..':
					// @phan-suppress-next-line PhanTypeInvalidDimOffset False positive
					if ( !$path_len || ( $path[$path_len - 1] === '..' ) ) {
						$path[] = $segment;
						$path_len++;
					} else {
						array_pop( $path );
						$path_len--;
					}
					break;

				default:
					$path[] = $segment;
					$path_len++;
					break;
			}
		}

		return implode( '/', $path );
	}

	public function unshiftFrame( $frame ) {
		array_unshift( $this->frames, $frame );
	}

	public function shiftFrame() {
		return array_shift( $this->frames );
	}

}
