<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

class SyntaxHighlight_GeSHi {
	/**
	 * Has GeSHi been initialised this session?
	 */
	private static $initialised = false;

	/**
	 * List of languages available to GeSHi
	 * @var array
	 */
	private static $languages = null;

	/**
	 * Parser hook
	 *
	 * @param string $text
	 * @param array $args
	 * @param Parser $parser
	 * @return string
	 */
	public static function parserHook( $text, $args = array(), $parser ) {
		global $wgSyntaxHighlightDefaultLang, $wgUseSiteCss, $wgUseTidy;
		wfProfileIn( __METHOD__ );
		self::initialise();
		$text = rtrim( $text );
		// Don't trim leading spaces away, just the linefeeds
		$text = preg_replace( '/^\n+/', '', $text );

		// Validate language
		if( isset( $args['lang'] ) && $args['lang'] ) {
			$lang = $args['lang'];
		} else {
			// language is not specified. Check if default exists, if yes, use it.
			if ( !is_null( $wgSyntaxHighlightDefaultLang ) ) {
				$lang = $wgSyntaxHighlightDefaultLang;
			} else {
				$error = self::formatLanguageError( $text );
				wfProfileOut( __METHOD__ );
				return $error;
			}
		}
		$lang = strtolower( $lang );
		if( !preg_match( '/^[a-z_0-9-]*$/', $lang ) ) {
			$error = self::formatLanguageError( $text );
			wfProfileOut( __METHOD__ );
			return $error;
		}
		$geshi = self::prepare( $text, $lang );
		if( !$geshi instanceof GeSHi ) {
			$error = self::formatLanguageError( $text );
			wfProfileOut( __METHOD__ );
			return $error;
		}

		$enclose = self::getEncloseType( $args );

		// Line numbers
		if( isset( $args['line'] ) ) {
			$geshi->enable_line_numbers( GESHI_FANCY_LINE_NUMBERS );
		}
		// Highlighting specific lines
		if( isset( $args['highlight'] ) ) {
			$lines = self::parseHighlightLines( $args['highlight'] );
			if ( count( $lines ) ) {
				$geshi->highlight_lines_extra( $lines );
			}
		}
		// Starting line number
		if( isset( $args['start'] ) ) {
			$geshi->start_line_numbers_at( $args['start'] );
		}
		$geshi->set_header_type( $enclose );
		// Strict mode
		if( isset( $args['strict'] ) ) {
			$geshi->enable_strict_mode();
		}
		// Format
		$out = $geshi->parse_code();
		if ( $geshi->error == GESHI_ERROR_NO_SUCH_LANG ) {
			// Common error :D
			$error = self::formatLanguageError( $text );
			wfProfileOut( __METHOD__ );
			return $error;
		}
		$err = $geshi->error();
		if( $err ) {
			// Other unknown error!
			$error = self::formatError( $err );
			wfProfileOut( __METHOD__ );
			return $error;
		}
		// Armour for Parser::doBlockLevels()
		if( $enclose === GESHI_HEADER_DIV ) {
			$out = str_replace( "\n", '', $out );
		}
		// HTML Tidy will convert tabs to spaces incorrectly (bug 30930).
		// But the conversion from tab to space occurs while reading the input,
		// before the conversion from &#9; to tab, so we can armor it that way.
		if( $wgUseTidy ) {
			$out = str_replace( "\t", '&#9;', $out );
		}
		// Register CSS
		$parser->getOutput()->addModuleStyles( "ext.geshi.language.$lang" );

		if ( $wgUseSiteCss ) {
			$parser->getOutput()->addModuleStyles( 'ext.geshi.local' );
		}

		$encloseTag = $enclose === GESHI_HEADER_NONE ? 'span' : 'div';
		$attribs = Sanitizer::validateTagAttributes( $args, $encloseTag );

		//lang is valid in HTML context, but also used on GeSHi
		unset( $attribs['lang'] );

		if ( $enclose === GESHI_HEADER_NONE ) {
			$attribs = self::addAttribute( $attribs, 'class', 'mw-geshi ' . $lang . ' source-' . $lang );
		} else {
			// Default dir="ltr" (but allow dir="rtl", although unsure if needed)
			$attribs['dir'] = isset( $attribs['dir'] ) && $attribs['dir'] === 'rtl' ? 'rtl' : 'ltr';
			$attribs = self::addAttribute( $attribs, 'class', 'mw-geshi mw-code mw-content-' . $attribs['dir'] );
		}
		$out = Html::rawElement( $encloseTag, $attribs, $out );

		wfProfileOut( __METHOD__ );
		return $out;
	}

	/**
	 * @param $attribs array
	 * @param $name string
	 * @param $value string
	 * @return array
	 */
	private static function addAttribute( $attribs, $name, $value ) {
		if( isset( $attribs[$name] ) ) {
			$attribs[$name] = $value . ' ' . $attribs[$name];
		} else {
			$attribs[$name] = $value;
		}
		return $attribs;
	}

	/**
	 * Take an input specifying a list of lines to highlight, returning
	 * a raw list of matching line numbers.
	 *
	 * Input is comma-separated list of lines or line ranges.
	 *
	 * @param $arg string
	 * @return array of ints
	 */
	protected static function parseHighlightLines( $arg ) {
		$lines = array();
		$values = array_map( 'trim', explode( ',', $arg ) );
		foreach ( $values as $value ) {
			if ( ctype_digit($value) ) {
				$lines[] = (int) $value;
			} elseif ( strpos( $value, '-' ) !== false ) {
				list( $start, $end ) = array_map( 'trim', explode( '-', $value ) );
				if ( self::validHighlightRange( $start, $end ) ) {
					for ($i = intval( $start ); $i <= $end; $i++ ) {
						$lines[] = $i;
					}
				} else {
					wfDebugLog( 'geshi', "Invalid range: $value\n" );
				}
			} else {
				wfDebugLog( 'geshi', "Invalid line: $value\n" );
			}
		}
		return $lines;
	}

	/**
	 * Validate a provided input range
	 * @param $start
	 * @param $end
	 * @return bool
	 */
	protected static function validHighlightRange( $start, $end ) {
		// Since we're taking this tiny range and producing a an
		// array of every integer between them, it would be trivial
		// to DoS the system by asking for a huge range.
		// Impose an arbitrary limit on the number of lines in a
		// given range to reduce the impact.
		$arbitrarilyLargeConstant = 10000;
		return
			ctype_digit($start) &&
			ctype_digit($end) &&
			$start > 0 &&
			$start < $end &&
			$end - $start < $arbitrarilyLargeConstant;
	}

	/**
	 * @param $args array
	 * @return int
	 */
	static function getEncloseType( $args ) {
		// "Enclose" parameter
		$enclose = GESHI_HEADER_PRE_VALID;
		if ( isset( $args['enclose'] ) ) {
			if ( $args['enclose'] === 'div' ) {
				$enclose = GESHI_HEADER_DIV;
			} elseif ( $args['enclose'] === 'none' ) {
				$enclose = GESHI_HEADER_NONE;
			}
		}

		return $enclose;
	}

	/**
	 * Hook into Content::getParserOutput to provide syntax highlighting for
	 * script content.
	 *
	 * @return bool
	 * @since MW 1.21
	 */
	public static function renderHook( Content $content, Title $title,
			$revId, ParserOptions $options, $generateHtml, ParserOutput &$output
	) {

		global $wgSyntaxHighlightModels, $wgUseSiteCss,
			$wgParser, $wgTextModelsToParse;

		// Determine the language
		$model = $content->getModel();
		if ( !isset( $wgSyntaxHighlightModels[$model] ) ) {
			// We don't care about this model, carry on.
			return true;
		}

		if ( !$generateHtml ) {
			// Nothing special for us to do, let MediaWiki handle this.
			return true;
		}

		// Hope that $wgSyntaxHighlightModels does not contain silly types.
		$text = ContentHandler::getContentText( $content );

		if ( $text === null || $text === false ) {
			// Oops! Non-text content? Let MediaWiki handle this.
			return true;
		}

		// Parse using the standard parser to get links etc. into the database, HTML is replaced below.
		// We could do this using $content->fillParserOutput(), but alas it is 'protected'.
		if ( $content instanceof TextContent && in_array( $model, $wgTextModelsToParse ) ) {
			$output = $wgParser->parse( $text, $title, $options, true, true, $revId );
		}

		$lang = $wgSyntaxHighlightModels[$model];

		// Attempt to format
		$geshi = self::prepare( $text, $lang );
		if( $geshi instanceof GeSHi ) {

			$out = $geshi->parse_code();
			if( !$geshi->error() ) {
				// Done
				$output->addModuleStyles( "ext.geshi.language.$lang" );
				$output->setText( "<div dir=\"ltr\">{$out}</div>" );

				if( $wgUseSiteCss ) {
					$output->addModuleStyles( 'ext.geshi.local' );
				}

				// Inform MediaWiki that we have parsed this page and it shouldn't mess with it.
				return false;
			}
		}

		// Bottle out
		return true;
	}

	/**
	 * Initialise a GeSHi object to format some code, performing
	 * common setup for all our uses of it
	 *
	 * @param string $text
	 * @param string $lang
	 * @return GeSHi
	 */
	public static function prepare( $text, $lang ) {

		global $wgSyntaxHighlightKeywordLinks;

		self::initialise();
		$geshi = new GeSHi( $text, $lang );
		if( $geshi->error() == GESHI_ERROR_NO_SUCH_LANG ) {
			return null;
		}
		$geshi->set_encoding( 'UTF-8' );
		$geshi->enable_classes();
		$geshi->set_overall_class( "source-$lang" );
		$geshi->enable_keyword_links( $wgSyntaxHighlightKeywordLinks );

		// If the source code is over 100 kB, disable higlighting of symbols.
		// If over 200 kB, disable highlighting of strings too.
		$bytes = strlen( $text );
		if ( $bytes > 102400 ) {
			$geshi->set_symbols_highlighting( false );
			if ( $bytes > 204800 ) {
				$geshi->set_strings_highlighting( false );
			}
		}

		/**
		 * GeSHi comes by default with a font-family set to monospace, which
		 * causes the font-size to be smaller than one would expect.
		 * We append a CSS hack to the default GeSHi styles: specifying 'monospace'
		 * twice "resets" the browser font-size specified for monospace.
		 *
		 * The hack is documented in MediaWiki core under
		 * docs/uidesign/monospace.html and in bug 33496.
		 */
		// Preserve default since we don't want to override the other style
		// properties set by geshi (padding, font-size, vertical-align etc.)
		$geshi->set_code_style(
			'font-family: monospace, monospace;',
			/* preserve defaults = */ true
		);

		// No need to preserve default (which is just "font-family: monospace;")
		// outputting both is unnecessary
		$geshi->set_overall_style(
			'font-family: monospace, monospace;',
			/* preserve defaults = */ false
		);

		return $geshi;
	}

	/**
	 * Prepare a CSS snippet suitable for use as a ParserOutput/OutputPage
	 * head item.
	 *
	 * Not used anymore, kept for backwards-compatibility with other extensions.
	 *
	 * @deprecated
	 * @param GeSHi $geshi
	 * @return string
	 */
	public static function buildHeadItem( $geshi ) {
		wfDeprecated( __METHOD__ );
		$css = array();
		$css[] = '<style type="text/css">/*<![CDATA[*/';
		$css[] = self::getCSS( $geshi );
		$css[] = '/*]]>*/';
		$css[] = '</style>';
		return implode( "\n", $css );
	}

	/**
	 * Get the complete CSS code necessary to display styles for given GeSHi instance.
	 *
	 * @param GeSHi $geshi
	 * @return string
	 */
	public static function getCSS( $geshi ) {
		$lang = $geshi->language;
		$css = array();
		$css[] = ".source-$lang {line-height: normal;}";
		$css[] = ".source-$lang li, .source-$lang pre {";
		$css[] = "\tline-height: normal; border: 0px none white;";
		$css[] = "}";
		$css[] = $geshi->get_stylesheet( /*$economy_mode*/ false );
		return implode( "\n", $css );
	}

	/**
	 * Format an 'unknown language' error message and append formatted
	 * plain text to it.
	 *
	 * @param string $text
	 * @return string HTML fragment
	 */
	private static function formatLanguageError( $text ) {
		$msg = wfMessage( 'syntaxhighlight-err-language' )->inContentLanguage()->escaped();
		$error = self::formatError( $msg, $text );
		return $error . '<pre>' . htmlspecialchars( $text ) . '</pre>';
	}

	/**
	 * Format an error message
	 *
	 * @param string $error
	 * @return string
	 */
	private static function formatError( $error = '' ) {
		$html = '';
		if( $error ) {
			$html .= "<p>{$error}</p>";
		}
		$html .= '<p>' . wfMessage( 'syntaxhighlight-specify')->inContentLanguage()->escaped()
			. ' <samp>&lt;source lang=&quot;html4strict&quot;&gt;...&lt;/source&gt;</samp></p>'
			. '<p>' . wfMessage( 'syntaxhighlight-supported' )->inContentLanguage()->escaped()
			. '</p>' . self::formatLanguages();
		return "<div style=\"border: solid red 1px; padding: .5em;\">{$html}</div>";
	}

	/**
	 * Format the list of supported languages
	 *
	 * @return string
	 */
	private static function formatLanguages() {
		$langs = self::getSupportedLanguages();
		$list = array();
		if( count( $langs ) > 0 ) {
			foreach( $langs as $lang ) {
				$list[] = '<samp>' . htmlspecialchars( $lang ) . '</samp>';
			}
			return '<p class="mw-collapsible mw-collapsed" style="padding: 0em 1em;">' . implode( ', ', $list ) . '</p><br style="clear: all"/>';
		} else {
			return '<p>' . wfMessage( 'syntaxhighlight-err-loading' )->inContentLanguage()->escaped() . '</p>';
		}
	}

	/**
	 * Get the list of supported languages
	 *
	 * @return array
	 */
	private static function getSupportedLanguages() {
		if( !is_array( self::$languages ) ) {
			self::initialise();
			self::$languages = array();
			foreach( glob( GESHI_LANG_ROOT . "/*.php" ) as $file ) {
				self::$languages[] = basename( $file, '.php' );
			}
			sort( self::$languages );
		}
		return self::$languages;
	}

	/**
	 * Initialise messages and ensure the GeSHi class is loaded
	 * @return bool
	 */
	private static function initialise() {
		if( !self::$initialised ) {
			if( !class_exists( 'GeSHi' ) ) {
				require( dirname( __FILE__ ) . '/geshi/geshi.php' );
			}
			self::$initialised = true;
		}
		return true;
	}

	/**
	 * Register a ResourceLoader module providing styles for each supported language.
	 *
	 * @param ResourceLoader $resourceLoader
	 * @return bool true
	 */
	public static function resourceLoaderRegisterModules( &$resourceLoader ) {
		$modules = array();

		foreach ( self::getSupportedLanguages() as $lang ) {
			$modules["ext.geshi.language.$lang" ] = array(
				'class' => 'ResourceLoaderGeSHiModule',
				'lang' => $lang,
			);
		}

		$resourceLoader->register( $modules );

		return true;
	}
}
