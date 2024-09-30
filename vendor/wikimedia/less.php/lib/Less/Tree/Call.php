<?php
/**
 * @private
 * @see less.tree.Call in less.js 3.0.0 https://github.com/less/less.js/blob/v3.0.0/dist/less.js#L6336
 */
class Less_Tree_Call extends Less_Tree implements Less_Tree_HasValueProperty {
	public $value;

	public $name;
	public $args;
	/** @var bool */
	public $mathOn;
	public $index;
	public $currentFileInfo;

	public function __construct( $name, $args, $index, $currentFileInfo = null ) {
		$this->name = $name;
		$this->args = $args;
		$this->mathOn = ( $name !== 'calc' );
		$this->index = $index;
		$this->currentFileInfo = $currentFileInfo;
	}

	public function accept( $visitor ) {
		$this->args = $visitor->visitArray( $this->args );
	}

	/**
	 * @see less-2.5.3.js#functionCaller.prototype.call
	 */
	private function functionCaller( $function, array $arguments ) {
		// This code is terrible and should be replaced as per this issue...
		// https://github.com/less/less.js/issues/2477
		$filtered = [];
		foreach ( $arguments as $argument ) {
			if ( $argument instanceof Less_Tree_Comment ) {
				continue;
			}
			$filtered[] = $argument;
		}
		foreach ( $filtered as $index => $argument ) {
			if ( $argument instanceof Less_Tree_Expression ) {
				$filtered[$index] = $argument->mapToFunctionCallArgument();
			}
		}
		return $function( ...$filtered );
	}

	//
	// When evaluating a function call,
	// we either find the function in Less_Functions,
	// in which case we call it, passing the evaluated arguments,
	// or we simply print it out as it literal CSS.
	//
	// The reason why we compile the arguments, is in the case one
	// of them is a LESS variable that only PHP knows the value of,
	// like: `saturate(@mycolor)`.
	// The function should receive the value, not the variable.
	//
	public function compile( $env ) {
		// Turn off math for calc(). https://phabricator.wikimedia.org/T331688
		$currentMathContext = $env->strictMath;
		$env->strictMath = !$this->mathOn;

		$args = [];
		foreach ( $this->args as $a ) {
			$args[] = $a->compile( $env );
		}

		$env->strictMath = $currentMathContext;

		$nameLC = strtolower( $this->name );
		switch ( $nameLC ) {
			case '%':
				$nameLC = '_percent';
				break;

			case 'get-unit':
				$nameLC = 'getunit';
				break;

			case 'data-uri':
				$nameLC = 'datauri';
				break;

			case 'svg-gradient':
				$nameLC = 'svggradient';
				break;
			case 'image-size':
				$nameLC = 'imagesize';
				break;
			case 'image-width':
				$nameLC = 'imagewidth';
				break;
			case 'image-height':
				$nameLC = 'imageheight';
				break;
		}

		$result = null;
		if ( $nameLC === 'default' ) {
			$result = Less_Tree_DefaultFunc::compile();
		} else {
			$func = null;
			$functions = new Less_Functions( $env, $this->currentFileInfo );
			$funcBuiltin = [ $functions, $nameLC ];
			// Avoid method_exists() as that considers private utility functions too
			if ( is_callable( $funcBuiltin ) ) {
				$func = $funcBuiltin;
			} elseif ( isset( $env->functions[$nameLC] ) && is_callable( $env->functions[$nameLC] ) ) {
				$func = $env->functions[$nameLC];
			}
			// If the function name isn't known to LESS, output it unchanged as CSS.
			if ( $func ) {
				try {
					$result = $this->functionCaller( $func, $args );
				} catch ( Exception $e ) {
					// Preserve original trace, especially from custom functions.
					// https://github.com/wikimedia/less.php/issues/38
					throw new Less_Exception_Compiler(
						'error evaluating function `' . $this->name . '` ' . $e->getMessage()
							. ' index: ' . $this->index,
						$e
					);
				}
			}
		}

		if ( $result !== null ) {
			return $result;
		}

		return new self( $this->name, $args, $this->index, $this->currentFileInfo );
	}

	/**
	 * @see Less_Tree::genCSS
	 */
	public function genCSS( $output ) {
		$output->add( $this->name . '(', $this->currentFileInfo, $this->index );
		$args_len = count( $this->args );
		for ( $i = 0; $i < $args_len; $i++ ) {
			$this->args[$i]->genCSS( $output );
			if ( $i + 1 < $args_len ) {
				$output->add( ', ' );
			}
		}

		$output->add( ')' );
	}

}
