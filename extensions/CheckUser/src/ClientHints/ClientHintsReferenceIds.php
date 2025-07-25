<?php

namespace MediaWiki\CheckUser\ClientHints;

use LogicException;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;

/**
 * Value object for storing reference IDs with their associated
 * reference map ID.
 *
 * This is used, instead of a two-dimensional list, to enforce that
 * the map IDs are valid. This class stores the data as a two-dimensional
 * list.
 */
class ClientHintsReferenceIds {
	private array $referenceIds;

	/**
	 * Get a new ClientHintsReferenceIds object,
	 * optionally setting the internal reference IDs array.
	 *
	 * @param array $referenceIds If provided, set the internal referenceIds array to
	 *   this value. By default this is the empty array.
	 */
	public function __construct( array $referenceIds = [] ) {
		$this->referenceIds = $referenceIds;
	}

	/**
	 * Add reference IDs with a specific mapping ID to the internal array.
	 *
	 * @param int|int[] $referenceIds an integer or array of integers where the values are reference IDs
	 * @param int $mappingId any of UserAgentClientHintsManager::IDENTIFIER_* constants, which represent a valid
	 *  map ID for the cu_useragent_clienthints_map table
	 * @return void
	 */
	public function addReferenceIds( $referenceIds, int $mappingId ): void {
		if ( !$this->mappingIdExists( $mappingId ) ) {
			$this->referenceIds[$mappingId] = [];
		}
		$referenceIds = array_map( 'intval', (array)$referenceIds );
		$this->referenceIds[$mappingId] = array_unique( array_merge( $this->referenceIds[$mappingId], $referenceIds ) );
	}

	/**
	 * Gets the reference IDs for a specific $mappingId, or
	 * if $mappingId is null, all reference IDs.
	 *
	 * @param int|null $mappingId
	 * @return array
	 */
	public function getReferenceIds( ?int $mappingId = null ): array {
		if ( $mappingId === null ) {
			return $this->referenceIds;
		}
		if ( !$this->mappingIdExists( $mappingId ) ) {
			return [];
		}
		return $this->referenceIds[$mappingId];
	}

	/**
	 * Verifies that a mapping ID exists in the internal array.
	 *
	 * @param int $mappingId One of the UserAgentClientHintsManager::IDENTIFIER_* constants
	 * @throws LogicException if the mapping ID is not recognised
	 * @return bool True if the mapping ID exists in the internal array
	 */
	private function mappingIdExists( int $mappingId ): bool {
		if ( !array_key_exists( $mappingId, UserAgentClientHintsManager::IDENTIFIER_TO_TABLE_NAME_MAP ) ) {
			throw new LogicException( "Unrecognised map ID '$mappingId'" );
		}
		if ( !array_key_exists( $mappingId, $this->referenceIds ) ) {
			return false;
		}
		return true;
	}
}
