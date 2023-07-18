/*!
 * JavaScript for Special:Preferences: section navigation.
 */
( function () {
	var switchingNoHash;
	var session = require( 'mediawiki.storage' ).session;
	module.exports = {
		switchingNoHash: switchingNoHash,
		/**
		 * Make sure the accessibility tip is focussable so that keyboard users take notice,
		 * but hide it by default to reduce visual clutter.
		 * Make sure it becomes visible when focused.
		 */
		insertHints: function () {
			$( '<div>' ).addClass( 'mw-navigation-hint' )
				.text( mw.msg( 'prefs-sections-navigation-hint' ) )
				.attr( {
					sectionIndex: 0
				} )
				.insertBefore( '.mw-htmlform-ooui-wrapper' );
		},

		/**
		 * Call layout-specific function for jumping to the correct section and manage hash state.
		 *
		 * @param {Function} setSection callback for opening the section
		 * @param {string} sectionName The name of a section
		 * @param {string} [fieldset] A fieldset containing a subsection
		 * @param {boolean} [noHash] A hash will be set according to the current
		 *  open section. Use this flag to suppress this.
		 */
		switchPrefSection: function ( setSection, sectionName, fieldset, noHash ) {
			if ( noHash ) {
				this.switchingNoHash = true;
			}
			setSection( sectionName, fieldset );
			if ( noHash ) {
				this.switchingNoHash = false;
			}
		},

		/**
		 * Determine the correct section indicated by the hash.
		 * This function is called onload and onhashchange.
		 *
		 * @param {Function} setSection callback for opening the section
		 */
		detectHash: function ( setSection ) {
			var hash = location.hash;
			if ( /^#mw-prefsection-[\w]+$/.test( hash ) ) {
				session.remove( 'mwpreferences-prevTab' );
				// Open proper section.
				this.switchPrefSection( setSection, hash.slice( 1 ) );
			} else if ( /^#mw-[\w-]+$/.test( hash ) ) {
				var subsection = document.getElementById( hash.slice( 1 ) );
				var $section = $( subsection ).closest( '.mw-prefs-section-fieldset' );
				if ( $section.length ) {
					session.remove( 'mwpreferences-prevTab' );
					// Open proper section and scroll to selected fieldset.
					this.switchPrefSection( setSection, $section.attr( 'id' ), subsection, true );
				}
			}
		},

		/**
		 * Determine if there is a valid hash or default section.
		 *
		 * @param {Function} setSection callback for opening the section
		 * @param {string} defaultSectionName The name of a section to load by default
		 */
		onHashChange: function ( setSection, defaultSectionName ) {
			var hash = location.hash;
			if ( /^#mw-[\w-]+/.test( hash ) ) {
				this.detectHash( setSection );
			} else if ( hash === '' && defaultSectionName ) {
				this.switchPrefSection( setSection, defaultSectionName, undefined, true );
			}
		},

		/**
		 * Trigger onHashChange onload to select the proper tab on startup.
		 *
		 * @param {Function} setSection callback for opening the section
		 * @param {string} defaultSection The name of a section to load by default
		 */
		onLoad: function ( setSection, defaultSection ) {
			$( window ).on( 'hashchange', this.onHashChange.bind( this, setSection, defaultSection )
			).trigger( 'hashchange' );
		},

		/**
		 * Restore the active tab after saving the preferences
		 *
		 * @param {Function} setSection callback for opening the section
		 * @param {Function} onSubmit callback for saving the active section name
		 */
		restorePrevSection: function ( setSection, onSubmit ) {
			var sectionName = session.get( 'mwpreferences-prevTab' );
			if ( sectionName ) {
				this.switchPrefSection( setSection, sectionName, undefined, true );
				// Deleting the key, the section states should be reset until we press Save
				session.remove( 'mwpreferences-prevTab' );
			}
			$( '#mw-prefs-form' ).on( 'submit', onSubmit );
		}

	};
}() );
