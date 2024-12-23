<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use Generator;
use InvalidArgumentException;

class Matrix extends TexArray {

	/** @var string */
	private $top;
	private array $lines = [];

	private ?TexArray $columnSpecs = null;

	private ?string $renderedColumSpecs = null;
	private ?array $boarder = null;

	private ?string $alignInfo = null;

	public function __construct( string $top, TexArray $mainarg, $rowSpec = null ) {
		foreach ( $mainarg->args as $row ) {
			if ( !$row instanceof TexArray ) {
				throw new InvalidArgumentException( 'Nested arguments have to be type of TexArray' );
			}
			$this->lines[] = $row->containsFunc( '\hline' );
		}
		if ( $mainarg instanceof Matrix ) {
			$this->args = $mainarg->args;
			$this->curly = $mainarg->curly;
		} else {
			parent::__construct( ...$mainarg->args );
		}
		$this->top = $top;
		if ( $rowSpec && count( $this->args ) ) {
			// @phan-suppress-next-line PhanUndeclaredMethod
			$this->first()->setRowSpecs( $rowSpec );
		}
	}

	public function getLines(): array {
		return $this->lines;
	}

	/**
	 * @return string
	 */
	public function getTop(): string {
		return $this->top;
	}

	public function setTop( string $top ): Matrix {
		$this->top = $top;
		return $this;
	}

	public function getRenderedColumnSpecs(): string {
		if ( $this->renderedColumSpecs == null ) {
			$this->renderColumnSpecs();
		}
		return $this->renderedColumSpecs;
	}

	public function setColumnSpecs( TexArray $specs ): Matrix {
		$this->columnSpecs = $specs;
		$this->renderedColumSpecs = null;
		$this->alignInfo = null;
		$this->boarder = null;
		return $this;
	}

	public function hasColumnInfo(): bool {
		return $this->getRenderedColumnSpecs() !== '';
	}

	public function getAlignInfo(): string {
		if ( $this->alignInfo == null ) {
			$this->renderColumnSpecs();
		}
		return $this->alignInfo;
	}

	/**
	 * @return TexArray
	 */
	public function getMainarg(): TexArray {
		return $this;
	}

	public function containsFunc( $target, $args = null ) {
		if ( $args == null ) {
			$args = [
				'\\begin{' . $this->top . '}',
				'\\end{' . $this->top . '}',
				...$this->args,
			];
		}
		return parent::containsFunc( $target, $args );
	}

	public function inCurlies() {
		return $this->render();
	}

	public function render() {
		$colSpecs = $this->columnSpecs !== null ? $this->columnSpecs->render() : '';
		return '{\\begin{' . $this->top . '}' . $colSpecs . $this->renderMatrix( $this ) . '\\end{' .
			$this->top . '}}';
	}

	public function renderMML( $arguments = [], $state = [] ): string {
		return $this->parseToMML( $this->getTop(), $arguments, null );
	}

	private function renderMatrix( Matrix $matrix ): string {
		$renderedLines = '';
		for ( $i = 0; $i < count( $matrix->args ); $i++ ) {
			$renderedLines .= self::renderLine( $matrix->args[$i] );
			if ( $i < count( $matrix->args ) - 1 ) {
				// @phan-suppress-next-line PhanTypeMismatchArgumentSuperType
				$renderedLines .= $matrix->renderRowSpec( $matrix->args[$i] );
			}
		}
		return $renderedLines;
	}

	private static function renderLine( $l ) {
		$mapped = array_map( static function ( $x ){
			return $x->render();
		}, $l->args );
		return implode( '&', $mapped );
	}

	public function extractIdentifiers( $args = null ) {
		if ( $args == null ) {
			$args = $this->args;
		}

		$mapped = array_map( function ( $a ){
			return array_map( function ( $p ){
				return parent::extractIdentifiers( $p->args );
			}, $a->args );
		}, $args );

		return self::flatDeep( $mapped );
	}

	private static function flatDeep( $a ) {
		if ( !is_array( $a ) ) {
			return $a;
		}

		$reduced = array_reduce( $a, [ self::class, 'reduceCallback' ], [] );
		return $reduced;
	}

	private static function reduceCallback( $acc, $val ) {
		// Casting to array if output is string, this is required for array_merge function.
		$fld = self::flatDeep( $val );
		if ( !is_array( $fld ) ) {
			$fld = [ $fld ];
		}
		return array_merge( $acc, $fld );
	}

	/**
	 * @suppress PhanTypeMismatchReturn
	 * @return Generator<TexArray>
	 */
	public function getIterator(): Generator {
		return parent::getIterator();
	}

	/**
	 * @return void
	 */
	public function renderColumnSpecs(): void {
		$colSpecs = $this->columnSpecs ?? new TexArray();
		$this->renderedColumSpecs = trim( $colSpecs->render(), "{} \n\r\t\v\x00" );
		$align = '';
		$colNo = 0;
		$this->boarder = [];
		foreach ( str_split( $this->renderedColumSpecs ) as $chr ) {
			switch ( $chr ) {
				case '|':
					$this->boarder[$colNo] = true;
					break;
				case 'r':
					$align .= 'right ';
					$colNo++;
					break;
				case 'l':
					$align .= 'left ';
					$colNo++;
					break;
				case 'c':
					$colNo++;
					$align .= 'center ';
					break;
			}
		}
		$this->alignInfo = $align;
	}

	public function getBoarder(): array {
		if ( $this->boarder == null ) {
			$this->renderColumnSpecs();
		}
		return $this->boarder;
	}

	public function renderRowSpec( TexArray $row ): string {
		$rowSpecs = '';
		if ( $row->getRowSpecs() !== null ) {
			$rowSpecs = $row->getRowSpecs()->render();
		}
		return '\\\\' . $rowSpecs;
	}

}
