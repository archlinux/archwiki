/* -- (c) Aaron Schulz 2009 */
( function () {
	const showResults = function ( size, cidr, $form, hasCheckUserRight, hasCheckUserLogRight ) {
		if ( cidr.toString() === '!' ) {
			cidr = mw.message( 'checkuser-cidr-too-small' ).text();
		}
		$( '.mw-checkuser-cidr-res input', $form ).val( cidr );
		if ( mw.util.isIPAddress( cidr, true ) ) {
			$( '.mw-checkuser-cidr-tool-links', $form ).removeClass( 'mw-checkuser-cidr-tool-links-hidden' );
			$( '.mw-checkuser-cidr-tool-links', $form ).html(
				mw.message( 'checkuser-cidr-tool-links', cidr ).parse()
			);
			if ( !hasCheckUserRight ) {
				$( '.mw-checkuser-cidr-checkuser-only' ).addClass( 'mw-checkuser-cidr-tool-links-hidden' );
			}
			if ( !hasCheckUserLogRight ) {
				$( '.mw-checkuser-cidr-checkuserlog-only' ).addClass( 'mw-checkuser-cidr-tool-links-hidden' );
			}
		} else {
			$( '.mw-checkuser-cidr-tool-links', $form ).addClass( 'mw-checkuser-cidr-tool-links-hidden' );
		}
		$( '.mw-checkuser-cidr-ipnote', $form ).text(
			mw.message( 'checkuser-cidr-affected-ips' ).text() + ' ' + size.toLocaleString()
		);
	};

	/**
	 * Get and return the IPs from the input as an array of IPs
	 *
	 * @param {jQuery} $form The form to collect the IPs from
	 * @return {string[]}
	 */
	function getIPList( $form ) {
		const $iplist = $( '.mw-checkuser-cidr-iplist textarea', $form );
		if ( !$iplist ) {
			// No IP list, so return empty array
			return [];
		}
		const text = $iplist.val();
		const delimiters = [ '\n', '\t', ',', ' - ', '-', ' ', ';' ];
		for ( const i in delimiters ) {
			// If the delimiter is present in the IPs list, use it
			// to split into a list of IPs.
			if ( text.includes( delimiters[ i ] ) ) {
				const ips = text.split( delimiters[ i ] );
				for ( const j in ips ) {
					// Trim to remove excess whitespace.
					ips[ j ] = ips[ j ].trim();
				}
				return ips;
			}
		}
		// If no delimiter was present, then assume only one IP.
		return [ text.trim() ];
	}

	/**
	 * This function calculates the common range of a list of
	 * IPs. It should be set to update on keyUp.
	 *
	 * @param {jQuery} $form JQuery element for the form that is being updated
	 * @param {boolean} hasCheckUserRight Whether the user has the right to see Special:CheckUser
	 * @param {boolean} hasCheckUserLogRight Whether the user has the right to see Special:CheckUser
	 */
	function updateCIDRresult( $form, hasCheckUserRight, hasCheckUserLogRight ) {
		if ( !$form ) {
			return; // no JS form
		}
		$form.removeClass( 'mw-checkuser-cidr-calculator-hidden' );
		const ips = getIPList( $form );
		if ( ips.length === 0 ) {
			return;
		}
		let binPrefix = 0;
		let prefixCidr = 0;
		let prefix = '';
		let foundV4 = false;
		let foundV6 = false;
		let ipCount;
		let blocs;
		// Go through each IP in the list, get its binary form, and
		// track the largest binary prefix among them...
		for ( let i = 0; i < ips.length; i++ ) {
			// ...in the spirit of mediawiki.special.block.js, call this "addy"
			const addy = ips[ i ];
			// Match the first IP in each list (ignore other garbage)
			const ipV4 = mw.util.isIPv4Address( addy, true );
			const ipV6 = mw.util.isIPv6Address( addy, true );
			const ipCidr = addy.split( '/' );
			// Binary form
			let bin = '';
			let x = 0, z = 0, start = 0, end = 0, ip, cidr, bloc, binBlock;
			// Convert the IP to binary form: IPv4
			if ( ipV4 ) {
				foundV4 = true;
				if ( foundV6 ) { // disjoint address space
					prefix = '';
					break;
				}
				ip = ipCidr[ 0 ];
				cidr = ipCidr[ 1 ]; // CIDR if it exists, or undefined
				// Get each quad integer
				blocs = ip.split( '.' );
				for ( x = 0; x < blocs.length; x++ ) {
					bloc = parseInt( blocs[ x ], 10 );
					binBlock = bloc.toString( 2 ); // concat bin with binary form of bloc
					while ( binBlock.length < 8 ) {
						binBlock = '0' + binBlock; // pad out as needed
					}
					bin += binBlock;
				}
				prefix = ''; // Rebuild formatted binPrefix for each IP
				// Apply any valid CIDRs
				if ( cidr ) {
					bin = bin.slice( 0, Math.max( 0, cidr ) ); // truncate bin
				}
				// Init binPrefix
				if ( binPrefix === 0 ) {
					binPrefix = bin;
					// Get largest common binPrefix
				} else {
					for ( x = 0; x < binPrefix.length; x++ ) {
					// binPrefix always smaller than bin unless a CIDR was used on bin
						if ( bin[ x ] === undefined || binPrefix[ x ] !== bin[ x ] ) {
							binPrefix = binPrefix.slice( 0, Math.max( 0, x ) ); // shorten binPrefix
							break;
						}
					}
				}
				// Build the IP in CIDR form
				prefixCidr = binPrefix.length;
				// CIDR too small?
				if ( prefixCidr < 16 ) {
					showResults( '>' + Math.pow( 2, 32 - prefixCidr ).toLocaleString(), '!', $form, hasCheckUserRight, hasCheckUserLogRight );
					return; // too big
				}
				// Build the IP in dotted-quad form
				for ( z = 0; z <= 3; z++ ) {
					bloc = 0;
					start = z * 8;
					end = start + 7;
					for ( x = start; x <= end; x++ ) {
						if ( binPrefix[ x ] === undefined ) {
							break;
						}
						bloc += parseInt( binPrefix[ x ], 10 ) * Math.pow( 2, end - x );
					}
					prefix += ( z === 3 ) ? bloc : bloc + '.';
				}
				// Get IPs affected
				ipCount = Math.pow( 2, 32 - prefixCidr );
				// Is the CIDR meaningful?
				if ( prefixCidr === 32 ) {
					prefixCidr = false;
				}
				// Convert the IP to binary form: IPv6
			} else if ( ipV6 ) {
				foundV6 = true;
				if ( foundV4 ) { // disjoint address space
					prefix = '';
					break;
				}
				ip = ipCidr[ 0 ];
				cidr = ipCidr[ 1 ]; // CIDR if it exists, or undefined
				// Expand out "::"s
				const abbrevs = ip.match( /::/g );
				if ( abbrevs && abbrevs.length > 0 ) {
					const colons = ip.match( /:/g );
					let needed = 7 - ( colons.length - 2 ); // 2 from "::"
					let insert = '';
					while ( needed > 1 ) {
						insert += ':0';
						needed--;
					}
					ip = ip.replace( '::', insert + ':' );
					// For IPs that start with "::", correct the final IP
					// so that it starts with '0' and not ':'
					if ( ip[ 0 ] === ':' ) {
						ip = '0' + ip;
					}
				}
				// Get each hex octant
				blocs = ip.split( ':' );
				for ( x = 0; x <= 7; x++ ) {
					bloc = blocs[ x ] ? blocs[ x ] : '0';
					const intBlock = parseInt( bloc, 16 ); // convert hex -> int
					binBlock = intBlock.toString( 2 ); // concat bin with binary form of bloc
					while ( binBlock.length < 16 ) {
						binBlock = '0' + binBlock; // pad out as needed
					}
					bin += binBlock;
				}
				prefix = ''; // Rebuild formatted binPrefix for each IP
				// Apply any valid CIDRs
				if ( cidr ) {
					bin = bin.slice( 0, Math.max( 0, cidr ) ); // truncate bin
				}
				// Init binPrefix
				if ( binPrefix === 0 ) {
					binPrefix = bin;
					// Get largest common binPrefix
				} else {
					for ( x = 0; x < binPrefix.length; x++ ) {
					// binPrefix always smaller than bin unless a CIDR was used on bin
						if ( bin[ x ] === undefined || binPrefix[ x ] !== bin[ x ] ) {
							binPrefix = binPrefix.slice( 0, Math.max( 0, x ) ); // shorten binPrefix
							break;
						}
					}
				}
				// Build the IP in CIDR form
				prefixCidr = binPrefix.length;
				// CIDR too small?
				if ( prefixCidr < 32 ) {
					showResults( '>' + Math.pow( 2, 128 - prefixCidr ).toLocaleString(), '!', $form, hasCheckUserRight, hasCheckUserLogRight );
					return; // too big
				}
				// Build the IP in dotted-quad form
				for ( z = 0; z <= 7; z++ ) {
					bloc = 0;
					start = z * 16;
					end = start + 15;
					for ( x = start; x <= end; x++ ) {
						if ( binPrefix[ x ] === undefined ) {
							break;
						}
						bloc += parseInt( binPrefix[ x ], 10 ) * Math.pow( 2, end - x );
					}
					bloc = bloc.toString( 16 ); // convert to hex
					prefix += ( z === 7 ) ? bloc : bloc + ':';
				}
				// Get IPs affected
				ipCount = Math.pow( 2, 128 - prefixCidr );
				// Is the CIDR meaningful?
				if ( prefixCidr === 128 ) {
					prefixCidr = false;
				}
			}
		}
		// Update form
		if ( prefix !== '' ) {
			let full = prefix;
			if ( prefixCidr !== false ) {
				full += '/' + prefixCidr;
			}
			showResults( '~' + ipCount.toLocaleString(), full, $form, hasCheckUserRight, hasCheckUserLogRight );
		} else {
			showResults( '?', '', $form, hasCheckUserRight, hasCheckUserLogRight );
		}

	}

	$( () => {
		mw.user.getRights( ( rights ) => {
			const hasCheckUserRight = rights.includes( 'checkuser' );
			const hasCheckUserLogRight = rights.includes( 'checkuser-log' );
			$( '.mw-checkuser-cidrform' ).each( ( index, form ) => {
				updateCIDRresult( $( form ), hasCheckUserRight, hasCheckUserLogRight );
			} );
			$( '.mw-checkuser-cidr-iplist textarea' ).on( 'keyup click', function () {
				const $form = $( this ).closest( '.mw-checkuser-cidrform' );
				updateCIDRresult( $form, hasCheckUserRight, hasCheckUserLogRight );
			} );
		} );
	} );
}() );
