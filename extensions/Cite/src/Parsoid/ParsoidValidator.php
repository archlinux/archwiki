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

	public function validateName( string $name, ?RefGroup $refGroup, ReferencesData $referencesData ): ?DataMwError {
		if ( !$refGroup->lookupRefByName( $name ) && $referencesData->inReferenceList() ) {
			return new DataMwError(
				'cite_error_references_missing_key',
				[ $name ]
			);
		}

		return null;
	}

	public function validateFollow( string $followName, ?RefGroup $refGroup ): ?DataMwError {
		if ( !$refGroup->lookupRefByName( $followName ) ) {
			// FIXME: This key isn't exactly appropriate since this
			// is more general than just being in a <references>
			// section and it's the $followName we care about, but the
			// extension to the legacy parser doesn't have an
			// equivalent key and just outputs something wacky.
			return new DataMwError(
				'cite_error_references_missing_key',
				[ $followName ]
			);
		}

		return null;
	}

}
