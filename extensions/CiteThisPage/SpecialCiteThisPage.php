<?php

class SpecialCiteThisPage extends SpecialPage {
	public function __construct() {
		parent::__construct( 'CiteThisPage' );
	}

	public function execute( $par ) {
		global $wgUseTidy;

		// Having tidy on causes whitespace and <pre> tags to
		// be generated around the output of the CiteThisPageOutput
		// class TODO FIXME.
		$wgUseTidy = false;

		$this->setHeaders();
		$this->outputHeader();

		$page = $par !== null ? $par : $this->getRequest()->getText( 'page' );
		$title = Title::newFromText( $page );

		$this->showForm( $title );

		if ( $title && $title->exists() ) {
			$id = $this->getRequest()->getInt( 'id' );
			$cout = new CiteThisPageOutput( $title, $id );
			$cout->execute();
		}
	}

	private function showForm( Title $title = null ) {
		$this->getOutput()->addHTML(
			Xml::openElement( 'form',
				array(
					'id' => 'specialCiteThisPage',
					'method' => 'get',
					'action' => wfScript(),
				) ) .
			Html::hidden( 'title', SpecialPage::getTitleFor( 'CiteThisPage' )->getPrefixedDBkey() ) .
			Xml::openElement( 'label' ) .
			$this->msg( 'citethispage-change-target' )->escaped() . ' ' .
			Xml::element( 'input',
				array(
					'type' => 'text',
					'size' => 30,
					'name' => 'page',
					'value' => $title ? $title->getPrefixedText() : ''
				),
				''
			) .
			' ' .
			Xml::element( 'input',
				array(
					'type' => 'submit',
					'value' => $this->msg( 'citethispage-change-submit' )->escaped()
				),
				''
			) .
			Xml::closeElement( 'label' ) .
			Xml::closeElement( 'form' )
		);
	}

	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return (usually 10)
	 * @param int $offset Number of results to skip (usually 0)
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		$title = Title::newFromText( $search );
		if ( !$title || !$title->canExist() ) {
			// No prefix suggestion in special and media namespace
			return array();
		}
		// Autocomplete subpage the same as a normal search
		$prefixSearcher = new StringPrefixSearch;
		$result = $prefixSearcher->search( $search, $limit, array(), $offset );
		return $result;
	}

	protected function getGroupName() {
		return 'pagetools';
	}
}

class CiteThisPageOutput {
	/**
	 * @var Title
	 */
	public $mTitle;

	/**
	 * @var Article
	 */
	public $mArticle;

	public $mId;

	/**
	 * @var Parser
	 */
	public $mParser;

	/**
	 * @var ParserOptions
	 */
	public $mParserOptions;

	public $mSpTitle;

	function __construct( $title, $id ) {
		global $wgHooks, $wgParser;

		$this->mTitle = $title;
		$this->mArticle = new Article( $title );
		$this->mId = $id;

		$wgHooks['ParserGetVariableValueVarCache'][] = array( $this, 'varCache' );

		$this->genParserOptions();
		$this->genParser();

		$wgParser->setHook( 'citation', array( $this, 'citationTagParse' ) );
	}

	function execute() {
		global $wgOut, $wgParser, $wgHooks;

		$wgHooks['ParserGetVariableValueTs'][] = array( $this, 'timestamp' );

		$msg = wfMessage( 'citethispage-content' )->inContentLanguage()->plain();
		if ( $msg == '' ) {
			# With MediaWiki 1.20 the plain text files were deleted
			# and the text moved into SpecialCite.i18n.php
			# This code is kept for b/c in case an installation has its own file "citethispage-content-xx"
			# for a previously not supported language.
			global $wgContLang, $wgContLanguageCode;
			$dir = __DIR__ . DIRECTORY_SEPARATOR;
			$code = $wgContLang->lc( $wgContLanguageCode );
			if ( file_exists( "${dir}citethispage-content-$code" ) ) {
				$msg = file_get_contents( "${dir}citethispage-content-$code" );
			} elseif( file_exists( "${dir}citethispage-content" ) ){
				$msg = file_get_contents( "${dir}citethispage-content" );
			}
		}
		$ret = $wgParser->parse(
			$msg, $this->mTitle, $this->mParserOptions, false, true, $this->getRevId()
		);
		$wgOut->addModuleStyles( 'ext.citeThisPage' );

		# Introduced in 1.24
		if ( method_exists( $wgOut, 'addParserOutputContent' ) ) {
			$wgOut->addParserOutputContent( $ret );
		} else {
			$wgOut->addHTML( $ret->getText() );
		}
	}

	function genParserOptions() {
		global $wgUser;
		$this->mParserOptions = ParserOptions::newFromUser( $wgUser );
		$this->mParserOptions->setDateFormat( 'default' );
		$this->mParserOptions->setEditSection( false );
	}

	function genParser() {
		$this->mParser = new Parser;
		$this->mSpTitle = SpecialPage::getTitleFor( 'CiteThisPage' );
	}

	function citationTagParse( $in, $argv ) {
		$ret = $this->mParser->parse( $in, $this->mSpTitle, $this->mParserOptions, false );

		return $ret->getText();
	}

	function varCache() {
		return false;
	}

	function timestamp( &$parser, &$ts ) {
		if ( isset( $parser->mTagHooks['citation'] ) ) {
			$ts = wfTimestamp( TS_UNIX, $this->mArticle->getTimestamp() );
		}

		return true;
	}

	function getRevId() {
		if ( $this->mId ) {
			return $this->mId;
		} else {
			return $this->mTitle->getLatestRevID();
		}
	}
}
