<?php

namespace Shellbox\Command;

/**
 * An output glob for files that are written to a local directory
 */
class OutputGlobToFile extends OutputGlob {
	/** @var string */
	private $destDir;

	/**
	 * @internal
	 * @param string $prefix
	 * @param string $extension
	 * @param string $destDir
	 */
	public function __construct( $prefix, $extension, $destDir ) {
		parent::__construct( $prefix, $extension );
		$this->destDir = $destDir;
	}

	public function getOutputFile( $boxedName ) {
		$instance = new OutputFileToFile( $this->destDir . '/' . basename( $boxedName ) );
		$this->files[$boxedName] = $instance;
		return $instance;
	}
}
