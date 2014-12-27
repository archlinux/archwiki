<?php
/**
 * Hooks for InputBox extension
 *
 * @file
 * @ingroup Extensions
 */

// InputBox hooks
class InputBoxHooks {
	// Initialization
	public static function register( Parser &$parser ) {
		// Register the hook with the parser
		$parser->setHook( 'inputbox', array( 'InputBoxHooks', 'render' ) );

		// Continue
		return true;
	}

	// Prepend prefix to wpNewTitle if necessary
	public static function onSpecialPageBeforeExecute( $special, $subPage ) {
		$request = $special->getRequest();
		$prefix = $request->getText( 'prefix', '' );
		$title = $request->getText( 'wpNewTitle', '' );
		if ( $special->getName() == 'Movepage' && $prefix !== '' && $title !== '' ) {
			$request->setVal( 'wpNewTitle', $prefix . $title );
			$request->unsetVal( 'prefix' );
		}
		return true;
	}

	// Render the input box
	public static function render( $input, $args, Parser $parser ) {
		// Create InputBox
		$inputBox = new InputBox( $parser );

		// Configure InputBox
		$inputBox->extractOptions( $parser->replaceVariables( $input ) );

		// Return output
		return $inputBox->render();
	}

	/**
	 * <inputbox type=create...> sends requests with action=edit, and
	 * possibly a &prefix=Foo.  So we pick that up here, munge prefix
	 * and title together, and redirect back out to the real page
	 * @param $output OutputPage
	 * @param $article Article
	 * @param $title Title
	 * @param $user User
	 * @param $request WebRequest
	 * @param $wiki MediaWiki
	 * @return bool
	 */
	public static function onMediaWikiPerformAction(
		$output,
		$article,
		$title,
		$user,
		$request,
		$wiki
	) {
		if( $wiki->getAction( $request ) !== 'edit' ){
			# not our problem
			return true;
		}
		if( $request->getText( 'prefix', '' ) === '' ){
			# Fine
			return true;
		}

		$params = $request->getValues();
		$title = $params['prefix'];
		if ( isset( $params['title'] ) ) {
			$title .= $params['title'];
		}
		unset( $params['prefix'] );
		$params['title'] = $title;

		global $wgScript;
		$output->redirect( wfAppendQuery( $wgScript, $params ), '301' );
		return false;
	}
}
