<?php

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use InvalidArgumentException;

class LengthSpec extends TexNode {
	private readonly string $sign;
	private readonly string $number;

	public function __construct(
		?string $sign,
		array $number,
		private readonly string $unit,
	) {
		$this->sign = $sign ?? '';
		if ( count( $number ) === 3 ) {
			$this->number = implode( $number[0] ) . ( $number[1] ?? '' ) . implode( $number[2] );
		} elseif ( count( $number ) === 2 ) {
			$this->number = ( $number[0] ?? '' ) . implode( $number[1] );
		} else {
			throw new InvalidArgumentException( 'Invalid number in length spec' );
		}
		parent::__construct();
	}

	/** @inheritDoc */
	public function render() {
		return '[' . $this->sign . $this->number . $this->unit . ']';
	}

	public function getCssLength(): string {
		return $this->sign . $this->number . $this->unit;
	}

}
