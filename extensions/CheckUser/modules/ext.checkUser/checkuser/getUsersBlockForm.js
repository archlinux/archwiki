/**
 * Implements the Special:CheckUser's 'Get users' block form which links to
 * Special:InvestigateBlock to do the actual blocking.
 *
 * This also adds links to Special:MultiLock if CentralAuth is installed and the user has
 * the rights to use that special page.
 *
 * @param {string} documentRoot The root element to append hidden forms to. Defaults to 'body'.
 * @return {boolean} Returns false if the function returned early, otherwise true.
 */
module.exports = function ( documentRoot ) {
	const $userCheckboxes = $( '#checkuserresults li [type=checkbox]' ),
		$checkUserBlockFieldset = $( '.mw-checkuser-massblock fieldset' ),
		$blockAccountsButton = $( '.mw-checkuser-massblock-accounts-button', $checkUserBlockFieldset ),
		$blockIPsButton = $( '.mw-checkuser-massblock-ips-button', $checkUserBlockFieldset ),
		centralURL = mw.config.get( 'wgCUCAMultiLockCentral' );
	let selectedAccounts = [],
		selectedIPs = [];

	if ( !centralURL && ( !$blockAccountsButton.length || !$blockIPsButton.length ) ) {
		// If no central URL is set and the block buttons are missing, then return early
		// as we are likely not on the Special:CheckUser 'Get users' page or the user
		// does not have the rights to lock or block.
		return false;
	}

	if ( centralURL ) {
		// Initialize the link to Special:MultiLock.
		$checkUserBlockFieldset.append(
			$( '<a>' ).attr( {
				class: 'mw-checkuser-multilock-link',
				href: centralURL
			} ).text( mw.msg( 'checkuser-centralauth-multilock' ) )
		);
	}

	/**
	 * Handle a change in the state of the checkboxes. This regenerates the link(s) to
	 * Special:MultiLock as well as updates the list of selected accounts and IPs.
	 */
	function handleCheckboxesChange() {
		// Clear the list of selected IPs and accounts, and then fill these lists
		// from the state of the checkboxes.
		selectedAccounts = [];
		selectedIPs = [];
		$userCheckboxes.serializeArray().forEach( ( obj ) => {
			if ( obj.name && obj.name === 'users[]' ) {
				// Only registered accounts (not IPs) can be locked
				if ( !mw.util.isIPAddress( obj.value, true ) ) {
					selectedAccounts.push( obj.value );
				} else {
					selectedIPs.push( obj.value );
				}
			}
		} );

		if ( !centralURL ) {
			return;
		}

		const urls = [];
		$( '.mw-checkuser-multilock-link, .mw-checkuser-multilock-link-header, .mw-checkuser-multilock-link-list' ).remove();
		// Split the names up into batches of username length of a
		// maximum of 2,000 including the centralURL + other parts
		// of the GET parameters
		let i = 0;
		while ( i < selectedAccounts.length ) {
			let url = centralURL + '?wpTarget=';
			let firstUsername = true;
			while ( i < selectedAccounts.length ) {
				let urlComponent = selectedAccounts[ i ];
				if ( !firstUsername ) {
					urlComponent = '\n' + urlComponent;
				} else {
					firstUsername = false;
				}
				urlComponent = encodeURIComponent( urlComponent );
				if ( urlComponent.length + url.length >= 2000 ) {
					break;
				}
				url += urlComponent;
				i = i + 1;
			}
			urls.push( url );
		}

		// Update the href of the link with the latest change
		if ( urls.length > 1 ) {
			$checkUserBlockFieldset.append(
				$( '<span>' ).attr( {
					class: 'mw-checkuser-multilock-link-header'
				} ).text( mw.msg( 'checkuser-centralauth-multilock-list' ) )
			);
			let links = '';
			urls.forEach( ( urlToAdd, index ) => {
				const $li = $( '<li>' );
				const $a = $( '<a>' ).attr( 'class', 'mw-checkuser-multilock-link' );
				$a.attr( 'href', urlToAdd )
					.text( mw.msg( 'checkuser-centralauth-multilock-list-item', index + 1 ) );
				$li.append( $a );
				links += $li[ 0 ].outerHTML;
			} );
			$checkUserBlockFieldset.append(
				$( '<ul>' ).attr( { class: 'mw-checkuser-multilock-link-list' } ).append( links )
			);
		} else {
			$checkUserBlockFieldset.append(
				$( '<a>' ).attr( {
					class: 'mw-checkuser-multilock-link',
					href: urls[ 0 ]
				} ).text( mw.msg( 'checkuser-centralauth-multilock' ) )
			);
		}
	}

	// Change the URL of the link when a checkbox's state is changed
	$userCheckboxes.on( 'change', () => {
		handleCheckboxesChange();
	} );

	// Initialize the selected accounts and IPs, as the checkboxes may have been pre-selected.
	handleCheckboxesChange();

	/**
	 * Open the Special:InvestigateBlock page in a new tab with the given targets.
	 *
	 * @param {string[]} targets
	 */
	function openSpecialInvestigateBlockPage( targets ) {
		const $form = $( '<form>' ).attr( {
			action: new mw.Title( 'Special:InvestigateBlock' ).getUrl(),
			method: 'post',
			target: '_blank'
		} ).addClass( [ 'oo-ui-element-hidden', 'ext-checkuser-hidden-block-form' ] );

		$form.append( $( '<input>' ).attr( {
			type: 'hidden',
			name: 'wpTargets',
			value: targets.join( '\n' )
		} ) );

		if ( !documentRoot ) {
			documentRoot = 'body';
		}
		$form.appendTo( documentRoot ).trigger( 'submit' );
	}

	if ( !$blockAccountsButton.length || !$blockIPsButton.length ) {
		// If the block buttons are not present, then the user does not have
		// the rights to block but does have the rights to lock. As such, don't
		// try to interact with the non-existing block buttons and return early.
		return false;
	}

	// If the 'Block accounts' or 'Block IPs' button is pressed, then open the block form in
	// a new tab for the user.
	$blockAccountsButton.find( 'button' )[ 0 ].addEventListener( 'click', () => {
		openSpecialInvestigateBlockPage( selectedAccounts );
	} );
	$blockIPsButton.find( 'button' )[ 0 ].addEventListener( 'click', () => {
		openSpecialInvestigateBlockPage( selectedIPs );
	} );

	return true;
};
