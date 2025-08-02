<?php

namespace MediaWiki\CheckUser\ClientHints;

use InvalidArgumentException;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;

/**
 * Value object for the result of UserAgentClientHintsFormatter::batchFormatClientHintsData
 * which contains reference IDs to formatted strings of Client Hints data.
 */
class ClientHintsBatchFormatterResults {
	/** @var int[][] */
	private array $referenceIdsToFormattedClientHintsIndex;

	/** @var string[] */
	private array $formattedClientHints;

	/**
	 * @param int[][] $referenceIdsToFormattedClientHintsIndex A map of reference type and reference ID values
	 *   to integer keys in $formattedClientHints array.
	 * @param string[] $formattedClientHints An array of strings where the keys are integers that are the
	 *   second-dimension value in the first parameter.
	 */
	public function __construct( array $referenceIdsToFormattedClientHintsIndex, array $formattedClientHints ) {
		$this->referenceIdsToFormattedClientHintsIndex = $referenceIdsToFormattedClientHintsIndex;
		$this->formattedClientHints = $formattedClientHints;
	}

	/**
	 * Get the human-readable Client Hints data string for a given reference ID and reference type.
	 *
	 * @param int $referenceId The reference ID
	 * @param int $referenceType The reference type (one of the UserAgentClientHintsManager::IDENTIFIER_* integer
	 *   constants).
	 * @return string|null
	 */
	public function getStringForReferenceId( int $referenceId, int $referenceType ): ?string {
		// Validate that the $referenceType given is a valid reference type. If not, then
		// return an exception to indicate a problem in the code.
		if ( !array_key_exists( $referenceType, UserAgentClientHintsManager::IDENTIFIER_TO_TABLE_NAME_MAP ) ) {
			throw new InvalidArgumentException( "Unrecognised reference type '$referenceType'" );
		}
		// Check that the reference IDs to string index ID map has an
		// entry for this reference ID and reference type. Otherwise, return null.
		// If it does then also check that the index string array exists. Otherwise, return null.
		if (
			!array_key_exists( $referenceType, $this->referenceIdsToFormattedClientHintsIndex ) ||
			!array_key_exists( $referenceId, $this->referenceIdsToFormattedClientHintsIndex[$referenceType] ) ||
			!array_key_exists(
				$this->referenceIdsToFormattedClientHintsIndex[$referenceType][$referenceId],
				$this->formattedClientHints
			)
		) {
			return null;
		}
		// The reference ID matches a string in $this->formattedClientHints, so return it.
		return $this->formattedClientHints[
			$this->referenceIdsToFormattedClientHintsIndex[$referenceType][$referenceId]
		];
	}
}
