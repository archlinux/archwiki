<?php
declare( strict_types = 1 );

namespace Cite\Parsoid;

use Wikimedia\Parsoid\NodeData\DataMwError;

/**
 * @license GPL-2.0-or-later
 */
class ParsoidValidator {

	public function validateDir( string $refDir, RefGroupItem $ref ): ?DataMwError {
		if ( $ref->dir !== '' && $ref->dir !== $refDir ) {
			return new DataMwError( 'cite_error_ref_conflicting_dir', [ $ref->name ] );
		}
		return null;
	}

}
