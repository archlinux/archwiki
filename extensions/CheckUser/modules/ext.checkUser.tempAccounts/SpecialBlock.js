const { performFullRevealRequest } = require( './rest.js' );

let blockTargetWidget, lastUserRequest, lastIpRequest;

/**
 * Run code for use when the Special:Block page loads.
 *
 * This adds a button to the page if the target is a temporary account, for revealing the
 * IP addresses used by the temporary account. Note that, unlike other pages, there is only
 * a single button, and the IP addresses are not automatically revealed without the user
 * clicking the button.
 *
 * This is in a function to allow QUnit testing to call the method directly.
 */
function onLoad() {
	const $blockTargetWidget = $( '#mw-bi-target' );

	// This code is also loaded on the "block succeeded" page where there is no form,
	// so check for block target widget; if it exists, the form is present
	if ( $blockTargetWidget.length ) {
		if ( mw.config.get( 'wgUseCodexSpecialBlock' ) ) {
			if ( mw.config.get( 'wgAutoCreateTempUserEnabled' ) ) {
				mw.hook( 'codex.userlookup' ).add( ( components ) => {
					// Codex and Vue are fully loaded at this point.
					const ShowIPButton = require( './components/ShowIPButton.vue' );
					const TempUsersMessage = require( './components/TempUsersMessage.vue' );
					const blockConnectedTempAccountsField = require( './components/blockConnectedTempAccountsField.vue' );
					components.value.push( ShowIPButton );
					components.value.push( TempUsersMessage );
					components.value.push( blockConnectedTempAccountsField );
				} );
			}
			return;
		}

		blockTargetWidget = OO.ui.infuse( $blockTargetWidget );
		blockTargetWidget.on( 'change', ( blockTarget ) => {
			if ( lastUserRequest ) {
				lastUserRequest.abort();
			}
			if ( lastIpRequest ) {
				lastIpRequest.abort();
			}
			onTargetChange( blockTarget );
		} );
		onTargetChange( blockTargetWidget.getValue() );
	}
}

/**
 * Creates the button used to reveal the IPs of a temporary account on Special:Block.
 *
 * @return {OO.ui.ButtonWidget}
 */
function createButton() {
	return new OO.ui.ButtonWidget( {
		label: mw.msg( 'checkuser-tempaccount-reveal-ip-button-label' ),
		framed: false,
		flags: [ 'progressive' ],
		classes: [ 'ext-checkuser-tempaccount-specialblock-ips-link' ]
	} );
}

/**
 * Handles the change event of the block target widget for a temporary user target.
 *
 * @param {string} blockTarget
 */
function handleTemporaryUserTarget( blockTarget ) {
	const api = new mw.Api();
	lastUserRequest = api.get( {
		action: 'query',
		list: 'users',
		ususers: blockTargetWidget.getValue()
	} );
	lastUserRequest.then( ( data ) => {
		if ( data.query.users[ 0 ].userid ) {
			const revealButton = createButton();
			const $container = $( '<div>' )
				.addClass( 'ext-checkuser-tempaccount-specialblock-ips' )
				.append( revealButton.$element );
			$( '#mw-htmlform-target' ).after( $container );

			revealButton.once( 'click', () => {
				performFullRevealRequest( blockTarget ).then( ( response ) => {
					let message;
					if ( response.ips.length ) {
						// Wrap each IP in a link to Special:IPContributions
						// to allow further investigation.
						const ips = response.ips.map( ( ip ) => $( '<a>' )
							.attr( 'href', new mw.Title( 'Special:IPContributions/' + ip ).getUrl() )
							.text( ip )
							.prop( 'outerHTML' )
						);
						message = mw.message(
							'checkuser-tempaccount-specialblock-ips',
							ips.length,
							$( $.parseHTML( mw.language.listToText( ips ) ) )
						).parse();
						message = new OO.ui.HtmlSnippet( message );
					} else {
						message = mw.msg(
							'checkuser-tempaccount-no-ip-results',
							Math.round( mw.config.get( 'wgCUDMaxAge' ) / 86400 )
						);
					}
					$container.empty().append( new OO.ui.LabelWidget( {
						label: message
					} ).$element );
				} ).catch( () => {
					$container.empty()
						.addClass( 'ext-checkuser-tempaccount-reveal-ip' )
						.append( new OO.ui.LabelWidget( {
							label: mw.msg( 'checkuser-tempaccount-reveal-ip-error' )
						} ).$element );
				} );
			} );
		}
	} );
}

/**
 * Handles the change event of the block target widget for an IP target.
 *
 * @param {string} blockTarget
 * @param {boolean} isCidr
 */
function handleIPTarget( blockTarget, isCidr ) {
	// Wait for the next tick, to ensure the container is added
	setTimeout( () => {
		const ipType = isCidr ? 'iprange' : 'ip';
		// Messages used:
		// * checkuser-tempaccount-specialblock-ip-target
		// * checkuser-tempaccount-specialblock-iprange-target
		const message = mw.message(
			`checkuser-tempaccount-specialblock-${ ipType }-target`,
			blockTarget
		).parseDom();
		const $message = $( '<p>' )
			.addClass( 'ext-checkuser-tempaccount-specialblock-ips' )
			.append( message );
		$( '.mw-block-target-ip-tempuser-info' ).before( $message );
	} );
}

/**
 * Handles the change event of the block target widget.
 *
 * @param {string} blockTarget
 */
function onTargetChange( blockTarget ) {
	$( '.ext-checkuser-tempaccount-specialblock-ips' ).remove();
	if ( mw.util.isTemporaryUser( blockTarget ) ) {
		handleTemporaryUserTarget( blockTarget );
		return;
	}
	if ( mw.util.isIPAddress( blockTarget, true ) ) {
		const isCidr = !mw.util.isIPAddress( blockTarget );
		handleIPTarget( blockTarget, isCidr );
		return;
	}
}

module.exports = {
	onLoad: onLoad,
	createButton: createButton
};
