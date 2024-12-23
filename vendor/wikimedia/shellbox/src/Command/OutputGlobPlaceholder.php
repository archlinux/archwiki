<?php

namespace Shellbox\Command;

/**
 * @internal
 */
class OutputGlobPlaceholder extends OutputGlob {
	public function getOutputFile( $boxedName ) {
		return new OutputFilePlaceholder;
	}
}
