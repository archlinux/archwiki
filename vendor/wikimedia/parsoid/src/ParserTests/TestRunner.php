<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\ParserTests;

use Closure;
use Psr\Log\LoggerInterface;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Api\DataAccess;
use Wikimedia\Parsoid\Config\Api\PageConfig;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Config\StubMetadataCollector;
use Wikimedia\Parsoid\Core\SelserData;
use Wikimedia\Parsoid\DOM\Document;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Mocks\MockPageConfig;
use Wikimedia\Parsoid\Mocks\MockPageContent;
use Wikimedia\Parsoid\Tools\ScriptUtils;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Wt2Html\PageConfigFrame;

/**
 * Test runner for parser tests
 */
class TestRunner {
	// Hard-code some interwiki prefixes, as is done
	// in ParserTestRunner::appendInterwikiSetup() in core
	// Note that ApiQuerySiteInfo will always expand the URL to include a
	// protocol, but will set 'protorel' to indicate whether its internal
	// form included a protocol or not.  So in this file 'url' will always
	// have a protocol and we'll include an explicit 'protorel' field; but
	// in core there is no 'protorel' field and 'url' will not always have
	// a protocol.
	private const PARSER_TESTS_IWPS = [
		[
			'prefix' => 'wikinvest',
			'local' => true,
			// This url doesn't have a $1 to exercise the fix in
			// ConfigUtils::computeInterwikiMap
			'url' => 'https://meta.wikimedia.org/wiki/Interwiki_map/discontinued#Wikinvest',
			'protorel' => false
		],
		[
			'prefix' => 'local',
			'url' => 'http://example.org/wiki/$1',
			'local' => true,
			'localinterwiki' => true
		],
		[
			// Local interwiki that matches a namespace name (T228616)
			'prefix' => 'project',
			'url' => 'http://example.org/wiki/$1',
			'local' => true,
			'localinterwiki' => true
		],
		[
			'prefix' => 'wikipedia',
			'url' => 'http://en.wikipedia.org/wiki/$1'
		],
		[
			'prefix' => 'meatball',
			// this has been updated in the live wikis, but the parser tests
			// expect the old value (as set in parserTest.inc:setupInterwikis())
			'url' => 'http://www.usemod.com/cgi-bin/mb.pl?$1'
		],
		[
			'prefix' => 'memoryalpha',
			'url' => 'http://www.memory-alpha.org/en/index.php/$1'
		],
		[
			'prefix' => 'zh',
			'url' => 'http://zh.wikipedia.org/wiki/$1',
			'language' => "中文",
			'local' => true
		],
		[
			'prefix' => 'es',
			'url' => 'http://es.wikipedia.org/wiki/$1',
			'language' => "español",
			'local' => true
		],
		[
			'prefix' => 'fr',
			'url' => 'http://fr.wikipedia.org/wiki/$1',
			'language' => "français",
			'local' => true
		],
		[
			'prefix' => 'ru',
			'url' => 'http://ru.wikipedia.org/wiki/$1',
			'language' => "русский",
			'local' => true
		],
		[
			'prefix' => 'mi',
			'url' => 'http://example.org/wiki/$1',
			// better for testing if one of the
			// localinterwiki prefixes is also a language
			'language' => 'Test',
			'local' => true,
			'localinterwiki' => true
		],
		[
			'prefix' => 'mul',
			'url' => 'http://wikisource.org/wiki/$1',
			'extralanglink' => true,
			'linktext' => 'Multilingual',
			'sitename' => 'WikiSource',
			'local' => true
		],
		// added to core's ParserTestRunner::appendInterwikiSetup() to support
		// Parsoid tests [T254181]
		[
			'prefix' => 'en',
			'url' => 'http://en.wikipedia.org/wiki/$1',
			'language' => 'English',
			'local' => true,
			'protorel' => true
		],
		[
			'prefix' => 'stats',
			'local' => true,
			'url' => 'https://stats.wikimedia.org/$1'
		],
		[
			'prefix' => 'gerrit',
			'local' => true,
			'url' => 'https://gerrit.wikimedia.org/$1'
		]
	];

	/** @var bool */
	private $runDisabled;

	/** @var bool */
	private $runPHP;

	/** @var string */
	private $offsetType;

	/** @var string */
	private $testFileName;

	/** @var string */
	private $testFilePath;

	/** @var string */
	private $knownFailuresInfix;

	/** @var string */
	private $knownFailuresPath;

	/** @var array */
	private $articles;

	/** @var LoggerInterface */
	private $defaultLogger;

	/**
	 * Sets one of 'regex' or 'string' properties
	 * - $testFilter['raw'] is the value of the filter
	 * - if $testFilter['regex'] is true, $testFilter['raw'] is used as a regex filter.
	 * - If $testFilter['string'] is true, $testFilter['raw'] is used as a plain string filter.
	 * @var array
	 */
	private $testFilter;

	/** @var Test[] */
	private $testCases;

	/** @var Stats */
	private $stats;

	/** @var MockApiHelper */
	private $mockApi;

	/** @var SiteConfig */
	private $siteConfig;

	/** @var DataAccess */
	private $dataAccess;

	/**
	 * Global cross-test env object only to be used for title processing while
	 * reading the parserTests file.
	 *
	 * Every test constructs its own private $env object.
	 *
	 * @var Env
	 */
	private $dummyEnv;

	/**
	 * Options needed to construct the per-test private $env object
	 * @var array
	 */
	private $envOptions;

	/**
	 * @param string $testFilePath
	 * @param ?string $knownFailuresInfix
	 * @param string[] $modes
	 */
	public function __construct( string $testFilePath, ?string $knownFailuresInfix, array $modes ) {
		$this->testFilePath = $testFilePath;
		$this->knownFailuresInfix = $knownFailuresInfix;

		$testFilePathInfo = pathinfo( $testFilePath );
		$this->testFileName = $testFilePathInfo['basename'];

		$newModes = [];
		foreach ( $modes as $mode ) {
			$newModes[$mode] = new Stats();
			$newModes[$mode]->failList = [];
			$newModes[$mode]->result = ''; // XML reporter uses this.
		}

		$this->stats = new Stats();
		$this->stats->modes = $newModes;

		$this->mockApi = new MockApiHelper();
		$this->siteConfig = new SiteConfig( $this->mockApi, [] );
		$this->dataAccess = new DataAccess( $this->mockApi, $this->siteConfig, [ 'stripProto' => false ] );
		$this->dummyEnv = new Env(
			$this->siteConfig,
			// Unused; needed to satisfy Env signature requirements
			new MockPageConfig( [], new MockPageContent( [ 'main' => '' ] ) ),
			// Unused; needed to satisfy Env signature requirements
			$this->dataAccess,
			// Unused; needed to satisfy Env signature requirements
			new StubMetadataCollector( $this->siteConfig->getLogger() )
		);

		// Init interwiki map to parser tests info.
		// This suppresses interwiki info from cached configs.
		$this->siteConfig->setupInterwikiMap( self::PARSER_TESTS_IWPS );
	}

	/**
	 * @param Test $test
	 * @param string $wikitext
	 * @return Env
	 */
	private function newEnv( Test $test, string $wikitext ): Env {
		$pageNs = $this->dummyEnv->makeTitleFromURLDecodedStr(
			$test->pageName()
		)->getNameSpaceId();

		$opts = [
			'title' => $test->pageName(),
			'pagens' => $pageNs,
			'pageContent' => $wikitext,
			'pageLanguage' => $this->siteConfig->lang(),
			'pageLanguagedir' => $this->siteConfig->rtl() ? 'rtl' : 'ltr'
		];

		$pageConfig = new PageConfig( null, $opts );

		$env = new Env(
			$this->siteConfig,
			$pageConfig,
			$this->dataAccess,
			new StubMetadataCollector( $this->siteConfig->getLogger() ),
			$this->envOptions
		);

		$env->pageCache = $this->articles;
		// Set parsing resource limits.
		// $env->setResourceLimits();

		return $env;
	}

	/**
	 * Parse the test file and set up articles and test cases
	 * @param array $options
	 */
	private function buildTests( array $options ): void {
		// Startup by loading .txt test file
		$warnFunc = static function ( string $warnMsg ): void {
			error_log( $warnMsg );
		};
		$normFunc = function ( string $title ): string {
			return $this->dummyEnv->normalizedTitleKey( $title, false, true );
		};
		$testReader = TestFileReader::read(
			$this->testFilePath, $warnFunc, $normFunc, $this->knownFailuresInfix
		);
		$this->knownFailuresPath = $testReader->knownFailuresPath;
		$this->testCases = $testReader->testCases;
		$this->articles = [];
		foreach ( $testReader->articles as $art ) {
			$key = $normFunc( $art->title );
			$this->articles[$key] = $art->text;
			$this->mockApi->addArticle( $key, $art );
		}
		if ( !ScriptUtils::booleanOption( $options['quieter'] ?? '' ) ) {
			if ( $this->knownFailuresPath ) {
				error_log( 'Loaded known failures from ' . $this->knownFailuresPath );
			} else {
				error_log( 'No known failures found.' );
			}
		}
	}

	/**
	 * Convert a wikitext string to an HTML Node
	 *
	 * @param Env $env
	 * @param Test $test
	 * @param string $mode
	 * @param string $wikitext
	 * @return Document
	 */
	private function convertWt2Html(
		Env $env, Test $test, string $mode, string $wikitext
	): Document {
		// FIXME: Ugly!  Maybe we should switch to using the entrypoint to
		// the library for parserTests instead of reusing the environment
		// and touching these internals.
		$content = $env->getPageConfig()->getRevisionContent();
		// @phan-suppress-next-line PhanUndeclaredProperty
		$content->data['main']['content'] = $wikitext;
		$env->topFrame = new PageConfigFrame(
			$env, $env->getPageConfig(), $env->getSiteConfig()
		);
		if ( $mode === 'html2html' ) {
			// Since this was set when serializing we need to setup a new doc
			$env->setupTopLevelDoc();
		}
		$handler = $env->getContentHandler();
		$extApi = new ParsoidExtensionAPI( $env );
		$doc = $handler->toDOM( $extApi );
		return $doc;
	}

	/**
	 * Convert a DOM to Wikitext.
	 *
	 * @param Env $env
	 * @param Test $test
	 * @param string $mode
	 * @param Document $doc
	 * @return string
	 */
	private function convertHtml2Wt( Env $env, Test $test, string $mode, Document $doc ): string {
		$startsAtWikitext = $mode === 'wt2wt' || $mode === 'wt2html' || $mode === 'selser';
		if ( $mode === 'selser' ) {
			$selserData = new SelserData( $test->wikitext, $test->cachedBODYstr );
		} else {
			$selserData = null;
		}
		$env->topLevelDoc = $doc;
		$extApi = new ParsoidExtensionAPI( $env );
		return $env->getContentHandler()->fromDOM( $extApi, $selserData );
	}

	/**
	 * Run test in the requested mode
	 * @param Test $test
	 * @param string $mode
	 * @param array $options
	 */
	private function runTest( Test $test, string $mode, array $options ): void {
		$test->time = [];
		$testOpts = $test->options;

		// These changes are for environment options that change between runs of
		// different modes. See `processTest` for changes per test.
		if ( $testOpts ) {
			// Page language matches "wiki language" (which is set by
			// the item 'language' option).
			if ( isset( $testOpts['langconv'] ) ) {
				$this->envOptions['wtVariantLanguage'] = $testOpts['sourceVariant'] ?? null;
				$this->envOptions['htmlVariantLanguage'] = $testOpts['variant'] ?? null;
			} else {
				// variant conversion is disabled by default
				$this->envOptions['wtVariantLanguage'] = null;
				$this->envOptions['htmlVariantLanguage'] = null;
			}
		}

		$env = $this->newEnv( $test, $test->wikitext ?? '' );

		// Some useful booleans
		$startsAtHtml = $mode === 'html2html' || $mode === 'html2wt';
		$endsAtHtml = $mode === 'wt2html' || $mode === 'html2html';

		$parsoidOnly = isset( $test->sections['html/parsoid'] ) ||
			isset( $test->sections['html/parsoid+standalone'] ) || (
			!empty( $testOpts['parsoid'] ) &&
			!isset( $testOpts['parsoid']['normalizePhp'] )
		);
		$test->time['start'] = microtime( true );
		$doc = null;
		$wt = null;

		if ( isset( $test->sections['html/parsoid+standalone'] ) ) {
			$test->parsoidHtml = $test->sections['html/parsoid+standalone'];
		}

		// Source preparation
		if ( $startsAtHtml ) {
			$html = $test->parsoidHtml;
			if ( !$parsoidOnly ) {
				// Strip some php output that has no wikitext representation
				// (like .mw-editsection) and won't html2html roundtrip and
				// therefore causes false failures.
				$html = TestUtils::normalizePhpOutput( $html );
			}
			$doc = ContentUtils::createDocument( $html );
			$wt = $this->convertHtml2Wt( $env, $test, $mode, $doc );
		} else { // startsAtWikitext
			// Always serialize DOM to string and reparse before passing to wt2wt
			if ( $test->cachedBODYstr === null ) {
				$doc = $this->convertWt2Html( $env, $test, $mode, $test->wikitext );

				// Cache parsed HTML
				$test->cachedBODYstr = ContentUtils::toXML( DOMCompat::getBody( $doc ) );

				// - In wt2html mode, pass through original DOM
				//   so that it is serialized just once.
				// - In wt2wt and selser modes, pass through serialized and
				//   reparsed DOM so that fostering/normalization effects
				//   are reproduced.
				if ( $mode === 'wt2html' ) {
					// no-op
				} else {
					$doc = ContentUtils::createDocument( $test->cachedBODYstr );
				}
			} else {
				$doc = ContentUtils::createDocument( $test->cachedBODYstr );
			}
		}

		// Generate and make changes for the selser test mode
		$testManualChanges = $testOpts['parsoid']['changes'] ?? null;
		if ( $mode === 'selser' ) {
			if ( $testManualChanges && $test->changetree === [ 'manual' ] ) {
				$test->applyManualChanges( $doc );
			} else {
				$changetree = isset( $options['changetree'] ) ?
					json_decode( $options['changetree'] ) : $test->changetree;
				if ( !$changetree ) {
					$changetree = $test->generateChanges( $doc );
				}
				$dumpOpts = [
					'dom:post-changes' => $env->hasDumpFlag( 'dom:post-changes' ),
					'logger' => $env->getSiteConfig()->getLogger()
				];
				$test->applyChanges( $dumpOpts, $doc, $changetree );
			}
			// Save the modified DOM so we can re-test it later.
			// Always serialize to string and reparse before passing to selser/wt2wt.
			$test->changedHTMLStr = ContentUtils::toXML( DOMCompat::getBody( $doc ) );
			$doc = ContentUtils::createDocument( $test->changedHTMLStr );
		} elseif ( $mode === 'wt2wt' ) {
			// Handle a 'changes' option if present.
			if ( $testManualChanges ) {
				$test->applyManualChanges( $doc );
			}
		}

		// Roundtrip stage
		if ( $mode === 'wt2wt' || $mode === 'selser' ) {
			$wt = $this->convertHtml2Wt( $env, $test, $mode, $doc );
		} elseif ( $mode === 'html2html' ) {
			$doc = $this->convertWt2Html( $env, $test, $mode, $wt );
		}

		// Result verification stage
		if ( $endsAtHtml ) {
			$this->processParsedHTML( $test, $options, $mode, $doc );
		} else {
			$this->processSerializedWT( $env, $test, $options, $mode, $wt );
		}
	}

	/**
	 * Check the given HTML result against the expected result,
	 * and throw an exception if necessary.
	 *
	 * @param Test $test
	 * @param array $options
	 * @param string $mode
	 * @param Document $doc
	 */
	private function processParsedHTML(
		Test $test, array $options, string $mode, Document $doc
	): void {
		$test->time['end'] = microtime( true );
		$checkPassed = $this->checkHTML( $test, DOMCompat::getBody( $doc ), $options, $mode );

		// Only throw an error if --exit-unexpected was set and there was an error
		// Otherwise, continue running tests
		if ( $options['exit-unexpected'] && !$checkPassed ) {
			throw new UnexpectedException;
		}
	}

	/**
	 * Check the given wikitext result against the expected result,
	 * and throw an exception if necessary.
	 *
	 * @param Env $env
	 * @param Test $test
	 * @param array $options
	 * @param string $mode
	 * @param string $wikitext
	 */
	private function processSerializedWT(
		Env $env, Test $test, array $options, string $mode, string $wikitext
	): void {
		$test->time['end'] = microtime( true );

		if ( $mode === 'selser' && $options['selser'] !== 'noauto' ) {
			if ( $test->changetree === [ 5 ] ) {
				$test->resultWT = $test->wikitext;
			} else {
				$doc = ContentUtils::createDocument( $test->changedHTMLStr );
				$test->resultWT = $this->convertHtml2Wt( $env, $test, 'wt2wt', $doc );
			}
		}

		$checkPassed = $this->checkWikitext( $test, $wikitext, $options, $mode );

		// Only throw an error if --exit-unexpected was set and there was an error
		// Otherwise, continue running tests
		if ( $options['exit-unexpected'] && !$checkPassed ) {
			throw new UnexpectedException;
		}
	}

	/**
	 * @param Test $test
	 * @param Element $out
	 * @param array $options
	 * @param string $mode
	 * @return bool
	 */
	private function checkHTML(
		Test $test, Element $out, array $options, string $mode
	): bool {
		list( $normOut, $normExpected ) = $test->normalizeHTML( $out, $test->cachedNormalizedHTML );
		$expected = [ 'normal' => $normExpected, 'raw' => $test->parsoidHtml ];
		$actual = [
			'normal' => $normOut,
			'raw' => ContentUtils::toXML( $out, [ 'innerXML' => true ] ),
			'input' => ( $mode === 'html2html' ) ? $test->parsoidHtml : $test->wikitext
		];

		return $options['reportResult'](
			$this->stats, $test, $options, $mode, $expected, $actual
		);
	}

	/**
	 * @param Test $test
	 * @param string $out
	 * @param array $options
	 * @param string $mode
	 * @return bool
	 */
	private function checkWikitext(
		Test $test, string $out, array $options, string $mode
	): bool {
		if ( $mode === 'html2wt' ) {
			$input = $test->parsoidHtml;
			$testWikitext = $test->wikitext;
		} elseif ( $mode === 'wt2wt' ) {
			if ( isset( $test->options['parsoid']['changes'] ) ) {
				$input = $test->wikitext;
				$testWikitext = $test->sections['wikitext/edited'];
			} else {
				$input = $testWikitext = $test->wikitext;
			}
		} else { /* selser */
			if ( $test->changetree === [ 5 ] ) { /* selser with oracle */
				$input = $test->changedHTMLStr;
				$testWikitext = $test->wikitext;
				$out = preg_replace( '/<!--' . Test::STATIC_RANDOM_STRING . '-->/', '', $out );
			} elseif ( $test->changetree === [ 'manual' ] &&
				isset( $test->options['parsoid']['changes'] )
			) { /* manual changes */
				$input = $test->wikitext;
				$testWikitext = $test->sections['wikitext/edited'];
			} else { /* automated selser changes, no oracle */
				$input = $test->changedHTMLStr;
				$testWikitext = $test->resultWT;
			}
		}

		list( $normalizedOut, $normalizedExpected ) = $test->normalizeWT( $out, $testWikitext );

		$expected = [ 'normal' => $normalizedExpected, 'raw' => $testWikitext ];
		$actual = [ 'normal' => $normalizedOut, 'raw' => $out, 'input' => $input ];

		return $options['reportResult'](
			$this->stats, $test, $options, $mode, $expected, $actual );
	}

	/**
	 * @param array $options
	 * @return array
	 */
	private function updateKnownFailures( array $options ): array {
		// Check in case any tests were removed but we didn't update
		// the knownFailures
		$knownFailuresChanged = false;
		$allModes = $options['wt2html'] && $options['wt2wt'] &&
			$options['html2wt'] && $options['html2html'] &&
			isset( $options['selser'] ) && !(
				isset( $options['filter'] ) ||
				isset( $options['regex'] ) ||
				isset( $options['maxtests'] )
			);
		$offsetType = $options['offsetType'] ?? 'byte';

		// Update knownFailures, if requested
		if ( $allModes ||
			ScriptUtils::booleanOption( $options['updateKnownFailures'] ?? null )
		) {
			if ( $this->knownFailuresPath !== null ) {
				$old = file_get_contents( $this->knownFailuresPath );
			} else {
				// If file doesn't exist, use the JSON representation of an
				// empty array, so it compares equal in the case that we
				// end up with an empty array of known failures below.
				$old = '[]';
			}
			$testKnownFailures = [];
			foreach ( $options['modes'] as $mode ) {
				foreach ( $this->stats->modes[$mode]->failList as $fail ) {
					if ( !isset( $testKnownFailures[$fail['testName']] ) ) {
						$testKnownFailures[$fail['testName']] = [];
					}
					$testKnownFailures[$fail['testName']][$mode . $fail['suffix']] = $fail['raw'];
				}
			}
			// Sort, otherwise, titles get added above based on the first
			// failing mode, which can make diffs harder to verify when
			// failing modes change.
			ksort( $testKnownFailures );
			$contents = json_encode(
				$testKnownFailures,
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES |
				JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE
			) . "\n";
			if ( ScriptUtils::booleanOption( $options['updateKnownFailures'] ?? null ) ) {
				file_put_contents( $this->knownFailuresPath, $contents );
			} elseif ( $allModes && $offsetType === 'byte' ) {
				$knownFailuresChanged = $contents !== $old;
			}
		}

		// Write updated tests from failed ones
		if ( isset( $options['update-tests'] ) ||
			 ScriptUtils::booleanOption( $options['update-unexpected'] ?? null )
		) {
			$updateFormat = $options['update-tests'] === 'raw' ? 'raw' : 'actualNormalized';
			$fileContent = file_get_contents( $this->testFilePath );
			foreach ( $this->stats->modes['wt2html']->failList as $fail ) {
				if ( isset( $options['update-tests'] ) || $fail['unexpected'] ) {
					$exp = '/(!!\s*test\s*' .
						 preg_quote( $fail['testName'], '/' ) .
						 '(?:(?!!!\s*end)[\s\S])*' .
						 ')(' . preg_quote( $fail['expected'], '/' ) .
						 ')/m';
					$fileContent = preg_replace_callback(
						$exp,
						static function ( array $matches ) use ( $fail, $updateFormat ) {
							return $matches[1] . $fail[$updateFormat];
						},
						$fileContent
					);
				}
			}
			file_put_contents( $this->testFilePath, $fileContent );
		}

		// print out the summary
		$options['reportSummary'](
			$options['modes'], $this->stats, $this->testFileName,
			$this->testFilter, $knownFailuresChanged, $options
		);

		// we're done!
		// exit status 1 == uncaught exception
		$failures = $this->stats->allFailures();
		$exitCode = ( $failures > 0 || $knownFailuresChanged ) ? 2 : 0;
		if ( ScriptUtils::booleanOption( $options['exit-zero'] ?? null ) ) {
			$exitCode = 0;
		}

		return [
			'exitCode' => $exitCode,
			'stats' => $this->stats,
			'file' => $this->testFileName,
			'knownFailuresChanged' => $knownFailuresChanged
		];
	}

	/**
	 * Run the test in all requested modes.
	 *
	 * @param Test $test
	 * @param array $options
	 */
	private function processTest( Test $test, array $options ): void {
		if ( !$test->options ) {
			$test->options = [];
		}

		$testOpts = $test->options;

		// ensure that test is not skipped if it has a wikitext/edited or
		// html/parsoid+langconv section (but not a parsoid html section)
		$haveHtml = ( $test->parsoidHtml !== null ) ||
			isset( $test->sections['wikitext/edited'] ) ||
			isset( $test->sections['html/parsoid+standalone'] ) ||
			isset( $test->sections['html/parsoid+langconv'] );
		$hasHtmlParsoid =
			isset( $test->sections['html/parsoid'] ) ||
			isset( $test->sections['html/parsoid+standalone'] );

		// Skip test whose title does not match --filter
		// or which is disabled or php-only
		if ( $test->wikitext === null ||
			!$haveHtml ||
			( isset( $testOpts['disabled'] ) && !$this->runDisabled ) ||
			( isset( $testOpts['php'] ) && !(
				$hasHtmlParsoid || $this->runPHP )
			) ||
			!$test->matchesFilter( $this->testFilter )
		) {
			return;
		}

		$suppressErrors = !empty( $testOpts['parsoid']['suppressErrors'] );
		$this->siteConfig->setLogger( $suppressErrors ?
			$this->siteConfig->suppressLogger : $this->defaultLogger );

		$targetModes = $test->computeTestModes( $options['modes'] );
		if ( !count( $targetModes ) ) {
			return;
		}

		// Honor language option
		$prefix = $testOpts['language'] ?? 'enwiki';
		if ( !str_contains( $prefix, 'wiki' ) ) {
			// Convert to our enwiki.. format
			$prefix .= 'wiki';
		}

		// Switch to requested wiki
		$this->mockApi->setApiPrefix( $prefix );
		$this->siteConfig->reset();

		// We don't do any sanity checking or type casting on $test->config
		// values here: if you set a bogus value in a parser test it *should*
		// blow things up, so that you fix your test case.

		// Update $wgInterwikiMagic flag
		// default (undefined) setting is true
		$this->siteConfig->setInterwikiMagic(
			$test->config['wgInterwikiMagic'] ?? true
		);

		// FIXME: Cite-specific hack
		$this->siteConfig->responsiveReferences = [
			'enabled' => $test->config['wgCiteResponsiveReferences'] ??
				$this->siteConfig->responsiveReferences['enabled'],
			'threshold' => $test->config['wgCiteResponsiveReferencesThreshold'] ??
				$this->siteConfig->responsiveReferences['threshold'],
		];

		if ( $testOpts ) {
			Assert::invariant( !isset( $testOpts['extensions'] ),
				'Cannot configure extensions in tests' );

			$this->siteConfig->disableSubpagesForNS( 0 );
			if ( isset( $testOpts['subpage'] ) ) {
				$this->siteConfig->enableSubpagesForNS( 0 );
			}

			$allowedPrefixes = [ '' ]; // all allowed
			if ( isset( $testOpts['wgallowexternalimages'] ) &&
				!preg_match( '/^(1|true|)$/D', $testOpts['wgallowexternalimages'] )
			) {
				$allowedPrefixes = [];
			}
			$this->siteConfig->allowedExternalImagePrefixes = $allowedPrefixes;

			// Process test-specific options
			$defaults = [ 'wrapSections' => false ]; // override for parser tests
			foreach ( $defaults as $opt => $defaultVal ) {
				$this->envOptions[$opt] = $testOpts['parsoid'][$opt] ?? $defaultVal;
			}

			// Emulate PHP parser's tag hook to tunnel content past the sanitizer
			if ( isset( $testOpts['styletag'] ) ) {
				$this->siteConfig->registerParserTestExtension( new StyleTag() );
			}

			if ( ( $testOpts['wgrawhtml'] ?? null ) === '1' ) {
				$this->siteConfig->registerParserTestExtension( new RawHTML() );
			}

			if ( isset( $testOpts['thumbsize'] ) ) {
				$this->siteConfig->thumbsize = (int)$testOpts['thumbsize'];
			}
			if ( isset( $testOpts['annotations'] ) ) {
				$this->siteConfig->registerParserTestExtension( new DummyAnnotation() );
			}
			if ( isset( $testOpts['i18next'] ) ) {
				$this->siteConfig->registerParserTestExtension( new I18nTag() );
			}
			if ( isset( $testOpts['check-referrer'] ) ) {
				$this->siteConfig->setExternalLinkTarget( $testOpts['check-referrer'] );
			}
		}

		// Ensure ParserHook is always registered!
		$this->siteConfig->registerParserTestExtension( new ParserHook() );

		$runner = $this;
		$test->testAllModes( $targetModes, $options, Closure::fromCallable( [ $this, 'runTest' ] ) );
	}

	/**
	 * Run parser tests for the file with the provided options
	 *
	 * @param array $options
	 * @return array
	 */
	public function run( array $options ): array {
		$this->runDisabled = ScriptUtils::booleanOption( $options['run-disabled'] ?? null );
		$this->runPHP = ScriptUtils::booleanOption( $options['run-php'] ?? null );
		$this->offsetType = $options['offsetType'] ?? 'byte';

		// Test case filtering
		$this->testFilter = null;
		if ( isset( $options['filter'] ) || isset( $options['regex'] ) ) {
			$this->testFilter = [
				'raw' => $options['regex'] ?? $options['filter'],
				'regex' => isset( $options['regex'] ),
				'string' => isset( $options['filter'] )
			];
		}

		$this->buildTests( $options );

		// Trim test cases to the desired amount
		if ( isset( $options['maxtests'] ) ) {
			$n = $options['maxtests'];
			if ( $n > 0 ) {
				$this->testCases = array_slice( $this->testCases, 0, $n );
			}
		}

		$this->envOptions = [
			'wrapSections' => false,
			'nativeTemplateExpansion' => true,
			'offsetType' => $this->offsetType,
		];
		ScriptUtils::setDebuggingFlags( $this->envOptions, $options );
		ScriptUtils::setTemplatingAndProcessingFlags( $this->envOptions, $options );

		if (
			ScriptUtils::booleanOption( $options['quiet'] ?? null ) ||
			ScriptUtils::booleanOption( $options['quieter'] ?? null )
		) {
			$this->envOptions['logLevels'] = [ 'fatal', 'error' ];
		}

		// Save default logger so we can be reset it after temporarily
		// switching to the suppressLogger to suppress expected error messages.
		$this->defaultLogger = $this->siteConfig->getLogger();

		/**
		 * PORT-FIXME(T238722)
		 * // Enable sampling to assert it's working while testing.
		 * $parsoidConfig->loggerSampling = [ [ '/^warn(\/|$)/', 100 ] ];
		 *
		 * // Override env's `setLogger` to record if we see `fatal` or `error`
		 * // while running parser tests.  (Keep it clean, folks!  Use
		 * // "suppressError" option on the test if error is expected.)
		 * $env->setLogger = ( ( function ( $parserTests, $superSetLogger ) {
		 * return function ( $_logger ) use ( &$parserTests ) {
		 * call_user_func( 'superSetLogger', $_logger );
		 * $this->log = function ( $level ) use ( &$_logger, &$parserTests ) {
		 * if ( $_logger !== $parserTests->suppressLogger &&
		 * preg_match( '/^(fatal|error)\b/', $level )
		 * ) {
		 * $parserTests->stats->loggedErrorCount++;
		 * }
		 * return call_user_func_array( [ $_logger, 'log' ], $arguments );
		 * };
		 * };
		 * } ) );
		 */

		$options['reportStart']();

		// Run tests
		foreach ( $this->testCases as $test ) {
			try {
				$this->processTest( $test, $options );
			} catch ( UnexpectedException $e ) {
				// Exit unexpected
				break;
			}
		}

		// Update knownFailures
		return $this->updateKnownFailures( $options );
	}
}
