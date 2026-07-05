<?php

namespace MediaWiki\Extension\AbuseFilter\Filter;

/**
 * (Mutable) value object to represent flags that can be *manually* set on a filter.
 */
class Flags {
	private bool $suppressed;

	private bool $hidden;

	private bool $protected;

	public const FILTER_PUBLIC = 0b000;
	public const FILTER_SUPPRESSED = 0b100;
	public const FILTER_HIDDEN = 0b001;
	public const FILTER_USES_PROTECTED_VARS = 0b010;

	public function __construct(
		private bool $enabled,
		private bool $deleted,
		private int $privacyLevel,
		private bool $global
	) {
		$this->suppressed = (bool)( self::FILTER_SUPPRESSED & $privacyLevel );
		$this->hidden = (bool)( self::FILTER_HIDDEN & $privacyLevel );
		$this->protected = (bool)( self::FILTER_USES_PROTECTED_VARS & $privacyLevel );
	}

	public function getEnabled(): bool {
		return $this->enabled;
	}

	public function setEnabled( bool $enabled ): void {
		$this->enabled = $enabled;
	}

	public function getDeleted(): bool {
		return $this->deleted;
	}

	public function setDeleted( bool $deleted ): void {
		$this->deleted = $deleted;
	}

	public function getSuppressed(): bool {
		return $this->suppressed;
	}

	public function setSuppressed( bool $suppressed ): void {
		$this->suppressed = $suppressed;
		$this->updatePrivacyLevel();
	}

	public function getHidden(): bool {
		return $this->hidden;
	}

	public function setHidden( bool $hidden ): void {
		$this->hidden = $hidden;
		$this->updatePrivacyLevel();
	}

	public function getProtected(): bool {
		return $this->protected;
	}

	public function setProtected( bool $protected ): void {
		$this->protected = $protected;
		$this->updatePrivacyLevel();
	}

	private function updatePrivacyLevel() {
		$suppressed = $this->suppressed ? self::FILTER_SUPPRESSED : 0;
		$hidden = $this->hidden ? self::FILTER_HIDDEN : 0;
		$protected = $this->protected ? self::FILTER_USES_PROTECTED_VARS : 0;
		$this->privacyLevel = $suppressed | $hidden | $protected;
	}

	public function getPrivacyLevel(): int {
		return $this->privacyLevel;
	}

	public function getGlobal(): bool {
		return $this->global;
	}

	public function setGlobal( bool $global ): void {
		$this->global = $global;
	}
}
