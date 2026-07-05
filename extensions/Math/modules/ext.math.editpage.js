// CodeMirror keybinding for inserting <math> tags.
mw.hook( 'ext.CodeMirror.ready' ).add( ( cm ) => {
	const { Prec } = require( 'ext.CodeMirror.lib' );
	cm.keymap.registerKeyBindingHelp( 'insert', 'math-inline', {
		key: 'Mod-e',
		run: () => {
			cm.textSelection.encapsulateSelection( {
				pre: '<math display="inline">',
				post: '</math>'
			} );
			return true;
		},
		prec: Prec.high,
		msg: mw.msg( 'math-editpage-insert-inline' )
	}, cm.view );

	cm.keymap.registerKeyBindingHelp( 'insert', 'math-block', {
		key: 'Shift-Mod-e',
		run: () => {
			cm.textSelection.encapsulateSelection( {
				pre: '<math display="block">',
				post: '</math>'
			} );
			return true;
		},
		msg: mw.msg( 'math-editpage-insert-block' )
	}, cm.view );
} );

// WikiEditor toolbar button for inserting <math> tags.
mw.hook( 'wikiEditor.toolbarReady' ).add( ( $textarea ) => {
	$textarea.wikiEditor( 'addToToolbar', {
		section: 'advanced',
		group: 'insert',
		tools: {
			mathInline: {
				label: mw.msg( 'math-editpage-insert-inline' ),
				type: 'button',
				oouiIcon: 'mathematicsDisplayInline',
				action: {
					type: 'encapsulate',
					options: {
						pre: '<math display="inline">',
						post: '</math>'
					}
				},
				hotkey: 'e'
			},
			mathBlock: {
				label: mw.msg( 'math-editpage-insert-block' ),
				type: 'button',
				oouiIcon: 'mathematicsDisplayBlock',
				action: {
					type: 'encapsulate',
					options: {
						pre: '<math display="block">',
						post: '</math>'
					}
				}
			}
		}
	} );
} );
