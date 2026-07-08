<?php
/**
 * Benchmark for UstringLibrary::ustringGsub().
 *
 * Run from the MediaWiki base install path via:
 *   php maintenance/run.php Scribunto:BenchmarkUstringGsub [--count N]
 */

namespace MediaWiki\Extension\Scribunto\Maintenance;

use MediaWiki\Extension\Scribunto\Engines\LuaCommon\UstringLibrary;
use MediaWiki\Maintenance\Benchmarker;
use ReflectionMethod;

// @codeCoverageIgnoreStart
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/includes/Benchmarker.php";
// @codeCoverageIgnoreEnd

class BenchmarkUstringGsub extends Benchmarker {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Benchmark UstringLibrary::ustringGsub()' );
	}

	public function execute() {
		$services = $this->getServiceContainer();
		$engineFactory = $services->getService( 'Scribunto.EngineFactory' );
		$engine = $engineFactory->getDefaultEngine();

		$library = new UstringLibrary( $engine );
		$method = new ReflectionMethod( $library, 'ustringGsub' );
		$gsubCallable = $method->getClosure( $library );

		$benches = array_map(
			static fn ( array $case ) => [
				'function' => static function () use ( $case, $gsubCallable ) {
					$gsubCallable(
						$case['text'],
						$case['pattern'],
						$case['replacement'],
						$case['limit'] ?? null
					);
				},
			],
			$this->buildBenchCases()
		);

		$this->bench( $benches );
	}

	/**
	 * Build benchmark inputs.
	 *
	 * @return array[]
	 */
	private function buildBenchCases(): array {
		$trimSample = "\t  May 25, 2006  \n";
		$trimSample2 = "  Example Committee Office  \n";
		$linkSnippet = '[[Example Link]]';
		$linkSample = str_repeat( $linkSnippet . ' ', 200 );

		return [
			'HTML quote escaping' => [
				'text' => str_repeat( '"Example, Person (1900-2000)" ', 200 ),
				'pattern' => '"',
				'replacement' => '&quot;',
			],
			'Placeholder substitution' => [
				'text' => str_repeat( 'https://example.test/resource/$1', 200 ),
				'pattern' => '%$1',
				'replacement' => 'record/50a1bb6c-32ab-4995-816e-1de87a96d134',
			],
			'Whitespace trimming capture (%1)' => [
				'text' => str_repeat( $trimSample, 400 ),
				'pattern' => '^%s*(.-)%s*$',
				'replacement' => '%1',
			],
			'Whitespace trimming capture (long)' => [
				'text' => str_repeat( $trimSample2, 200 ),
				'pattern' => '^%s*(.-)%s*$',
				'replacement' => '%1',
			],
			'Curly quotes normalization' => [
				'text' => str_repeat( '‘Sample headline’ in Locale ', 200 ),
				'pattern' => "[‘’]",
				'replacement' => "'",
			],
			'Link extraction via table (hit)' => [
				'text' => $linkSample,
				'pattern' => '^%[%[.-%]%]',
				'replacement' => [ $linkSnippet => 'Example Link' ],
				'limit' => 1,
			],
			'Link extraction via table (miss)' => [
				'text' => $linkSample,
				'pattern' => '^%[%[.-%]%]',
				'replacement' => [],
				'limit' => 1,
			],
			'Whole match via %0' => [
				'text' => str_repeat( 'alpha123beta456gamma789', 200 ),
				'pattern' => '(%a+)(%d+)',
				'replacement' => '[%0]',
			],
		];
	}
}

// @codeCoverageIgnoreStart
$maintClass = BenchmarkUstringGsub::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
