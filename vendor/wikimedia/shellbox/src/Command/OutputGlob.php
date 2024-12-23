<?php

namespace Shellbox\Command;

use Shellbox\Shellbox;
use Shellbox\ShellboxError;

/**
 * The base class for output file glob patterns
 */
abstract class OutputGlob {
	use UserDataTrait;

	/** @var string */
	protected $prefix;
	/** @var string */
	protected $extension;
	/** @var OutputFile[] */
	protected $files = [];

	/**
	 * @internal
	 * @param string $prefix
	 * @param string $extension
	 */
	public function __construct( $prefix, $extension ) {
		$this->prefix = Shellbox::normalizePath( $prefix );
		Shellbox::checkExtension( $extension );
		$this->extension = $extension;
	}

	/**
	 * Get an OutputFile corresponding to a single file that matches the glob.
	 *
	 * @internal
	 * @param string $boxedName
	 * @return OutputFile
	 */
	abstract public function getOutputFile( $boxedName );

	/**
	 * @return string
	 */
	public function getPrefix() {
		return $this->prefix;
	}

	/**
	 * @return string
	 */
	public function getExtension() {
		return $this->extension;
	}

	/**
	 * @internal
	 * @return string
	 */
	public function getId() {
		return $this->prefix . '.' . $this->extension;
	}

	/**
	 * Get JSON serializable data for client/server communication
	 *
	 * @internal
	 * @return array
	 */
	public function getClientData() {
		return [
			'prefix' => $this->prefix,
			'extension' => $this->extension
		];
	}

	/**
	 * This is used on the server side to create a placeholder object for globs
	 * based on a specification received from the client. Because the content is
	 * never actually populated, it's not necessary to distinguish between the
	 * different glob types.
	 *
	 * @internal
	 * @param array $data
	 * @return OutputGlob
	 */
	public static function newFromClientData( array $data ) {
		if ( ( $data['type'] ?? '' ) === 'url' ) {
			if ( !isset( $data['url'] ) ) {
				throw new ShellboxError( 'Missing required parameter for URL glob: "url"' );
			}
			return new OutputGlobToUrl( $data['prefix'], $data['extension'], $data['url'] );
		}
		return new OutputGlobPlaceholder( $data['prefix'], $data['extension'] );
	}

	/**
	 * @return OutputFile[]
	 */
	public function getFiles() {
		return $this->files;
	}

	/**
	 * Determine whether a given relative path matches the glob pattern
	 *
	 * @param string $boxedName
	 * @return bool
	 */
	public function isMatch( $boxedName ) {
		$ext = '.' . $this->extension;
		return substr_compare( $boxedName, $this->prefix, 0, strlen( $this->prefix ) ) === 0
			&& substr_compare( $boxedName, $ext, -strlen( $ext ) ) === 0;
	}
}
