<?php

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use InvalidArgumentException;

class LengthSpec extends TexNode {
	private string $sign;
	private string $unit;
	private string $number;

	public function __construct( ?string $sign, array $number, string $unit ) {
		$this->sign = $sign ?? '';
		$this->unit = $unit;
		if ( count( $number ) === 3 ) {

				$this->number = implode( $number[0] ) . ( $number[1] ?? '' ) . implode( $number[2] );
		} elseif ( count( $number ) === 2 ) {
			$this->number = ( $number[0] ?? '' ) . implode( $number[1] );
		} else {
			throw new InvalidArgumentException( 'Invalid number in length spec' );
		}
		parent::__construct();
	}

	public function render() {
		return '[' . $this->sign . $this->number . $this->unit . ']';
	}

}
