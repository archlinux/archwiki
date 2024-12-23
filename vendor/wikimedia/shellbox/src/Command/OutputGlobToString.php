<?php

namespace Shellbox\Command;

/**
 * An output glob for files that are handled as strings
 */
class OutputGlobToString extends OutputGlob {
	public function getOutputFile( $boxedName ) {
		$instance = new OutputFileToString;
		$this->files[$boxedName] = $instance;
		return $instance;
	}
}
