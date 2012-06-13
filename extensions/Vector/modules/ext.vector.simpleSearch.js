/**
 * JavaScript for SimpleSearch
 */

jQuery( document ).ready( function ( $ ) {

	// Ensure that the thing is actually present!
	if ( $( '#simpleSearch' ).length === 0 ) {
		// Don't try to set anything up if simpleSearch is disabled sitewide.
		// The loader code loads us if the option is present, even if we're
		// not actually enabled (anymore).
		return;
	}

	// Compatibility map
	var map = {
		browsers: {
			// Left-to-right languages
			ltr: {
				// SimpleSearch is broken in Opera < 9.6
				opera: [['>=', 9.6]],
				docomo: false,
				blackberry: false,
				ipod: false,
				iphone: false
			},
			// Right-to-left languages
			rtl: {
				opera: [['>=', 9.6]],
				docomo: false,
				blackberry: false,
				ipod: false,
				iphone: false
			}
		}
	};
	if ( !$.client.test( map ) ) {
		return true;
	}

	// Disable MWSuggest if loaded
	if ( window.os_MWSuggestDisable ) {
		window.os_MWSuggestDisable();
	}

	// Placeholder text for SimpleSearch box
	$( '#simpleSearch > input#searchInput' )
		.attr( 'placeholder', mw.msg( 'vector-simplesearch-search' ) )
		.placeholder();

	// General suggestions functionality for all search boxes
	$( '#searchInput, #searchInput2, #powerSearchText, #searchText' )
		.suggestions( {
			fetch: function ( query ) {
				var $el = $(this);
				if ( query.length !== 0 ) {
					var jqXhr = $.ajax( {
						url: mw.util.wikiScript( 'api' ),
						data: {
							format: 'json',
							action: 'opensearch',
							search: query,
							namespace: 0,
							suggest: ''
						},
						dataType: 'json',
						success: function ( data ) {
							if ( $.isArray( data ) && data.length ) {
								$el.suggestions( 'suggestions', data[1] );
							}
						}
					});
					$el.data( 'request', jqXhr );
				}
			},
			cancel: function () {
				var jqXhr = $(this).data( 'request' );
				// If the delay setting has caused the fetch to have not even happend yet,
				// the jqXHR object will have never been set.
				if ( jqXhr && $.isFunction ( jqXhr.abort ) ) {
					jqXhr.abort();
					$(this).removeData( 'request' );
				}
			},
			result: {
				select: function ( $input ) {
					$input.closest( 'form' ).submit();
				}
			},
			delay: 120,
			positionFromLeft: $( 'body' ).hasClass( 'rtl' ),
			highlightInput: true
		} )
		.bind( 'paste cut drop', function ( e ) {
			// make sure paste and cut events from the mouse and drag&drop events
			// trigger the keypress handler and cause the suggestions to update
			$( this ).trigger( 'keypress' );
		} );
	// Special suggestions functionality for skin-provided search box
	$( '#searchInput' ).suggestions( {
		result: {
			select: function ( $input ) {
				$input.closest( 'form' ).submit();
			}
		},
		special: {
			render: function ( query ) {
				var $el = $(this);
				if ( $el.children().length === 0 ) {
					$el.show();
					$( '<div>', {
							'class': 'special-label',
							text: mw.msg( 'vector-simplesearch-containing' )
						})
						.appendTo( $el );
					$( '<div>', {
							'class': 'special-query',
							text: query
						})
						.appendTo( $el )
						.autoEllipsis();
				} else {
					$el.find( '.special-query' )
						.empty()
						.text( query )
						.autoEllipsis();
				}
			},
			select: function ( $input ) {
				$input.closest( 'form' ).append(
					$( '<input>', {
						type: 'hidden',
						name: 'fulltext',
						val: '1'
					})
				);
				$input.closest( 'form' ).submit();
			}
		},
		$region: $( '#simpleSearch' )
	} );
});