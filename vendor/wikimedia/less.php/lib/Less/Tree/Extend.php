<?php
/**
 * @private
 */
class Less_Tree_Extend extends Less_Tree {

	/** @var Less_Tree_Selector */
	public $selector;
	/** @var string */
	public $option;
	/** @var int */
	public $index;
	/** @var Less_Tree_Selector[] */
	public $selfSelectors = [];
	/** @var bool */
	public $allowBefore;
	/** @var bool */
	public $allowAfter;
	/** @var bool */
	public $firstExtendOnThisSelectorPath;
	/** @var Less_Tree_Ruleset|null */
	public $ruleset;
	/** @var string */
	public $object_id;
	/** @var array<string,true> */
	public $parent_ids = [];

	/**
	 * @param Less_Tree_Selector $selector
	 * @param string $option
	 * @param int $index
	 */
	public function __construct( $selector, $option, $index ) {
		static $i = 0;
		$this->selector = $selector;
		$this->option = $option;
		$this->index = $index;

		switch ( $option ) {
			case "all":
				$this->allowBefore = true;
				$this->allowAfter = true;
				break;
			default:
				$this->allowBefore = false;
				$this->allowAfter = false;
				break;
		}

		// This must use a string (instead of int) so that array_merge()
		// preserves keys on arrays that use IDs in their keys.
		$this->object_id = 'id_' . $i++;

		$this->parent_ids = [
			$this->object_id => true
		];
	}

	public function accept( $visitor ) {
		$this->selector = $visitor->visitObj( $this->selector );
	}

	public function compile( $env ) {
		Less_Parser::$has_extends = true;
		$this->selector = $this->selector->compile( $env );
		return $this;
		// return new self( $this->selector->compile($env), $this->option, $this->index);
	}

	public function clone() {
		return new self( $this->selector, $this->option, $this->index );
	}

	public function findSelfSelectors( $selectors ) {
		$selfElements = [];

		for ( $i = 0, $selectors_len = count( $selectors ); $i < $selectors_len; $i++ ) {
			$selectorElements = $selectors[$i]->elements;
			// duplicate the logic in genCSS function inside the selector node.
			// future TODO - move both logics into the selector joiner visitor
			if ( $i && $selectorElements && $selectorElements[0]->combinator === "" ) {
				$selectorElements[0]->combinator = ' ';
			}
			$selfElements = array_merge( $selfElements, $selectors[$i]->elements );
		}

		$this->selfSelectors = [ new Less_Tree_Selector( $selfElements ) ];
	}

}
