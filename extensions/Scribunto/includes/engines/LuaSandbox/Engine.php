<?php

class Scribunto_LuaSandboxEngine extends Scribunto_LuaEngine {
	public $options, $loaded = false;
	protected $lineCache = [];

	/**
	 * @var Scribunto_LuaSandboxInterpreter
	 */
	protected $interpreter;

	public function getPerformanceCharacteristics() {
		return [
			'phpCallsRequireSerialization' => false,
		];
	}

	public function getSoftwareInfo( array &$software ) {
		try {
			Scribunto_LuaSandboxInterpreter::checkLuaSandboxVersion();
		} catch ( Scribunto_LuaInterpreterNotFoundError $e ) {
			// They shouldn't be using this engine if the extension isn't
			// loaded. But in case they do for some reason, let's not have
			// Special:Version fatal.
			return;
		} catch ( Scribunto_LuaInterpreterBadVersionError $e ) {
			// Same for if the extension is too old.
			return;
		}

		$versions = LuaSandbox::getVersionInfo();
		$software['[https://www.mediawiki.org/wiki/LuaSandbox LuaSandbox]'] =
			$versions['LuaSandbox'];
		$software['[http://www.lua.org/ Lua]'] = str_replace( 'Lua ', '', $versions['Lua'] );
		if ( isset( $versions['LuaJIT'] ) ) {
			$software['[http://luajit.org/ LuaJIT]'] = str_replace( 'LuaJIT ', '', $versions['LuaJIT'] );
		}
	}

	public function getResourceUsage( $resource ) {
		$this->load();
		switch ( $resource ) {
		case self::MEM_PEAK_BYTES:
			return $this->interpreter->getPeakMemoryUsage();
		case self::CPU_SECONDS:
			return $this->interpreter->getCPUUsage();
		default:
			return false;
		}
	}

	private function getLimitReportData() {
		$ret = [];
		$this->load();

		$t = $this->interpreter->getCPUUsage();
		$ret['scribunto-limitreport-timeusage'] = [
			sprintf( "%.3f", $t ),
			sprintf( "%.3f", $this->options['cpuLimit'] )
		];
		$ret['scribunto-limitreport-memusage'] = [
			$this->interpreter->getPeakMemoryUsage(),
			$this->options['memoryLimit'],
		];

		$logs = $this->getLogBuffer();
		if ( $logs !== '' ) {
			$ret['scribunto-limitreport-logs'] = $logs;
		}

		if ( $t < 1.0 ) {
			return $ret;
		}

		$percentProfile = $this->interpreter->getProfilerFunctionReport(
			Scribunto_LuaSandboxInterpreter::PERCENT
		);
		if ( !count( $percentProfile ) ) {
			return $ret;
		}
		$timeProfile = $this->interpreter->getProfilerFunctionReport(
			Scribunto_LuaSandboxInterpreter::SECONDS
		);

		$lines = [];
		$cumulativePercent = 0;
		$num = $otherTime = $otherPercent = 0;
		foreach ( $percentProfile as $name => $percent ) {
			$time = $timeProfile[$name] * 1000;
			$num++;
			if ( $cumulativePercent <= 99 && $num <= 10 ) {
				// Map some regularly appearing internal names
				if ( preg_match( '/^<mw.lua:(\d+)>$/', $name, $m ) ) {
					$line = $this->getMwLuaLine( $m[1] );
					if ( preg_match( '/^\s*(local\s+)?function ([a-zA-Z0-9_.]*)/', $line, $m ) ) {
						$name = $m[2] . ' ' . $name;
					}
				}
				$lines[] = [ $name, sprintf( '%.0f', $time ), sprintf( '%.1f', $percent ) ];
			} else {
				$otherTime += $time;
				$otherPercent += $percent;
			}
			$cumulativePercent += $percent;
		}
		if ( $otherTime ) {
			$lines[] = [ '[others]', sprintf( '%.0f', $otherTime ), sprintf( '%.1f', $otherPercent ) ];
		}
		$ret['scribunto-limitreport-profile'] = $lines;
		return $ret;
	}

	public function reportLimitData( ParserOutput $output ) {
		$data = $this->getLimitReportData();
		foreach ( $data as $k => $v ) {
			$output->setLimitReportData( $k, $v );
		}
		if ( isset( $data['scribunto-limitreport-logs'] ) ) {
			$output->addModules( 'ext.scribunto.logs' );
		}
	}

	public function formatLimitData( $key, &$value, &$report, $isHTML, $localize ) {
		global $wgLang;
		$lang = $localize ? $wgLang : Language::factory( 'en' );
		switch ( $key ) {
			case 'scribunto-limitreport-logs':
				if ( $isHTML ) {
					$report .= $this->formatHtmlLogs( $value, $localize );
				}
				return false;

			case 'scribunto-limitreport-memusage':
				$value = array_map( [ $lang, 'formatSize' ], $value );
				break;
		}

		if ( $key !== 'scribunto-limitreport-profile' ) {
			return true;
		}

		$keyMsg = wfMessage( 'scribunto-limitreport-profile' );
		$msMsg = wfMessage( 'scribunto-limitreport-profile-ms' );
		$percentMsg = wfMessage( 'scribunto-limitreport-profile-percent' );
		if ( !$localize ) {
			$keyMsg->inLanguage( 'en' )->useDatabase( false );
			$msMsg->inLanguage( 'en' )->useDatabase( false );
			$percentMsg->inLanguage( 'en' )->useDatabase( false );
		}

		// To avoid having to do actual work in Message::fetchMessage for each
		// line in the loops below, call ->exists() here to populate ->message.
		$msMsg->exists();
		$percentMsg->exists();

		if ( $isHTML ) {
			$report .= Html::openElement( 'tr' ) .
				Html::rawElement( 'th', [ 'colspan' => 2 ], $keyMsg->parse() ) .
				Html::closeElement( 'tr' ) .
				Html::openElement( 'tr' ) .
				Html::openElement( 'td', [ 'colspan' => 2 ] ) .
				Html::openElement( 'table' );
			foreach ( $value as $line ) {
				$name = $line[0];
				$location = '';
				if ( preg_match( '/^(.*?) *<([^<>]+):(\d+)>$/', $name, $m ) ) {
					$name = $m[1];
					$title = Title::newFromText( $m[2] );
					if ( $title && $title->hasContentModel( CONTENT_MODEL_SCRIBUNTO ) ) {
						$location = '&lt;' . Linker::link( $title ) . ":{$m[3]}&gt;";
					} else {
						$location = htmlspecialchars( "<{$m[2]}:{$m[3]}>" );
					}
				}
				$ms = clone $msMsg;
				$ms->params( $line[1] );
				$pct = clone $percentMsg;
				$pct->params( $line[2] );
				$report .= Html::openElement( 'tr' ) .
					Html::element( 'td', null, $name ) .
					Html::rawElement( 'td', null, $location ) .
					Html::rawElement( 'td', [ 'align' => 'right' ], $ms->parse() ) .
					Html::rawElement( 'td', [ 'align' => 'right' ], $pct->parse() ) .
					Html::closeElement( 'tr' );
			}
			$report .= Html::closeElement( 'table' ) .
				Html::closeElement( 'td' ) .
				Html::closeElement( 'tr' );
		} else {
			$report .= $keyMsg->text() . ":\n";
			foreach ( $value as $line ) {
				$ms = clone $msMsg;
				$ms->params( $line[1] );
				$pct = clone $percentMsg;
				$pct->params( $line[2] );
				$report .= sprintf( "    %-59s %11s %11s\n", $line[0], $ms->text(), $pct->text() );
			}
		}

		return false;
	}

	protected function getMwLuaLine( $lineNum ) {
		if ( !isset( $this->lineCache['mw.lua'] ) ) {
			$this->lineCache['mw.lua'] = file( $this->getLuaLibDir() . '/mw.lua' );
		}
		return $this->lineCache['mw.lua'][$lineNum - 1];
	}

	protected function newInterpreter() {
		return new Scribunto_LuaSandboxInterpreter( $this, $this->options );
	}
}

class Scribunto_LuaSandboxInterpreter extends Scribunto_LuaInterpreter {
	/**
	 * @var Scribunto_LuaEngine
	 */
	public $engine;

	/**
	 * @var LuaSandbox
	 */
	public $sandbox;

	/**
	 * @var bool
	 */
	public $profilerEnabled;

	const SAMPLES = 0;
	const SECONDS = 1;
	const PERCENT = 2;

	/**
	 * Check that php-luasandbox is available and of a recent-enough version
	 * @throws Scribunto_LuaInterpreterNotFoundError
	 * @throws Scribunto_LuaInterpreterBadVersionError
	 */
	public static function checkLuaSandboxVersion() {
		if ( !extension_loaded( 'luasandbox' ) ) {
			throw new Scribunto_LuaInterpreterNotFoundError(
				'The luasandbox extension is not present, this engine cannot be used.' );
		}

		if ( !is_callable( 'LuaSandbox::getVersionInfo' ) ) {
			throw new Scribunto_LuaInterpreterBadVersionError(
				'The luasandbox extension is too old (version 1.6+ is required), ' .
					'this engine cannot be used.'
			);
		}
	}

	public function __construct( $engine, array $options ) {
		self::checkLuaSandboxVersion();

		$this->engine = $engine;
		$this->sandbox = new LuaSandbox;
		$this->sandbox->setMemoryLimit( $options['memoryLimit'] );
		$this->sandbox->setCPULimit( $options['cpuLimit'] );
		if ( !isset( $options['profilerPeriod'] ) ) {
			$options['profilerPeriod'] = 0.02;
		}
		if ( $options['profilerPeriod'] ) {
			$this->profilerEnabled = true;
			$this->sandbox->enableProfiler( $options['profilerPeriod'] );
		}
	}

	protected function convertSandboxError( LuaSandboxError $e ) {
		$opts = [];
		if ( isset( $e->luaTrace ) ) {
			$opts['trace'] = $e->luaTrace;
		}
		$message = $e->getMessage();
		if ( preg_match( '/^(.*?):(\d+): (.*)$/', $message, $m ) ) {
			$opts['module'] = $m[1];
			$opts['line'] = $m[2];
			$message = $m[3];
		}
		return $this->engine->newLuaError( $message, $opts );
	}

	/**
	 * @param string $text
	 * @param string $chunkName
	 * @return mixed
	 * @throws Scribunto_LuaError
	 */
	public function loadString( $text, $chunkName ) {
		try {
			return $this->sandbox->loadString( $text, $chunkName );
		} catch ( LuaSandboxError $e ) {
			throw $this->convertSandboxError( $e );
		}
	}

	public function registerLibrary( $name, array $functions ) {
		$realLibrary = [];
		foreach ( $functions as $funcName => $callback ) {
			$realLibrary[$funcName] = [
				new Scribunto_LuaSandboxCallback( $callback ),
				$funcName ];
		}
		$this->sandbox->registerLibrary( $name, $realLibrary );

		# TODO: replace this with
		# $this->sandbox->registerVirtualLibrary(
		# 	$name, [ $this, 'callback' ], $functions );
	}

	public function callFunction( $func, ...$args ) {
		try {
			$ret = $func->call( ...$args );
			if ( $ret === false ) {
				// Per the documentation on LuaSandboxFunction::call, a return value
				// of false means that something went wrong and it's PHP's fault,
				// so throw a "real" exception.
				throw new MWException(
					__METHOD__ . ': LuaSandboxFunction::call returned false' );
			}
			return $ret;
		} catch ( LuaSandboxTimeoutError $e ) {
			throw $this->engine->newException( 'scribunto-common-timeout' );
		} catch ( LuaSandboxError $e ) {
			throw $this->convertSandboxError( $e );
		}
	}

	public function wrapPhpFunction( $callable ) {
		return $this->sandbox->wrapPhpFunction( $callable );
	}

	public function isLuaFunction( $object ) {
		return $object instanceof LuaSandboxFunction;
	}

	public function getPeakMemoryUsage() {
		return $this->sandbox->getPeakMemoryUsage();
	}

	public function getCPUUsage() {
		return $this->sandbox->getCPUUsage();
	}

	public function getProfilerFunctionReport( $units ) {
		if ( $this->profilerEnabled ) {
			static $unitsMap;
			if ( !$unitsMap ) {
				$unitsMap = [
					self::SAMPLES => LuaSandbox::SAMPLES,
					self::SECONDS => LuaSandbox::SECONDS,
					self::PERCENT => LuaSandbox::PERCENT,
				];
			}
			return $this->sandbox->getProfilerFunctionReport( $unitsMap[$units] );
		} else {
			return [];
		}
	}

	public function pauseUsageTimer() {
		$this->sandbox->pauseUsageTimer();
	}

	public function unpauseUsageTimer() {
		$this->sandbox->unpauseUsageTimer();
	}
}

class Scribunto_LuaSandboxCallback {

	/**
	 * @var callable
	 */
	protected $callback;

	public function __construct( $callback ) {
		$this->callback = $callback;
	}

	/**
	 * We use __call with a variable function name so that LuaSandbox will be
	 * able to return a meaningful function name in profiling data.
	 * @param string $funcName
	 * @param array $args
	 * @return mixed
	 */
	public function __call( $funcName, $args ) {
		try {
			return ( $this->callback )( ...$args );
		} catch ( Scribunto_LuaError $e ) {
			throw new LuaSandboxRuntimeError( $e->getLuaMessage() );
		}
	}
}
