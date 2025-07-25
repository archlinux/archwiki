module.exports = function addBlockForm( documentRoot ) {
	// Attributes used for pinnable highlighting
	const accountsBlockButton = OO.ui.infuse( $( '.ext-checkuser-investigate-subtitle-block-accounts-button' ) ),
		ipsBlockButton = OO.ui.infuse( $( '.ext-checkuser-investigate-subtitle-block-ips-button' ) ),
		$placeholderWidget = $( '.ext-checkuser-investigate-subtitle-placeholder-widget' ),
		targets = mw.config.get( 'wgCheckUserInvestigateTargets' ),
		excludeTargets = mw.config.get( 'wgCheckUserInvestigateExcludeTargets' ),
		targetsWidget = new OO.ui.MenuTagMultiselectWidget( {
			classes: [ 'ext-checkuser-investigate-subtitle-targets-widget' ]
		} ),
		continueButton = new OO.ui.ButtonWidget( {
			label: mw.msg( 'checkuser-investigate-subtitle-continue-button-label' ),
			flags: [ 'primary', 'progressive' ],
			classes: [
				'ext-checkuser-investigate-subtitle-continue-button'
			]
		} ),
		cancelButton = new OO.ui.ButtonWidget( {
			label: mw.msg( 'checkuser-investigate-subtitle-cancel-button-label' ),
			flags: [ 'progressive' ],
			framed: false,
			classes: [
				'ext-checkuser-investigate-subtitle-cancel-button'
			]
		} );

	function toggleBlockFromButtons( showBlockForm, mode ) {
		// Toggle the visibility status of the block form and associated
		// buttons depending on showBlockForm.
		accountsBlockButton.toggle( !showBlockForm );
		ipsBlockButton.toggle( !showBlockForm );
		continueButton.toggle( showBlockForm );
		cancelButton.toggle( showBlockForm );
		targetsWidget.toggle( showBlockForm );
		if ( !showBlockForm ) {
			// Return early if the block form is not being shown, as we do not need to therefore
			// set the targets.
			return;
		}
		// Clear the existing menu items, so we can add either account or
		// IP targets based on the mode.
		targetsWidget.menu.clearItems();
		// Generate the list of selected and unselected options based on the mode.
		let selectedOptions = [];
		let unselectedOptions = [];
		if ( mode === 'accounts' ) {
			// If the mode is 'accounts', we want to pre-select all account targets
			// in the check and add as an option all excluded targets which are accounts.
			selectedOptions = targets.filter(
				( target ) => !excludeTargets.includes( target ) &&
					mw.util.isIPAddress( target, true ) === false
			);
			unselectedOptions = excludeTargets.filter(
				( target ) => mw.util.isIPAddress( target, true ) === false
			);
		} else if ( mode === 'ips' ) {
			// If the mode is 'ips', we want to pre-select all IP targets in the check
			// and add as unselected options all excluded targets which are IPs.
			selectedOptions = targets.filter(
				( target ) => !excludeTargets.includes( target ) &&
					mw.util.isIPAddress( target, true )
			);
			unselectedOptions = excludeTargets.filter(
				( target ) => mw.util.isIPAddress( target, true )
			);
		}
		// Initially add all options (selected and unselected) to the widget as unselected
		// options. This needs to happen first, as ::setValue will only allow selecting options
		// which are listed as options in the widget unless allowArbitrary is set to true
		// (which we do not want).
		targetsWidget.addOptions( unselectedOptions.concat( selectedOptions ).map( ( target ) => ( {
			data: target,
			label: target
		} ) ) );
		// Mark as selected all the options in the selectedOptions array.
		targetsWidget.setValue( selectedOptions );
	}

	$placeholderWidget.replaceWith( targetsWidget.$element );
	accountsBlockButton.$element.parent().prepend(
		continueButton.$element,
		cancelButton.$element
	);

	toggleBlockFromButtons( false );
	accountsBlockButton.on( 'click', toggleBlockFromButtons.bind( null, true, 'accounts' ) );
	ipsBlockButton.on( 'click', toggleBlockFromButtons.bind( null, true, 'ips' ) );
	cancelButton.on( 'click', toggleBlockFromButtons.bind( null, false ) );

	continueButton.on( 'click', () => {
		const $form = $( '<form>' ).attr( {
			action: new mw.Title( 'Special:InvestigateBlock' ).getUrl(),
			method: 'post',
			target: '_blank'
		} ).addClass( [ 'oo-ui-element-hidden', 'ext-checkuser-investigate-hidden-block-form' ] );

		$form.append( $( '<input>' ).attr( {
			type: 'hidden',
			name: 'wpTargets',
			value: targetsWidget.getValue().join( '\n' )
		} ) );

		if ( !documentRoot ) {
			documentRoot = 'body';
		}
		$form.appendTo( documentRoot ).trigger( 'submit' );
	} );
};
