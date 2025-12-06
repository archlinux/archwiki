<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences;

use MediaWiki\Extension\AbuseFilter\ActionSpecifier;
use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;

/**
 * Immutable value object that provides "base" parameters to Consequence objects
 */
class Parameters {
	/** @var ExistingFilter */
	private $filter;

	/** @var bool */
	private $isGlobalFilter;

	/** @var ActionSpecifier */
	private $specifier;

	/**
	 * @param ExistingFilter $filter
	 * @param bool $isGlobalFilter
	 * @param ActionSpecifier $specifier
	 */
	public function __construct(
		ExistingFilter $filter,
		bool $isGlobalFilter,
		ActionSpecifier $specifier
	) {
		$this->filter = $filter;
		$this->isGlobalFilter = $isGlobalFilter;
		$this->specifier = $specifier;
	}

	public function getFilter(): ExistingFilter {
		return $this->filter;
	}

	public function getIsGlobalFilter(): bool {
		return $this->isGlobalFilter;
	}

	public function getActionSpecifier(): ActionSpecifier {
		return $this->specifier;
	}

	public function getUser(): UserIdentity {
		return $this->specifier->getUser();
	}

	public function getTarget(): LinkTarget {
		return $this->specifier->getTitle();
	}

	public function getAction(): string {
		return $this->specifier->getAction();
	}
}
