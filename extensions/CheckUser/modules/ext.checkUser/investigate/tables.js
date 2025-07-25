const InvestigateMenuSelectWidget = require( './InvestigateMenuSelectWidget.js' );

/**
 * Add highlight pinning capability and tool links to tables.
 */
module.exports = function setupTables() {
	// Attributes used for pinnable highlighting
	const highlightData = mw.storage.session.get( 'checkuser-investigate-highlight' ),
		toggleButtons = {};

	// The message 'checkuser-toollinks' was parsed in PHP, since translations
	// may contain wikitext that is too complex for the JS parser:
	// https://www.mediawiki.org/wiki/Manual:Messages_API#Feature_support_in_JavaScript
	mw.messages.set( require( './message.json' ) );

	function logEvent( data ) {
		mw.track( 'event.SpecialInvestigate', data );
	}

	function getDataKey( $element ) {
		return JSON.stringify( [
			$element.data( 'field' ),
			$element.data( 'value' )
		] );
	}

	function updateMatchingElements( $target, value, classSuffix ) {
		const dataField = $target.data( 'field' ),
			dataValue = $target.data( 'value' ),
			cellClass = 'ext-checkuser-investigate-table-cell-' + classSuffix,
			rowClass = 'ext-checkuser-investigate-table-row-' + classSuffix;

		const $matches = $( 'td[data-field="' + dataField + '"][data-value="' + dataValue + '"]' );
		// The following messages can be passed here:
		// * ext-checkuser-investigate-table-cell-hover-data-match
		// * ext-checkuser-investigate-table-cell-pinned-data-match
		$matches.toggleClass( cellClass, value );

		// Rows should be highlighted iff they contain highlighted cells
		$matches.closest( 'tr' ).each( function () {
			// The following messages can be passed here:
			// * ext-checkuser-investigate-table-row-hover-data-match
			// * ext-checkuser-investigate-table-row-pinned-data-match
			$( this ).toggleClass(
				rowClass,
				value
			);
		} );
	}

	function onPinnableCellHover( event ) {
		// Toggle on for mouseover, off for mouseout
		updateMatchingElements( $( this ), event.type === 'mouseover', 'hover-data-match' );
	}

	function onToggleButtonChange( $tableCell, value ) {
		$( '.ext-checkuser-investigate-table' ).toggleClass( 'ext-checkuser-investigate-table-pinned', value );
		$tableCell.toggleClass( 'ext-checkuser-investigate-table-cell-pinned', value );

		toggleButtons[ getDataKey( $tableCell ) ].forEach( ( button ) => {
			button.setValue( value );
			button.setFlags( { progressive: value } );
		} );
		updateMatchingElements( $tableCell, value, 'pinned-data-match' );

		if ( value ) {
			mw.storage.session.set( 'checkuser-investigate-highlight', getDataKey( $tableCell ) );
		} else {
			mw.storage.session.remove( 'checkuser-investigate-highlight' );
		}
	}

	function filterValue( $tableCell ) {
		$( 'textarea[name=exclude-targets]' ).val( function () {
			return this.value + '\n' + $tableCell.data( 'value' );
		} );
		$( '.mw-htmlform' ).trigger( 'submit' );
	}

	function addTargets( $tableCell ) {
		let target = $tableCell.data( 'value' );
		if ( mw.util.isIPv6Address( target ) ) {
			target += '/64';
		}
		$( 'input[name=targets]' ).val( target );
		$( '.mw-htmlform' ).trigger( 'submit' );
	}

	function appendButtons( $tableCell, buttonTypes ) {
		// eslint-disable-next-line no-jquery/no-class-state
		const isTarget = $tableCell.hasClass( 'ext-checkuser-compare-table-cell-target' ),
			$optionsContainer = $( '<div>' ).addClass( 'ext-checkuser-investigate-table-options-container' ),
			key = getDataKey( $tableCell ),
			options = [];
		let selectWidget,
			contribsOptionWidget,
			checksOptionWidget,
			toggleButton,
			message,
			$links;

		/**
		 * Hack for setting an icon as the indicator. Call setIndicator with
		 * a dummy indicator type, to do everything that is needed to set an
		 * indicator, then swap in the class for the newWindow icon.
		 *
		 * @param {OO.ui.MenuOptionWidget} optionWidget
		 */
		function setNewWindowIndicator( optionWidget ) {
			optionWidget.setIndicator( 'placeholder' );
			optionWidget.$indicator.removeClass( 'oo-ui-indicator-placeholder' );
			optionWidget.$indicator.addClass( 'oo-ui-icon-newWindow' );
		}

		$tableCell.prepend( $optionsContainer );

		if ( buttonTypes.filter ) {
			options.push( new OO.ui.MenuOptionWidget( {
				icon: 'funnel',
				classes: [
					'ext-checkuser-investigate-button-filter-ip'
				],
				label: mw.msg( 'checkuser-investigate-compare-table-button-filter-label' ),
				data: { type: 'filter' }
			} ) );
		}

		if ( buttonTypes.addUsers ) {
			options.push( new OO.ui.MenuOptionWidget( {
				disabled: isTarget,
				icon: 'add',
				classes: [
					'ext-checkuser-investigate-button-add-user-targets'
				],
				label: $tableCell.data( 'actions' ) === $tableCell.data( 'all-actions' ) ?
					mw.msg( 'checkuser-investigate-compare-table-button-add-user-targets-log-label' ) :
					mw.msg( 'checkuser-investigate-compare-table-button-add-user-targets-label' ),
				data: { type: 'addUsers' }
			} ) );
		}

		if ( buttonTypes.addIps ) {
			options.push( new OO.ui.MenuOptionWidget( {
				disabled: isTarget,
				icon: 'add',
				label: mw.msg( 'checkuser-investigate-compare-table-button-add-ip-targets-label' ),
				data: { type: 'addIps' }
			} ) );
		}

		if ( buttonTypes.contribs ) {
			contribsOptionWidget = new OO.ui.MenuOptionWidget( {
				icon: 'userContributions',
				label: mw.msg( 'checkuser-investigate-compare-table-button-contribs-label' ),
				data: {
					type: 'toolLinks',
					href: new mw.Title( 'Special:Contributions' ).getUrl( {
						target: $tableCell.data( 'value' )
					} ),
					tool: 'Special:Contributions'
				}
			} );
			setNewWindowIndicator( contribsOptionWidget );
			options.push( contribsOptionWidget );
		}

		if ( buttonTypes.checks ) {
			checksOptionWidget = new OO.ui.MenuOptionWidget( {
				icon: 'check',
				label: mw.msg( 'checkuser-investigate-compare-table-button-checks-label' ),
				data: {
					type: 'toolLinks',
					href: new mw.Title( 'Special:CheckUserLog' ).getUrl( {
						cuSearch: $tableCell.data( 'value' )
					} ),
					tool: 'Special:InvestigateLog'
				}
			} );
			setNewWindowIndicator( checksOptionWidget );
			options.push( checksOptionWidget );
		}

		if ( buttonTypes.toolLinks ) {
			message = mw.msg( 'checkuser-investigate-compare-toollinks', $tableCell.data( 'value' ) );
			$links = $( '<div>' ).html( message ).find( 'a' );
			$links.each( ( i, $link ) => {
				const label = $link.text,
					href = $link.getAttribute( 'href' );
				const optionWidget = new OO.ui.MenuOptionWidget( {
					icon: 'globe',
					label: label,
					data: {
						type: 'toolLinks',
						href: href,
						tool: new URL( href, location.href ).host
					}
				} );
				setNewWindowIndicator( optionWidget );
				options.push( optionWidget );
			} );
		}

		if ( options.length > 0 ) {
			selectWidget = new OO.ui.ButtonMenuSelectWidget( {
				icon: 'ellipsis',
				framed: false,
				classes: [ 'ext-checkuser-investigate-table-select' ],
				menu: {
					horizontalPosition: 'end',
					items: options
				},
				menuClass: InvestigateMenuSelectWidget
			} );

			selectWidget.getMenu().on( 'investigate', ( item ) => {
				const data = item.getData();
				switch ( data.type ) {
					case 'filter':
						filterValue( $tableCell );
						break;
					case 'addIps':
						addTargets( $tableCell );
						break;
					case 'addUsers':
						addTargets( $tableCell );
						break;
					case 'toolLinks':
						logEvent( {
							action: 'tool',
							tool: data.tool
						} );
						window.open( data.href, '_blank' );
						break;
				}
			} );

			$optionsContainer.append( selectWidget.$element );
		}

		if ( buttonTypes.toggle ) {
			toggleButton = new OO.ui.ToggleButtonWidget( {
				icon: 'pushPin',
				framed: false,
				classes: [ 'ext-checkuser-investigate-table-button-pin' ]
			} );
			toggleButtons[ key ] = toggleButtons[ key ] || [];
			toggleButtons[ key ].push( toggleButton );
			toggleButton.on( 'change', onToggleButtonChange.bind( null, $tableCell ) );
			// Log the click not the change, since clicking on one button
			// can lead to several other buttons changing
			toggleButton.on( 'click', () => {
				if ( toggleButton.getValue() ) {
					logEvent( { action: 'pin' } );
				}
			} );
			$optionsContainer.append( toggleButton.$element );
		}
	}

	$( 'td.ext-checkuser-investigate-table-cell-pinnable' ).on( 'mouseover mouseout focusin focusout', onPinnableCellHover );

	$( '.ext-checkuser-investigate-table-preliminary-check td.ext-checkuser-investigate-table-cell-pinnable' )
		.each( function () {
			appendButtons( $( this ), {
				toggle: true
			} );
		} );

	$( '.ext-checkuser-investigate-table-compare .ext-checkuser-compare-table-cell-user-agent' )
		.each( function () {
			appendButtons( $( this ), {
				toggle: true
			} );
		} );

	$( '.ext-checkuser-investigate-table-compare .ext-checkuser-compare-table-cell-ip-target' )
		.each( function () {
			appendButtons( $( this ), {
				toggle: true,
				filter: true,
				addUsers: true,
				contribs: true,
				checks: true,
				toolLinks: true
			} );
		} );

	$( 'td.ext-checkuser-compare-table-cell-user-target' )
		.each( function () {
			appendButtons( $( this ), {
				filter: true,
				addIps: true,
				contribs: true,
				checks: true
			} );
		} );

	// Persist highlights across paginated tabs
	if (
		highlightData !== null &&
		toggleButtons[ highlightData ] &&
		toggleButtons[ highlightData ].length > 0
	) {
		toggleButtons[ highlightData ][ 0 ].setValue( true );
	}
};
