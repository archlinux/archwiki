<?php

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations\Signals;

use LogicException;

/**
 * Value object that stores whether a given signal has matched a user, and if the signal has matched the user
 * then also data that describes the match.
 */
class SuggestedInvestigationsSignalMatchResult {

	private function __construct(
		private readonly bool $isMatch,
		private readonly string $name,
		private readonly mixed $value = null,
		private readonly bool $allowsMerging = false,
		private readonly array $equivalentNamesForMerging = [],
		private readonly int $triggerId = 0,
		private readonly string $triggerIdTable = '',
		private readonly int $userInfoBitFlags = 0,
	) {
	}

	/**
	 * Creates an object which indicates that a user did match the given signal.
	 *
	 * @param string $name The internal name of the signal. See {@link self::getName} for more detail.
	 * @param string $value A value that describes the positive match in string format (which can be saved in
	 *   the cusi_signal table).
	 * @param bool $allowsMerging Whether open suggested investigation cases which share the same value for this
	 *   signal should be merged into one case
	 * @param int $triggerId An ID of a revision or logging table row that triggered this signal, or otherwise
	 *   associated with the matched signal. Default 0 for no associated trigger ID.
	 * @param string $triggerIdTable The database table where the row referenced by the ID in $triggerId is.
	 *   Currently supports values of the array
	 *   {@link SuggestedInvestigationsCaseManagerService::TRIGGER_TYPE_TO_TABLE_NAME_MAP}.
	 * @param string[] $equivalentNamesForMerging A list of values that can be returned by {@link self::getName} that
	 *   should be considered as the same as this signal's {@link self::getName} value when merging cases.
	 *   Only used if $allowsMerging is true. Defaults to an empty array.
	 * @param int $userInfoBitFlags The integer representing bit flags to be stored with the matched user
	 *   which represents information about this user related to the case and/or signals the user matched.
	 *   If the user already exists in a case, the bit flags are combined using the bitwise inclusive OR
	 *   operator (`|`).
	 */
	public static function newPositiveResult(
		string $name,
		string $value,
		bool $allowsMerging,
		int $triggerId = 0,
		string $triggerIdTable = '',
		array $equivalentNamesForMerging = [],
		int $userInfoBitFlags = 0
	): self {
		return new self(
			true,
			$name,
			$value,
			$allowsMerging,
			$equivalentNamesForMerging,
			$triggerId,
			$triggerIdTable,
			$userInfoBitFlags
		);
	}

	/**
	 * Creates an object which indicates that a user did not match the given signal.
	 *
	 * @param string $name The internal name of the signal. See {@link self::getName} for more detail.
	 */
	public static function newNegativeResult( string $name ): self {
		return new self( false, $name );
	}

	/**
	 * Returns whether the user matched the signal
	 */
	public function isMatch(): bool {
		return $this->isMatch;
	}

	/**
	 * Returns the name of the signal that was matched against. This form is the internal name for the
	 * signal and can be used to:
	 * * Store the name of the signal in the database
	 * * Construct message keys that describe the name of the signal in a localised form
	 */
	public function getName(): string {
		return $this->name;
	}

	/**
	 * If the user matched this signal, then the value associated with the match.
	 *
	 * @throws LogicException If the signal did not match the user.
	 */
	public function getValue(): string {
		if ( !$this->isMatch ) {
			throw new LogicException( 'No value is associated with a negative match for a signal.' );
		}
		return $this->value;
	}

	/**
	 * If the signal matched the user, then this returns whether an exact match for the value in other
	 * open suggested investigations should cause the user to be associated with all of them.
	 *
	 * If this returns true then:
	 * * Merge any open suggested investigation cases which have this signal and the value matches the value
	 *   provided by {@link self::getValue}.
	 * * If the above found any matching suggested investigation cases, then add the user to the resulting
	 *   one open suggested investigation case. Otherwise, create a new suggested investigation as normal.
	 *
	 * @throws LogicException If the signal did not match the user.
	 */
	public function valueMatchAllowsMerging(): bool {
		if ( !$this->isMatch ) {
			throw new LogicException( 'No value is associated with a negative match for a signal.' );
		}
		return $this->allowsMerging;
	}

	/**
	 * If the signal matched the user and {@link self::valueMatchAllowsMerging} returns `true`, then this
	 * is a list of values that should be considered equal to our {@link self::getName} when finding
	 * existing cases to merge this signal into.
	 *
	 * @return string[]
	 */
	public function getEquivalentNamesForMerging(): array {
		if ( !$this->isMatch ) {
			throw new LogicException( 'Negative matched signals cannot be merged with existing cases.' );
		}
		return $this->equivalentNamesForMerging;
	}

	/**
	 * If the signal matched the user, then this returns the ID of a row in a table that is considered
	 * to have triggered the signal to be matched. What exactly is defined as the trigger of the signal
	 * is left up to the individual signal.
	 *
	 * Callers are expected to call {@link self::getTriggerIdTable} to determine what table the ID is
	 * referring to.
	 *
	 * @throws LogicException If the signal did not match the user.
	 */
	public function getTriggerId(): int {
		if ( !$this->isMatch ) {
			throw new LogicException( 'No trigger ID is associated with a negative match for a signal.' );
		}
		return $this->triggerId;
	}

	/**
	 * If the signal matched the user, then this returns the database table where the row referenced by the ID
	 * from {@link self::getTriggerId} is.
	 *
	 * @throws LogicException If the signal did not match the user.
	 */
	public function getTriggerIdTable(): string {
		if ( !$this->isMatch ) {
			throw new LogicException( 'No trigger table is associated with a negative match for a signal.' );
		}
		return $this->triggerIdTable;
	}

	/**
	 * If the signal matched the user, then this returns the bit flags storing information about
	 * this user related to the case and/or this matched signal. If the user is already associated
	 * with this signal and case, then the bit flags should be combined using the bitwise inclusive OR
	 * operator (`|`).
	 *
	 * @throws LogicException If the signal did not match the user.
	 */
	public function getUserInfoBitFlags(): int {
		if ( !$this->isMatch ) {
			throw new LogicException(
				'No user info bit flags are associated with a negative match for a signal.'
			);
		}
		return $this->userInfoBitFlags;
	}
}

// @codeCoverageIgnoreStart
/**
 * @deprecated since 1.46
 */
class_alias(
	SuggestedInvestigationsSignalMatchResult::class,
	'MediaWiki\\CheckUser\\SuggestedInvestigations\\Signals\\SuggestedInvestigationsSignalMatchResult'
);
// @codeCoverageIgnoreEnd
