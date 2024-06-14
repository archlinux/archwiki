<?php
/**
 * @private
 * @see less-2.5.3.js#Anonymous.prototype
 */
class Less_Tree_Directive extends Less_Tree implements Less_Tree_HasValueProperty {
	public $name;
	public $value;
	public $rules;
	public $index;
	public $isReferenced;
	public $isRooted;
	public $currentFileInfo;
	public $debugInfo;

	public function __construct( $name, $value = null, $rules = null, $index = null, $isRooted = false, $currentFileInfo = null, $debugInfo = null ) {
		$this->name = $name;
		$this->value = $value;

		if ( $rules !== null ) {
			if ( is_array( $rules ) ) {
				$this->rules = $rules;
			} else {
				$this->rules = [ $rules ];
				$this->rules[0]->selectors = $this->emptySelectors();
			}

			foreach ( $this->rules as $rule ) {
				$rule->allowImports = true;
			}
		}

		$this->index = $index;
		$this->isRooted = $isRooted;
		$this->currentFileInfo = $currentFileInfo;
		$this->debugInfo = $debugInfo;
	}

	public function accept( $visitor ) {
		if ( $this->rules ) {
			$this->rules = $visitor->visitArray( $this->rules );
		}
		if ( $this->value ) {
			$this->value = $visitor->visitObj( $this->value );
		}
	}

	public function isRulesetLike() {
		return $this->rules || !$this->isCharset();
	}

	public function isCharset() {
		return $this->name === "@charset";
	}

	/**
	 * @see Less_Tree::genCSS
	 */
	public function genCSS( $output ) {
		$output->add( $this->name, $this->currentFileInfo, $this->index );
		if ( $this->value ) {
			$output->add( ' ' );
			$this->value->genCSS( $output );
		}
		if ( $this->rules !== null ) {
			Less_Tree::outputRuleset( $output, $this->rules );
		} else {
			$output->add( ';' );
		}
	}

	public function compile( $env ) {
		$value = $this->value;
		$rules = $this->rules;

		// Media stored inside other directive should not bubble over it
		// backup media bubbling information
		$mediaPathBackup = $env->mediaPath;
		$mediaPBlocksBackup = $env->mediaBlocks;
		// Deleted media bubbling information
		$env->mediaPath = [];
		$env->mediaBlocks = [];

		if ( $value ) {
			$value = $value->compile( $env );
		}

		if ( $rules ) {
			// Assuming that there is only one rule at this point - that is how parser constructs the rule
			$rules = $rules[0]->compile( $env );
			$rules->root = true;
		}

		// Restore media bubbling information
		$env->mediaPath = $mediaPathBackup;
		$env->mediaBlocks = $mediaPBlocksBackup;

		return new self( $this->name, $value, $rules, $this->index, $this->isRooted, $this->currentFileInfo, $this->debugInfo );
	}

	public function variable( $name ) {
		if ( $this->rules ) {
			return $this->rules[0]->variable( $name );
		}
	}

	public function find( $selector ) {
		if ( $this->rules ) {
			return $this->rules[0]->find( $selector, $this );
		}
	}

	public function markReferenced() {
		$this->isReferenced = true;
		if ( $this->rules ) {
			Less_Tree::ReferencedArray( $this->rules );
		}
	}

	public function emptySelectors() {
		$el = new Less_Tree_Element( '', '&', $this->index, $this->currentFileInfo );
		$sels = [ new Less_Tree_Selector( [ $el ], [], null, $this->index, $this->currentFileInfo ) ];
		$sels[0]->mediaEmpty = true;
		return $sels;
	}

}
