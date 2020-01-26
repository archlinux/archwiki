<?php

/**
 * Trait that helps LuaEngineTestBase and LuaEngineUnitTestBase
 */
trait Scribunto_LuaEngineTestHelper {
	private static $engineConfigurations = [
		'LuaSandbox' => [
			'memoryLimit' => 50000000,
			'cpuLimit' => 30,
			'allowEnvFuncs' => true,
			'maxLangCacheSize' => 30,
		],
		'LuaStandalone' => [
			'errorFile' => null,
			'luaPath' => null,
			'memoryLimit' => 50000000,
			'cpuLimit' => 30,
			'allowEnvFuncs' => true,
			'maxLangCacheSize' => 30,
		],
	];

	protected static function makeSuite( $className, $group = null ) {
		$suite = new PHPUnit_Framework_TestSuite;
		$suite->setName( $className );

		$class = new ReflectionClass( $className );

		foreach ( self::$engineConfigurations as $engineName => $opts ) {
			if ( $group !== null && $group !== $engineName ) {
				continue;
			}

			try {
				$parser = new Parser;
				$parser->startExternalParse( Title::newMainPage(), new ParserOptions, Parser::OT_HTML, true );
				$engineClass = "Scribunto_{$engineName}Engine";
				$engine = new $engineClass(
					self::$engineConfigurations[$engineName] + [ 'parser' => $parser ]
				);
				$parser->scribunto_engine = $engine;
				$engine->setTitle( $parser->getTitle() );
				$engine->getInterpreter();
			} catch ( Scribunto_LuaInterpreterNotFoundError $e ) {
				$suite->addTest(
					new Scribunto_LuaEngineTestSkip(
						$className, "interpreter for $engineName is not available"
					), [ 'Lua', $engineName ]
				);
				continue;
			}

			// Work around PHPUnit breakage: the only straightforward way to
			// get the data provider is to call
			// PHPUnit_Util_Test::getProvidedData, but that instantiates the
			// class without passing any parameters to the constructor. But we
			// *need* that engine name.
			self::$staticEngineName = $engineName;

			$engineSuite = new PHPUnit_Framework_TestSuite;
			$engineSuite->setName( "$engineName: $className" );

			foreach ( $class->getMethods() as $method ) {
				if ( PHPUnit_Framework_TestSuite::isTestMethod( $method ) && $method->isPublic() ) {
					$name = $method->getName();
					$groups = PHPUnit_Util_Test::getGroups( $className, $name );
					$groups[] = 'Lua';
					$groups[] = $engineName;
					$groups = array_unique( $groups );

					$data = PHPUnit_Util_Test::getProvidedData( $className, $name );
					if ( is_array( $data ) || $data instanceof Iterator ) {
						// with @dataProvider
						$dataSuite = new PHPUnit_Framework_TestSuite_DataProvider(
							$className . '::' . $name
						);
						foreach ( $data as $k => $v ) {
							$dataSuite->addTest(
								new $className( $name, $v, $k, $engineName ),
								$groups
							);
						}
						$engineSuite->addTest( $dataSuite );
					} elseif ( $data === false ) {
						// invalid @dataProvider
						$engineSuite->addTest( new PHPUnit_Framework_Warning(
							"The data provider specified for {$className}::$name is invalid."
						) );
					} else {
						// no @dataProvider
						$engineSuite->addTest(
							new $className( $name, [], '', $engineName ),
							$groups
						);
					}
				}
			}

			$suite->addTest( $engineSuite );
		}

		return $suite;
	}

	/**
	 * @return ScribuntoEngineBase
	 */
	protected function getEngine() {
		if ( !$this->engine ) {
			$parser = new Parser;
			$options = new ParserOptions;
			$options->setTemplateCallback( [ $this, 'templateCallback' ] );
			$parser->startExternalParse( $this->getTestTitle(), $options, Parser::OT_HTML, true );
			$class = "Scribunto_{$this->engineName}Engine";
			$this->engine = new $class(
				self::$engineConfigurations[$this->engineName] + [ 'parser' => $parser ]
			);
			$parser->scribunto_engine = $this->engine;
			$this->engine->setTitle( $parser->getTitle() );
		}
		return $this->engine;
	}

	public function templateCallback( $title, $parser ) {
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

}
