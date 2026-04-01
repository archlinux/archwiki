<?php
/**
 * @private
 */
class Less_Tree_Element extends Less_Tree implements Less_Tree_HasValueProperty {

	/** @var string */
	public $combinator;
	/** @var bool Whether combinator is null (represented by empty string) or child (single space) */
	public $combinatorIsEmptyOrWhitespace;
	/** @var string|Less_Tree */
	public $value;
	/** @var int|null */
	public $index;
	/** @var array|null */
	public $currentFileInfo;

	/**
	 * @param null|string $combinator
	 * @param string|Less_Tree $value
	 * @param int|null $index
	 * @param array|null $currentFileInfo
	 */
	public function __construct( $combinator, $value, $index = null, $currentFileInfo = null ) {
		$this->value = $value;

		// see less-2.5.3.js#Combinator
		$this->combinator = $combinator ?? '';
		$this->combinatorIsEmptyOrWhitespace = ( $combinator === null || trim( $combinator ) === '' );

		$this->index = $index;
		$this->currentFileInfo = $currentFileInfo;
	}

	public function accept( $visitor ) {
		if ( $this->value instanceof Less_Tree ) {
			$this->value = $visitor->visitObj( $this->value );
		}
	}

	public function compile( $env ) {
		return new self(
			$this->combinator,
			( $this->value instanceof Less_Tree ? $this->value->compile( $env ) : $this->value ),
			$this->index,
			$this->currentFileInfo
		);
	}

	/**
	 * @see Less_Tree::genCSS
	 */
	public function genCSS( $output ) {
		$output->add( $this->toCSS(), $this->currentFileInfo, $this->index );
	}

	public function toCSS() {
		if ( $this->value instanceof Less_Tree ) {
			$value = $this->value->toCSS();
		} else {
			$value = $this->value;
		}

		$spaceOrEmpty = ' ';
		if ( Less_Parser::$options['compress'] ||
			( isset( Less_Environment::$_noSpaceCombinators[$this->combinator] ) && Less_Environment::$_noSpaceCombinators[$this->combinator] )
		) {
			$spaceOrEmpty = '';
		}

		return $spaceOrEmpty . $this->combinator . $spaceOrEmpty . $value;
	}

}
