<?php

namespace MediaWiki\CheckUser\ClientHints;

use InvalidArgumentException;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;

/**
 * Value object for the result of UserAgentClientHintsLookup::getClientHintsByReferenceIds
 * which contains reference IDs to ClientHintsData objects.
 *
 * This is used, instead of a two-dimensional list, to enforce that
 * the map IDs are valid. This class stores the data as a two-dimensional
 * list.
 */
class ClientHintsLookupResults {
	/** @var int[][] */
	private array $referenceIdsToClientHintsDataIndex;

	/** @var ClientHintsData[] */
	private array $clientHintsDataObjects;

	/**
	 * @param int[][] $referenceIdsToClientHintsDataIndex A map of reference type and reference ID values
	 *   to integer keys in $clientHintsDataObjects array.
	 * @param ClientHintsData[] $clientHintsDataObjects An array of ClientHintsData objects where the keys are
	 *   integers that are the second-dimension value in the first parameter.
	 */
	public function __construct( array $referenceIdsToClientHintsDataIndex, array $clientHintsDataObjects ) {
		$this->referenceIdsToClientHintsDataIndex = $referenceIdsToClientHintsDataIndex;
		$this->clientHintsDataObjects = $clientHintsDataObjects;
	}

	/**
	 * Get unique ClientHintsData objects and the number of times they were used for a
	 * array of reference IDs.
	 *
	 * @param ClientHintsReferenceIds|null $referenceIds The reference IDs to get the objects for.
	 *   Null for all reference IDs.
	 * @return array[] An array of two arrays. The first array has keys corresponding to a key in the second array and
	 *   the value is the number of rows the ClientHintsData object is associated with. The second array has values
	 *   of unique ClientHintsData objects.
	 */
	public function getGroupedClientHintsDataForReferenceIds( ?ClientHintsReferenceIds $referenceIds ): array {
		// Store the keys to the ClientHintsData objects in $this->clientHintsDataObjects
		// as values in the array $clientHintsDataObjectKeys that are associated with
		// the reference IDs provided in the first parameter to this method.
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
		$groupedClientHintsIds = array_count_values( $clientHintsDataObjectKeys );

		// Create an array of ClientHintsData objects where the keys
		// are the key for this object in $this->clientHintsDataObjects.
		// This array will contain the subset of $this->clientHintsDataObjects
		// where each key is present in $groupedClientHintsIds.
		$clientHintsDataObjects = [];
		foreach ( array_keys( $groupedClientHintsIds ) as $clientHintsId ) {
			if ( array_key_exists( $clientHintsId, $this->clientHintsDataObjects ) ) {
				// Add the ClientHintsData object into the return array.
				$clientHintsDataObjects[$clientHintsId] = $this->clientHintsDataObjects[$clientHintsId];
			} else {
				// If, for some reason, there is no ClientHintsData object in
				// $this->clientHintsDataObjects, then just silently ignore
				// and remove the group from the return list.
				unset( $groupedClientHintsIds[$clientHintsId] );
			}
		}
		return [ $groupedClientHintsIds, $clientHintsDataObjects ];
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
	 * @return array[]
	 */
	public function getRawData(): array {
		return [ $this->referenceIdsToClientHintsDataIndex, $this->clientHintsDataObjects ];
	}
}
