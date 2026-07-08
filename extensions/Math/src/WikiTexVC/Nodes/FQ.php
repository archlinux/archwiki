<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\WikiTexVC\Nodes;

use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\TexClass;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLarray;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsubsup;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmunderover;
use MediaWiki\Extension\Math\WikiTexVC\TexUtil;

class FQ extends TexNode {

	public function __construct(
		private readonly TexNode $base,
		private readonly TexNode $down,
		private readonly TexNode $up,
	) {
		parent::__construct( $base, $down, $up );
	}

	public function getBase(): TexNode {
		return $this->base;
	}

	public function getUp(): TexNode {
		return $this->up;
	}

	public function getDown(): TexNode {
		return $this->down;
	}

	/** @inheritDoc */
	public function render() {
		return $this->base->render() . '_' . $this->down->inCurlies() . '^' . $this->up->inCurlies();
	}

	/** @inheritDoc */
	public function toMMLTree( $arguments = [], &$state = [] ): MMLbase {
		$tu = TexUtil::getInstance();

		$hasLimits = array_key_exists( 'limits', $state );
		$displaystyle = ( $state['styleargs']['displaystyle'] ?? 'true' ) === 'true';

		if ( $hasLimits ) {
			$argsOp = [ 'form' => 'prefix' ];
			if ( !$displaystyle ) {
				$argsOp['movablelimits'] = 'true';
			}
			if ( $this->base->containsFunc( '\\limits' ) ) {
				$argsOp['movablelimits'] = 'false';
			}
			if ( $this->base->containsFunc( '\\nolimits' ) ) {
				$argsOp['movablelimits'] = 'false';
				$hasLimits = false;
			}
			$base = $state['limits'];
			unset( $state['limits'] );
		} else {
			$base = $this->getBase();
			$argsOp = $arguments;
		}

		// Special-case: sideset with empty (non-curly) base -> return array of under/over rows.
		if ( isset( $state['sideset'] ) && $base->getLength() === 0 && !$base->isCurly() ) {
			return new MMLarray(
				new MMLmrow( TexClass::ORD, [], $this->getDown()->toMMLTree( [], $state ) ),
				new MMLmrow( TexClass::ORD, [], $this->getUp()->toMMLTree( [], $state ) )
			);
		}

		$above = false;
		$movablelimitsEnabled = false;

		// Determine whether to use munderover (above=true) vs mmlsubsup (above=false).
		if ( $base instanceof Literal ) {
			$litArg = trim( $base->getArgs()[0] );
			$useMoveLimits = $tu->operator_rendering( $litArg )[1]['movesupsub'] ?? false;

			$containsLimits = $this->getBase()->containsFunc( '\\limits' );
			$movablelimitsEnabled = ( $argsOp['movablelimits'] ?? 'true' ) === 'true';

			if ( $containsLimits || ( $useMoveLimits && $movablelimitsEnabled && $displaystyle ) ) {
				// replicate original mutation semantics
				if ( $containsLimits || ( $useMoveLimits && $displaystyle ) ) {
					$argsOp['movablelimits'] = 'false';
				}
				if ( !$useMoveLimits ) {
					unset( $argsOp['movablelimits'] );
				}
				$above = true;
			}
		} elseif ( $base instanceof Fun1 && $tu->over_operator( $base->getFname() ) ) {
			$above = true;
		} elseif ( $this instanceof DQ && $this->getBase()->containsFunc( "\underbrace" ) ) {
			$above = true;
		}

		$baseMML = $base->toMMLTree( $argsOp, $state );
		if ( $this instanceof DQ ) {
			// the movablelimits option is only available for mo elements
			// for other elements such as mrow we need to msub instead of mover
			// bug T417375
			$moveMrow = !$displaystyle && $baseMML instanceof MMLmrow && $movablelimitsEnabled;
			if ( $hasLimits && !$moveMrow ) {
				$above = true;
			}
			if ( $this->isEmpty() ) {
				return new MMLarray();
			}
			if ( $displaystyle && $tu->operator( trim( $base->render() ) ) ) {
				$above = true;
			}
		}

		$emptyMrow = $base->isEmpty() ? new MMLmrow() : new MMLarray();

		return $this->newMmlElement(
			$above,
			new MMLarray( $emptyMrow, $baseMML ),
			new MMLmrow( TexClass::ORD, [], $this->getDown()->toMMLTree( $arguments, $state ) ),
			new MMLmrow( TexClass::ORD, [], $this->getUp()->toMMLTree( $arguments, $state ) )
		);
	}

	protected function newMmlElement( bool $above, MMLbase $base, MMLbase $down, MMLbase $up ): MMLbase {
		return $above
			? MMLmunderover::newSubtree( $base, $down, $up )
			: MMLmsubsup::newSubtree( $base, $down, $up );
	}
}
