<?php
namespace MediaWiki\Extension\Math\WikiTexVC\MMLmappings;

use IntlChar;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Misc;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Sizes;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Tag;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\TexClass;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\TexConstants\Variants;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLParsingUtil;
use MediaWiki\Extension\Math\WikiTexVC\MMLmappings\Util\MMLutil;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLarray;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLbase;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmenclose;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmerror;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmfrac;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmi;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmmultiscripts;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmo;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmover;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmpadded;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmphantom;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmroot;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmrow;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmspace;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsqrt;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmstyle;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmsup;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtable;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtd;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtext;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmtr;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmunder;
use MediaWiki\Extension\Math\WikiTexVC\MMLnodes\MMLmunderover;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\DQ;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\FQ;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Fun1;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Fun2;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Fun2sq;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Fun4;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Literal;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\Matrix;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexArray;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\TexNode;
use MediaWiki\Extension\Math\WikiTexVC\Nodes\UQ;
use MediaWiki\Extension\Math\WikiTexVC\TexUtil;
use MediaWiki\Extension\Math\WikiTexVC\TexVC;

/**
 * Parsing functions for specific recognized mappings.
 * Usually the parsing functions are invoked from the BaseMethods classes.
 */
class BaseParsing {

	public static function accent( $node, $passedArgs, $name, $operatorContent, $accent, $stretchy = null ): MMLbase {
		// Currently this is own implementation from Fun1.php
		// TODO The first if-clause is mathjax specific (and not necessary by generic parsers)
		// and will most probably removed (just for running all tc atm)
		if ( $accent == "00B4" || $accent == "0060" ) {
			$attrs = [ Tag::SCRIPTTAG => "true" ];
		} else {
			if ( $stretchy == null ) {
				// $attrs = [ "stretchy" => "false" ]; // not mention explicit stretchy
				$attrs = [];
			} else {
				$attrs = [ "stretchy" => "true" ];
			}
		}
		if ( trim( $operatorContent ) === '\\vec' ) {
			// T418686
			$attrs['class'] = 'mwe-math-vec';
			$attrs['stretchy'] = 'false';
		}
		if ( trim( $operatorContent ) === '\\hat' ) {
			// T409152
			$attrs['stretchy'] = 'false';
		}

		// Fetching entity from $accent key tbd
		$entity = MMLutil::createEntity( $accent );
		if ( !$entity ) {
			$entity = $accent;
		}
		$inner = $node->getArg()->toMMLtree( $passedArgs );

		return MMLmover::newSubtree(
			!$inner->isEmpty() ? $inner : ( new MMLmrow() ),
			new MMLmo( '', $attrs, $entity )
		);
	}

	public static function array( $node, $passedArgs, $operatorContent, $name, $begin = null, $open = null,
		$close = null, $align = null, $spacing = null,
		$vspacing = null, $style = null, $raggedHeight = null
	): MMLbase {
		$output = [];
		if ( $open != null ) {
			$resDelimiter = TexUtil::getInstance()->delimiter( trim( $open ) ) ?? false;
			if ( $resDelimiter ) {
				// $retDelim = $bm->checkAndParseDelimiter($open, $node,$passedArgs,true);
				$output[] = ( new MMLmo( TexClass::OPEN, [], $resDelimiter[0] ) );
			}
		}
		if ( $name == "Bmatrix" || $name == "bmatrix" || $name == "Vmatrix"
			|| $name == "vmatrix" || $name == "smallmatrix" || $name == "pmatrix" || $name == "matrix" ) {
			// This is a workaround and might be improved mapping BMatrix to Matrix directly instead of array
			return self::matrix( $node, $passedArgs, $operatorContent, $name,
				$open, $close, null, null, null, null, true );

		} else {
			$output[] = new MMLmrow( TexClass::ORD, [], $node->getMainarg()->toMMLtree() );
		}

		if ( $close != null ) {
			$resDelimiter = TexUtil::getInstance()->delimiter( trim( $close ) ) ?? false;
			if ( $resDelimiter ) {
				$output[] = new MMLmo( TexClass::CLOSE, [], $resDelimiter[0] );
			}
		}
		return new MMLarray( ...$output );
	}

	public static function alignAt( Matrix $node, $passedArgs, $operatorContent, $name, $align = null,
		$smth2 = null
	): MMLbase {
		$mtable  = new MMLmtable( '', [ 'displaystyle' => 'true' ] );
		$inner = [];
		$align ??= $node->getAlign();
		foreach ( $node as $tableRow ) {
			$mtds = [];
			$colNo = 0;
			$attributes = [];
			$rowSpecs = $tableRow->getRowSpecs();
			if ( $rowSpecs ) {
				$attributes['style'] = "padding-bottom: {$rowSpecs->getCssLength()};";
			}
			foreach ( $tableRow->getArgs() as $tableCell ) {
				$class = '';
				if ( in_array( $align[$colNo] ?? [], [ 'l', 'r' ] ) ) {
					// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
					$class .= ' mwe-math-columnalign-' . $align[$colNo];
				}
				$class = trim( $class );
				$mtds[] = new MMLmtd( "",
					$attributes + ( $class ? [ 'class' => $class ] : [] ),
					$tableCell->toMMLtree() );
				$colNo++;
			}
			$inner[] = new MMLmtr( "", [], ...$mtds );
		}
		$mtable->addChild( ...$inner );
		return $mtable;
	}

	public static function boldsymbol( $node, $passedArgs, $operatorContent, $name, $smth = null,
		$smth2 = null
	): MMLbase {
		$passedArgs = array_merge( $passedArgs, [ 'mathvariant' => Variants::BOLDITALIC ] );
		return new MMLmrow( TexClass::ORD, [], $node->getArg()->toMMLtree( $passedArgs ) );
	}

	public static function cancel( Fun1 $node, $passedArgs, $operatorContent, $name, $notation = '' ): MMLbase {
		$bars = [];
		foreach ( explode( ' ', $notation ) as $element ) {
			$bars[] = ( new MMLmrow( '', [ 'class' => 'menclose-' . $element ] ) );
		}

		return new MMLmenclose( '', [ 'notation' => $notation, 'class' => 'menclose' ],
			$node->getArg()->toMMLtree(), ...$bars );
	}

	public static function cancelTo( $node, $passedArgs, $operatorContent, $name, $notation = null ): MMLbase {
		$mpAdded = new MMLmpadded( "", [ "depth" => "-.1em", "height" => "+.1em", "voffset" => ".1em" ],
			$node->getArg1()->toMMLtree() );
		$menclose = new MMLmenclose( "", [ "notation" => $notation ], $node->getArg2()->toMMLtree() );
		return new MMLmrow( TexClass::ORD, [], MMLmsup::newSubtree( $menclose, $mpAdded ) );
	}

	public static function chemCustom( $node, $passedArgs, $operatorContent, $name, $translation = null ): MMLmerror {
		return MMLmerror::newFromText( $translation ?: 'tbd chemCustom' );
	}

	public static function customLetters( $node, $passedArgs, $operatorContent, $name, $char,
			$isOperator = false
	): MMLbase {
		if ( $isOperator ) {
			return new MMLmrow( TexClass::ORD, [], new MMLmo( "", [], $char ) );
		}
		$variant = $passedArgs['mathvariant'] ?? Variants::NORMAL;
		return new MMLmrow( TexClass::ORD, [], new MMLmi( "", [ 'mathvariant' => $variant ], $char ) );
	}

	public static function cFrac( $node, $passedArgs, $operatorContent, $name ): MMLbase {
		$mstyle1 = new MMLmstyle( "", [ "displaystyle" => "false", "scriptlevel" => "0" ],
			new MMLmrow( TexClass::ORD, [], $node->getArg1()->toMMLtree() ) );
		$mstyle2 = new MMLmstyle( "", [ "displaystyle" => "false", "scriptlevel" => "0" ],
			new MMLmrow( TexClass::ORD, [], $node->getArg2()->toMMLtree() ) );
		// See TexUtilMMLTest testcase 81
		// (mml3 might be erronous here, but this element seems to be rendered correctly)
		$whatIsThis = new MMLmrow( TexClass::ORD, [],
			new MMLmpadded( "", [ "depth" => "3pt", "height" => "8.6pt", "width" => "0" ] ) );
		$inner = new MMLmrow( TexClass::ORD, [], $whatIsThis, $mstyle2 );
		$mfrac = MMLmfrac::newSubtree(
			new MMLmrow( TexClass::ORD, [], $whatIsThis, $mstyle1 ), $inner );
		return new MMLmrow( TexClass::ORD, [], $mfrac );
	}

	public static function crLaTeX( $node, $passedArgs, $operatorContent, $name ): MMLbase {
		return new MMLmspace( "", [ "linebreak" => "newline" ] );
	}

	public static function dots( $node, $passedArgs, $operatorContent, $name, $smth = null, $smth2 = null ): MMLbase {
		// lowerdots || centerdots seems aesthetical, just using lowerdots atm s
		return new MMLmo( "", $passedArgs, "&#x2026;" );
	}

	public static function genFrac( $node, $passedArgs, $operatorContent, $name,
		$left = null, $right = null, $thick = null, $style = null
	): MMLbase {
		// Actually this is in AMSMethods, consider refactoring  left, right, thick, style
		$bm = new BaseMethods();
		$ret = $bm->checkAndParseDelimiter( $name, $node, $passedArgs, $operatorContent, true );
		if ( !$ret->isEmpty() ) {
			// TBD
			if ( $left == null ) {
				$left = $ret;
			}
			if ( $right == null ) {
				$right = $ret;
			}
			if ( $thick == null ) {
				$thick = $ret;
			}
			if ( $style == null ) {
				$style = trim( $ret );
			}
		}
		$attrs = [];
		$displayStyle = "false";
		if ( in_array( $thick, [ 'thin', 'medium', 'thick', '0' ], true ) ) {
			$attrs = array_merge( $attrs, [ "linethickness" => $thick ] );
		}
		if ( $style !== '' && !( ( $operatorContent['styleargs']['displaystyle'] ?? true ) === "false" ) ) {
			$styleDigit = intval( $style, 10 );
			$styleAlpha = [ 'D', 'T', 'S', 'SS' ][$styleDigit];
			if ( $styleAlpha == null ) {
				return new MMLmrow( TexClass::ORD, [], new MMLmtext( "", [], "Bad math style" ) );
			}

			if ( $styleAlpha === 'D' ) {
				// NodeUtil_js_1.default.setProperties(frac, { displaystyle: true, scriptlevel: 0 });

				// tbd add props
				$displayStyle = "true";
				$styleAttr = [ "minsize" => "2.047em" ];

			} else {
				$styleAttr = [ "minsize" => "1.2em" ];
			}
		} else {
			// NodeUtil_js_1.default.setProperties(frac, { displaystyle: false,
			//    scriptlevel: styleDigit - 1 });
			// tbd add props
			$styleAttr = [ "maxsize" => "1.2em", "minsize" => "1.2em" ];

		}
		$output = [];
		if ( $left ) {
			$mrowOpen = new MMLmrow( TexClass::OPEN, [], new MMLmo( "", $styleAttr, $left ) );
			$output[] = $mrowOpen;
		}
		$mrow1 = new MMLmrow( TexClass::ORD, [], $node->getArg1()->toMMLtree() );
		$mrow2 = new MMLmrow( TexClass::ORD, [], $node->getArg2()->toMMLtree() );

		$output[] = MMLmfrac::newSubtree( $mrow1, $mrow2, "", $attrs );
		if ( $right ) {
			$mrowClose = new MMLmrow( TexClass::CLOSE, [], new MMLmo( "", $styleAttr, $right ) );
			$output[] = $mrowClose;
		}
		$output = new MMLmrow( TexClass::ORD, [], ...$output );
		if ( $style !== '' ) {
			$output = new MMLmstyle( "", [ "displaystyle" => $displayStyle, "scriptlevel" => "0" ], $output );
		}

		return new MMLmrow( TexClass::ORD, [], $output );
	}

	public static function frac( $node, $passedArgs, $operatorContent, $name ): MMLbase {
		if ( $node instanceof Fun2 ) {
			$inner = [ new MMLmrow( TexClass::ORD, [], $node->getArg1()->toMMLtree() ),
				new MMLmrow( TexClass::ORD, [], $node->getArg2()->toMMLtree() ) ];
		} elseif ( $node instanceof DQ ) {
			$inner = [ new MMLmrow( TexClass::ORD, [], $node->getBase()->toMMLtree() ),
				new MMLmrow( TexClass::ORD, [], $node->getDown()->toMMLtree() ) ];
		} else {
			$inner = [];
			foreach ( $node->getArgs() as $arg ) {
				$rendered = is_string( $arg ) ? $arg : $arg->toMMLtree();
				$inner[] = new MMLmrow( TexClass::ORD, [], $rendered );
			}
		}
		$mfrac = MMLmfrac::newSubtree( $inner[0], $inner[1] );
		return new MMLmrow( TexClass::ORD, [], $mfrac );
	}

	public static function hline( $node, $passedArgs, $operatorContent, $name,
			$smth1 = null, $smth2 = null, $smth3 = null, $smth4 = null
	): MMLbase {
		// HLine is most probably not parsed this way, since only parsed in Matrix context
		return new MMLmrow( "tbd", [], new MMLmtext( "", [], "HLINE TBD" ) );
	}

	public static function hskip( $node, $passedArgs, $operatorContent, $name ): MMLbase {
		if ( $node->getArg()->isCurly() ) {
			$unit = MMLutil::squashLitsToUnit( $node->getArg() );
			if ( !$unit ) {
				return new MMLarray();
			}
			$em = MMLutil::dimen2em( $unit );
		} else {
			// Prevent parsing in unmapped cases
			return new MMLarray();
		}
		// Added kern j4t
		if ( $name == "mskip" || $name == "mkern" || "kern" ) {
			$args = [ "width" => $em ];
		} else {
			return new MMLarray();
		}

		return new MMLmspace( "", $args );
	}

	public static function handleOperatorName( $node, $passedArgs, $operatorContent, $name ): MMLbase {
		// In example "\\operatorname{a}"
		$applyFct = self::getApplyFct( $operatorContent );
		$mmlNot = "";
		if ( isset( $operatorContent['not'] ) && $operatorContent['not'] ) {
			$mmlNot = MMLParsingUtil::createNot();
		}
		$passedArgs = array_merge( $passedArgs, [ Tag::CLASSTAG => TexClass::OP, 'mathvariant' => Variants::NORMAL ] );
		$state = [ 'squashLiterals' => true ];
		$inner = $node->getArg()->toMMLtree( $passedArgs, $state );
		if ( $inner instanceof MMLarray && count( $inner->getChildren() ) == 1 ) {
			$mi = $inner->getChildren()[0];
			// this check needs to be made explicit for phan
			if ( $mi instanceof MMLmi ) {
				$inner = new MMLmo( TexClass::OP, $mi->getAttributes(), $mi->getText() );
			}
		}
		return new MMLarray( $mmlNot, $inner, $applyFct );
	}

	public static function macro( $node, $passedArgs, $operatorContent, $name,
		$macro = '', $argcount = null, $def = null
	): MMLbase {
		// Parse the Macro
		if ( $macro == "\\text{ }" ) {
			return new MMLmtext( "", [], '&#160;' );
		}
		switch ( trim( $name ) ) {
			case "\\mod":
				// @phan-suppress-next-line PhanUndeclaredMethod
				$inner = $node->getArg() instanceof TexNode ? $node->getArg()->toMMLtree() : "";
				return new MMLmrow( TexClass::ORD, [],
					 new MMLmo( "", [ "lspace" => "2.5pt", "rspace" => "2.5pt" ], "mod" ), $inner );
			case "\\pmod":
				// tbd indicate in mapping that this is composed within php
				// @phan-suppress-next-line PhanUndeclaredMethod
				$inner = $node->getArg() instanceof TexNode ? $node->getArg()->toMMLtree() : "";

				return new MMLmrow(
					TexClass::ORD,
					[],
					new MMLmspace( "", [ "width" => "0.444em" ] ),
					new MMLmo( "", [ "stretchy" => "false" ], "(" ),
					new MMLmi( "", [], "mod" ),
					new MMLmspace( "", [ "width" => "0.333em" ] ),
					$inner,
					new MMLmo( "", [ "stretchy" => "false" ], ")" )
				);
			case "\\varlimsup":
			case "\\varliminf":
				// hardcoded macro in php (there is also a dynamic mapping which is not completely resolved atm)
				if ( trim( $name ) === "\\varlimsup" ) {
					$movu = MMLmover::newSubtree( (
						new MMLmi( "", [], "lim" ) ),
						new MMLmo( "", [ "accent" => "true" ], "&#x2015;" ) );
				} else {
					$movu = MMLmunder::newSubtree( (
						new MMLmi( "", [], "lim" ) ),
						new MMLmo( "", [ "accent" => "true" ], "&#x2015;" ) );
				}
				return new MMLmrow( TexClass::OP, [], $movu );

			case "\\varinjlim":
				return new MMLmrow(
					TexClass::OP,
					[],
					MMLmunder::newSubtree(
						new MMLmi( "", [], "lim" ),
						new MMLmo( "", [], "&#x2192;" )
					)
				);
			case "\\varprojlim":
				return new MMLmrow(
					TexClass::OP,
					[],
					MMLmunder::newSubtree(
						new MMLmi( "", [], "lim" ),
						new MMLmo( "", [], "&#x2190;" )
					)
				);
			case "\\stackrel":
				// hardcoded macro in php (there is also a dynamic mapping which is not not completely resolved atm)
				if ( $node instanceof DQ ) {
					$inner = MMLmover::newSubtree( new MMLmrow( TexClass::OP, [],
							$node->getBase()->toMMLtree() ),
						new MMLmrow( TexClass::ORD, [], $node->getDown()->toMMLtree() )
					);
				} else {
					$inner = MMLmover::newSubtree( new MMLmrow( TexClass::OP, [],
						// @phan-suppress-next-line PhanUndeclaredMethod
							$node->getArg2()->toMMLtree() ),
						// @phan-suppress-next-line PhanUndeclaredMethod
						new MMLmrow( TexClass::ORD, [], $node->getArg1()->toMMLtree() )
					);
				}
				return new MMLmrow( TexClass::ORD, [], new MMLmrow( TexClass::REL, [], $inner ) );
			case "\\bmod":
				$mspace = new MMLmspace( "", [ "width" => "0.167em" ] );
				// @phan-suppress-next-line PhanUndeclaredMethod
				$inner = $node->getArg() instanceof TexNode ?
					// @phan-suppress-next-line PhanUndeclaredMethod
					new MMLmrow( TexClass::ORD, [], $node->getArg()->toMMLtree() ) : "";
				return new MMLmrow( TexClass::ORD, [],
					new MMLmo( "", [ "lspace" => Sizes::THICKMATHSPACE, "rspace" => Sizes::THICKMATHSPACE ], "mod" ),
					$inner, new MMLmrow( TexClass::ORD, [], $mspace ) );
			case "\\implies":
				$mstyle = new MMLmstyle( "", [ "scriptlevel" => "0" ],
					new MMLmspace( "", [ "width" => "0.278em" ] ) );
				return new MMLarray( $mstyle, ( new MMLmo( "", [], "&#x27F9;" ) ), $mstyle );
			case "\\iff":
				$mstyle = new MMLmstyle( "", [ "scriptlevel" => "0" ],
					new MMLmspace( "", [ "width" => "0.278em" ] ) );
				return new MMLarray( $mstyle, ( new MMLmo( "", [], "&#x27FA;" ) ), $mstyle );
			case "\\tripledash":
				// Using emdash for rendering here.
				return new MMLmo( "", [], "&#x2014;" );
			case "\\longrightleftharpoons":
			case "\\longLeftrightharpoons":
			case "\\longRightleftharpoons":
				$texvc = new TexVC();
				$warnings = [];
				$checkRes = $texvc->check( $macro, [ "usemhchem" => true, "usemhchemtexified" => true ],
					$warnings, true );
				return $checkRes["input"]->toMMLtree();
			case "\\longleftrightarrows":
				// The tex-cmds used in makro are not supported, just use a hardcoded mml macro here.
				$mover = MMLmover::newSubtree(
					new MMLmrow( TexClass::OP, [],
						new MMLmrow( TexClass::ORD, [],
							new MMLmpadded( "", [ "height" => "0", "depth" => "0" ],
								new MMLmo( "", [ "stretchy" => "false" ], "&#x27F5;" )
							)
						),
						new MMLmspace( "", [ "width" => "0px", "height" => ".25em", "depth" => "0px",
							"mathbackground" => "black" ]
						)
					),
					new MMLmrow( TexClass::ORD, [],
						new MMLmo( "", [ "stretchy" => "false" ], "&#x27F6;" )
					)
				);
				return new MMLarray(
					new MMLmtext( "", [], "&#xA0;" ),
					new MMLmrow( TexClass::REL, [], $mover ) );
		}

		// Removed all token based parsing, since macro resolution for the supported macros can be hardcoded in php
		return MMLmerror::newFromText( "macro not resolved: $macro" );
	}

	public static function matrix( Matrix $node, $passedArgs, $operatorContent,
		$name, $open = null, $close = null, $align = null, $spacing = null,
		$vspacing = null, $style = null, $cases = null, $numbered = null
	): MMLbase {
		$resInner = [];
		$boarder = $node->getBoarder();
		if ( !$align ) {
			$align = $node->getAlign();
		}
		$rowNo = 0;
		$lines = $node->getLines();
		foreach ( $node as $row ) {
			$innerInnter = [];
			$colNo = 0;
			$isEmptyLine = true;
			foreach ( $row  as $cell ) {
				$usedArg = clone $cell;
				if ( $usedArg instanceof TexArray &&
					$usedArg->getLength() >= 1
				) {
					$firstArg = $usedArg[0];
					if ( $firstArg instanceof Literal &&
						$firstArg->getArg() === '\\hline '
					) {
						$usedArg->pop();
					}
				}
				$mtdAttributes = [];
				$texclass = $lines[$rowNo] ? TexClass::TOP : '';
				$texclass .= $lines[$rowNo + 1] ?? false ? ' ' . TexClass::BOTTOM : '';
				$texclass .= $boarder[$colNo] ?? false ? ' ' . TexClass::LEFT : '';
				$texclass .= $boarder[$colNo + 1 ] ?? false ? ' ' . TexClass::RIGHT : '';
				if ( in_array( $align[$colNo] ?? [], [ 'l', 'r' ] ) ) {
					// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
					$texclass .= ' mwe-math-columnalign-' . $align[$colNo];
				}
				$texclass = trim( $texclass );
				if ( $texclass ) {
					$mtdAttributes['class'] = $texclass;
				}
				$state = [ 'inMatrix'	=> true ];
				$isEmptyLine = $isEmptyLine && $usedArg->isEmpty();
				$innerInnter[] = new MMLmtd( "", $mtdAttributes, $usedArg->toMMLtree( $passedArgs, $state ) );
				$colNo++;
			}
			$rowNo++;
			// empty trailing lines with only one empty cell should not be rendered
			if ( $rowNo === count( $lines ) && $colNo === 1 && $isEmptyLine ) {
				break;
			}
			$resInner[] = new MMLmtr( "", [], ...$innerInnter );
		}
		$mtable = new MMLmtable( '',
		$name === 'smallmatrix' ?
		[ 'class' => 'mwe-math-smallmatrix' ] : []
		);
		if ( $cases || ( $open != null && $close != null ) ) {
			$bm = new BaseMethods();
			$mmlMoOpen = $bm->checkAndParseDelimiter( $open, $node, [], [],
				true, TexClass::OPEN );
			if ( $mmlMoOpen->isEmpty() ) {
				$mmlMoOpen = new MMLmo( TexClass::OPEN, [], $open ?? '' );
			}

			$closeAtts = [ "fence" => "true", "stretchy" => "true", "symmetric" => "true" ];
			$mmlMoClose = $bm->checkAndParseDelimiter( $close, $node, $closeAtts,
				null, true, TexClass::CLOSE );
			if ( $mmlMoClose->isEmpty() ) {
				$mmlMoClose = ( new MMLmo( TexClass::CLOSE, $closeAtts, $close ?? '' ) );
			}
			$mtable->addChild( ...$resInner );
			return new MMLmrow( TexClass::ORD, [], $mmlMoOpen, $mtable, $mmlMoClose );
		}
		$mtable->addChild( ...$resInner );
		return $mtable;
	}

	public static function over( $node, $passedArgs, $operatorContent, $name, $id = null ): MMLbase {
		$attributes = [];
		$start = null;
		$tail = null;
		if ( trim( $name ) === "\\atop" ) {
			$attributes = [ "linethickness" => "0" ];
		} elseif ( trim( $name ) == "\\choose" ) {
			$start = new MMLmrow( TexClass::OPEN, [],
				// T418144 2.047em was used by mathoid
				new MMLmo( "", [ "maxsize" => "2.047em", "minsize" => "2.047em" ], "(" )
			);
			$tail = new MMLmrow( TexClass::CLOSE, [],
				new MMLmo( "", [ "maxsize" => "2.047em", "minsize" => "2.047em" ], ")" )
			);
			$attributes = [ "linethickness" => "0" ];
		}
		if ( $node instanceof Fun2 ) {
			$mfrac = MMLmfrac::newSubtree( new MMLmrow( "", [], $node->getArg1()->toMMLtree() ),
				new MMLmrow( "", [], $node->getArg2()->toMMLtree() ), "", $attributes );
			if ( $start === null ) {
				return $mfrac;
			}
			return new MMLmrow( TexClass::ORD, [], $start, $mfrac, $tail );
		}
		$inner = [];
		foreach ( $node->getArgs() as $arg ) {
			if ( is_string( $arg ) && str_contains( $arg, $name ) ) {
				continue;
			}
			$rendered = $arg instanceof TexNode ? $arg->toMMLtree() : $arg;
			$inner[] = new MMLmrow( "", [], $rendered );
		}
		$mfrac = MMLmfrac::newSubtree( $inner[0], $inner[1], "", $attributes );
		if ( $start === null ) {
			return $mfrac;
		}
		return new MMLmrow( TexClass::ORD, [], $start, $mfrac, $tail );
	}

	public static function oint( $node, $passedArgs, $operatorContent,
		$name, $uc = null, $attributes = null, $smth2 = null
	): MMLbase {
		// This is a custom mapping not in js.
		switch ( trim( $name ) ) {
			case "\\oint":
			case "\\P":
				return new MMLmo( "", [], MMLutil::uc2xNotation( $uc ) );
			case "\\oiint":
			case "\\oiiint":
			case "\\ointctrclockwise":
			case "\\varointclockwise":
				return new MMLmrow( TexClass::ORD, [],
					new MMLmstyle( "", [ "mathsize" => "2.07em" ],
						new MMLmtext( "", $attributes, MMLutil::uc2xNotation( $uc ) ),
						new MMLmspace( "", [ "width" => Sizes::THINMATHSPACE ] )
					)
				);
			default:
				return MMLmerror::newFromText( "not found in OintMethod" );
		}
	}

	public static function overset( $node, $passedArgs, $operatorContent, $name, $id = null ): MMLbase {
		if ( $node instanceof DQ ) {
			return new MMLmrow( TexClass::ORD, [],
				MMLmover::newSubtree( new MMLmrow(
					"",
					[],
					$node->getDown()->toMMLtree() ),
					$node->getDown()->toMMLtree()
				)
			);
		}
		return new MMLmrow( TexClass::ORD, [],
			MMLmover::newSubtree(
				new MMLmrow(
					"",
					[],
				$node->getArg2()->toMMLtree() ),
				$node->getArg1()->toMMLtree()
			)
		);
	}

	public static function phantom( $node, $passedArgs, $operatorContent,
		$name, $vertical = null, $horizontal = null, $smh3 = null
	): MMLbase {
		$attrs = [];
		if ( $vertical ) {
			$attrs = array_merge( $attrs, [ "width" => "0" ] );
		}
		if ( $horizontal ) {
			$attrs = array_merge( $attrs, [ "depth" => "0", "height" => "0" ] );
		}
		return new MMLmrow(
			TexClass::ORD,
			[],
			new MMLmrow(
				TexClass::ORD,
				[],
				new MMLmpadded(
					"",
					$attrs,
					new MMLmphantom( "", [], $node->getArg()->toMMLtree() )
				)
			)
		);
	}

	public static function raiseLower( $node, $passedArgs, $operatorContent, $name ): MMLbase {
		if ( !$node instanceof Fun2 ) {
			return new MMLarray();
		}

		$arg1 = $node->getArg1();
		// the second check is to avoid a false positive for PhanTypeMismatchArgumentSuperType
		if ( $arg1->isCurly() && $arg1 instanceof TexArray ) {
			$unit = MMLutil::squashLitsToUnit( $arg1 );
			if ( !$unit ) {
				return new MMLarray();
			}
			$em = MMLutil::dimen2em( $unit );
			if ( !$em ) {
				return new MMLarray();
			}
		} else {
			return new MMLarray();
		}

		if ( trim( $name ) === "\\raise" ) {
			$args = [ "height" => MMLutil::addPreOperator( $em, "+" ),
				"depth" => MMLutil::addPreOperator( $em, "-" ),
				"voffset" => MMLutil::addPreOperator( $em, "+" ) ];
		} elseif ( trim( $name ) === "\\lower" ) {
			$args = [ "height" => MMLutil::addPreOperator( $em, "-" ),
				"depth" => MMLutil::addPreOperator( $em, "+" ),
				"voffset" => MMLutil::addPreOperator( $em, "-" ) ];
		} else {
			// incorrect name, should not happen, prevent erroneous mappings from getting rendered.
			return new MMLarray();
		}
		return new MMLmrow( "", [], new MMLmpadded( "", $args, $node->getArg2()->toMMLtree() ) );
	}

	public static function underset( $node, $passedArgs, $operatorContent, $name, $smh = null ): MMLbase {
		$inrow = $node->getArg2()->toMMLtree();
		$arg1 = $node->getArg1()->toMMLtree();
		// MMLarray may be empty
		if ( $inrow->hasChildren() && $arg1->hasChildren() ) {
			return new MMLmrow( TexClass::ORD, [], MMLmunder::newSubtree( $inrow, $arg1 ) );
		}

		// If there are no two elements in munder, not render munder
		return new MMLmrow( TexClass::ORD, [], $inrow, $arg1 );
	}

	public static function underOver( Fun1 $node, $passedArgs, $operatorContent,
		$name, $operatorSymbol = null, $stack = null ): MMLbase {
		// tbd verify if stack interpreted correctly ?
		$texClass = $stack ? TexClass::OP : TexClass::ORD; // ORD or ""

		$fname = $node->getFname();
		if ( str_starts_with( $fname, '\\over' ) ) {
			$movun = new MMLmover();
		} elseif ( str_starts_with( $fname, '\\under' ) ) {
			$movun = new MMLmunder();
		} else {
			// incorrect name, should not happen, prevent erroneous mappings from getting rendered.
			return MMLmerror::newFromText(
				'underOver rendering requires macro to start with either \\under or \\over.' );
		}

		if ( $operatorSymbol === '―' ) { // eventually move such cases to mapping
			$mo = new MMLmo( "", [ "accent" => "true" ], $operatorSymbol );
		} else {
			$mo = new MMLmo( "", [], $operatorSymbol );
		}
		return new MMLmrow( $texClass, [], $movun::newSubtree( $node->getArg()->toMMLtree( $passedArgs ), $mo ) );
	}

	public static function mathFont( $node, $passedArgs, $operatorContent, $name, $mathvariant = null ): MMLbase {
		$args = MMLParsingUtil::getFontArgs( $name, $mathvariant, $passedArgs );
		$state = [];

			return new MMLmrow( TexClass::ORD, [], $node->getArg()->toMMLtree( $args, $state ) );
	}

	public static function mathChoice( $node, $passedArgs, $operatorContent, $name, $smth = null ): MMLbase {
		if ( !$node instanceof Fun4 ) {
			return MMLmerror::newFromText( "Wrong node type in mathChoice" );
		}

		/**
		 * Parametrization for mathchoice:
		 * \mathchoice
		 * {<material for display style>}
		 * {<material for text style>}
		 * {<material for script style>}
		 * {<material for scriptscript style>}
		 */

		if ( isset( $operatorContent["styleargs"] ) ) {
			$styleArgs = $operatorContent["styleargs"];
			$displayStyle = $styleArgs["displaystyle"] ?? "true";
			$scriptLevel = $styleArgs["scriptlevel"] ?? "0";

			if ( $displayStyle == "true" && $scriptLevel == "0" ) {
				// This is displaystyle
				return $node->getArg1()->toMMLtree( $passedArgs, $operatorContent );
			} elseif ( $displayStyle == "false" && $scriptLevel == "0" ) {
				// This is textstyle
				return $node->getArg2()->toMMLtree( $passedArgs, $operatorContent );
			} elseif ( $displayStyle == "false" && $scriptLevel == "1" ) {
				// This is scriptstyle
				return $node->getArg3()->toMMLtree( $passedArgs, $operatorContent );
			} elseif ( $displayStyle == "false" && $scriptLevel == "2" ) {
				// This is scriptscriptstyle
				return $node->getArg4()->toMMLtree( $passedArgs, $operatorContent );
			}
		}
		// By default render displaystyle
		return $node->getArg1()->toMMLtree( $passedArgs, $operatorContent );
	}

	public static function makeBig( $node, $passedArgs, $operatorContent, $name, $texClass = null,
		$size = null
	): MMLbase {
		// Create the em format and shorten commas
		$size *= Misc::P_HEIGHT;
		$sizeShortened = MMLutil::size2em( strval( $size ) );
		$passedArgs = array_merge( $passedArgs, [ "maxsize" => $sizeShortened, "minsize" => $sizeShortened ] );
		// Sieve arg if it is a delimiter (it seems args are not applied here
		$bm = new BaseMethods();
		$argcurrent = trim( $node->getArg() );
		switch ( $argcurrent ) {
			case "\\|":
			case "\\vert":
			case "|":
				$passedArgs = array_merge( $passedArgs, [ "stretchy" => "true", "symmetric" => "true" ] );
				break;
			case "\\uparrow":
			case "\\downarrow":
			case "\\Uparrow":
			case "\\Downarrow":
			case "\\updownarrow":
			case "/":
			case "\\backslash":
			case "\\Updownarrow":
				$passedArgs = array_merge(
					[ "fence" => "true" ],
					$passedArgs,
					[ "stretchy" => "true", "symmetric" => "true" ] );
				break;
		}

		if ( in_array( $name, [ "\\bigl", "\\Bigl", "\\biggl", "\\Biggl" ] ) ) {
			$passedArgs = array_merge( $passedArgs, [ Tag::CLASSTAG => TexClass::OPEN ] );
		}

		if ( in_array( $name, [ "\\bigr", "\\Bigr", "\\biggr", "\\Biggr" ] ) ) {
			$passedArgs = array_merge( $passedArgs, [ Tag::CLASSTAG => TexClass::CLOSE ] );
		}

		$ret = $bm->checkAndParseDelimiter( $node->getArg(), $node, $passedArgs, $operatorContent, true );
		if ( !$ret->isEmpty() ) {
			return $ret;
		}

		$argPrep = $node->getArg();
		return new MMLmrow( TexClass::ORD, [],
			new MMLmrow( $texClass, [], new MMLmo( "", $passedArgs, $argPrep ) )
		);
	}

	public static function machine( $node, $passedArgs, $operatorContent, $name, $type = null ): MMLbase {
		// this could also be shifted to MhChem.php renderMML for ce
		// For parsing chem (ce) or ??? (pu)
		return new MMLmrow( "", [], $node->getArg()->toMMLtree() );
	}

	public static function setFont( $node, $passedArgs, $operatorContent, $name, $variant = null ): MMLbase {
		return self::mathFont( $node, $passedArgs, $operatorContent, $name, $variant );
	}

	public static function sideset( $node, $passedArgs, $operatorContent, $name ): MMLbase {
		if ( !array_key_exists( "sideset", $operatorContent ) ) {
			return MMLmerror::newFromText( "Error parsing sideset expression, no succeeding operator found" );
		}

		if ( $operatorContent["sideset"] instanceof Literal ) {
			$bm = new BaseMethods();
			$opParsed = $bm->checkAndParseOperator( $operatorContent["sideset"]->getArg(), null, [], [], null );
			if ( $opParsed->isEmpty() ) {
				throw new \LogicException( "null is not a valid base for MMLmmultiscripts." );
			}
			$in1 = $node->getArg1()->toMMLtree();
			$in2 = $node->getArg2()->toMMLtree();
			return new MMLmrow( TexClass::OP, [],
				MMLmmultiscripts::newSubtree( $opParsed, $in2, null, $in1, null,
					"", [ Tag::ALIGN => "left" ]
				)
			);
		}

		if ( $operatorContent["sideset"] instanceof FQ ||
			$operatorContent["sideset"] instanceof DQ ||
			$operatorContent["sideset"] instanceof UQ ) {
			$bm = new BaseMethods();
			if ( count( $operatorContent["sideset"]->getBase()->getArgs() ) == 1 ) {
				$baseOperator = $operatorContent["sideset"]->getBase()->getArgs()[0];
				if ( is_string( $baseOperator ) ) {
					$opParsed = $bm->checkAndParseOperator( $baseOperator,
						null, [ "largeop" => "true", "movablelimits" => "false", "symmetric" => "true" ], [], null );
				} else {
					// We know $baseOperator instanceof TexNode
					$opParsed = $baseOperator->toMMLTree();
				}
				if ( $opParsed->isEmpty() ) {
					$opParsed = $operatorContent["sideset"]->getBase()->toMMLtree();
				}
			} else {
				$opParsed = MMLmerror::newFromText( "Sideset operator parsing not implemented yet" );
			}
			$state = [ 'sideset' => true ];
			$in1 = $node->getArg1()->toMMLtree( [], $state );
			$in2 = $node->getArg2()->toMMLtree( [], $state );

			$down = $operatorContent["sideset"] instanceof UQ ? '<mrow />' :
				$operatorContent["sideset"]->getDown()->toMMLtree();
			$end1 = new MMLmrow( "", [], $down );
			$up = $operatorContent["sideset"] instanceof DQ ? '<mrow />' :
				$operatorContent["sideset"]->getUp()->toMMLtree();
			$end2 = new MMLmrow( "", [], $up );

			return new MMLmrow(
				TexClass::OP,
				[],
				MMLmunderover::newSubtree(
					new MMLmstyle( "", [ "displaystyle" => "true" ],
					MMLmmultiscripts::newSubtree( $opParsed, $in2, null, $in1 ) ),
					$end1,
					$end2
				)
			);
		}

		return MMLmerror::newFromText( "Error parsing sideset expression, no valid succeeding operator found" );
	}

	public static function spacer( $node, $passedArgs, $operatorContent, $name, $withIn = null, $smth2 = null
	): MMLbase {
		return new MMLmspace( "", [ "width" => MMLutil::round2em( $withIn ) ] );
	}

	public static function smash( $node, $passedArgs, $operatorContent, $name ): MMLbase {
		$mpArgs = [];
		$inner = "";
		if ( $node instanceof Fun2sq ) {
			$arg1 = $node->getArg1();
			$arg1i = "";
			if ( $arg1->isCurly() ) {
				$arg1i = $arg1->render();
			}

			if ( str_contains( $arg1i, "{b}" ) ) {
				$mpArgs = [ "depth" => "0" ];
			}
			if ( str_contains( $arg1i, "{t}" ) ) {
				$mpArgs = [ "height" => "0" ];
			}
			if ( str_contains( $arg1i, "{tb}" ) || str_contains( $arg1i, "{bt}" ) ) {
				$mpArgs = [ "height" => "0", "depth" => "0" ];
			}

			$inner = $node->getArg2()->toMMLtree();
		} elseif ( $node instanceof Fun1 ) {
			// Implicitly assume "tb" as default mode
			$mpArgs = [ "height" => "0", "depth" => "0" ];
			$inner = $node->getArg()->toMMLtree();
		}
		return new MMLmrow( TexClass::ORD, [], new MMLmpadded( "", $mpArgs, $inner ) );
	}

	public static function texAtom( $node, $passedArgs, $operatorContent, $name, $texClass = null ): MMLbase {
		switch ( $name ) {
			case '\mathbin':
				// no break
			case '\mathop':
				// no break
			case '\mathrel':
				$inner = $node->getArg()->toMMLtree();
				return new MMLmrow( $texClass, [], $inner );
			default:
				$inner = $node->getArg()->toMMLtree();
				return new MMLmrow( TexClass::ORD, [], new MMLmrow( $texClass, [], $inner ) );
		}
	}

	public static function intent( $node, $passedArgs, $operatorContent, $name, $smth = null ): MMLbase {
		if ( !$node instanceof Fun2 ) {
			return new MMLarray();
		}
		// if there is intent annotation add intent to root element
		// match args in row of subargs, unless an element has explicit annotations
		// nested annotations ?
		$arg1 = $node->getArg1();
		$arg2 = $node->getArg2();
		if ( !$arg2->isCurly() ) {
			return new MMLarray();
		}
		// tbd refactor intent form and fiddle in mml or tree
		$intentStr = MMLutil::squashLitsToUnitIntent( $arg2 );
		$intentContent = MMLParsingUtil::getIntentContent( $intentStr );
		$intentParams = MMLParsingUtil::getIntentParams( $intentContent );
		// Sometimes the intent has additioargs = {array[3]} nal args in the same string
		$intentArg = MMLParsingUtil::getIntentArgs( $intentStr );
		if ( !$intentContent && !$intentParams && $intentArg !== null ) {
			// explicit args annotation parsing in literal
			// return $arg1->renderMML([],["intent-params-expl"=>$intentArg]);
			// alternative just add the arg here
			return $arg1->toMMLtree( [ "arg" => $intentArg ] );
		}
		$intentContentAtr = [ "intent" => $intentContent ];
		if ( $intentArg !== null ) {
			$intentContentAtr["arg"] = $intentArg;
		}
		// tbd refine intent params and operator content merging (does it overwrite ??)
		$intentParamsState = $intentParams ? [ "intent-params" => $intentParams ] : $operatorContent;
		// Here are some edge cases, they might go into renderMML in the related element
		if ( str_contains( $intentContent ?? '', "matrix" ) ||
			( $arg1->isCurly() && $arg1->getArgs()[0] instanceof Matrix ) ) {
			$element = $arg1->getArgs()[0];
			$rendered = $element->toMMLtree( [], $intentParamsState );
			return MMLParsingUtil::forgeIntentToSpecificElement( $rendered,
				$intentContentAtr, "mtable" );
		} elseif ( $arg1->isCurly() && count( $arg1->getArgs() ) >= 2 ) {
			// Create a surrounding element which holds the intents
			return new MMLmrow( "", $intentContentAtr, $arg1->toMMLtree( [], $intentParamsState ) );
		} elseif ( $arg1->isCurly() && count( $arg1->getArgs() ) >= 1 ) {
			// Forge the intent attribute to the top-level element after MML rendering
			$element = $arg1->getArgs()[0];
			$rendered = $element->toMMLtree( [], $intentParamsState );
			return MMLParsingUtil::forgeIntentToTopElement( $rendered, $intentContentAtr );
		} else {
			// This is the default case
			return $arg1->toMMLtree( $intentContentAtr, $intentParamsState );
		}
	}

	public static function hBox( $node, $passedArgs, $operatorContent, $name, $smth = null ): MMLbase {
		switch ( trim( $name ) ) {
			case "\\mbox":
				if ( isset( $operatorContent['foundOC'] ) ) {
					$op = $operatorContent['foundOC'];
					$macro = TexUtil::getInstance()->nullary_macro_in_mbox( $op ) ?
						/* tested in \MediaWiki\Extension\Math\Tests\WikiTexVC\TexUtilTest::testUnicodeDefined */
						[ TexUtil::getInstance()->unicode_char( $op ) ] :
						TexUtil::getInstance()->identifier( $op );
					$input = $macro[0] ?? $op;
					return new MMLmrow( TexClass::ORD, [], new MMLmo( "", [], MMLutil::uc2xNotation( $input ) ) );
				} else {
					return new MMLmrow( TexClass::ORD, [], new MMLmtext( "", [], "\mbox" ) );
				}
			case "\\hbox":
				$inner = $node->getArg() instanceof TexNode ? $node->getArg()->toMMLtree() : $node->getArg();
				return new MMLmrow( TexClass::ORD, [],
					new MMLmstyle( "", [ "displaystyle" => "false", "scriptlevel" => "0" ],
						new MMLmtext( "", [], $inner )
					)
				);
			case "\\text":
				$inner = $node->getArg() instanceof TexNode ? $node->getArg()->toMMLtree() : $node->getArg();
				return new MMLmrow( TexClass::ORD, [], new MMLmtext( "", [], $inner ) );
			case "\\textbf":
				// no break
			case "\\textit":
				// no break
			case "\\textrm":
				// no break
			case "\\textsf":
				// no break
			case "\\texttt":
				$state = [ "inHBox" => true, 'squashLiterals' => true ];
				$fontArgs = MMLParsingUtil::getFontArgs( $name, null, null );
				$inner = $node->getArg()->isCurly() ? $node->getArg()->toMMLtree(
					$fontArgs, $state )
					: $node->getArg()->toMMLtree( $fontArgs );
				if ( $inner instanceof MMLbase ) {
					$inner = $inner->getTextContent();
				}
				return new MMLmtext( "", $fontArgs, $inner ?? '' );

		}

		return MMLmerror::newFromText( "undefined hbox" );
	}

	public static function setStyle( $node, $passedArgs, $operatorContent, $name,
		$smth = null, $smth1 = null, $smth2 = null
	): MMLmrow {
		// Just discard setstyle since they are captured in TexArray now}
		return new MMLmrow();
	}

	public static function not( $node, $passedArgs, $operatorContent, $name, $smth = null,
		$smth1 = null, $smth2 = null
	): MMLbase {
		// This is only tested for \not statement without follow-up parameters
		if ( $node instanceof Literal ) {
			return MMLParsingUtil::createNot();
		}
		return MMLmerror::newFromText( "TBD implement not" );
	}

	public static function vbox( $node, $passedArgs, $operatorContent, $name, $smth = null ): MMLbase {
		// This is only example functionality for vbox("ab").
		// TBD: it should be discussed if vbox is supported since it
		// does not seem to be supported by mathjax
		if ( is_string( $node->getArg() ) ) {
			$arr1 = str_split( $node->getArg() );
			$inner = [];
			foreach ( $arr1 as $char ) {
				$inner[] = new MMLmrow( TexClass::ORD, [], $char );
			}
			return MMLmover::newSubtree( $inner[0], $inner[1] );
		}
		return MMLmerror::newFromText( "no implemented vbox" );
	}

	public static function sqrt( $node, $passedArgs, $operatorContent, $name ): MMLbase {
		// There is an additional argument for the root
		if ( $node instanceof Fun2sq ) {
			// In case of an empty curly add an mrow
			$arg2Rendered = $node->getArg2()->toMMLtree( $passedArgs );
			if ( $arg2Rendered->isEmpty() ) {
				$arg2Rendered = new MMLmrow( TexClass::ORD, [] );
			}
			return new MMLmrow( TexClass::ORD, [],
				MMLmroot::newSubtree(
					$arg2Rendered,
					new MMLmrow( TexClass::ORD, [],
						$node->getArg1()->toMMLtree( $passedArgs )
					)
				)
			);
		}
		// Currently this is own implementation from Fun1.php
		return new MMLmrow( TexClass::ORD, [], // assuming that this is always encapsulated in mrow
			new MMLmsqrt( "", [],
				$node->getArg()->toMMLtree( $passedArgs )
			)
		);
	}

	public static function tilde( $node, $passedArgs, $operatorContent, $name ): MMLbase {
		return new MMLmspace( "", [ "width" => "0.5em" ] );
	}

	public static function xArrow( $node, $passedArgs, $operatorContent, $name, $chr = null, $l = null,
								   $r = null ): MMLbase {
		$defWidth = "+" . MMLutil::round2em( ( $l + $r ) / 18 );
		$defLspace = MMLutil::round2em( $l / 18 );

		$char = IntlChar::chr( $chr );

		$mpaddedArgs = [ "height" => "-.2em", "lspace" => $defLspace, "voffset" => "-.2em", "width" => $defWidth ];
		$mspace = new MMLmspace( "", [ "depth" => ".25em" ] );
		if ( $node instanceof Fun2sq ) {
			return new MMLmrow( TexClass::ORD, [], MMLmunderover::newSubtree(
				new MMLmstyle( "", [ "scriptlevel" => "0" ],
					new MMLmo( Texclass::REL, [], $char )
				),
				new MMLmpadded( "", $mpaddedArgs,
					new MMLmrow( TexClass::ORD, [],
						$node->getArg1()->toMMLtree()
					),
					$mspace
				),
				new MMLmpadded( "", $mpaddedArgs,
					$node->getArg2()->toMMLtree()
				)
			) );

		}
		return MMLmover::newSubtree(
			new MMLmstyle( "", [ "scriptlevel" => "0" ], new MMLmo( Texclass::REL, [], $char ) ),
			new MMLmpadded( "", $mpaddedArgs, $node->getArg()->toMMLtree(), $mspace )
		);
	}

	public static function getApplyFct( array $operatorContent ): MMLbase {
		$applyFct = new MMLarray();
		if ( array_key_exists( "foundNamedFct", $operatorContent ) ) {
			$hasNamedFct = $operatorContent['foundNamedFct'][0];
			$hasValidParameters = $operatorContent["foundNamedFct"][1];
			if ( $hasNamedFct && $hasValidParameters ) {
				$applyFct = MMLParsingUtil::renderApplyFunction();
			}
		}
		return $applyFct;
	}
}
