<?php
/**
 * Generate an efficient FST from a conversion table, as (say) provided
 * to str_replace().
 *
 * The standard FOMA algorithm for longest-substring matching is
 * probably nice and efficient but blows up the size of the generated
 * FST.  Use a different technique to generate an FST with O(SIGMA+M)
 * states (where SIGMA is the number of characters in the "alphabet"
 * and M is the total # of characters in the input and output strings)
 * and O(SIGMA^2 + M) edges.  This won't blow up if the # of
 * replacement strings is high (in fact, it might improve a bit).  It
 * does get bigger for CJK languages with large alphabets, but not in
 * an ever-increasing way.
 */
require_once __DIR__ . '/Maintenance.php';

use Wikimedia\LangConv\Construct\GenReplFst as ConsGenReplFst;

class GenReplFst extends Maintenance {

	/** @inheritDoc */
	public function execute() {
		$zh = Language::factory( 'zh' );
		$converter = $zh->getConverter();
		# autoConvert will trigger the tables to be loaded
		$converter->autoConvertToAllVariants( "xyz" );
		foreach ( $converter->mTables as $var => $table ) {
			if ( !preg_match( '/^zh/', $var ) ) {
				continue;
			}
			if ( count( $table->getArray() ) === 0 ) {
				continue;
			}
			$name = "TABLE'" . preg_replace( '/-/', "'", strtoupper( $var ) );

			if ( $name !== "TABLE'ZH'HANS" ) {
				continue;
			}
			# error_log( $name );
			$g = new ConsGenReplFst( $name, $table->getArray(), 'HANS' );
			$g->writeATT( STDOUT );
		}
	}
}

$maintClass = GenReplFst::class;
require_once RUN_MAINTENANCE_IF_MAIN;
