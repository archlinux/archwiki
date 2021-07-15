<?php

namespace Shellbox\Command;

use Shellbox\ShellboxError;

class OutputGlobPlaceholder extends OutputGlob {
	public function getInstance( $boxedName ) {
		throw new ShellboxError( __METHOD__ . ': not implemented' );
	}
}
