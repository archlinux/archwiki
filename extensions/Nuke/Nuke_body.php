<?php

class SpecialNuke extends SpecialPage {
	
	public function __construct() {
		parent::__construct( 'Nuke', 'nuke' );
	}

	public function execute( $par ){
		global $wgUser, $wgRequest;

		if( !$this->userCanExecute( $wgUser ) ){
			$this->displayRestrictionError();
			return;
		}

		$this->setHeaders();
		$this->outputHeader();
		
		if( $wgRequest->wasPosted() && $wgUser->matchEditToken( $wgRequest->getVal( 'wpEditToken' ) ) ) {
			$target = $wgRequest->getText( 'target', $par );
				
			// Normalise name
			if ( $target !== '' ) {
				$user = User::newFromName( $target );
				if ( $user ) $target = $user->getName();
			}

			$reason = $wgRequest->getText(
				'wpReason',
				wfMsgForContent(
					'nuke-defaultreason',
					$target === '' ? wfMsg( 'nuke-multiplepeople' ) : $target
				)
			);
			
			if ( $wgRequest->getVal( 'action' ) == 'delete' ) {
				$pages = $wgRequest->getArray( 'pages' );
				
				if( $pages ) {
					return $this->doDelete( $pages, $reason );
				}
			}
			else {
				$this->listForm( $target, $reason, $wgRequest->getInt( 'limit' ) );
			}
		}
		else {
			$this->promptForm();
		}
	}

	/**
	 * Prompt for a username or IP address.
	 */
	protected function promptForm() {
		global $wgOut, $wgUser;

		$wgOut->addWikiMsg( 'nuke-tools' );
		
		$wgOut->addHTML(
			Xml::openElement(
				'form',
				array(
					'action' => $this->getTitle()->getLocalURL( 'action=submit' ),
					'method' => 'post'
				)
			)
			. '<table><tr>'
				. '<td>' . htmlspecialchars( wfMsg( 'nuke-userorip' ) ) . '</td>'
				. '<td>' . Xml::input( 'target', 40 ) . '</td>'
			. '</tr><tr>'
				. '<td>' . htmlspecialchars( wfMsg( 'nuke-maxpages' ) ) . '</td>'
				. '<td>' . Xml::input( 'limit', 7, '500' ) . '</td>'
			. '</tr><tr>'
				. '<td></td>'
				. '<td>' . Xml::submitButton( wfMsg( 'nuke-submit-user' ) ) . '</td>'
			.'</tr></table>'  
			. Html::hidden( 'wpEditToken', $wgUser->editToken() ) 
			. Xml::closeElement( 'form' )
		);
	}

	/**
	 * Display list of pages to delete.
	 * 
	 * @param string $username
	 * @param string $reason
	 * @param integer $limit
	 */
	protected function listForm( $username, $reason, $limit ) {
		global $wgUser, $wgOut, $wgLang;

		$pages = $this->getNewPages( $username, $limit );

		if( count( $pages ) == 0 ) {
			$wgOut->addWikiMsg( 'nuke-nopages', $username );
			return $this->promptForm();
		}
		
		if ( $username == '' ) {
			$wgOut->addWikiMsg( 'nuke-list-multiple' );
		}
		else {
			$wgOut->addWikiMsg( 'nuke-list', $username );
		}

		$nuke = $this->getTitle();

		$script = <<<JAVASCRIPT
<script type="text/javascript">
function selectPages( bool ) {
	var inputs = document.getElementsByTagName("input");
	for (i = 0; i < inputs.length; i++) {
		if (inputs[i].type == "checkbox") {
			inputs[i].checked = bool;
		}
	}
}
</script>
JAVASCRIPT;
		$wgOut->addScript( $script );

		$wgOut->addHTML(
			Xml::openElement( 'form', array(
				'action' => $nuke->getLocalURL( 'action=delete' ),
				'method' => 'post',
				'name' => 'nukelist')
			) .
			Html::hidden( 'wpEditToken', $wgUser->editToken() ) .
			Xml::tags( 'p',
				null,
				Xml::inputLabel(
					wfMsg( 'deletecomment' ), 'wpReason', 'wpReason', 60, $reason
				)
			)
		);

		// Select: All, None
		$links = array();
		$links[] = '<a href="#" onclick="selectPages( true ); return false;">' . 
			wfMsg( 'powersearch-toggleall' ) . '</a>';
		$links[] = '<a href="#" onclick="selectPages( false ); return false;">' . 
			wfMsg( 'powersearch-togglenone' ) . '</a>';
		$wgOut->addHTML(
			Xml::tags( 'p',
				null,
				wfMsg( 'nuke-select', $wgLang->commaList( $links ) )
			)
		);

		// Delete button
		$wgOut->addHTML(
			Xml::submitButton( wfMsg( 'nuke-submit-delete' ) )
		);

		$wgOut->addHTML( '<ul>' );

		$sk = $wgUser->getSkin();
		foreach( $pages as $info ) {
			list( $title, $edits, $userName ) = $info;
			$image = $title->getNamespace() == NS_IMAGE ? wfLocalFile( $title ) : false;
			$thumb = $image && $image->exists() ? $image->transform( array( 'width' => 120, 'height' => 120 ), 0 ) : false;

			$changes = wfMsgExt( 'nchanges', 'parsemag', $wgLang->formatNum( $edits ) );
			
			$wgOut->addHTML( '<li>' .
				Xml::check( 'pages[]', true,
					array( 'value' =>  $title->getPrefixedDbKey() )
				) .
				'&#160;' .
				( $thumb ? $thumb->toHtml( array( 'desc-link' => true ) ) : '' ) .
				$sk->makeKnownLinkObj( $title ) .
				'&#160;(' .
				( $userName ? wfMsgExt( 'nuke-editby', 'parseinline', $userName ) . ',&#160;' : '' ) .
				$sk->makeKnownLinkObj( $title, $changes, 'action=history' ) .
				")</li>\n" );
		}
		
		$wgOut->addHTML(
			"</ul>\n" .
			Xml::submitButton( wfMsg( 'nuke-submit-delete' ) ) .
			"</form>"
		);
	}

	/**
	 * Gets a list of new pages by the specified user or everyone when none is specified.
	 * 
	 * @param string $username
	 * @param integer $limit
	 * 
	 * @return array
	 */
	protected function getNewPages( $username, $limit ) {
		$dbr = wfGetDB( DB_SLAVE );
		
		$what = array(
			'rc_namespace',
			'rc_title',
			'rc_timestamp',
			'COUNT(*) AS edits'
		);		
		
		$where = array( "(rc_new = 1) OR (rc_log_type = 'upload' AND rc_log_action = 'upload')" );
		
		if ( $username == '' ) {
			$what[] = 'rc_user_text';
		}
		else {
			$where['rc_user_text'] = $username;
		}
		
		$result = $dbr->select( 'recentchanges',
			$what,
			$where,
			__METHOD__,
			array(
				'ORDER BY' => 'rc_timestamp DESC',
				'GROUP BY' => 'rc_namespace, rc_title',
				'LIMIT' => $limit
			)
		);
		
		$pages = array();
		
		foreach ( $result as $row ) {
			$pages[] = array(
				Title::makeTitle( $row->rc_namespace, $row->rc_title ),
				$row->edits,
				$username == '' ? $row->rc_user_text : false
			);
		}
		
		$dbr->freeResult( $result );
		
		return $pages;
	}

	/**
	 * Does the actual deletion of the pages.
	 * 
	 * @param array $pages The pages to delete
	 * @param string $reason
	 */
	protected function doDelete( array $pages, $reason ) {
		global $wgOut;

		$res = array();
		foreach( $pages as $page ) {
			$title = Title::newFromURL( $page );
			$file = $title->getNamespace() == NS_FILE ? wfLocalFile( $title ) : false;
			if ( $file ) {
				$oldimage = null; // Must be passed by reference
				$ok = FileDeleteForm::doDelete( $title, $file, $oldimage, $reason, false )->isOK();
			} else {
				$article = new Article( $title, 0 );
				$ok = $article->doDeleteArticle( $reason );
			}
			if ( $ok ) {
				$res[] = wfMsgExt( 'nuke-deleted', array( 'parseinline' ), $title->getPrefixedText() );
			} else {
				$res[] = wfMsgExt( 'nuke-not-deleted', array( 'parseinline' ), $title->getPrefixedText() );
			}
		}
		$wgOut->addHTML( "<ul>\n<li>" .implode( "</li>\n<li>", $res ) . "</li>\n</ul>\n" );
	}
}
