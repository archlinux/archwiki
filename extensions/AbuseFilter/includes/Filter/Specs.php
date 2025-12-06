<?php

namespace MediaWiki\Extension\AbuseFilter\Filter;

/**
 * (Mutable) value object that represents the "specs" of a filter.
 */
class Specs {
	/** @var string */
	private $rules;
	/** @var string */
	private $comments;
	/** @var string */
	private $name;
	/** @var string[] */
	private $actionsNames;
	/** @var string */
	private $group;

	/**
	 * @param string $rules
	 * @param string $comments
	 * @param string $name
	 * @param string[] $actionsNames
	 * @param string $group
	 */
	public function __construct( string $rules, string $comments, string $name, array $actionsNames, string $group ) {
		$this->rules = $rules;
		$this->comments = $comments;
		$this->name = $name;
		$this->actionsNames = $actionsNames;
		$this->group = $group;
	}

	public function getRules(): string {
		return $this->rules;
	}

	public function setRules( string $rules ): void {
		$this->rules = $rules;
	}

	public function getComments(): string {
		return $this->comments;
	}

	public function setComments( string $comments ): void {
		$this->comments = $comments;
	}

	public function getName(): string {
		return $this->name;
	}

	public function setName( string $name ): void {
		$this->name = $name;
	}

	/**
	 * @return string[]
	 */
	public function getActionsNames(): array {
		return $this->actionsNames;
	}

	/**
	 * @param string[] $actionsNames
	 */
	public function setActionsNames( array $actionsNames ): void {
		$this->actionsNames = $actionsNames;
	}

	public function getGroup(): string {
		return $this->group;
	}

	public function setGroup( string $group ): void {
		$this->group = $group;
	}
}
