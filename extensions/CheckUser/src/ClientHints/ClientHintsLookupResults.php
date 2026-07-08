<?php

namespace MediaWiki\Extension\CheckUser\ClientHints;

use InvalidArgumentException;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;

/**
 * Value object for the result of UserAgentClientHintsLookup::getClientHintsByReferenceIds
 * which contains reference IDs to ClientHintsData objects.
 *
 * This is used, instead of a two-dimensional list, to enforce that
 * the map IDs are valid. This class stores the data as a two-dimensional
 * list.
 */
class ClientHintsLookupResults {

	/**
	 * @param array<int,array<int,int>> $referenceIdsToClientHintsDataIndex A map of reference type
	 *   and reference ID values to integer keys in $clientHintsDataObjects array.
	 * @param array<int,ClientHintsData> $clientHintsDataObjects An array of ClientHintsData objects
	 *   where the keys are integers that are the second-dimension value in the first parameter.
	 */
	public function __construct(
		private readonly array $referenceIdsToClientHintsDataIndex,
		private readonly array $clientHintsDataObjects,
	) {
	}

	/**
	 * Get unique ClientHintsData objects and the number of times they were used for a
	 * array of reference IDs.
	 *
	 * @param ClientHintsReferenceIds|null $referenceIds The reference IDs to get the objects for.
	 *   Null for all reference IDs.
	 * @return array{0: array<int,int>, 1: array<int,ClientHintsData>} An array of two arrays.
	 *   The first array has keys corresponding to a key in the second array and the value is the
	 *   number of rows the ClientHintsData object is associated with. The second array has values
	 *   of unique ClientHintsData objects.
	 */
	public function getGroupedClientHintsDataForReferenceIds( ?ClientHintsReferenceIds $referenceIds ): array {
		// Store the keys to the ClientHintsData objects in $this->clientHintsDataObjects
		// as values in the array $clientHintsDataObjectKeys that are associated with
		// the reference IDs provided in the first parameter to this method.
		/** @var int[] $clientHintsDataObjectKeys */
		$clientHintsDataObjectKeys = [];
		foreach ( $this->referenceIdsToClientHintsDataIndex as $referenceType => $referenceIdsForReferenceType ) {
			if ( $referenceIds ) {
				// If filtering for specific reference IDs, filter so that only the reference IDs that
				// were requested are used for the grouping and counting operations later in this method.
				$clientHintsDataObjectKeys = array_merge( $clientHintsDataObjectKeys, array_values( array_intersect_key(
					$referenceIdsForReferenceType,
					array_flip( $referenceIds->getReferenceIds( $referenceType ) ),
				) ) );
			} else {
				// If the $referenceIds parameter was null, then this means apply no filtering to the reference
				// IDs that are used.
				$clientHintsDataObjectKeys = array_merge(
					$clientHintsDataObjectKeys,
					array_values( $referenceIdsForReferenceType )
				);
			}
		}

		// Count the number of occurrences for each integer in the $clientHintsDataObjectKeys
		// array. This counts how many occurrences of a given unique ClientHintsData object
		// are present for the reference IDs in $referenceIds.
		/** @var array<int,int> $groupedClientHintsIds */
		$groupedClientHintsIds = array_count_values( $clientHintsDataObjectKeys );

		return [
			// If, for some reason, there is no ClientHintsData object in
			// $this->clientHintsDataObjects, then just silently ignore
			// and remove the group from the return list.
			array_intersect_key( $groupedClientHintsIds, $this->clientHintsDataObjects ),
			// Create an array of ClientHintsData objects where the keys
			// are the key for this object in $this->clientHintsDataObjects.
			// This array will contain the subset of $this->clientHintsDataObjects
			// where each key is present in $groupedClientHintsIds.
			array_intersect_key( $this->clientHintsDataObjects, $groupedClientHintsIds ),
		];
	}

	/**
	 * Get the ClientHintsData object for a given reference ID and reference type.
	 *
	 * @param int $referenceId The reference ID
	 * @param int $referenceType The reference type (one of the UserAgentClientHintsManager::IDENTIFIER_* integer
	 *   constants).
	 * @return ClientHintsData|null
	 */
	public function getClientHintsDataForReferenceId( int $referenceId, int $referenceType ): ?ClientHintsData {
		// Validate that the $referenceType given is a valid reference type. If not, then
		// return an exception to indicate a problem in the code.
		if ( !array_key_exists( $referenceType, UserAgentClientHintsManager::IDENTIFIER_TO_TABLE_NAME_MAP ) ) {
			throw new InvalidArgumentException( "Unrecognised reference type '$referenceType'" );
		}
		// Check that the reference IDs to ClientHintsData object index ID map has an
		// entry for this reference ID and reference type. Otherwise, return null.
		// If it does then also check that the index in the ClientHintsData objects array
		// exists. Otherwise, return null.
		if (
			!array_key_exists( $referenceType, $this->referenceIdsToClientHintsDataIndex ) ||
			!array_key_exists( $referenceId, $this->referenceIdsToClientHintsDataIndex[$referenceType] ) ||
			!array_key_exists(
				$this->referenceIdsToClientHintsDataIndex[$referenceType][$referenceId],
				$this->clientHintsDataObjects
			)
		) {
			return null;
		}
		// The reference ID matches a ClientHintsData object, so return it.
		return $this->clientHintsDataObjects[$this->referenceIdsToClientHintsDataIndex[$referenceType][$referenceId]];
	}

	/**
	 * Allows UserAgentClientHintsFormatter to get the raw data
	 * for UserAgentClientHintsFormatter::batchFormatClientHintsData.
	 *
	 * @internal For use by UserAgentClientHintsFormatter only.
	 * @return array{0: array, 1: array}
	 */
	public function getRawData(): array {
		return [ $this->referenceIdsToClientHintsDataIndex, $this->clientHintsDataObjects ];
	}
}
