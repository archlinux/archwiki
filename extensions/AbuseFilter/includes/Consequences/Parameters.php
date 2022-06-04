<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences;

use MediaWiki\Extension\AbuseFilter\Filter\ExistingFilter;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\User\UserIdentity;

/**
 * Immutable value object that provides "base" parameters to Consequence objects
 * @todo Should use ActionSpecifier
 */
class Parameters {
	/** @var ExistingFilter */
	private $filter;

	/** @var bool */
	private $isGlobalFilter;

	/** @var UserIdentity */
	private $user;

	/** @var LinkTarget */
	private $target;

	/** @var string */
	private $action;

	/**
	 * @param ExistingFilter $filter
	 * @param bool $isGlobalFilter
	 * @param UserIdentity $user
	 * @param LinkTarget $target
	 * @param string $action
	 */
	public function __construct(
		ExistingFilter $filter,
		bool $isGlobalFilter,
		UserIdentity $user,
		LinkTarget $target,
		string $action
	) {
		$this->filter = $filter;
		$this->isGlobalFilter = $isGlobalFilter;
		$this->user = $user;
		$this->target = $target;
		$this->action = $action;
	}

	/**
	 * @return ExistingFilter
	 */
	public function getFilter(): ExistingFilter {
		return $this->filter;
	}

	/**
	 * @return bool
	 */
	public function getIsGlobalFilter(): bool {
		return $this->isGlobalFilter;
	}

	/**
	 * @return UserIdentity
	 */
	public function getUser(): UserIdentity {
		return $this->user;
	}

	/**
	 * @return LinkTarget
	 */
	public function getTarget(): LinkTarget {
		return $this->target;
	}

	/**
	 * @return string
	 */
	public function getAction(): string {
		return $this->action;
	}
}
