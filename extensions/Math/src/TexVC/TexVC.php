<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\Math\TexVC;

use Exception;
use MediaWiki\Extension\Math\TexVC\Nodes\TexArray;
use stdClass;

/**
 * A TeX/LaTeX validator.
 * TexVC(PHP) takes user input and validates it while replacing
 * MediaWiki-specific functions.  It is a PHP port of the JavaScript port of texvc,
 * which was originally written in Ocaml for the Math extension.
 *
 * @author Johannes Stegmüller
 */
class TexVC {
	/** @var Parser */
	private $parser;
	/** @var TexUtil */
	private $tu;

	public function __construct() {
		$this->parser = new Parser();
		$this->tu = TexUtil::getInstance();
	}

	private function strStartsWith( $haystack, $needle ): bool {
		return strpos( $haystack, $needle ) === 0;
	}

	/**
	 * Usually this step is done implicitly within the check-method.
	 * @param string $input tex-string as input for the grammar
	 * @param null|array $options array options for the grammar.
	 * @return mixed output of the grammar.
	 * @throws SyntaxError when SyntaxError in the input
	 */
	public function parse( $input, $options = null ) {
		return $this->parser->parse( $input, $options );
	}

	/** status is one character:
	 *  + : success! result is in 'output'
	 *  E : Lexer exception raised
	 *  F : TeX function not recognized
	 *  S : Parsing error
	 *  - : Generic/Default failure code. Might be an invalid argument,
	 *      output file already exist, a problem with an external
	 *      command ...
	 * @param string|TexArray|stdClass $input tex to be checked as string,
	 * can also be the output of former parser call
	 * @param array $options array options for settings of the check
	 * @param array &$warnings reference on warnings occurring during the check
	 * @return array|string[] output with information status (see above)
	 * @throws Exception in case of a major problem with the check and activated debug option.
	 */
	public function check( $input, $options = [], &$warnings = [] ) {
		try {
			$options = ParserUtil::createOptions( $options );
			if ( is_string( $input ) ) {
				$input = $this->parser->parse( $input, $options );
			}
			$output = $input->render();
			$result = [
				'status' => '+',
				'output' => $output,
				'warnings' => $warnings,
				'input' => $input,
				'success' => true,
			];

			if ( $options['report_required'] ) {
				$pkgs = [ 'ams', 'cancel', 'color', 'euro', 'teubner', 'mhchem', 'mathoid' ];

				foreach ( $pkgs as $pkg ) {
					$pkg .= '_required';
					$tuRef = $this->tu->getBaseElements()[$pkg];
					$result[$pkg] = $input->containsFunc( $tuRef );
				}
			}

			if ( !$options['usemhchem'] ) {
				if ( $result['mhchem_required'] ??
						$input->containsFunc( $this->tu->getBaseElements()['mhchem_required'] )
				) {
					return [
						'status' => 'C',
						'details' => 'mhchem package required.'
					];
				}
			}
			return $result;
		} catch ( Exception $ex ) {
			if ( $ex instanceof SyntaxError && !$options['oldtexvc']
					&& $this->strStartsWith( $ex->getMessage(), 'Deprecation' ) ) {

				$warnings[] = [
					'type' => 'texvc-deprecation',
					'details' => $this->handleTexError( $ex, $options )
				];
				$options['oldtexvc'] = true;
				return $this->check( $input, $options, $warnings );
			}

			if ( $ex instanceof SyntaxError && $options['usemhchem'] && !$options['oldmhchem'] ) {
				$warnings[] = [
					'type' => 'mhchem-deprecation',
					'details' => $this->handleTexError( $ex, $options )
				];
				$options['oldmhchem'] = true;
				return $this->check( $input, $options, $warnings );
			}
		}
		return $this->handleTexError( $ex, $options );
	}

	private function handleTexError( Exception $e, $options = null ) {
		if ( $options && $options['debug'] ) {
			throw $e;
		}
		$report = [ 'success' => false, 'warnings' => [] ];
		if ( $e instanceof SyntaxError ) {
			if ( $e->getMessage() === 'Illegal TeX function' ) {
				$report['status'] = 'F';
				$report['details'] = $e->found;
				$report += $this->getLocationInfo( $e );
			} else {
				$report['status'] = 'S';
				$report['details'] = $e->getMessage();
				$report += $this->getLocationInfo( $e );
			}
			$report['error'] = [
				'message' => $e->getMessage(),
				'expected' => $e->expected,
				'found' => $e->found,
				'location' => [
					/** This currently only has the start location, since end is not noted in SyntaxError in PHP
					 * this issue is tracked in: https://phabricator.wikimedia.org/T321060
					 */
					'offset' => $e->grammarOffset,
					'line' => $e->grammarLine,
					'column' => $e->grammarColumn
				],
				'name' => $e->name
			];

		} else {
			$report['status'] = '-';
			$report['details'] = $e->getMessage();
			$report['error'] = $e;
		}
		return $report;
	}

	/**
	 * Gets the location information of an error object, or returns default error
	 * location if no location information was specified.
	 * @param SyntaxError $e error object
	 * @return array information on the error.
	 */
	private function getLocationInfo( SyntaxError $e ) {
		try {
			return [
				'offset'  => $e->grammarOffset,
				'line' => $e->grammarLine,
				'column' => $e->grammarColumn
			];
		} catch ( Exception $err ) {
			return [ 'offset' => 0, 'line' => 0, 'column' => 0 ];
		}
	}

}
