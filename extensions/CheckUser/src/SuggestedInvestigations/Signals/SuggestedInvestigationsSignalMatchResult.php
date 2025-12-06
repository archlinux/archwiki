<?php

namespace MediaWiki\CheckUser\SuggestedInvestigations\Signals;

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
		private readonly bool $allowsMerging = false
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
	 */
	public static function newPositiveResult( string $name, string $value, bool $allowsMerging ): self {
		return new self( true, $name, $value, $allowsMerging );
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
}
