<?php

/**
 * Special page that allows authorised users to rename
 * user accounts
 */
class SpecialRenameuser extends SpecialPage {
	public function __construct() {
		parent::__construct( 'Renameuser', 'renameuser' );
	}

	/**
	 * Show the special page
	 *
	 * @param mixed $par Parameter passed to the page
	 * @throws PermissionsError
	 * @throws ReadOnlyError
	 * @throws UserBlockedError
	 */
	public function execute( $par ) {
		global $wgContLang, $wgCapitalLinks;

		$this->setHeaders();

		$out = $this->getOutput();
		$out->addWikiMsg( 'renameuser-summary' );

		$user = $this->getUser();
		if ( !$user->isAllowed( 'renameuser' ) ) {
			throw new PermissionsError( 'renameuser' );
		}

		if ( wfReadOnly() ) {
			throw new ReadOnlyError;
		}

		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $this->getUser()->mBlock );
		}

		$request = $this->getRequest();
		$showBlockLog = $request->getBool( 'submit-showBlockLog' );
		$usernames = explode( '/', $par, 2 ); // this works as "/" is not valid in usernames
		$oldnamePar = trim( str_replace( '_', ' ', $request->getText( 'oldusername', $usernames[0] ) ) );
		$oldusername = Title::makeTitle( NS_USER, $oldnamePar );
		$newnamePar = isset( $usernames[1] ) ? $usernames[1] : null;
		$newnamePar = trim( str_replace( '_', ' ', $request->getText( 'newusername', $newnamePar ) ) );
		// Force uppercase of newusername, otherwise wikis
		// with wgCapitalLinks=false can create lc usernames
		$newusername = Title::makeTitleSafe( NS_USER, $wgContLang->ucfirst( $newnamePar ) );
		$oun = is_object( $oldusername ) ? $oldusername->getText() : '';
		$nun = is_object( $newusername ) ? $newusername->getText() : '';
		$token = $user->getEditToken();
		$reason = $request->getText( 'reason' );

		$move_checked = $request->getBool( 'movepages', !$request->wasPosted() );
		$suppress_checked = $request->getCheck( 'suppressredirect' );

		$warnings = array();
		if ( $oun && $nun && !$request->getCheck( 'confirmaction' ) ) {
			Hooks::run( 'RenameUserWarning', array( $oun, $nun, &$warnings ) );
		}

		$out->addHTML(
			Xml::openElement( 'form', array(
				'method' => 'post',
				'action' => $this->getPageTitle()->getLocalURL(),
				'id' => 'renameuser'
			) ) .
			Xml::openElement( 'fieldset' ) .
			Xml::element( 'legend', null, $this->msg( 'renameuser' )->text() ) .
			Xml::openElement( 'table', array( 'id' => 'mw-renameuser-table' ) ) .
			"<tr>
				<td class='mw-label'>" .
			Xml::label( $this->msg( 'renameuserold' )->text(), 'oldusername' ) .
			"</td>
				<td class='mw-input'>" .
			Xml::input( 'oldusername', 20, $oun, array( 'type' => 'text', 'tabindex' => '1' ) ) . ' ' .
			"</td>
			</tr>
			<tr>
				<td class='mw-label'>" .
			Xml::label( $this->msg( 'renameusernew' )->text(), 'newusername' ) .
			"</td>
				<td class='mw-input'>" .
			Xml::input( 'newusername', 20, $nun, array( 'type' => 'text', 'tabindex' => '2' ) ) .
			"</td>
			</tr>
			<tr>
				<td class='mw-label'>" .
			Xml::label( $this->msg( 'renameuserreason' )->text(), 'reason' ) .
			"</td>
				<td class='mw-input'>" .
			Xml::input(
				'reason',
				40,
				$reason,
				array( 'type' => 'text', 'tabindex' => '3', 'maxlength' => 255 )
			) .
			'</td>
			</tr>'
		);
		if ( $user->isAllowed( 'move' ) ) {
			$out->addHTML( "
				<tr>
					<td>&#160;
					</td>
					<td class='mw-input'>" .
				Xml::checkLabel( $this->msg( 'renameusermove' )->text(), 'movepages', 'movepages',
					$move_checked, array( 'tabindex' => '4' ) ) .
				'</td>
				</tr>'
			);

			if ( $user->isAllowed( 'suppressredirect' ) ) {
				$out->addHTML( "
					<tr>
						<td>&#160;
						</td>
						<td class='mw-input'>" .
					Xml::checkLabel(
						$this->msg( 'renameusersuppress' )->text(),
						'suppressredirect',
						'suppressredirect',
						$suppress_checked,
						array( 'tabindex' => '5' )
					) .
					'</td>
					</tr>'
				);
			}
		}
		if ( $warnings ) {
			$warningsHtml = array();
			foreach ( $warnings as $warning ) {
				$warningsHtml[] = is_array( $warning ) ?
					$this->msg( $warning[0] )->rawParams( array_slice( $warning, 1 ) )->escaped() :
					$this->msg( $warning )->escaped();
			}

			$out->addHTML( "
				<tr>
					<td class='mw-label'>" . $this->msg( 'renameuserwarnings' )->escaped() . "
					</td>
					<td class='mw-input'>" .
				'<ul style="color: red; font-weight: bold"><li>' .
				implode( '</li><li>', $warningsHtml ) . '</li></ul>' .
				'</td>
				</tr>'
			);
			$out->addHTML( "
				<tr>
					<td>&#160;
					</td>
					<td class='mw-input'>" .
				Xml::checkLabel(
					$this->msg( 'renameuserconfirm' )->text(),
					'confirmaction',
					'confirmaction',
					false,
					array( 'tabindex' => '6' )
				) .
				'</td>
				</tr>'
			);
		}
		$out->addHTML( "
			<tr>
				<td>&#160;
				</td>
				<td class='mw-submit'>" .
			Xml::submitButton(
				$this->msg( 'renameusersubmit' )->text(),
				array(
					'name' => 'submit',
					'tabindex' => '7',
					'id' => 'submit'
				)
			) .
			' ' .
			Xml::submitButton(
				$this->msg( 'renameuser-submit-blocklog' )->text(),
				array(
					'name' => 'submit-showBlockLog',
					'id' => 'submit-showBlockLog',
					'tabindex' => '8'
				)
			) .
			'</td>
			</tr>' .
			Xml::closeElement( 'table' ) .
			Xml::closeElement( 'fieldset' ) .
			Html::hidden( 'token', $token ) .
			Xml::closeElement( 'form' ) . "\n"
		);

		// Show block log if requested
		if ( $showBlockLog && is_object( $oldusername ) ) {
			$this->showLogExtract( $oldusername, 'block', $out );

			return;
		}

		if ( $request->getText( 'token' ) === '' ) {
			# They probably haven't even submitted the form, so don't go further.
			return;
		} elseif ( $warnings ) {
			# Let user read warnings
			return;
		} elseif ( !$request->wasPosted() || !$user->matchEditToken( $request->getVal( 'token' ) ) ) {
			$out->wrapWikiMsg( "<div class=\"errorbox\">$1</div>", 'renameuser-error-request' );

			return;
		} elseif ( !is_object( $oldusername ) ) {
			$out->wrapWikiMsg( "<div class=\"errorbox\">$1</div>",
				array( 'renameusererrorinvalid', $request->getText( 'oldusername' ) ) );

			return;
		} elseif ( !is_object( $newusername ) ) {
			$out->wrapWikiMsg( "<div class=\"errorbox\">$1</div>",
				array( 'renameusererrorinvalid', $request->getText( 'newusername' ) ) );

			return;
		} elseif ( $oldusername->getText() === $newusername->getText() ) {
			$out->wrapWikiMsg( "<div class=\"errorbox\">$1</div>", 'renameuser-error-same-user' );

			return;
		}

		// Suppress username validation of old username
		$olduser = User::newFromName( $oldusername->getText(), false );
		$newuser = User::newFromName( $newusername->getText(), 'creatable' );

		// It won't be an object if for instance "|" is supplied as a value
		if ( !is_object( $olduser ) ) {
			$out->wrapWikiMsg( "<div class=\"errorbox\">$1</div>",
				array( 'renameusererrorinvalid', $oldusername->getText() ) );

			return;
		}
		if ( !is_object( $newuser ) || !User::isCreatableName( $newuser->getName() ) ) {
			$out->wrapWikiMsg( "<div class=\"errorbox\">$1</div>",
				array( 'renameusererrorinvalid', $newusername->getText() ) );

			return;
		}

		// Check for the existence of lowercase oldusername in database.
		// Until r19631 it was possible to rename a user to a name with first character as lowercase
		if ( $oldusername->getText() !== $wgContLang->ucfirst( $oldusername->getText() ) ) {
			// oldusername was entered as lowercase -> check for existence in table 'user'
			$dbr = wfGetDB( DB_SLAVE );
			$uid = $dbr->selectField( 'user', 'user_id',
				array( 'user_name' => $oldusername->getText() ),
				__METHOD__ );
			if ( $uid === false ) {
				if ( !$wgCapitalLinks ) {
					$uid = 0; // We are on a lowercase wiki but lowercase username does not exists
				} else {
					// We are on a standard uppercase wiki, use normal
					$uid = $olduser->idForName();
					$oldusername = Title::makeTitleSafe( NS_USER, $olduser->getName() );
				}
			}
		} else {
			// oldusername was entered as upperase -> standard procedure
			$uid = $olduser->idForName();
		}

		if ( $uid === 0 ) {
			$out->wrapWikiMsg( "<div class=\"errorbox\">$1</div>",
				array( 'renameusererrordoesnotexist', $oldusername->getText() ) );

			return;
		}

		if ( $newuser->idForName() !== 0 ) {
			$out->wrapWikiMsg( "<div class=\"errorbox\">$1</div>",
				array( 'renameusererrorexists', $newusername->getText() ) );

			return;
		}

		// Give other affected extensions a chance to validate or abort
		if ( !Hooks::run(
			'RenameUserAbort',
			array( $uid, $oldusername->getText(), $newusername->getText() )
		) ) {
			return;
		}

		// Do the heavy lifting...
		$rename = new RenameuserSQL(
			$oldusername->getText(),
			$newusername->getText(),
			$uid,
			$this->getUser(),
			array( 'reason' => $reason )
		);
		if ( !$rename->rename() ) {
			return;
		}

		// If this user is renaming his/herself, make sure that Title::moveTo()
		// doesn't make a bunch of null move edits under the old name!
		if ( $user->getId() === $uid ) {
			$user->setName( $newusername->getText() );
		}

		// Move any user pages
		if ( $request->getCheck( 'movepages' ) && $user->isAllowed( 'move' ) ) {
			$dbr = wfGetDB( DB_SLAVE );

			$pages = $dbr->select(
				'page',
				array( 'page_namespace', 'page_title' ),
				array(
					'page_namespace IN (' . NS_USER . ',' . NS_USER_TALK . ')',
					'(page_title ' . $dbr->buildLike( $oldusername->getDBkey() . '/', $dbr->anyString() ) .
					' OR page_title = ' . $dbr->addQuotes( $oldusername->getDBkey() ) . ')'
				),
				__METHOD__
			);

			$suppressRedirect = false;

			if ( $request->getCheck( 'suppressredirect' ) && $user->isAllowed( 'suppressredirect' ) ) {
				$suppressRedirect = true;
			}

			$output = '';
			foreach ( $pages as $row ) {
				$oldPage = Title::makeTitleSafe( $row->page_namespace, $row->page_title );
				$newPage = Title::makeTitleSafe( $row->page_namespace,
					preg_replace( '!^[^/]+!', $newusername->getDBkey(), $row->page_title ) );
				# Do not autodelete or anything, title must not exist
				if ( $newPage->exists() && !$oldPage->isValidMoveTarget( $newPage ) ) {
					$link = Linker::linkKnown( $newPage );
					$output .= Html::rawElement(
						'li',
						array( 'class' => 'mw-renameuser-pe' ),
						$this->msg( 'renameuser-page-exists' )->rawParams( $link )->escaped()
					);
				} else {
					$success = $oldPage->moveTo(
						$newPage,
						false,
						$this->msg(
							'renameuser-move-log',
							$oldusername->getText(),
							$newusername->getText() )->inContentLanguage()->text(),
						!$suppressRedirect
					);
					if ( $success === true ) {
						# oldPage is not known in case of redirect suppression
						$oldLink = Linker::link( $oldPage, null, array(), array( 'redirect' => 'no' ) );

						# newPage is always known because the move was successful
						$newLink = Linker::linkKnown( $newPage );

						$output .= Html::rawElement(
							'li',
							array( 'class' => 'mw-renameuser-pm' ),
							$this->msg( 'renameuser-page-moved' )->rawParams( $oldLink, $newLink )->escaped()
						);
					} else {
						$oldLink = Linker::linkKnown( $oldPage );
						$newLink = Linker::link( $newPage );
						$output .= Html::rawElement(
							'li', array( 'class' => 'mw-renameuser-pu' ),
							$this->msg( 'renameuser-page-unmoved' )->rawParams( $oldLink, $newLink )->escaped()
						);
					}
				}
			}
			if ( $output ) {
				$out->addHTML( Html::rawElement( 'ul', array(), $output ) );
			}
		}

		// Output success message stuff :)
		$out->wrapWikiMsg( "<div class=\"successbox\">$1</div><br style=\"clear:both\" />",
			array( 'renameusersuccess', $oldusername->getText(), $newusername->getText() ) );
	}

	/**
	 * @param $username Title
	 * @param $type
	 * @param $out OutputPage
	 */
	protected function showLogExtract( $username, $type, &$out ) {
		# Show relevant lines from the logs:
		$logPage = new LogPage( $type );
		$out->addHTML( Xml::element( 'h2', null, $logPage->getName()->text() ) . "\n" );
		LogEventsList::showLogExtract( $out, $type, $username->getPrefixedText() );
	}

	protected function getGroupName() {
		return 'users';
	}
}
