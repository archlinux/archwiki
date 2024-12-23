<?php

namespace MediaWiki\Extension\AbuseFilter\Filter;

/**
 * (Mutable) value object to represent flags that can be *manually* set on a filter.
 */
class Flags {
	/** @var bool */
	private $enabled;
	/** @var bool */
	private $deleted;
	/** @var bool */
	private $hidden;
	/** @var bool */
	private $protected;
	/** @var int */
	private $privacyLevel;
	/** @var bool */
	private $global;

	public const FILTER_PUBLIC = 0b00;
	public const FILTER_HIDDEN = 0b01;
	public const FILTER_USES_PROTECTED_VARS = 0b10;

	/**
	 * @param bool $enabled
	 * @param bool $deleted
	 * @param int $privacyLevel
	 * @param bool $global
	 */
	public function __construct( bool $enabled, bool $deleted, int $privacyLevel, bool $global ) {
		$this->enabled = $enabled;
		$this->deleted = $deleted;
		$this->hidden = (bool)( self::FILTER_HIDDEN & $privacyLevel );
		$this->protected = (bool)( self::FILTER_USES_PROTECTED_VARS & $privacyLevel );
		$this->privacyLevel = $privacyLevel;
		$this->global = $global;
	}

	/**
	 * @return bool
	 */
	public function getEnabled(): bool {
		return $this->enabled;
	}

	/**
	 * @param bool $enabled
	 */
	public function setEnabled( bool $enabled ): void {
		$this->enabled = $enabled;
	}

	/**
	 * @return bool
	 */
	public function getDeleted(): bool {
		return $this->deleted;
	}

	/**
	 * @param bool $deleted
	 */
	public function setDeleted( bool $deleted ): void {
		$this->deleted = $deleted;
	}

	/**
	 * @return bool
	 */
	public function getHidden(): bool {
		return $this->hidden;
	}

	/**
	 * @param bool $hidden
	 */
	public function setHidden( bool $hidden ): void {
		$this->hidden = $hidden;
		$this->updatePrivacyLevel();
	}

	/**
	 * @return bool
	 */
	public function getProtected(): bool {
		return $this->protected;
	}

	/**
	 * @param bool $protected
	 */
	public function setProtected( bool $protected ): void {
		$this->protected = $protected;
		$this->updatePrivacyLevel();
	}

	private function updatePrivacyLevel() {
		$hidden = $this->hidden ? self::FILTER_HIDDEN : 0;
		$protected = $this->protected ? self::FILTER_USES_PROTECTED_VARS : 0;
		$this->privacyLevel = $hidden | $protected;
	}

	/**
	 * @return int
	 */
	public function getPrivacyLevel(): int {
		return $this->privacyLevel;
	}

	/**
	 * @return bool
	 */
	public function getGlobal(): bool {
		return $this->global;
	}

	/**
	 * @param bool $global
	 */
	public function setGlobal( bool $global ): void {
		$this->global = $global;
	}
}
