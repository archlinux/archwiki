<?php
/**
 * @private
 * @see less-2.5.3.js#Anonymous.prototype
 */
class Less_Tree_Anonymous extends Less_Tree implements Less_Tree_HasValueProperty {
	public $value;
	public $quote;
	public $index;
	public $mapLines;
	public $currentFileInfo;
	/** @var bool */
	public $rulesetLike;
	public $isReferenced;

	/**
	 * @param string $value
	 * @param int|null $index
	 * @param array|null $currentFileInfo
	 * @param bool|null $mapLines
	 * @param bool $rulesetLike
	 * @param bool $referenced
	 */
	public function __construct( $value, $index = null, $currentFileInfo = null, $mapLines = null, $rulesetLike = false, $referenced = false ) {
		$this->value = $value;
		$this->index = $index;
		$this->mapLines = $mapLines;
		$this->currentFileInfo = $currentFileInfo;
		$this->rulesetLike = $rulesetLike;
		$this->isReferenced = $referenced;
	}

	public function compile( $env ) {
		return new self( $this->value, $this->index, $this->currentFileInfo, $this->mapLines, $this->rulesetLike, $this->isReferenced );
	}

	/**
	 * @param Less_Tree|mixed $x
	 * @return int|null
	 * @see less-2.5.3.js#Anonymous.prototype.compare
	 */
	public function compare( $x ) {
		return ( is_object( $x ) && $this->toCSS() === $x->toCSS() ) ? 0 : null;
	}

	public function isRulesetLike() {
		return $this->rulesetLike;
	}

	public function genCSS( $output ) {
		$output->add( $this->value, $this->currentFileInfo, $this->index, $this->mapLines );
	}

	public function markReferenced() {
		$this->isReferenced = true;
	}

	public function getIsReferenced() {
		return !isset( $this->currentFileInfo['reference'] ) || !$this->currentFileInfo['reference'] || $this->isReferenced;
	}
}
