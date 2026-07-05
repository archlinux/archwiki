<?php

namespace MediaWiki\Extension\Scribunto\Engines\LuaCommon;

use DateTime;
use DateTimeZone;
use Exception;
use MediaWiki\Cache\GenderCache;
use MediaWiki\Config\Config;
use MediaWiki\Language\Language;
use MediaWiki\Language\LanguageCode;
use MediaWiki\Language\LanguageFactory;
use MediaWiki\Language\LanguageFallback;
use MediaWiki\Language\LanguageNameUtils;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\CoreMagicVariables;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
use MediaWiki\Utils\MWTimestamp;
use Wikimedia\RequestTimeout\TimeoutException;

class LanguageLibrary extends LibraryBase {

	private const FALLBACK_MESSAGES = 'FALLBACK_MESSAGES';
	private const FALLBACK_STRICT = 'FALLBACK_STRICT';
	private const FALLBACK_MAP = [
		self::FALLBACK_MESSAGES => LanguageFallback::MESSAGES,
		self::FALLBACK_STRICT => LanguageFallback::STRICT,
	];

	private readonly ?string $localTimeZone;

	/** @var Language[] */
	public array $langCache = [];
	/** @var array[] */
	public array $timeCache = [];
	/** @var int */
	public $maxLangCacheSize;

	public function __construct(
		LuaEngine $engine,
		private readonly Config $mainConfig,
		private readonly GenderCache $genderCache,
		private readonly Language $contentLanguage,
		private readonly LanguageFactory $languageFactory,
		private readonly LanguageFallback $languageFallback,
		private readonly LanguageNameUtils $languageNameUtils,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
		parent::__construct( $engine );
		$this->localTimeZone = $this->mainConfig->get( MainConfigNames::Localtimezone );
	}

	/** @inheritDoc */
	public function register() {
		// Pre-populate the language cache
		$this->langCache[$this->contentLanguage->getCode()] = $this->contentLanguage;
		$this->maxLangCacheSize = $this->getEngine()->getOption( 'maxLangCacheSize' );

		$lib = [
			'getContLangCode' => $this->getContLangCode( ... ),
			'isSupportedLanguage' => $this->isSupportedLanguage( ... ),
			'isKnownLanguageTag' => $this->isKnownLanguageTag( ... ),
			'isValidCode' => $this->isValidCode( ... ),
			'isValidBuiltInCode' => $this->isValidBuiltInCode( ... ),
			'fetchLanguageName' => $this->fetchLanguageName( ... ),
			'fetchLanguageNames' => $this->fetchLanguageNames( ... ),
			'getFallbacksFor' => $this->getFallbacksFor( ... ),
			'toBcp47Code' => $this->toBcp47Code( ... ),
		];
		$methods = [
			'lcfirst',
			'ucfirst',
			'lc',
			'uc',
			'caseFold',
			'formatNum',
			'formatDate',
			'formatDuration',
			'getDurationIntervals',
			'parseFormattedNumber',
			'convertPlural',
			'convertGrammar',
			'gender',
			'isRTL',
		];
		foreach ( $methods as $name ) {
			$lib[$name] = function ( ...$args ) use ( $name ) {
				return $this->languageMethod( $name, $args );
			};
		}
		return $this->getEngine()->registerInterface( 'mw.language.lua', $lib, [
			'constants' => [
				'FALLBACK_MESSAGES' => self::FALLBACK_MESSAGES,
				'FALLBACK_STRICT' => self::FALLBACK_STRICT,
			],
		] );
	}

	/**
	 * Handler for getContLangCode
	 * @return string[]
	 */
	private function getContLangCode() {
		return [ $this->contentLanguage->getCode() ];
	}

	/**
	 * Handler for isSupportedLanguage
	 * @param string $code
	 * @return bool[]
	 */
	private function isSupportedLanguage( $code ) {
		$this->checkType( 'isSupportedLanguage', 1, $code, 'string' );
		return [ $this->languageNameUtils->isSupportedLanguage( $code ) ];
	}

	/**
	 * Handler for isKnownLanguageTag
	 * @param string $code
	 * @return bool[]
	 */
	private function isKnownLanguageTag( $code ) {
		$this->checkType( 'isKnownLanguageTag', 1, $code, 'string' );
		return [ $this->languageNameUtils->isKnownLanguageTag( $code ) ];
	}

	/**
	 * Handler for isValidCode
	 * @param string $code
	 * @return bool[]
	 */
	private function isValidCode( $code ) {
		$this->checkType( 'isValidCode', 1, $code, 'string' );
		return [ $this->languageNameUtils->isValidCode( $code ) ];
	}

	/**
	 * Handler for isValidBuiltInCode
	 * @param string $code
	 * @return bool[]
	 */
	private function isValidBuiltInCode( $code ) {
		$this->checkType( 'isValidBuiltInCode', 1, $code, 'string' );
		return [ $this->languageNameUtils->isValidBuiltInCode( $code ) ];
	}

	/**
	 * Handler for fetchLanguageName
	 * @param string $code
	 * @param null|string $inLanguage
	 * @return string[]
	 */
	private function fetchLanguageName( $code, $inLanguage ) {
		$this->checkType( 'fetchLanguageName', 1, $code, 'string' );
		$this->checkTypeOptional( 'fetchLanguageName', 2, $inLanguage, 'string', LanguageNameUtils::AUTONYMS );
		return [ $this->languageNameUtils->getLanguageName( $code, $inLanguage ) ];
	}

	/**
	 * Handler for fetchLanguageNames
	 * @param null|string $inLanguage
	 * @param null|string $include
	 * @return string[][]
	 */
	private function fetchLanguageNames( $inLanguage, $include ) {
		$this->checkTypeOptional( 'fetchLanguageNames', 1, $inLanguage, 'string', LanguageNameUtils::AUTONYMS );
		$this->checkTypeOptional( 'fetchLanguageNames', 2, $include, 'string', LanguageNameUtils::DEFINED );
		return [ $this->languageNameUtils->getLanguageNames( $inLanguage, $include ) ];
	}

	/**
	 * Handler for fetchLanguageNames
	 * @param string $code
	 * @param int|null $mode
	 * @return string[][]
	 */
	private function getFallbacksFor( $code, $mode ) {
		$this->checkType( 'getFallbacksFor', 1, $code, 'string' );
		$mode ??= self::FALLBACK_MESSAGES;
		if ( !isset( self::FALLBACK_MAP[$mode] ) ) {
			$this->checkType( 'getFallbacksFor', 2, $mode,
				'one of mw.language.FALLBACK_MESSAGES or mw.language.FALLBACK_STRICT' );
		}
		$ret = $this->languageFallback->getAll( $code, self::FALLBACK_MAP[$mode] );
		// Make 1-based
		if ( count( $ret ) ) {
			$ret = array_combine( range( 1, count( $ret ) ), $ret );
		}
		return [ $ret ];
	}

	/**
	 * Handler for toBcp47Code
	 * @param string $code a MediaWiki-internal code
	 * @return string[] a BCP-47 language tag
	 */
	private function toBcp47Code( $code ) {
		$this->checkType( 'toBcp47Code', 1, $code, 'string' );
		$ret = LanguageCode::bcp47( $code );
		return [ $ret ];
	}

	/**
	 * Language object method handler
	 * @param string $name
	 * @param array $args
	 * @return array
	 * @throws LuaError
	 */
	private function languageMethod( string $name, array $args ): array {
		if ( !is_string( $args[0] ?? null ) ) {
			throw new LuaError(
				"invalid code property of language object when calling $name"
			);
		}
		$code = array_shift( $args );
		if ( !isset( $this->langCache[$code] ) ) {
			if ( count( $this->langCache ) > $this->maxLangCacheSize ) {
				throw new LuaError( 'too many language codes requested' );
			}
			if ( $this->languageNameUtils->isValidCode( $code ) ) {
				$this->langCache[$code] = $this->languageFactory->getLanguage( $code );
			} else {
				throw new LuaError( "language code '$code' is invalid" );
			}
		}
		$lang = $this->langCache[$code];
		switch ( $name ) {
			// Zero arguments
			case 'isRTL':
				return [ $lang->$name() ];

			// One string argument passed straight through
			case 'lcfirst':
			case 'ucfirst':
			case 'lc':
			case 'uc':
			case 'caseFold':
				$this->checkType( $name, 1, $args[0], 'string' );
				return [ $lang->$name( $args[0] ) ];

			case 'parseFormattedNumber':
				if ( is_numeric( $args[0] ) ) {
					$args[0] = strval( $args[0] );
				}
				if ( $this->getLuaType( $args[0] ) !== 'string' ) {
					// Be like tonumber(), return nil instead of erroring out
					return [ null ];
				}
				return [ $lang->$name( $args[0] ) ];

			// Custom handling
			default:
				return $this->$name( $lang, $args );
		}
	}

	/**
	 * convertPlural handler
	 * @param Language $lang
	 * @param array $args
	 * @return array
	 */
	private function convertPlural( $lang, $args ) {
		$number = array_shift( $args );
		$this->checkType( 'convertPlural', 1, $number, 'number' );
		if ( is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$forms = array_values( array_map( strval( ... ), $args ) );
		return [ $lang->convertPlural( $number, $forms ) ];
	}

	/**
	 * convertGrammar handler
	 * @param Language $lang
	 * @param array $args
	 * @return array
	 */
	private function convertGrammar( $lang, $args ) {
		$this->checkType( 'convertGrammar', 1, $args[0], 'string' );
		$this->checkType( 'convertGrammar', 2, $args[1], 'string' );
		return [ $lang->convertGrammar( $args[0], $args[1] ) ];
	}

	/**
	 * gender handler
	 * @param Language $lang
	 * @param array $args
	 * @return array
	 */
	private function gender( $lang, $args ) {
		$this->checkType( 'gender', 1, $args[0], 'string' );
		$username = trim( array_shift( $args ) );

		if ( is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$forms = array_values( array_map( strval( ... ), $args ) );

		// Shortcuts
		if ( count( $forms ) === 0 ) {
			return [ '' ];
		} elseif ( count( $forms ) === 1 ) {
			return [ $forms[0] ];
		}

		if ( $username === 'male' || $username === 'female' ) {
			$gender = $username;
		} else {
			// default
			$gender = $this->userOptionsLookup->getDefaultOption( 'gender' );

			// Check for "User:" prefix
			$title = Title::newFromText( $username );
			if ( $title && $title->getNamespace() === NS_USER ) {
				$username = $title->getText();
			}

			// check parameter, or use the ParserOptions if in a message
			$user = User::newFromName( $username );
			if ( $user ) {
				$gender = $this->genderCache->getGenderOf( $user, __METHOD__ );
			} elseif ( $username === '' ) {
				$parserOptions = $this->getParserOptions();
				if ( $parserOptions->isMessage() ) {
					$gender = $this->genderCache->getGenderOf( $parserOptions->getUserIdentity(), __METHOD__ );
				}
			}
		}
		return [ $lang->gender( $gender, $forms ) ];
	}

	/**
	 * formatNum handler
	 * @param Language $lang
	 * @param array $args
	 * @return array
	 * @throws LuaError
	 */
	private function formatNum( $lang, $args ) {
		$num = $args[0];
		$this->checkType( 'formatNum', 1, $num, 'number' );
		if ( is_infinite( $num ) ) {
			throw new LuaError( "bad argument #1 to 'formatNum' (infinite)" );
		}
		if ( is_nan( $num ) ) {
			throw new LuaError( "bad argument #1 to 'formatNum' (NaN)" );
		}

		$noCommafy = false;
		if ( isset( $args[1] ) ) {
			$this->checkType( 'formatNum', 2, $args[1], 'table' );
			$options = $args[1];
			$noCommafy = !empty( $options['noCommafy'] );
		}
		if ( $noCommafy ) {
			return [ $lang->formatNumNoSeparators( $num ) ];
		} else {
			return [ $lang->formatNum( $num ) ];
		}
	}

	/**
	 * formatDate handler
	 * @param Language $lang
	 * @param array $args
	 * @return array
	 * @throws LuaError
	 */
	private function formatDate( $lang, $args ) {
		$this->checkType( 'formatDate', 1, $args[0], 'string' );
		$this->checkTypeOptional( 'formatDate', 2, $args[1], 'string', '' );
		$this->checkTypeOptional( 'formatDate', 3, $args[2], 'boolean', false );

		[ $format, $date, $local ] = $args;
		$langcode = $lang->getCode();

		if ( $date === '' ) {
			$cacheKey = $this->getParserOptions()->getTimestamp();
			$timestamp = new MWTimestamp( $cacheKey );
			$date = $timestamp->getTimestamp( TS_ISO_8601 );
			$useTTL = true;
		} else {
			# Correct for DateTime interpreting 'XXXX' as XX:XX o'clock
			if ( preg_match( '/^[0-9]{4}$/', $date ) ) {
				$date = '00:00 ' . $date;
			}

			$cacheKey = $date;
			$useTTL = false;
		}

		if ( isset( $this->timeCache[$format][$cacheKey][$langcode][$local] ) ) {
			$ttl = $this->timeCache[$format][$cacheKey][$langcode][$local][1];
			if ( $useTTL && $ttl !== null ) {
				$source = ( $this->getEngine()->getCurrentModuleName() ?? 'unknown' ) . ' (formatDate)';
				CoreMagicVariables::applyCacheExpiry( $this->getParser(), $ttl, null, $source );
			}
			return [ $this->timeCache[$format][$cacheKey][$langcode][$local][0] ];
		}

		# Default input timezone is UTC.
		try {
			$utc = new DateTimeZone( 'UTC' );
			$dateObject = new DateTime( $date, $utc );
		} catch ( TimeoutException $ex ) {
			// Unfortunately DateTime throws a generic Exception, but we can't
			// ignore an exception generated by the RequestTimeout library.
			throw $ex;
		} catch ( Exception ) {
			throw new LuaError( "bad argument #2 to 'formatDate': invalid timestamp '$date'" );
		}

		# Set output timezone.
		if ( $local ) {
			if ( $this->localTimeZone !== null ) {
				$tz = new DateTimeZone( $this->localTimeZone );
			} else {
				$tz = new DateTimeZone( date_default_timezone_get() );
			}
		} else {
			$tz = $utc;
		}
		$dateObject->setTimezone( $tz );
		# Generate timestamp
		$ts = $dateObject->format( 'YmdHis' );

		if ( $ts < 0 ) {
			throw new LuaError( "mw.language:formatDate() only supports years from 0" );
		} elseif ( $ts >= 100000000000000 ) {
			throw new LuaError( "mw.language:formatDate() only supports years up to 9999" );
		}

		$ttl = null;
		$ret = $lang->sprintfDate( $format, $ts, $tz, $ttl );
		$this->timeCache[$format][$cacheKey][$langcode][$local] = [ $ret, $ttl ];
		if ( $useTTL && $ttl !== null ) {
			$source = ( $this->getEngine()->getCurrentModuleName() ?? 'unknown' ) . ' (formatDate)';
			CoreMagicVariables::applyCacheExpiry( $this->getParser(), $ttl, null, $source );
		}
		return [ $ret ];
	}

	/**
	 * formatDuration handler
	 * @param Language $lang
	 * @param array $args
	 * @return array
	 */
	private function formatDuration( $lang, $args ) {
		$this->checkType( 'formatDuration', 1, $args[0], 'number' );
		$this->checkTypeOptional( 'formatDuration', 2, $args[1], 'table', [] );

		[ $seconds, $chosenIntervals ] = $args;
		$chosenIntervals = array_values( $chosenIntervals );

		$ret = $lang->formatDuration( $seconds, $chosenIntervals );
		return [ $ret ];
	}

	/**
	 * getDurationIntervals handler
	 * @param Language $lang
	 * @param array $args
	 * @return array
	 */
	private function getDurationIntervals( $lang, $args ) {
		$this->checkType( 'getDurationIntervals', 1, $args[0], 'number' );
		$this->checkTypeOptional( 'getDurationIntervals', 2, $args[1], 'table', [] );

		[ $seconds, $chosenIntervals ] = $args;
		$chosenIntervals = array_values( $chosenIntervals );

		$ret = $lang->getDurationIntervals( $seconds, $chosenIntervals );
		return [ $ret ];
	}
}
