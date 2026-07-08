<?php

namespace MediaWiki\Extension\AbuseFilter\Parser;

use MediaWiki\Extension\AbuseFilter\Parser\Exception\ExceptionBase;
use MediaWiki\Extension\AbuseFilter\Parser\Exception\UserVisibleWarning;

class ParserStatus {
	/**
	 * @param ExceptionBase|null $excep An exception thrown while parsing, or null if it parsed correctly
	 * @param UserVisibleWarning[] $warnings
	 * @param int $condsUsed
	 */
	public function __construct(
		protected readonly ?ExceptionBase $excep,
		protected readonly array $warnings,
		protected readonly int $condsUsed
	) {
	}

	public function getException(): ?ExceptionBase {
		return $this->excep;
	}

	/**
	 * @return UserVisibleWarning[]
	 */
	public function getWarnings(): array {
		return $this->warnings;
	}

	public function getCondsUsed(): int {
		return $this->condsUsed;
	}

	/**
	 * Whether the parsing/evaluation happened successfully.
	 * @return bool
	 */
	public function isValid(): bool {
		return !$this->excep;
	}
}
