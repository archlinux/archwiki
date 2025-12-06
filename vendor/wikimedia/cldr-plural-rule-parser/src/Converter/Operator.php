<?php
/**
 * @author Niklas Laxström, Tim Starling
 * @license GPL-2.0-or-later
 * @file
 */

namespace CLDRPluralRuleParser\Converter;

use CLDRPluralRuleParser\Converter;

/**
 * Helper for Converter.
 * An operator object, representing a region of the input string (for error
 * messages), and the binary operator at that location.
 */
class Operator extends Fragment {
	/** @var string The name */
	public $name;

	/**
	 * Each op type has three characters: left operand type, right operand type and result type
	 *
	 *   b = boolean
	 *   n = number
	 *   r = range
	 *
	 * A number is a kind of range.
	 *
	 * @var array
	 */
	private const OP_TYPES = [
		'or' => 'bbb',
		'and' => 'bbb',
		'is' => 'nnb',
		'is-not' => 'nnb',
		'in' => 'nrb',
		'not-in' => 'nrb',
		'within' => 'nrb',
		'not-within' => 'nrb',
		'mod' => 'nnn',
		',' => 'rrr',
		'..' => 'nnr',
	];

	/**
	 * Map converting from the abbreviation to the full form.
	 *
	 * @var array
	 */
	private const TYPE_SPEC_MAP = [
		'b' => 'boolean',
		'n' => 'number',
		'r' => 'range',
	];

	/**
	 * Map for converting the new operators introduced in Rev 33 to the old forms
	 *
	 * @var array
	 */
	private const ALIAS_MAP = [
		'%' => 'mod',
		'!=' => 'not-in',
		'=' => 'in'
	];

	/**
	 * Initialize a new instance of a CLDRPluralRuleConverterOperator object
	 *
	 * @param Converter $parser The parser
	 * @param string $name The operator name
	 * @param int $pos The length
	 * @param int $length
	 */
	public function __construct( Converter $parser, $name, $pos, $length ) {
		parent::__construct( $parser, $pos, $length );
		if ( isset( self::ALIAS_MAP[$name] ) ) {
			$name = self::ALIAS_MAP[$name];
		}
		$this->name = $name;
	}

	/**
	 * Compute the operation
	 *
	 * @param Expression $left The left part of the expression
	 * @param Expression $right The right part of the expression
	 * @return Expression The result of the operation
	 */
	public function operate( Expression $left, Expression $right ): Expression {
		$typeSpec = self::OP_TYPES[$this->name];

		$leftType = self::TYPE_SPEC_MAP[$typeSpec[0]];
		$rightType = self::TYPE_SPEC_MAP[$typeSpec[1]];
		$resultType = self::TYPE_SPEC_MAP[$typeSpec[2]];

		$start = min( $this->pos, $left->pos, $right->pos );
		$end = max( $this->end, $left->end, $right->end );
		$length = $end - $start;

		$newExpr = new Expression(
			$this->parser,
			$resultType,
			"{$left->rpn} {$right->rpn} {$this->name}",
			$start,
			$length
		);

		if ( !$left->isType( $leftType ) ) {
			$newExpr->error( "invalid type for left operand: expected $leftType, got {$left->type}" );
		}

		if ( !$right->isType( $rightType ) ) {
			$newExpr->error( "invalid type for right operand: expected $rightType, got {$right->type}" );
		}

		return $newExpr;
	}
}
