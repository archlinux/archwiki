<?php
/**
 * Copyright (c) 2023 Johannes Stegmüller
 *
 * This file is a port of mhchemParser originally authored by Martin Hensel in javascript/typescript.
 * The original license for this software can be found in the accompanying LICENSE.mhchemParser-ts.txt file.
 */

namespace MediaWiki\Extension\Math\WikiTexVC\Mhchem;

use MediaWiki\Extension\Math\WikiTexVC\MHChem\MhchemUtil as MU;
use RuntimeException;

/**
 * Takes MhchemParser output and convert it to TeX
 *
 * Functionality is the same as mhchemTexify class at ~line 1505 in mhchemParser.js
 * in mhchemParser by Martin Hensel.
 *
 * @author Johannes Stegmüller
 * @license GPL-2.0-or-later
 */
class MhchemTexify {

	/** @var bool optimize the output TeX for WikiTexVC */
	private bool $optimizeForTexVC;

	/**
	 * Takes MhchemParser output and convert it to TeX
	 * @param bool $optimizeForTexVC optimizes the output for WikiTexVC grammar by
	 * wrapping dimensions for some TeX commands in curly brackets.
	 */
	public function __construct( bool $optimizeForTexVC = false ) {
		$this->optimizeForTexVC = $optimizeForTexVC;
	}

	/**
	 * @param array|mixed $input
	 * @param bool $addOuterBraces
	 */
	public function go( $input, bool $addOuterBraces ): string {
		if ( !MhchemUtil::issetJS( $input ) ) {
			return "";
		}
		$res = "";
		$cee = false;
		foreach ( $input as $val ) {
			if ( is_string( $val ) ) {
				$res .= $val;
			} else {
				$res .= $this->go2( $val );
				if ( $val["type_"] === '1st-level escape' ) {
					$cee = true;
				}
			}
		}
		if ( $addOuterBraces && !$cee && $res ) {
			$res = "{" . $res . "}";
		}
		return $res;
	}

	private function goInner( array $input ): string {
		return $this->go( $input, false );
	}

	private function strReplaceFirst( string $search, string $replace, string $subject ): string {
		return implode( $replace, explode( $search, $subject, 2 ) );
	}

	private function go2( array $buf ): string {
		switch ( $buf["type_"] ) {
			case 'chemfive':
				$res = "";
				$b5 = [
					"a" => $this->goInner( $buf["a"] ),
					"b" => $this->goInner( $buf["b"] ),
					"p" => $this->goInner( $buf["p"] ),
					"o" => $this->goInner( $buf["o"] ),
					"q" => $this->goInner( $buf["q"] ),
					"d" => $this->goInner( $buf["d"] )
				];
				if ( MU::issetJS( $b5["a"] ) ) {
					if ( preg_match( "/^[+\-]/", $b5["a"] ) ) {
						$b5["a"] = "{" . $b5["a"] . "}";
					}
					$res .= $b5["a"] . "\\,";
				}
				if ( MU::issetJS( $b5["b"] ) || MU::issetJS( $b5["p"] ) ) {
					$res .= "{\\vphantom{A}}";
					$res .= "^{\\hphantom{" . ( $b5["b"] ) . "}}_{\\hphantom{" . ( $b5["p"] ) . "}}";
					$res .= !$this->optimizeForTexVC ? "\\mkern-1.5mu" : "\\mkern{-1.5mu}";
					$res .= "{\\vphantom{A}}";
					$res .= "^{\\smash[t]{\\vphantom{2}}\\llap{" . ( $b5["b"] ) . "}}";
					$res .= "_{\\vphantom{2}\\llap{\\smash[t]{" . ( $b5["p"] ) . "}}}";
				}

				if ( MU::issetJS( $b5["o"] ) ) {
					if ( preg_match( "/^[+\-]/", $b5["o"] ) ) {
						$b5["o"] = "{" . $b5["o"] . "}";
					}
					$res .= $b5["o"];
				}
				if ( isset( $buf["dType"] ) && $buf["dType"] === 'kv' ) {
					if ( MU::issetJS( $b5["d"] ) || MU::issetJS( $b5["q"] ) ) {
						$res .= "{\\vphantom{A}}";
					}
					if ( MU::issetJS( $b5["d"] ) ) {
						$res .= "^{" . $b5["d"] . "}";
					}
					if ( MU::issetJS( $b5["q"] ) ) {
						$res .= "_{\\smash[t]{" . $b5["q"] . "}}";
					}
				} elseif ( MU::issetJS( $buf["dType"] ?? null ) && $buf["dType"] === 'oxidation' ) {
					if ( MU::issetJS( $b5["d"] ) ) {
						$res .= "{\\vphantom{A}}";
						$res .= "^{" . $b5["d"] . "}";
					}
					if ( MU::issetJS( $b5["q"] ) ) {
						$res .= "{\\vphantom{A}}";
						$res .= "_{\\smash[t]{" . $b5["q"] . "}}";
					}
				} else {
					if ( MU::issetJS( $b5["q"] ) ) {
						$res .= "{\\vphantom{A}}";
						$res .= "_{\\smash[t]{" . $b5["q"] . "}}";
					}
					if ( MU::issetJS( $b5["d"] ) ) {
						$res .= "{\\vphantom{A}}";
						$res .= "^{" . $b5["d"] . "}";
					}
				}
				break;
			case 'roman numeral':
			case 'rm':
				$res = "\\mathrm{" . $buf["p1"] . "}";
				break;
			case 'text':
				if ( preg_match( "/[\^_]/", $buf["p1"] ) ) {
					$buf["p1"] = $this->strReplaceFirst( "-", "\\text{-}",
						$this->strReplaceFirst( " ", "~", $buf["p1"] )
					);
					$res = "\\mathrm{" . $buf["p1"] . "}";
				} else {
					$res = "\\text{" . $buf["p1"] . "}";
				}
				break;
			case 'state of aggregation':
				$res = ( !$this->optimizeForTexVC ? "\\mskip2mu " : "\\mskip{2mu} " ) . $this->goInner( $buf["p1"] );
				break;
			case 'state of aggregation subscript':
				$res = ( !$this->optimizeForTexVC ? "\\mskip1mu " : "\\mskip{1mu} " ) . $this->goInner( $buf["p1"] );
				break;
			case 'bond':
				$res = $this->getBond( $buf["kind_"] );
				if ( !$res ) {
					throw new RuntimeException( "MhchemErrorBond: mhchem Error. Unknown bond type ("
						. $buf["kind_"] . ")" );
				}
				break;
			case 'frac':
				$c = "\\frac{" . $buf["p1"] . "}{" . $buf["p2"] . "}";
				$res = "\\mathchoice{\\textstyle" . $c . "}{" . $c . "}{" . $c . "}{" . $c . "}";
				break;
			case 'pu-frac':
				$d = "\\frac{" . $this->goInner( $buf["p1"] ) . "}{" . $this->goInner( $buf["p2"] ) . "}";
				$res = "\\mathchoice{\\textstyle" . $d . "}{" . $d . "}{" . $d . "}{" . $d . "}";
				break;
			case '1st-level escape':
			case 'tex-math':
				$res = $buf["p1"] . " ";
				break;
			case 'frac-ce':
				$res = "\\frac{" . $this->goInner( $buf["p1"] ) . "}{" . $this->goInner( $buf["p2"] ) . "}";
				break;
			case 'overset':
				$res = "\\overset{" . $this->goInner( $buf["p1"] ) . "}{" . $this->goInner( $buf["p2"] ) . "}";
				break;
			case 'underset':
				$res = "\\underset{" . $this->goInner( $buf["p1"] ) . "}{" . $this->goInner( $buf["p2"] ) . "}";
				break;
			case 'underbrace':
				$res = "\\underbrace{" . $this->goInner( $buf["p1"] ) . "}_{" . $this->goInner( $buf["p2"] ) . "}";
				break;
			case 'color':
				$res = "{\\color{" . $buf["color1"] . "}{" . $this->goInner( $buf["color2"] ) . "}}";
				break;
			case 'color0':
				$res = "\\color{" . $buf["color"] . "}";
				break;
			case 'arrow':
				$b6 = [
					"rd" => $this->goInner( $buf["rd"] ),
					"rq" => $this->goInner( $buf["rq"] )
				];
				$arrow = $this->getArrow( $buf["r"] );
				if ( MU::issetJS( $b6["rd"] ) || MU::issetJS( $b6["rq"] ) ) {
					if ( $buf["r"] === "<=>" || $buf["r"] === "<=>>" || $buf["r"] === "<<=>" || $buf["r"] === "<-->" ) {
						$arrow = "\\long" . $arrow;
						if ( MU::issetJS( $b6["rd"] ) ) {
							$arrow = "\\overset{" . $b6["rd"] . "}{" . $arrow . "}";
						}
						if ( MU::issetJS( $b6["rq"] ) ) {
							if ( $buf["r"] === "<-->" ) {
								$arrow = !$this->optimizeForTexVC ?
									"\\underset{\\lower2mu{" . $b6["rq"] . "}}{" . $arrow . "}"
									: "\\underset{\\lower{2mu}{" . $b6["rq"] . "}}{" . $arrow . "}";
							} else {
								$arrow = !$this->optimizeForTexVC ?
									"\\underset{\\lower6mu{" . $b6["rq"] . "}}{" . $arrow . "}"
									: "\\underset{\\lower{6mu}{" . $b6["rq"] . "}}{" . $arrow . "}";
							}
						}
						$arrow = " {}\\mathrel{" . $arrow . "}{} ";
					} else {
						if ( MU::issetJS( $b6["rq"] ) ) {
							$arrow .= "[{" . $b6["rq"] . "}]";
						}
						$arrow .= "{" . $b6["rd"] . "}";
						$arrow = " {}\\mathrel{\\x" . $arrow . "}{} ";
					}
				} else {
					$arrow = " {}\\mathrel{\\long" . $arrow . "}{} ";
				}
				$res = $arrow;
				break;
			case 'operator':
				$res = $this->getOperator( $buf["kind_"] );
				break;
			default:
				$res = null;
		}
		if ( $res !== null ) {
			return $res;
		}

		return match ( $buf["type_"] ) {
			'space' => " ",
			'tinySkip' => !$this->optimizeForTexVC ? '\\mkern2mu' : '\\mkern{2mu}',
			'pu-space-1', 'entitySkip' => "~",
			'pu-space-2' => !$this->optimizeForTexVC ? "\\mkern3mu " : "\\mkern{3mu} ",
			'1000 separator' => !$this->optimizeForTexVC ? "\\mkern2mu " : "\\mkern{2mu} ",
			'commaDecimal' => "{,}",
			'comma enumeration L' => "{" .
				$buf["p1"] .
				"}" .
				( !$this->optimizeForTexVC ? "\\mkern6mu " : "\\mkern{6mu} " ),
			'comma enumeration M' => "{" .
				$buf["p1"] .
				"}" .
				( !$this->optimizeForTexVC ? "\\mkern3mu " : "\\mkern{3mu} " ),
			'comma enumeration S' => "{" .
				$buf["p1"] .
				"}" .
				( !$this->optimizeForTexVC ? "\\mkern1mu " : "\\mkern{1mu} " ),
			'hyphen' => "\\text{-}",
			'addition compound' => "\\,{\\cdot}\\,",
			'electron dot' => !$this->optimizeForTexVC ? "\\mkern1mu \\bullet\\mkern1mu "
				: "\\mkern{1mu} \\bullet\\mkern{1mu} ",
			'KV x' => "{\\times}",
			'prime' => "\\prime ",
			'cdot' => "\\cdot ",
			'tight cdot' => !$this->optimizeForTexVC ? "\\mkern1mu{\\cdot}\\mkern1mu "
				: "\\mkern{1mu}{\\cdot}\\mkern{1mu} ",
			'times' => "\\times ",
			'circa' => "{\\sim}",
			'^' => "uparrow",
			'v' => "downarrow",
			'ellipsis' => "\\ldots ",
			'/' => "/",
			' / ' => "\\,/\\,",
			default => throw new RuntimeException( "MhchemBugT: mhchem bug T. Please report." ),
		};
	}

	private function getArrow( string $a ): string {
		return match ( $a ) {
			"\u2192", "\u27F6", "->" => "rightarrow",
			"<-" => "leftarrow",
			"<->" => "leftrightarrow",
			"<-->" => "leftrightarrows",
			"\u21CC", "<=>" => "rightleftharpoons",
			"<=>>" => "Rightleftharpoons",
			"<<=>" => "Leftrightharpoons",
			default => throw new RuntimeException( "MhchemBugT: mhchem bug T. Please report." ),
		};
	}

	private function getBond( string $a ): string {
		return match ( $a ) {
			"1", "-" => "{-}",
			"2", "=" => "{=}",
			"3", "#" => "{\\equiv}",
			"~" => "{\\tripledash}",
			"~-" => !$this->optimizeForTexVC ? "{\\rlap{\\lower.1em{-}}\\raise.1em{\\tripledash}}"
				: "{\\rlap{\\lower{.1em}{-}}\\raise{.1em}{\\tripledash}}",
			"~--", "~=" => !$this->optimizeForTexVC ? "{\\rlap{\\lower.2em{-}}\\rlap{\\raise.2em{\\tripledash}}-}"
				: "{\\rlap{\\lower{.2em}{-}}\\rlap{\\raise{.2em}{\\tripledash}}-}",
			"-~-" => !$this->optimizeForTexVC ? "{\\rlap{\\lower.2em{-}}\\rlap{\\raise.2em{-}}\\tripledash}"
				: "{\\rlap{\\lower{.2em}{-}}\\rlap{\\raise{.2em}{-}}\\tripledash}",
			"..." => "{{\\cdot}{\\cdot}{\\cdot}}",
			"...." => "{{\\cdot}{\\cdot}{\\cdot}{\\cdot}}",
			"->" => "{\\rightarrow}",
			"<-" => "{\\leftarrow}",
			"<" => "{<}",
			">" => "{>}",
			default => throw new RuntimeException( "MhchemBugT: mhchem bug T. Please report." ),
		};
	}

	private function getOperator( string $a ): string {
		return match ( $a ) {
			"+" => " {}+{} ",
			"-" => " {}-{} ",
			"=" => " {}={} ",
			"<" => " {}<{} ",
			">" => " {}>{} ",
			"<<" => " {}\\ll{} ",
			">>" => " {}\\gg{} ",
			"\\pm" => " {}\\pm{} ",
			"$\\approx$", "\\approx" => " {}\\approx{} ",
			"(v)", "v" => " \\downarrow{} ",
			"(^)", "^" => " \\uparrow{} ",
			default => throw new RuntimeException( "MhchemBugT: mhchem bug T. Please report." ),
		};
	}

}
