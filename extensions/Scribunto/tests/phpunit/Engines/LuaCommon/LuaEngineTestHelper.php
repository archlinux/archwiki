<?php

namespace MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon;

use MediaWiki\Extension\Scribunto\Engines\LuaSandbox\LuaSandboxEngine;
use MediaWiki\Extension\Scribunto\Engines\LuaStandalone\LuaStandaloneEngine;
use MediaWiki\Extension\Scribunto\ScribuntoEngineBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\CoreMagicVariables;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestSuite;
use Wikimedia\TestingAccessWrapper;

/**
 * Trait that helps LuaEngineTestBase and LuaEngineUnitTestBase
 */
trait LuaEngineTestHelper {
	/** @var array[] */
	private static $engineConfigurations = [
		'LuaSandbox' => [
			'class' => LuaSandboxEngine::class,
			'memoryLimit' => 50000000,
			'cpuLimit' => 30,
			'allowEnvFuncs' => true,
			'maxLangCacheSize' => 30,
			'shareInvocationEnv' => false,
		],
		'LuaStandalone' => [
			'class' => LuaStandaloneEngine::class,
			'errorFile' => null,
			'luaPath' => null,
			'memoryLimit' => 50000000,
			'cpuLimit' => 30,
			'allowEnvFuncs' => true,
			'maxLangCacheSize' => 30,
			'shareInvocationEnv' => false,
		],
	];
	/** @var int[] */
	protected $templateLoadCounts = [];
	/** @var array */
	protected $extraModules = [];

	/**
	 * Create a PHPUnit test suite to run the test against all engines.
	 *
	 * @deprecated since 1.46. Override getEngineName() in separate subclasses instead.
	 * @param string $className Test class name
	 * @param string|null $group Engine to run with, or null to run all engines
	 * @return TestSuite
	 */
	protected static function makeSuite( $className, $group = null ) {
		$suite = new TestSuite();
		$suite->setName( $className );
		return $suite;
	}

	/**
	 * @return ScribuntoEngineBase
	 */
	protected function getEngine() {
		if ( !$this->engine ) {
			$engineName = $this->getEngineName();
			$services = MediaWikiServices::getInstance();
			$parser = $services->getParserFactory()->create();
			$options = ParserOptions::newFromAnon();
			$options->setTemplateCallback( [ $this, 'templateCallback' ] );
			$options->setTargetLanguage( $services->getLanguageFactory()->getLanguage( 'en' ) );
			$parser->startExternalParse( $this->getTestTitle(), $options, Parser::OT_HTML, true );

			if ( $engineName === 'LuaSandbox' ) {
				$class = LuaSandboxEngine::class;
			} elseif ( $engineName === 'LuaStandalone' ) {
				$class = LuaStandaloneEngine::class;
			}

			$this->engine = new $class(
				self::$engineConfigurations[$engineName] + [ 'parser' => $parser, 'title' => $parser->getTitle() ]
			);
		}
		return $this->engine;
	}

	/**
	 * @see Parser::statelessFetchTemplate
	 * @param Title $title
	 * @param Parser|false $parser
	 * @return array
	 */
	public function templateCallback( $title, $parser ) {
		$this->templateLoadCounts[$title->getFullText()] =
			( $this->templateLoadCounts[$title->getFullText()] ?? 0 ) + 1;
		if ( isset( $this->extraModules[$title->getFullText()] ) ) {
			return [
				'text' => $this->extraModules[$title->getFullText()],
				'finalTitle' => $title,
				'deps' => []
			];
		}

		$modules = $this->getTestModules();
		foreach ( $modules as $name => $fileName ) {
			$modTitle = Title::makeTitle( NS_MODULE, $name );
			if ( $modTitle->equals( $title ) ) {
				return [
					'text' => file_get_contents( $fileName ),
					'finalTitle' => $title,
					'deps' => []
				];
			}
		}
		return Parser::statelessFetchTemplate( $title, $parser );
	}

	/**
	 * Get the title used for unit tests
	 *
	 * @return Title
	 */
	protected function getTestTitle() {
		return Title::newMainPage();
	}

	/**
	 * Reset the cached engine. The next call to getEngine() will return a new
	 * object.
	 */
	protected function resetEngine() {
		$this->engine = null;
	}

	public function assertTtl( ?int $ttl, ParserOutput $parserOutput, string $msg ) {
		if ( $ttl === null ) {
			$this->assertFalse( $parserOutput->hasReducedExpiry(), $msg );
			return;
		}
		// TTLs have some stagger, so verify that the cache expirty from the
		// ParserOutput is within the expected range.
		$fudge = TestingAccessWrapper::constant(
			CoreMagicVariables::class, 'DEADLINE_TTL_CLOCK_FUDGE'
		);
		$stagger = TestingAccessWrapper::constant(
			CoreMagicVariables::class, 'DEADLINE_TTL_STAGGER_MAX'
		);
		$min = TestingAccessWrapper::constant(
			CoreMagicVariables::class, 'MIN_DEADLINE_TTL'
		);
		$actual = $parserOutput->getCacheExpiry();
		$this->assertLessThan( max( $ttl, $min ) + $fudge + $stagger, $actual, $msg );
		$this->assertGreaterThanOrEqual( max( $ttl, $min ) + $fudge, $actual, $msg );
	}

	public function assertTtlLessThan( int $ttl, ParserOutput $parserOutput, string $msg ) {
		// TTLs have some stagger, so verify that the cache expirty from the
		// ParserOutput is within the expected range.
		$fudge = TestingAccessWrapper::constant(
			CoreMagicVariables::class, 'DEADLINE_TTL_CLOCK_FUDGE'
		);
		$stagger = TestingAccessWrapper::constant(
			CoreMagicVariables::class, 'DEADLINE_TTL_STAGGER_MAX'
		);
		$min = TestingAccessWrapper::constant(
			CoreMagicVariables::class, 'MIN_DEADLINE_TTL'
		);
		$actual = $parserOutput->getCacheExpiry();
		$this->assertLessThan( max( $ttl, $min ) + $fudge + $stagger, $actual, $msg );
	}

	public function assertSameCacheExpiry(
		ParserOutput $a,
		ParserOutput $b,
		string $msg,
		int $maxDelta = 10
	) {
		$this->assertSame( $a->hasReducedExpiry(), $b->hasReducedExpiry(), $msg );
		if ( $a->hasReducedExpiry() ) {
			$this->assertLessThanOrEqual(
				$maxDelta,
				abs( $a->getCacheExpiry() - $b->getCacheExpiry() ),
				$msg
			);
		}
	}
}
