<?php

class ExtParserFunctions {
	static $mExprParser;
	static $mTimeCache = array();
	static $mTimeChars = 0;
	static $mMaxTimeChars = 6000; # ~10 seconds

	/**
	 * @param $parser Parser
	 * @return bool
	 */
	public static function clearState( $parser ) {
		self::$mTimeChars = 0;
		return true;
	}

	/**
	 * Register ParserClearState hook.
	 * We defer this until needed to avoid the loading of the code of this file
	 * when no parser function is actually called.
	 */
	public static function registerClearHook() {
		static $done = false;
		if( !$done ) {
			global $wgHooks;
			$wgHooks['ParserClearState'][] = __CLASS__ . '::clearState';
			$done = true;
		}
	}

	/**
	 * @return ExprParser
	 */
	public static function &getExprParser() {
		if ( !isset( self::$mExprParser ) ) {
			self::$mExprParser = new ExprParser;
		}
		return self::$mExprParser;
	}

	/**
	 * @param $parser Parser
	 * @param $expr string
	 * @return string
	 */
	public static function expr( $parser, $expr = '' ) {
		try {
			return self::getExprParser()->doExpression( $expr );
		} catch ( ExprError $e ) {
			return '<strong class="error">' . htmlspecialchars( $e->getMessage() ) . '</strong>';
		}
	}

	/**
	 * @param $parser Parser
	 * @param $expr string
	 * @param $then string
	 * @param $else string
	 * @return string
	 */
	public static function ifexpr( $parser, $expr = '', $then = '', $else = '' ) {
		try {
			$ret = self::getExprParser()->doExpression( $expr );
			if ( is_numeric( $ret ) ) {
				$ret = floatval( $ret );
			}
			if ( $ret ) {
				return $then;
			} else {
				return $else;
			}
		} catch ( ExprError $e ) {
			return '<strong class="error">' . htmlspecialchars( $e->getMessage() ) . '</strong>';
		}
	}

	/**
	 * @param $parser Parser
	 * @param $frame PPFrame
	 * @param $args array
	 * @return string
	 */
	public static function ifexprObj( $parser, $frame, $args ) {
		$expr = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		$then = isset( $args[1] ) ? $args[1] : '';
		$else = isset( $args[2] ) ? $args[2] : '';
		$result = self::ifexpr( $parser, $expr, $then, $else );
		if ( is_object( $result ) ) {
			$result = trim( $frame->expand( $result ) );
		}
		return $result;
	}

	/**
	 * @param $parser Parser
	 * @param $frame PPFrame
	 * @param $args array
	 * @return string
	 */
	public static function ifObj( $parser, $frame, $args ) {
		$test = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		if ( $test !== '' ) {
			return isset( $args[1] ) ? trim( $frame->expand( $args[1] ) ) : '';
		} else {
			return isset( $args[2] ) ? trim( $frame->expand( $args[2] ) ) : '';
		}
	}

	/**
	 * @param $parser Parser
	 * @param $frame PPFrame
	 * @param $args array
	 * @return string
	 */
	public static function ifeqObj( $parser, $frame, $args ) {
		$left = isset( $args[0] ) ? self::decodeTrimExpand( $args[0], $frame ) : '';
		$right = isset( $args[1] ) ? self::decodeTrimExpand( $args[1], $frame ) : '';
		if ( $left == $right ) {
			return isset( $args[2] ) ? trim( $frame->expand( $args[2] ) ) : '';
		} else {
			return isset( $args[3] ) ? trim( $frame->expand( $args[3] ) ) : '';
		}
	}

	/**
	 * @param $parser Parser
	 * @param $test string
	 * @param $then string
	 * @param $else bool
	 * @return bool|string
	 */
	public static function iferror( $parser, $test = '', $then = '', $else = false ) {
		if ( preg_match( '/<(?:strong|span|p|div)\s(?:[^\s>]*\s+)*?class="(?:[^"\s>]*\s+)*?error(?:\s[^">]*)?"/', $test ) ) {
			return $then;
		} elseif ( $else === false ) {
			return $test;
		} else {
			return $else;
		}
	}

	/**
	 * @param $parser Parser
	 * @param $frame PPFrame
	 * @param $args array
	 * @return string
	 */
	public static function iferrorObj( $parser, $frame, $args ) {
		$test = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		$then = isset( $args[1] ) ? $args[1] : false;
		$else = isset( $args[2] ) ? $args[2] : false;
		$result = self::iferror( $parser, $test, $then, $else );
		if ( $result === false ) {
			return '';
		} else {
			return trim( $frame->expand( $result ) );
		}
	}

	/**
	 * @param $parser Parser
	 * @param $frame PPFrame
	 * @param $args
	 * @return string
	 */
	public static function switchObj( $parser, $frame, $args ) {
		if ( count( $args ) == 0 ) {
			return '';
		}
		$primary = self::decodeTrimExpand( array_shift( $args ), $frame );
		$found = $defaultFound = false;
		$default = null;
		$lastItemHadNoEquals = false;
		$lastItem = '';
		$mwDefault =& MagicWord::get( 'default' );
		foreach ( $args as $arg ) {
			$bits = $arg->splitArg();
			$nameNode = $bits['name'];
			$index = $bits['index'];
			$valueNode = $bits['value'];

			if ( $index === '' ) {
				# Found "="
				$lastItemHadNoEquals = false;
				if ( $found ) {
					# Multiple input match
					return trim( $frame->expand( $valueNode ) );
				} else {
					$test = self::decodeTrimExpand( $nameNode, $frame );
					if ( $test == $primary ) {
						# Found a match, return now
						return trim( $frame->expand( $valueNode ) );
					} elseif ( $defaultFound || $mwDefault->matchStartToEnd( $test ) ) {
						$default = $valueNode;
						$defaultFound = false;
					} # else wrong case, continue
				}
			} else {
				# Multiple input, single output
				# If the value matches, set a flag and continue
				$lastItemHadNoEquals = true;
				// $lastItem is an "out" variable
				$decodedTest = self::decodeTrimExpand( $valueNode, $frame, $lastItem );
				if ( $decodedTest == $primary ) {
					$found = true;
				} elseif ( $mwDefault->matchStartToEnd( $decodedTest ) ) {
					$defaultFound = true;
				}
			}
		}
		# Default case
		# Check if the last item had no = sign, thus specifying the default case
		if ( $lastItemHadNoEquals ) {
			return $lastItem;
		} elseif ( !is_null( $default ) ) {
			return trim( $frame->expand( $default ) );
		} else {
			return '';
		}
	}

	/**
	 * Returns the absolute path to a subpage, relative to the current article
	 * title. Treats titles as slash-separated paths.
	 *
	 * Following subpage link syntax instead of standard path syntax, an
	 * initial slash is treated as a relative path, and vice versa.
	 *
	 * @param $parser Parser
	 * @param $to string
	 * @param $from string
	 *
	 * @return string
	 */
	public static function rel2abs( $parser , $to = '' , $from = '' ) {

		$from = trim( $from );
		if ( $from == '' ) {
			$from = $parser->getTitle()->getPrefixedText();
		}

		$to = rtrim( $to , ' /' );

		// if we have an empty path, or just one containing a dot
		if ( $to == '' || $to == '.' ) {
			return $from;
		}

		// if the path isn't relative
		if ( substr( $to , 0 , 1 ) != '/' &&
		 substr( $to , 0 , 2 ) != './' &&
		 substr( $to , 0 , 3 ) != '../' &&
		 $to != '..' )
		{
			$from = '';
		}
		// Make a long path, containing both, enclose it in /.../
		$fullPath = '/' . $from . '/' .  $to . '/';

		// remove redundant current path dots
		$fullPath = preg_replace( '!/(\./)+!', '/', $fullPath );

		// remove double slashes
		$fullPath = preg_replace( '!/{2,}!', '/', $fullPath );

		// remove the enclosing slashes now
		$fullPath = trim( $fullPath , '/' );
		$exploded = explode ( '/' , $fullPath );
		$newExploded = array();

		foreach ( $exploded as $current ) {
			if ( $current == '..' ) { // removing one level
				if ( !count( $newExploded ) ) {
					// attempted to access a node above root node
					$msg = wfMessage( 'pfunc_rel2abs_invalid_depth', $fullPath )->inContentLanguage()->escaped();
					return '<strong class="error">' . $msg . '</strong>';
				}
				// remove last level from the stack
				array_pop( $newExploded );
			} else {
				// add the current level to the stack
				$newExploded[] = $current;
			}
		}

		// we can now join it again
		return implode( '/' , $newExploded );
	}

	/**
	 * @param $parser Parser
	 * @param $frame PPFrame
	 * @param $titletext string
	 * @param $then string
	 * @param $else string
	 *
	 * @return string
	 */
	public static function ifexistCommon( $parser, $frame, $titletext = '', $then = '', $else = '' ) {
		global $wgContLang;
		$title = Title::newFromText( $titletext );
		$wgContLang->findVariantLink( $titletext, $title, true );
		if ( $title ) {
			if ( $title->getNamespace() == NS_MEDIA ) {
				/* If namespace is specified as NS_MEDIA, then we want to
				 * check the physical file, not the "description" page.
				 */
				if ( !$parser->incrementExpensiveFunctionCount() ) {
					return $else;
				}
				$file = wfFindFile( $title );
				if ( !$file ) {
					return $else;
				}
				$parser->mOutput->addImage(
					$file->getName(), $file->getTimestamp(), $file->getSha1() );
				return $file->exists() ? $then : $else;
			} elseif ( $title->getNamespace() == NS_SPECIAL ) {
				/* Don't bother with the count for special pages,
				 * since their existence can be checked without
				 * accessing the database.
				 */
				return SpecialPageFactory::exists( $title->getDBkey() ) ? $then : $else;
			} elseif ( $title->isExternal() ) {
				/* Can't check the existence of pages on other sites,
				 * so just return $else.  Makes a sort of sense, since
				 * they don't exist _locally_.
				 */
				return $else;
			} else {
				$pdbk = $title->getPrefixedDBkey();
				$lc = LinkCache::singleton();
				$id = $lc->getGoodLinkID( $pdbk );
				if ( $id != 0 ) {
					$parser->mOutput->addLink( $title, $id );
					return $then;
				} elseif ( $lc->isBadLink( $pdbk ) ) {
					$parser->mOutput->addLink( $title, 0 );
					return $else;
				}
				if (  !$parser->incrementExpensiveFunctionCount() ) {
					return $else;
				}
				$id = $title->getArticleID();
				$parser->mOutput->addLink( $title, $id );

				// bug 70495: don't just check whether the ID != 0
				if ( $title->exists() ) {
					return $then;
				}
			}
		}
		return $else;
	}

	/**
	 * @param $parser Parser
	 * @param $frame PPFrame
	 * @param $args array
	 * @return string
	 */
	public static function ifexistObj( $parser, $frame, $args ) {
		$title = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		$then = isset( $args[1] ) ? $args[1] : null;
		$else = isset( $args[2] ) ? $args[2] : null;

		$result = self::ifexistCommon( $parser, $frame, $title, $then, $else );
		if ( $result === null ) {
			return '';
		} else {
			return trim( $frame->expand( $result ) );
		}
	}

	/**
	 * @param $parser Parser
	 * @param $frame PPFrame
	 * @param $format string
	 * @param $date string
	 * @param $language string
	 * @param $local string|bool
	 * @return string
	 */
	public static function timeCommon( $parser, $frame = null, $format = '', $date = '', $language = '', $local = false ) {
		global $wgLocaltimezone;
		self::registerClearHook();
		if ( $date === '' ) {
			$cacheKey = $parser->getOptions()->getTimestamp();
			$timestamp = new MWTimestamp( $cacheKey );
			$date = $timestamp->getTimestamp( TS_ISO_8601 );
			$useTTL = true;
		} else {
			$cacheKey = $date;
			$useTTL = false;
		}
		if ( isset( self::$mTimeCache[$format][$cacheKey][$language][$local] ) ) {
			$cachedVal = self::$mTimeCache[$format][$cacheKey][$language][$local];
			if ( $useTTL && $cachedVal[1] !== null && $frame && is_callable( array( $frame, 'setTTL' ) ) ) {
				$frame->setTTL( $cachedVal[1] );
			}
			return $cachedVal[0];
		}

		# compute the timestamp string $ts
		# PHP >= 5.2 can handle dates before 1970 or after 2038 using the DateTime object

		$invalidTime = false;

		# the DateTime constructor must be used because it throws exceptions
		# when errors occur, whereas date_create appears to just output a warning
		# that can't really be detected from within the code
		try {

			# Default input timezone is UTC.
			$utc = new DateTimeZone( 'UTC' );

			# Correct for DateTime interpreting 'XXXX' as XX:XX o'clock
			if ( preg_match( '/^[0-9]{4}$/', $date ) ) {
				$date = '00:00 '.$date;
			}

			# Parse date
			# UTC is a default input timezone.
			$dateObject = new DateTime( $date, $utc );

			# Set output timezone.
			if ( $local ) {
				if ( isset( $wgLocaltimezone ) ) {
					$tz = new DateTimeZone( $wgLocaltimezone );
				} else {
					$tz = new DateTimeZone( date_default_timezone_get() );
				}
			} else {
				$tz = $utc;
			}
			$dateObject->setTimezone( $tz );
			# Generate timestamp
			$ts = $dateObject->format( 'YmdHis' );

		} catch ( Exception $ex ) {
			$invalidTime = true;
		}

		$ttl = null;
		# format the timestamp and return the result
		if ( $invalidTime ) {
			$result = '<strong class="error">' . wfMessage( 'pfunc_time_error' )->inContentLanguage()->escaped() . '</strong>';
		} else {
			self::$mTimeChars += strlen( $format );
			if ( self::$mTimeChars > self::$mMaxTimeChars ) {
				return '<strong class="error">' . wfMessage( 'pfunc_time_too_long' )->inContentLanguage()->escaped() . '</strong>';
			} else {
				if ( $ts < 0 ) { // Language can't deal with BC years
					return '<strong class="error">' . wfMessage( 'pfunc_time_too_small' )->inContentLanguage()->escaped() . '</strong>';
				} elseif ( $ts < 100000000000000 ) { // Language can't deal with years after 9999
					if ( $language !== '' && Language::isValidBuiltInCode( $language ) ) {
						// use whatever language is passed as a parameter
						$langObject = Language::factory( $language );
					} else {
						// use wiki's content language
						$langObject = $parser->getFunctionLang();
						StubObject::unstub( $langObject ); // $ttl is passed by reference, which doesn't work right on stub objects
					}
					$result = $langObject->sprintfDate( $format, $ts, $tz, $ttl );
				} else {
					return '<strong class="error">' . wfMessage( 'pfunc_time_too_big' )->inContentLanguage()->escaped() . '</strong>';
				}
			}
		}
		self::$mTimeCache[$format][$cacheKey][$language][$local] = array( $result, $ttl );
		if ( $useTTL && $ttl !== null && $frame && is_callable( array( $frame, 'setTTL' ) ) ) {
			$frame->setTTL( $ttl );
		}
		return $result;
	}

	/**
	 * @param $parser Parser
	 * @param $format string
	 * @param $date string
	 * @param $language string
	 * @param $local string|bool
	 * @return string
	 */
	public static function time( $parser, $format = '', $date = '', $language = '', $local = false ) {
		return self::timeCommon( $parser, null, $format, $date, $language, $local );
	}


	/**
	 * @param $parser Parser
	 * @param $frame PPFrame
	 * @param $args array
	 * @return string
	 */
	public static function timeObj( $parser, $frame, $args ) {
		$format = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		$date = isset( $args[1] ) ? trim( $frame->expand( $args[1] ) ) : '';
		$language = isset( $args[2] ) ? trim( $frame->expand( $args[2] ) ) : '';
		$local = isset( $args[3] ) && trim( $frame->expand( $args[3] ) );
		return self::timeCommon( $parser, $frame, $format, $date, $language, $local );
	}

	/**
	 * @param $parser Parser
	 * @param $format string
	 * @param $date string
	 * @param $language string
	 * @return string
	 */
	public static function localTime( $parser, $format = '', $date = '', $language = '' ) {
		return self::timeCommon( $parser, null, $format, $date, $language, true );
	}

	/**
	 * @param $parser Parser
	 * @param $frame PPFrame
	 * @param $args array
	 * @return string
	 */
	public static function localTimeObj( $parser, $frame, $args ) {
		$format = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		$date = isset( $args[1] ) ? trim( $frame->expand( $args[1] ) ) : '';
		$language = isset( $args[2] ) ? trim( $frame->expand( $args[2] ) ) : '';
		return self::timeCommon( $parser, $frame, $format, $date, $language, true );
	}

	/**
	 * Obtain a specified number of slash-separated parts of a title,
	 * e.g. {{#titleparts:Hello/World|1}} => "Hello"
	 *
	 * @param $parser Parser Parent parser
	 * @param $title string Title to split
	 * @param $parts int Number of parts to keep
	 * @param $offset int Offset starting at 1
	 * @return string
	 */
	public static function titleparts( $parser, $title = '', $parts = 0, $offset = 0 ) {
		$parts = intval( $parts );
		$offset = intval( $offset );
		$ntitle = Title::newFromText( $title );
		if ( $ntitle instanceof Title ) {
			$bits = explode( '/', $ntitle->getPrefixedText(), 25 );
			if ( count( $bits ) <= 0 ) {
				 return $ntitle->getPrefixedText();
			} else {
				if ( $offset > 0 ) {
					--$offset;
				}
				if ( $parts == 0 ) {
					return implode( '/', array_slice( $bits, $offset ) );
				} else {
					return implode( '/', array_slice( $bits, $offset, $parts ) );
				}
			}
		} else {
			return $title;
		}
	}

	/**
	 *  Verifies parameter is less than max string length.
	 * @param $text
	 * @return bool
	 */
	private static function checkLength( $text ) {
		global $wgPFStringLengthLimit;
		return ( mb_strlen( $text ) < $wgPFStringLengthLimit );
	}

	/**
	 * Generates error message.  Called when string is too long.
	 * @return string
	 */
	private static function tooLongError() {
		global $wgPFStringLengthLimit;
		$msg = wfMessage( 'pfunc_string_too_long' )->numParams( $wgPFStringLengthLimit );
		return '<strong class="error">' . $msg->inContentLanguage()->escaped() . '</strong>';
	}

	/**
	 * {{#len:string}}
	 *
	 * Reports number of characters in string.
	 * @param $parser Parser
	 * @param $inStr string
	 * @return int
	 */
	public static function runLen ( $parser, $inStr = '' ) {
		$inStr = $parser->killMarkers( (string)$inStr );
		return mb_strlen( $inStr );
	}

	/**
	 * {{#pos: string | needle | offset}}
	 *
	 * Finds first occurrence of "needle" in "string" starting at "offset".
	 *
	 * Note: If the needle is an empty string, single space is used instead.
	 * Note: If the needle is not found, empty string is returned.
	 * @param $parser Parser
	 * @param $inStr string
	 * @param $inNeedle int|string
	 * @param $inOffset int
	 * @return int|string
	 */
	public static function runPos ( $parser, $inStr = '', $inNeedle = '', $inOffset = 0 ) {
		$inStr = $parser->killMarkers( (string)$inStr );
		$inNeedle = $parser->killMarkers( (string)$inNeedle );

		if ( !self::checkLength( $inStr ) ||
			!self::checkLength( $inNeedle ) ) {
			return self::tooLongError();
		}

		if ( $inNeedle == '' ) { $inNeedle = ' '; }

		$pos = mb_strpos( $inStr, $inNeedle, intval( $inOffset ) );
		if ( $pos === false ) { $pos = ""; }

		return $pos;
	}

	/**
	 * {{#rpos: string | needle}}
	 *
	 * Finds last occurrence of "needle" in "string".
	 *
	 * Note: If the needle is an empty string, single space is used instead.
	 * Note: If the needle is not found, -1 is returned.
	 * @param $parser Parser
	 * @param $inStr string
	 * @param $inNeedle int|string
	 * @return int|string
	 */
	public static function runRPos ( $parser, $inStr = '', $inNeedle = '' ) {
		$inStr = $parser->killMarkers( (string)$inStr );
		$inNeedle = $parser->killMarkers( (string)$inNeedle );

		if ( !self::checkLength( $inStr ) ||
			!self::checkLength( $inNeedle ) ) {
			return self::tooLongError();
		}

		if ( $inNeedle == '' ) { $inNeedle = ' '; }

		$pos = mb_strrpos( $inStr, $inNeedle );
		if ( $pos === false ) { $pos = -1; }

		return $pos;
	}

	/**
	 * {{#sub: string | start | length }}
	 *
	 * Returns substring of "string" starting at "start" and having
	 * "length" characters.
	 *
	 * Note: If length is zero, the rest of the input is returned.
	 * Note: A negative value for "start" operates from the end of the
	 *   "string".
	 * Note: A negative value for "length" returns a string reduced in
	 *   length by that amount.
	 *
	 * @param $parser Parser
	 * @param $inStr string
	 * @param $inStart int
	 * @param $inLength int
	 * @return string
	 */
	public static function runSub ( $parser, $inStr = '', $inStart = 0, $inLength = 0 ) {
		$inStr = $parser->killMarkers( (string)$inStr );

		if ( !self::checkLength( $inStr ) ) {
			return self::tooLongError();
		}

		if ( intval( $inLength ) == 0 ) {
			$result = mb_substr( $inStr, intval( $inStart ) );
		} else {
			$result = mb_substr( $inStr, intval( $inStart ), intval( $inLength ) );
		}

		return $result;
	}

	/**
	 * {{#count: string | substr }}
	 *
	 * Returns number of occurrences of "substr" in "string".
	 *
	 * Note: If "substr" is empty, a single space is used.
	 * @param $parser
	 * @param $inStr string
	 * @param $inSubStr string
	 * @return int|string
	 */
	public static function runCount ( $parser, $inStr = '', $inSubStr = '' ) {
		$inStr = $parser->killMarkers( (string)$inStr );
		$inSubStr = $parser->killMarkers( (string)$inSubStr );

		if ( !self::checkLength( $inStr ) ||
			!self::checkLength( $inSubStr ) ) {
			return self::tooLongError();
		}

		if ( $inSubStr == '' ) {
			$inSubStr = ' ';
		}

		$result = mb_substr_count( $inStr, $inSubStr );

		return $result;
	}

	/**
	 * {{#replace:string | from | to | limit }}
	 *
	 * Replaces each occurrence of "from" in "string" with "to".
	 * At most "limit" replacements are performed.
	 *
	 * Note: Armored against replacements that would generate huge strings.
	 * Note: If "from" is an empty string, single space is used instead.
	 * @param $parser Parser
	 * @param $inStr string
	 * @param $inReplaceFrom string
	 * @param $inReplaceTo string
	 * @param $inLimit int
	 * @return mixed|string
	 */
	public static function runReplace( $parser, $inStr = '',
			$inReplaceFrom = '', $inReplaceTo = '', $inLimit = -1 ) {
		global $wgPFStringLengthLimit;

		$inStr = $parser->killMarkers( (string)$inStr );
		$inReplaceFrom = $parser->killMarkers( (string)$inReplaceFrom );
		$inReplaceTo = $parser->killMarkers( (string)$inReplaceTo );

		if ( !self::checkLength( $inStr ) ||
			!self::checkLength( $inReplaceFrom ) ||
			!self::checkLength( $inReplaceTo ) ) {
			return self::tooLongError();
		}

		if ( $inReplaceFrom == '' ) { $inReplaceFrom = ' '; }

		// Precompute limit to avoid generating enormous string:
		$diff = mb_strlen( $inReplaceTo ) - mb_strlen( $inReplaceFrom );
		if ( $diff > 0 ) {
			$limit = ( ( $wgPFStringLengthLimit - mb_strlen( $inStr ) ) / $diff ) + 1;
		} else {
			$limit = -1;
		}

		$inLimit = intval( $inLimit );
		if ( $inLimit >= 0 ) {
			if ( $limit > $inLimit || $limit == -1 ) { $limit = $inLimit; }
		}

		// Use regex to allow limit and handle UTF-8 correctly.
		$inReplaceFrom = preg_quote( $inReplaceFrom, '/' );
		$inReplaceTo = StringUtils::escapeRegexReplacement( $inReplaceTo );

		$result = preg_replace( '/' . $inReplaceFrom . '/u',
						$inReplaceTo, $inStr, $limit );

		if ( !self::checkLength( $result ) ) {
			return self::tooLongError();
		}

		return $result;
	}


	/**
	 * {{#explode:string | delimiter | position | limit}}
	 *
	 * Breaks "string" into chunks separated by "delimiter" and returns the
	 * chunk identified by "position".
	 *
	 * Note: Negative position can be used to specify tokens from the end.
	 * Note: If the divider is an empty string, single space is used instead.
	 * Note: Empty string is returned if there are not enough exploded chunks.
	 * @param $parser Parser
	 * @param $inStr string
	 * @param $inDiv string
	 * @param $inPos int
	 * @param $inLim int|null
	 * @return string
	 */
	public static function runExplode ( $parser, $inStr = '', $inDiv = '', $inPos = 0, $inLim = null ) {
		$inStr = $parser->killMarkers( (string)$inStr );
		$inDiv = $parser->killMarkers( (string)$inDiv );

		if ( $inDiv == '' ) {
			$inDiv = ' ';
		}

		if ( !self::checkLength( $inStr ) ||
			!self::checkLength( $inDiv ) ) {
			return self::tooLongError();
		}

		$inDiv = preg_quote( $inDiv, '/' );

		$matches = preg_split( '/' . $inDiv . '/u', $inStr, $inLim );

		if ( $inPos >= 0 && isset( $matches[$inPos] ) ) {
			$result = $matches[$inPos];
		} elseif ( $inPos < 0 && isset( $matches[count( $matches ) + $inPos] ) ) {
			$result = $matches[count( $matches ) + $inPos];
		} else {
			$result = '';
		}

		return $result;
	}

	/**
	 * {{#urldecode:string}}
	 *
	 * Decodes URL-encoded (like%20that) strings.
	 * @param $parser Parser
	 * @param $inStr string
	 * @return string
	 */
	public static function runUrlDecode( $parser, $inStr = '' ) {
		$inStr = $parser->killMarkers( (string)$inStr );
		if ( !self::checkLength( $inStr ) ) {
			return self::tooLongError();
		}

		return urldecode( $inStr );
	}

	/**
	 * Take a PPNode (-ish thing), expand it, remove entities, and trim.
	 *
	 * For use when doing string comparisions, where user expects entities
	 * to be equal for what they stand for (e.g. comparisions with {{PAGENAME}})
	 *
	 * @param $obj PPNode|string Thing to expand
	 * @param $frame PPFrame
	 * @param &$trimExpanded String Expanded and trimmed version of PPNode, but with char refs intact
	 * @return String The trimmed, expanded and entity reference decoded version of the PPNode
	 */
	private static function decodeTrimExpand( $obj, $frame, &$trimExpanded = null ) {
		$expanded = $frame->expand( $obj );
		$trimExpanded = trim( $expanded );
		return trim( Sanitizer::decodeCharReferences( $expanded ) );
	}
}
