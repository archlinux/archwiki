<?php
/**
 * This class handles formatting poems in WikiText, specifically anything within
 * <poem></poem> tags.
 */
class Poem {
	/**
	 * Bind the renderPoem function to the <poem> tag
	 * @param Parser $parser
	 * @return bool true
	 */
	public static function init( &$parser ) {
		$parser->setHook( 'poem', array( 'Poem', 'renderPoem' ) );
		return true;
	}

	/**
	 * Parse the text into proper poem format
	 * @param string $in The text inside the poem tag
	 * @param array $param
	 * @param Parser $parser
	 * @param boolean $frame
	 * @return string
	 */
	public static function renderPoem( $in, $param = array(), $parser = null, $frame = false ) {
		// using newlines in the text will cause the parser to add <p> tags,
		// which may not be desired in some cases
		$newline = isset( $param['compact'] ) ? '' : "\n";

		$tag = $parser->insertStripItem( "<br />", $parser->mStripState );

		// replace colons with indented spans
		$text = preg_replace_callback( '/^(:+)(.+)$/m', array( 'Poem', 'indentVerse' ), $in );

		// replace newlines with <br /> tags unless they are at the beginning or end
		// of the poem
		$text = preg_replace(
			array( "/^\n/", "/\n$/D", "/\n/" ),
			array( "", "", "$tag\n" ),
			$text );

		// replace spaces at the beginning of a line with non-breaking spaces
		$text = preg_replace_callback( '/^( +)/m', array( 'Poem', 'replaceSpaces' ), $text );

		$text = $parser->recursiveTagParse( $text, $frame );

		$attribs = Sanitizer::validateTagAttributes( $param, 'div' );

		// Wrap output in a <div> with "poem" class.
		if ( isset( $attribs['class'] ) ) {
			$attribs['class'] = 'poem ' . $attribs['class'];
		} else {
			$attribs['class'] = 'poem';
		}

		return Html::rawElement( 'div', $attribs, $newline . trim( $text ) . $newline );
	}

	/**
	 * Callback for preg_replace_callback() that replaces spaces with non-breaking spaces
	 * @param array $m Matches from the regular expression
	 *   - $m[1] consists of 1 or more spaces
	 * @return mixed
	 */
	protected static function replaceSpaces( $m ) {
		return str_replace( ' ', '&#160;', $m[1] );
	}

	/**
	 * Callback for preg_replace_callback() that wraps content in an indented span
	 * @param array $m Matches from the regular expression
	 *   - $m[1] consists of 1 or more colons
	 *   - $m[2] consists of the text after the colons
	 * @return string
	 */
	protected static function indentVerse( $m ) {
		$attribs = array(
			'class' => 'mw-poem-indented',
			'style' => 'display: inline-block; margin-left: ' . strlen( $m[1] ) . 'em;'
		);
		// @todo Should this really be raw?
		return Html::rawElement( 'span', $attribs, $m[2] );
	}
}
