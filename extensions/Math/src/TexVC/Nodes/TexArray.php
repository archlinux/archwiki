<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC\Nodes;

use InvalidArgumentException;
use MediaWiki\Extension\Math\TexVC\MMLmappings\Util\MMLParsingUtil;
use MediaWiki\Extension\Math\TexVC\MMLmappings\Util\MMLutil;
use MediaWiki\Extension\Math\TexVC\MMLnodes\MMLmstyle;

class TexArray extends TexNode {

	public function __construct( ...$args ) {
		$nargs = [];

		foreach ( $args as &$arg ) {
			if ( $arg !== null ) {
				array_push( $nargs, $arg );
			}
		}

		self::checkInput( $nargs );
		parent::__construct( ...$nargs );
	}

	public function checkForStyleArgs( $node ) {
		if ( $node instanceof Literal ) {
			$name = trim( $node->getArg() );
			switch ( $name ) {
				case "\\displaystyle":
					return [ "displaystyle" => "true", "scriptlevel" => "0" ];
				 case "\\scriptstyle":
					 return [ "displaystyle" => "false", "scriptlevel" => "1" ];
				 case "\\scriptscriptstyle":
					 return [ "displaystyle" => "false", "scriptlevel" => "2" ];
				 case "\\textstyle":
					 return [ "displaystyle" => "false", "scriptlevel" => "0" ];
			}
		}
		return null;
	}

	/**
	 * Checks if an TexNode of Literal contains color information (color, pagecolor)
	 * and returns info how to continue with the parsing.
	 * @param TexNode $node node to check if it contains color info
	 * @return array index 0: (bool) was color element found, index 1: (string) specified color
	 */
	public function checkForColor( TexNode $node ) {
		if ( $node instanceof Literal ) {
			$name = trim( $node->getArg() );
			if ( str_contains( $name, "\\color" ) ) {
				$foundOperatorContent = MMLutil::initalParseLiteralExpression( $node->getArg() );
				if ( !$foundOperatorContent ) {
					// discarding color elements which not specify color
					return [ true, null ];
				} else {
					return [ true, $foundOperatorContent[2][0] ];
				}
			} elseif ( str_contains( $name, "\\pagecolor" ) ) {
				return [ true, null ];
			}
		}
		return [ false, null ];
	}

	public function checkForColorDefinition( TexNode $node ): ?array {
		if ( $node instanceof Literal ) {
			$name = trim( $node->getArg() );
			if ( str_contains( $name, "\\definecolor" ) ) {
				return MMLParsingUtil::parseDefineColorExpression( $node->getArg() );
			}
		}
		return null;
	}

	/**
	 * Checks two sequential nodes in TexArray if they contain information on sideset expressions.
	 * @param TexNode $currentNode first node in array to check (for sideset expression)
	 * @param TexNode|null $nextNode second node in array to check (for succeeding operator)
	 * @return TexNode|null the succeeding operator for further Parsing or null if sideset not found or invalid
	 */
	public function checkForSideset( TexNode $currentNode, ?TexNode $nextNode ): ?TexNode {
		if ( !( $currentNode instanceof Fun2nb && $currentNode->getFname() == "\\sideset" ) ) {
			return null;
		}
		if ( $nextNode instanceof Literal ) {
			return $nextNode;
		}
		if ( $nextNode instanceof FQ ) {
			return $nextNode;
		}
		return null;
	}

	public function renderMML( $arguments = [], $state = [] ) {
		// Everything here is for parsing displaystyle, probably refactored to TexVC grammar later
		$fullRenderedArray = "";
		$mmlStyle = null;
		$currentColor = null;
		for ( $i = 0, $count = count( $this->args ); $i < $count; $i++ ) {
			$current = $this->args[$i];
			if ( isset( $this->args[$i + 1] ) ) {
				$next = $this->args[$i + 1];
			} else {
				$next = null;
			}
			// Check for sideset
			$foundSideset = $this->checkForSideset( $current, $next );
			if ( $foundSideset ) {
				$state["sideset"] = $foundSideset;
				// Skipping the succeeding Literal
				$i++;
			}

			// Check if there is a new color definition and add it to state
			$foundColorDef = $this->checkForColorDefinition( $current );
			if ( $foundColorDef ) {
				$state["colorDefinitions"][$foundColorDef["name"]] = $foundColorDef;
				continue;
			}
			// Pass preceding color info to state
			$foundColor = $this->checkForColor( $current );
			if ( $foundColor[0] ) {
				$currentColor = $foundColor[1];
				// Skipping the color element itself for rendering
				continue;
			}
			$styleArguments = $this->checkForStyleArgs( $current );
			// For cases with "displaystyle{someargs} otherargs"
			if ( $styleArguments ) {
				$mmlStyle = new MMLmstyle( "", $styleArguments );
				$fullRenderedArray .= $mmlStyle->getStart();
				if ( $next instanceof Curly ) {
					$fullRenderedArray .= $this->renderMMLwithColor( $currentColor, $current, $state, $arguments );
					$fullRenderedArray .= $mmlStyle->getEnd();
					$mmlStyle = null;
					$i++;
				}
			} else {
				$fullRenderedArray .= $this->renderMMLwithColor( $currentColor, $current, $state, $arguments );
			}
		}
		if ( $mmlStyle ) {
			$fullRenderedArray .= $mmlStyle->getEnd();
		}
		return $fullRenderedArray;
	}

	private function renderMMLwithColor( $currentColor, $currentNode, $state, $arguments ) {
		if ( $currentColor ) {
			if ( array_key_exists( "colorDefinitions", $state )
				&& is_array( $state["colorDefinitions"] )
				&& array_key_exists( $currentColor, $state["colorDefinitions"] ?? [] )
			   ) {
				$displayedColor = $state["colorDefinitions"][$currentColor]["hex"];
			} else {
				$displayedColor = $currentColor;
			}
			$mmlStyleColor = new MMLmstyle( "", [ "mathcolor" => $displayedColor ] );
			return $mmlStyleColor->encapsulateRaw( $currentNode->renderMML( $arguments, $state ) );
		} else {
		   return $currentNode->renderMML( $arguments, $state );
		}
	}

	public function inCurlies() {
		if ( isset( $this->args[0] ) && count( $this->args ) == 1 ) {
			return $this->args[0]->inCurlies();
		} else {
			return '{' . $this->render() . '}';
		}
	}

	public function extractSubscripts() {
		$y = [];

		foreach ( $this->args as $x ) {
			$y = array_merge( $y, $x->extractSubscripts() );
		}
		if ( isset( $this->args[0] ) && ( count( $this->args ) == count( $y ) ) ) {
			return implode( '', $y );
		}
		return [];
	}

	public function extractIdentifiers( $args = null ) {
		if ( $args == null ) {
			$args = $this->args;
		}
		$list = parent::extractIdentifiers( $args );
		$outpos = 0;
		$offset = 0;
		$int = 0;

		for ( $inpos = 0; $inpos < count( $list ); $inpos++ ) {
			$outpos = $inpos - $offset;
			switch ( $list[$inpos] ) {
				case '\'':
					$list[$outpos - 1] .= '\'';
					$offset++;
					break;
				case '\\int':
					$int++;
					$offset++;
					break;
				case '\\mathrm{d}':
				case 'd':
					if ( $int ) {
						$int--;
						$offset++;
						break;
					}
				// no break
				default:
					if ( isset( $list[0] ) ) {
						$list[$outpos] = $list[$inpos];
					}
			}
		}
		return array_slice( $list, 0, count( $list ) - $offset );
	}

	public function getModIdent() {
		$y = [];

		foreach ( $this->args as $x ) {
			$y = array_merge( $y, $x->getModIdent() );
		}

		if ( isset( $this->args[0] ) && ( count( $this->args ) == count( $y ) ) ) {
			return implode( "", $y );
		}
		return [];
	}

	public function push( ...$elements ) {
		self::checkInput( $elements );

		array_push( $this->args, ...$elements );
	}

	public function pop() {
		array_splice( $this->args, 0, 1 );
	}

	/**
	 * @return TexNode|string|null first value
	 */
	public function first() {
		if ( isset( $this->args[0] ) ) {
			return $this->args[0];
		} else {
			return null;
		}
	}

	/**
	 * @return TexNode|string|null second value
	 */
	public function second() {
		if ( isset( $this->args[1] ) ) {
			return $this->args[1];
		} else {
			return null;
		}
	}

	public function unshift( ...$elements ) {
		array_unshift( $this->args, ...$elements );
	}

	/**
	 * @throws InvalidArgumentException if args not of correct type
	 * @param TexNode[] $args input args
	 * @return void
	 */
	private static function checkInput( $args ): void {
		foreach ( $args as $arg ) {
			if ( !( $arg instanceof TexNode ) ) {
				throw new InvalidArgumentException( 'Wrong input type specified in input elements.' );
			}
		}
	}
}
