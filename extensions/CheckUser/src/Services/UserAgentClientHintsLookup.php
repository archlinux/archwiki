<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\CheckUser\ClientHints\ClientHintsData;
use MediaWiki\CheckUser\ClientHints\ClientHintsLookupResults;
use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use Wikimedia\Rdbms\IReadableDatabase;

/**
 * A service that gets ClientHintsData objects from the database given
 * a ClientHintsReferenceIds object of reference IDs.
 */
class UserAgentClientHintsLookup {
	private IReadableDatabase $dbr;

	public function __construct( IReadableDatabase $dbr ) {
		$this->dbr = $dbr;
	}

	/**
	 * Gets and returns ClientHintsData objects for given
	 * reference IDs.
	 *
	 * @param ClientHintsReferenceIds $referenceIds The reference IDs
	 * @return ClientHintsLookupResults The ClientHintsData objects in a helper object.
	 */
	public function getClientHintsByReferenceIds( ClientHintsReferenceIds $referenceIds ): ClientHintsLookupResults {
		$referenceIdsToClientHintIds = $this->prepareFirstResultsArray( $referenceIds );

		// If there are no conditions, then the reference IDs list was empty.
		// Therefore, in this case, return with no data.
		if ( !count( $referenceIdsToClientHintIds ) ) {
			return new ClientHintsLookupResults( [], [] );
		}

		$res = $this->dbr->newSelectQueryBuilder()
			->field( '*' )
			->table( 'cu_useragent_clienthints_map' )
			// The results list can be used to generate the WHERE condition.
			->where( $this->dbr->makeWhereFrom2d(
				$referenceIdsToClientHintIds, 'uachm_reference_type', 'uachm_reference_id'
			) )
			->caller( __METHOD__ )
			->fetchResultSet();

		// If there are no map rows, then there is no Client Hints data
		// so return early.
		if ( !$res->count() ) {
			return new ClientHintsLookupResults( [], [] );
		}

		// Fill out the results list with the associated uach_id values
		// for each reference ID provided in the first parameter to this method.
		$clientHintIds = [];
		foreach ( $res as $row ) {
			$clientHintIds[] = $row->uachm_uach_id;
			$referenceIdsToClientHintIds[$row->uachm_reference_type][$row->uachm_reference_id][] = $row->uachm_uach_id;
		}

		// De-duplicate the $clientHintsIds array to reduce the size of the WHERE condition
		// of the SQL query.
		$clientHintIds = array_unique( $clientHintIds );

		$uniqueClientHintsDataCombinations = $this->generateUniqueClientHintsIdCombinations(
			$referenceIdsToClientHintIds
		);

		// Get all the data for the uach_id values that were collected in the first query.
		$clientHintsRows = $this->dbr->newSelectQueryBuilder()
			->field( '*' )
			->table( 'cu_useragent_clienthints' )
			->where( [ 'uach_id' => $clientHintIds ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		// Make an array of Client Hints data database rows for each uach_id.
		$clientHintsRowsAsArray = [];
		foreach ( $clientHintsRows as $row ) {
			$clientHintsRowsAsArray[$row->uach_id] = [
				'uach_name' => $row->uach_name, 'uach_value' => $row->uach_value
			];
		}

		foreach ( $uniqueClientHintsDataCombinations as &$clientHintsData ) {
			$clientHintRowIdsForReferenceId = array_intersect_key(
				$clientHintsRowsAsArray,
				array_flip( $clientHintsData )
			);
			$clientHintsData = ClientHintsData::newFromDatabaseRows( $clientHintRowIdsForReferenceId );
		}

		// Return the results in a result wrapper to make the results easier to use by the callers.
		return new ClientHintsLookupResults( $referenceIdsToClientHintIds, $uniqueClientHintsDataCombinations );
	}

	/**
	 * Generates the first results array for use in
	 * ::getClientHintsByReferenceIds.
	 *
	 * @param ClientHintsReferenceIds $referenceIds The reference IDs passed to ::getClientHintsByReferenceIds
	 * @return array The first results array which is a two-dimensional map where the first
	 *   dimension keys is the reference type, the second dimension keys is the reference ID,
	 *   and the values is initially an empty array.
	 */
	private function prepareFirstResultsArray( ClientHintsReferenceIds $referenceIds ): array {
		$referenceIdsToClientHintIds = [];
		foreach ( $referenceIds->getReferenceIds() as $mappingId => $referenceIdsForMappingId ) {
			if ( !count( $referenceIdsForMappingId ) ) {
				// If there are no reference IDs for this reference type, then just skip to the
				// next reference type.
				continue;
			}
			// Use an empty array at first as the results have not been generated.
			$referenceIdsToClientHintIds[$mappingId] = array_fill_keys(
				$referenceIdsForMappingId,
				[]
			);
		}
		return $referenceIdsToClientHintIds;
	}

	/**
	 * Generates an array of unique combinations of uach_ids
	 * and updates the first results array provided as the
	 * first argument to reference the key associated with
	 * the array in the newly generated second results array.
	 *
	 * @param array &$referenceIdsToClientHintIds The first results list currently under construction
	 * @return array The unique combinations of uach_ids as values with integer keys.
	 */
	private function generateUniqueClientHintsIdCombinations( array &$referenceIdsToClientHintIds ): array {
		// Generate an two-dimensional array of all the uach_ids
		// grouped by their reference ID.
		$clientHintIdCombinations = [];
		foreach ( $referenceIdsToClientHintIds as &$referenceIdsForMapId ) {
			foreach ( $referenceIdsForMapId as &$clientHintsIds ) {
				// Sort the array as the ordering of the uach_id values
				// does not affect what ClientHintsData object is produced.
				// Therefore sorting the IDs helps to eliminate duplicates.
				sort( $clientHintsIds, SORT_NUMERIC );
				// Add this array of uach_ids to the combinations array
				$clientHintIdCombinations[] = $clientHintsIds;
			}
		}

		// Make the combinations unique (SORT_REGULAR is used as this is performed on arrays)
		// and get rid of the previous integer keys.
		$clientHintIdCombinations = array_values( array_unique( $clientHintIdCombinations, SORT_REGULAR ) );

		foreach ( $referenceIdsToClientHintIds as &$referenceIdsForMapId ) {
			foreach ( $referenceIdsForMapId as &$clientHintsIds ) {
				// Update the $referenceIdsToClientHintIds array to now reference the integer key
				// for this array of uach_ids in $uniqueClientHintsDataCombinations.
				$clientHintsIds = array_search( $clientHintsIds, $clientHintIdCombinations );
			}
		}

		return $clientHintIdCombinations;
	}
}
