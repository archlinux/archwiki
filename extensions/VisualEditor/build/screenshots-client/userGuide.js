/* eslint-disable no-jquery/no-global-selector */

module.exports = {
	toolbar: function () {
		const done = arguments[ arguments.length - 1 ];
		// HACK: The test page is on the help namespace, so overwrite the
		// read tab with the nstab-main message.
		new mw.Api().loadMessagesIfMissing( [ 'nstab-main' ], { amenableparser: true } ).then( () => {
			$( '#ca-nstab-help a' ).text( mw.msg( 'nstab-main' ) );
			done(
				seleniumUtils.getBoundingRect( [
					ve.init.target.toolbar.$element[ 0 ],
					$( '#ca-view' )[ 0 ]
				] )
			);
		} );
	},
	toolbarActions: function () {
		const done = arguments[ arguments.length - 1 ];
		done(
			seleniumUtils.getBoundingRect( [
				ve.init.target.toolbar.$after[ 0 ]
			] )
		);
	},
	citoidInspector: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;
		ve.init.target.toolbar.tools.citoid.onSelect();
		setTimeout( () => {
			done(
				seleniumUtils.getBoundingRect( [
					surface.$element.find( '.ve-ce-mwReferenceNode' )[ 0 ],
					surface.context.inspectors.currentWindow.$element[ 0 ]
				] )
			);
		}, 500 );
	},
	citoidInspectorManual: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;
		surface.context.inspectors.currentWindow.setModePanel( 'manual' );
		setTimeout( () => {
			done(
				seleniumUtils.getBoundingRect( [
					surface.$element.find( '.ve-ce-mwReferenceNode' )[ 0 ],
					surface.context.inspectors.currentWindow.$element[ 0 ]
				] )
			);
		} );
	},
	citoidInspectorReuse: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;
		surface.context.inspectors.currentWindow.setModePanel( 'reuse' );
		setTimeout( () => {
			done(
				seleniumUtils.getBoundingRect( [
					surface.$element.find( '.ve-ce-mwReferenceNode' )[ 0 ],
					surface.context.inspectors.currentWindow.$element[ 0 ]
				] )
			);
		} );
	},
	citoidInspectorTeardown: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;
		surface.context.inspectors.currentWindow.close().closed.then( done );
	},
	toolbarHeadings: function () {
		seleniumUtils.runMenuTask( arguments[ arguments.length - 1 ], ve.init.target.toolbar.tools.paragraph );
	},
	toolbarFormatting: function () {
		seleniumUtils.runMenuTask( arguments[ arguments.length - 1 ], ve.init.target.toolbar.tools.bold, true );
	},
	toolbarLists: function () {
		seleniumUtils.runMenuTask( arguments[ arguments.length - 1 ], ve.init.target.toolbar.tools.bullet );
	},
	toolbarInsert: function () {
		seleniumUtils.runMenuTask( arguments[ arguments.length - 1 ], ve.init.target.toolbar.tools.media, true );
	},
	toolbarMedia: function () {
		seleniumUtils.runMenuTask( arguments[ arguments.length - 1 ], ve.init.target.toolbar.tools.media, false, true );
	},
	toolbarTemplate: function () {
		seleniumUtils.runMenuTask( arguments[ arguments.length - 1 ], ve.init.target.toolbar.tools.transclusion, false, true );
	},
	toolbarTable: function () {
		seleniumUtils.runMenuTask( arguments[ arguments.length - 1 ], ve.init.target.toolbar.tools.insertTable, false, true );
	},
	toolbarFormula: function () {
		seleniumUtils.runMenuTask( arguments[ arguments.length - 1 ], ve.init.target.toolbar.tools.math, true, true );
	},
	toolbarReferences: function () {
		seleniumUtils.runMenuTask( arguments[ arguments.length - 1 ], ve.init.target.toolbar.tools.referencesList, true, true );
	},
	toolbarSettings: function () {
		seleniumUtils.runMenuTask( arguments[ arguments.length - 1 ], ve.init.target.toolbar.tools.advancedSettings, false, false,
			[ ve.init.target.toolbarSaveButton.$element[ 0 ] ]
		);
	},
	toolbarPageSettings: function () {
		seleniumUtils.runMenuTask( arguments[ arguments.length - 1 ], ve.init.target.toolbar.tools.settings, false, true );
	},
	toolbarCategory: function () {
		seleniumUtils.runMenuTask( arguments[ arguments.length - 1 ], ve.init.target.toolbar.tools.categories, false, true );
	},
	toolbarTeardown: function () {
		const done = arguments[ arguments.length - 1 ];
		seleniumUtils.collapseToolbar();
		done();
	},
	save: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;

		surface.dialogs.once( 'opening', ( win, opening ) => {
			opening.then( () => {
				setTimeout( () => {
					done(
						seleniumUtils.getBoundingRect( [
							ve.init.target.surface.dialogs.currentWindow.$frame[ 0 ]
						] )
					);
				}, 500 );
			} );
		} );
		ve.init.target.toolbarSaveButton.onSelect();
	},
	saveTeardown: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;
		surface.dialogs.currentWindow.close().closed.then( done );
	},
	specialCharacters: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;

		surface.getToolbarDialogs().once( 'opening', ( win, opening ) => {
			opening.then( () => {
				setTimeout( () => {
					done(
						seleniumUtils.getBoundingRect( [
							ve.init.target.toolbar.tools.specialCharacter.$element[ 0 ],
							ve.init.target.surface.toolbarDialogs.$element[ 0 ]
						] )
					);
				}, 500 );
			} );
		} );
		ve.init.target.toolbar.tools.specialCharacter.onSelect();
	},
	specialCharactersTeardown: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;
		surface.getToolbarDialogs().currentWindow.close().closed.then( done );
	},
	formula: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;

		surface.dialogs.once( 'opening', ( win, opening ) => {
			opening.then( () => {
				win.previewElement.once( 'render', () => {
					win.previewElement.$element.find( 'img' ).on( 'load', () => {
						done(
							seleniumUtils.getBoundingRect( [
								win.$frame[ 0 ]
							] )
						);
					} );
				} );
				win.input.setValue( 'E = mc^2' ).moveCursorToEnd();
			} );
		} );
		surface.executeCommand( 'mathDialog' );
	},
	formulaTeardown: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;
		surface.dialogs.currentWindow.close().closed.then( done );
	},
	referenceList: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;

		surface.dialogs.once( 'opening', ( win, opening ) => {
			opening.then( () => {
				setTimeout( () => {
					done(
						seleniumUtils.getBoundingRect( [
							win.$frame[ 0 ]
						] )
					);
				}, 500 );
			} );
		} );
		surface.executeCommand( 'referencesList' );
		// The first command inserts a reference list instantly, so run again to open the window
		surface.executeCommand( 'referencesList' );
	},
	referenceListTeardown: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;
		surface.dialogs.currentWindow.close().closed.then( () => {
			// Remove the reference list
			surface.getModel().undo();
			done();
		} );
	},
	toolbarCite: function () {
		const done = arguments[ arguments.length - 1 ];

		ve.init.target.toolbar.tools.citoid.$element.css( 'font-size', '250%' );
		// Wait for re-paint
		setTimeout( () => {
			done(
				seleniumUtils.getBoundingRect( [
					ve.init.target.toolbar.tools.citoid.$element[ 0 ]
				] )
			);
		}, 100 );
	},
	toolbarCiteTeardown: function () {
		const done = arguments[ arguments.length - 1 ];
		ve.init.target.toolbar.tools.citoid.$element.css( 'font-size', '' );
		done();
	},
	linkSearchResults: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;

		surface.getModel().getFragment()
			// TODO: i18n this message, the linked word, and the API endpoint of the link inspector
			.insertContent( 'World literature is literature that is read by many people all over' )
			.collapseToStart().select();

		surface.context.inspectors.once( 'opening', ( win, opening ) => {
			opening.then( () => {
				surface.context.inspectors.windows.link.annotationInput.input.requestRequest.then( () => {
					// Wait a while for the images to load using a time guesstimate - as they're background
					// images it's quite tricky to get load events.
					setTimeout( () => {
						done(
							seleniumUtils.getBoundingRect( [
								surface.$element.find( '.ve-ce-linkAnnotation' )[ 0 ],
								surface.context.inspectors.currentWindow.$element[ 0 ]
							] )
						);
					}, 2500 );
				} );
			} );
		} );
		ve.init.target.surface.executeCommand( 'link' );
	},
	linkSearchResultsTeardown: function () {
		const done = arguments[ arguments.length - 1 ],
			surface = ve.init.target.surface;

		surface.context.inspectors.currentWindow.close().closed.then( () => {
			// Remove content
			surface.getModel().undo();
			done();
		} );
	}
};
