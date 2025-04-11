<?php

require_once __DIR__ . '/../../../maintenance/Maintenance.php';

use MediaWiki\Extension\Math\WikiTexVC\TexUtil;

class GenerateDoc extends Maintenance {

	private array $baseElements;
	private array $letterMods;
	private array $literals;
	private array $omitElements;
	private array $sets = [
		'big_literals',
		'box_functions',
		'color_function',
		'declh_function',
		'definecolor_function',
		'fun_ar1',
		'fun_ar1nb',
		'fun_ar1opt',
		'fun_ar2',
		'fun_ar2nb',
		'fun_infix',
		'fun_mhchem',
		'hline_function',
		'latex_function_names',
		'left_function',
		'mediawiki_function_names',
		'mhchem_bond',
		'mhchem_macro_1p',
		'mhchem_macro_2p',
		'mhchem_macro_2pc',
		'mhchem_macro_2pu',
		'mhchem_single_macro',
		'nullary_macro',
		'nullary_macro_in_mbox',
		'other_delimiters1',
		'other_delimiters2',
		'right_function',
	];
	private array $argCounts = [
		'big_literals' => 1,
		'box_functions' => 1,
		'color_function' => 1,
		'definecolor_function' => 1,
		'fun_ar1' => 1,
		'fun_ar1nb' => 1,
		'fun_ar1opt' => 1,
		'fun_ar2' => 2,
		'fun_ar2nb' => 5,
		'fun_infix' => 1,
		'fun_mhchem' => 1,
		'left_function' => 1,
		'mhchem_bond' => 1,
		'mhchem_macro_1p' => 1,
		'mhchem_macro_2p' => 2,
		'mhchem_macro_2pu' => 1,
		'right_function' => 1,
	];
	private array $sampleArgs = [
		'big_literals' => '(',
		'color_function' => '{red}{red}',
		'definecolor_function' => '{mycolor}{cmyk}{.4,1,1,0}',
		'fun_ar2nb' => '{_1^2}{_3^4}\sum',
		'left_function' => '( \right.',
		'mhchem_bond' => '{-}',
		'mhchem_macro_2pc' => '{red}{red}',
		'right_function' => ')',
	];

	public function __construct() {
		parent::__construct();
		$this->baseElements = TexUtil::getInstance()->getBaseElements();
		$this->letterMods = array_keys( $this->baseElements['is_letter_mod'] );
		$this->literals = array_keys( $this->baseElements['is_literal'] );
		$this->omitElements = array_merge( array_keys( $this->baseElements['mhchemtexified_required'] ),
			array_keys( $this->baseElements['intent_required'] ) );
	}

	private function printSample( string $set, string $elem ): string {
		$count = $this->argCounts[$set] ?? 0;
		if ( in_array( $elem, $this->omitElements ) ) {
			// no output for special mhchem implementation
			return '';
		}

			$textString = str_replace( '\\', '\\textbackslash ', $elem );

		if ( $set === 'fun_infix' ) {
			return "\\texttt{{$textString}} applied on \$ x, y\$ is rendered as \$x{$elem} y\$";
		}
		if ( $set === 'hline_function' ) {
			return "\\texttt{{$textString}} applied in a table is rendered as \$"
				. "\\begin{matrix} x_{11} & x_{12} \\\\ \hline \\end{matrix}\$";
		}
		if ( $set === 'mediawiki_function_names' ) {
			return "\\texttt{{$textString}} is rendered as \$\\operatorname{" . substr( $elem, 1 ) . "} y\$";
		}
		if ( $set === 'right_function' ) {
			return "\\texttt{{$textString}} is rendered as \$\\left. \\right)\$";
		}

		if ( in_array( $elem, [ '\\limits', '\\nolimits' ] ) ) {
			return "\\texttt{{$textString}} is rendered for example as \$\\mathop\\cap{$elem}_a^b\$";
		}
		if ( $elem === '\\pagecolor' ) {
			return '\\texttt{\\textbackslash pagecolor} is not rendered.';
		}
		if ( $elem === '\\ca' ) {
			return '\\texttt{\\textbackslash ca} was never used. \\newline ' .
				' \\url{https://phabricator.wikimedia.org/T323878}';
		}

		$args = $this->sampleArgs[$set] ?? str_repeat( '{x}', $count );
		$argDesc = $count > 1 ? "applied on \${$args}\$ " : '';
		$rendering = strpos( $set, 'mhchem' ) === 0 ? "\\ce{{$elem}{$args}}" : $elem . $args;

		return "\\texttt{{$textString}} {$argDesc}is rendered as \${$rendering}\$";
	}

	private function writeFile( string $filename, string $content ): void {
		$filepath = __DIR__ . '/../doc/' . $filename . '.tex';
		if ( file_put_contents( $filepath, $content ) === false ) {
			echo "Error saving document" . PHP_EOL;
		} else {
			echo "The file \"{$filename}\" was saved!" . PHP_EOL;
		}
	}

	private function printMod( string $x ): string {
		$x_str = str_replace( '\\', '\\textbackslash ', $x );

		return "\\texttt{{$x_str}} applied on \$x,X\$ is rendered as \${$x}{x},{$x}{X}\$\n\n";
	}

	private function printLiteral( string $x ): string {
		$x_str = str_replace( '\\', '\\textbackslash ', $x );
		return "\\texttt{{$x_str}} is rendered as \${$x}\$\n\n";
	}

	public function execute() {
		$this->writeFile( 'commands', implode( "\n",
			array_map( [ $this, 'printMod' ], $this->letterMods ) ) );
		$this->writeFile( 'literals', implode( "\n",
			array_map( [ $this, 'printLiteral' ], $this->literals ) ) );
		$this->writeFile( 'groups', implode( "\n\n",
			array_map( function ( $set ) {
				return "\\section{ Group \\texttt{" .
				str_replace( '_', '\\textunderscore ', $set )
				. "}}\n\n" .
				implode( "\n\n",
					array_map( fn ( $elem ) => $this->printSample( $set, $elem ),
						array_keys( $this->baseElements[$set] ) ) );
			}, $this->sets ) ) );
	}
}
$maintClass = GenerateDoc::class;
require_once RUN_MAINTENANCE_IF_MAIN;
