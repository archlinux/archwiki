<?php
/**
 * Hooks for InputBox extension
 *
 * @file
 * @ingroup Extensions
 */

namespace MediaWiki\Extension\InputBox;

use MediaWiki\Actions\ActionEntryPoint;
use MediaWiki\Config\Config;
use MediaWiki\Hook\MediaWikiPerformActionHook;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Output\OutputPage;
use MediaWiki\Page\Article;
use MediaWiki\Parser\Parser;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\WebRequest;
use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

/**
 * InputBox hooks
 */
class InputBoxHooks implements
	ParserFirstCallInitHook,
	SpecialPageBeforeExecuteHook,
	MediaWikiPerformActionHook
{
	/** @var Config */
	private $config;

	private ExtensionRegistry $extensionRegistry;

	/**
	 * @param Config $config
	 */
	public function __construct(
		Config $config,
		ExtensionRegistry $extensionRegistry
	) {
		$this->config = $config;
		$this->extensionRegistry = $extensionRegistry;
	}

	/**
	 * Initialization
	 * @param Parser $parser
	 */
	public function onParserFirstCallInit( $parser ) {
		// Register the hook with the parser
		$parser->setHook( 'inputbox', [ $this, 'render' ] );
	}

	/**
	 * If necssary, prepend prefix to wpNewTitle, or update the search parameter with the searchfilter.
	 *
	 * @param SpecialPage $special
	 * @param string|null $subPage Subpage string, or null if no subpage was specified
	 * @return bool|void True or no return value to continue or false to prevent execution
	 */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		$request = $special->getRequest();
		$prefix = $request->getText( 'prefix', '' );
		$title = $request->getText( 'wpNewTitle', '' );
		$search = $request->getText( 'search', '' );
		$searchfilter = $request->getText( 'searchfilter', '' );
		if ( $special->getName() === 'Movepage' && $prefix !== '' && $title !== '' ) {
			$request->setVal( 'wpNewTitle', $prefix . $title );
			$request->unsetVal( 'prefix' );
		}
		if ( in_array( $special->getName(), [ 'Search', 'MediaSearch' ] ) && $searchfilter !== '' ) {
			// Redirect to Special:Search after modifying the search parameter.
			$searchQuery = $request->getQueryValues();
			$searchQuery[ 'search' ] = trim( $search ) . ' ' . $searchfilter;
			unset( $searchQuery['searchfilter'], $searchQuery['title'] );
			$special->getOutput()->redirect( $special->getPageTitle()->getFullURL( $searchQuery ) );
			return false;
		}
	}

	/**
	 * Render the input box
	 * @param string|null $input
	 * @param array $args
	 * @param Parser $parser
	 * @return string
	 */
	public function render( $input, $args, Parser $parser ) {
		if ( $input === null ) {
			return '';
		}

		// Create InputBox
		$inputBox = new InputBox( $this->config, $this->extensionRegistry, $parser );

		// Configure InputBox
		$inputBox->extractOptions( $parser->replaceVariables( $input ) );

		// Return output
		return $inputBox->render();
	}

	/**
	 * <inputbox type=create...> sends requests with action=edit, and
	 * possibly a &prefix=Foo.  So we pick that up here, munge prefix
	 * and title together, and redirect back out to the real page
	 * @param OutputPage $output
	 * @param Article $article
	 * @param Title $title
	 * @param User $user
	 * @param WebRequest $request
	 * @param ActionEntryPoint $wiki
	 * @return bool
	 */
	public function onMediaWikiPerformAction(
		$output,
		$article,
		$title,
		$user,
		$request,
		$wiki
	) {
		// In order to check for 'action=edit' in URL parameters, even if another extension overrides
		// the action, we must not use getActionName() here. (T337436)
		if ( $request->getRawVal( 'action' ) !== 'edit' && $request->getRawVal( 'veaction' ) !== 'edit' ) {
			// not our problem
			return true;
		}
		$prefix = $request->getText( 'prefix', '' );
		if ( $prefix === '' ) {
			// Fine
			return true;
		}

		$title = $prefix . $request->getText( 'title', '' );
		$params = $request->getValues();
		unset( $params['prefix'] );
		$params['title'] = $title;

		$output->redirect( wfAppendQuery( $output->getConfig()->get( 'Script' ), $params ), '301' );
		return false;
	}
}
