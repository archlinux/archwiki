import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import BlankPage from 'wdio-mediawiki/BlankPage.js';

const specDir = path.dirname( fileURLToPath( import.meta.url ) );

let skipSource;

const evaluateSkip = async ( source, support ) => browser.execute( ( sourceArg, supportArg ) => {
	const doc = globalThis.document;
	const makeElement = ( tag ) => {
		const el = {
			setAttribute: ( name, value ) => {
				if ( name === 'href' ) {
					el.href = value;
				}
			}
		};

		if ( tag === 'menclose' && supportArg.menclose ) {
			el.notation = '';
		}

		if ( ( tag === 'mtable' || tag === 'mtd' ) && supportArg.columnAlign ) {
			el.columnalign = '';
		}

		if ( tag === 'mrow' && supportArg.href ) {
			el.href = '';
		}

		return el;
	};

	const stubDocument = supportArg.createElementNS === false ? {} : {
		createElementNS: ( ns, tag ) => makeElement( tag )
	};

	// eslint-disable-next-line no-new-func
	const fn = new Function( 'document', sourceArg );
	return fn( stubDocument || doc );
}, source, support );

const evaluateSkipInLoader = async ( source, support, moduleName ) => browser.executeAsync(
	( sourceArg, supportArg, nameArg, done ) => {
		const win = globalThis.window || globalThis;
		const doc = globalThis.document;
		const originalCreateElementNS = doc.createElementNS;
		const makeElement = ( tag ) => {
			const el = {
				setAttribute: ( attr, value ) => {
					if ( attr === 'href' ) {
						el.href = value;
					}
				}
			};

			if ( tag === 'menclose' && supportArg.menclose ) {
				el.notation = '';
			}

			if ( ( tag === 'mtable' || tag === 'mtd' ) && supportArg.columnAlign ) {
				el.columnalign = '';
			}

			if ( tag === 'mrow' && supportArg.href ) {
				el.href = '';
			}

			return el;
		};

		doc.createElementNS = ( ns, tag ) => makeElement( tag );

		try {
			win.mw.loader.register( nameArg, '1', [], null, null, sourceArg );
			win.mw.loader.implement( nameArg, () => {
				win[ nameArg ] = true;
			} );

			win.mw.loader.using( nameArg ).then( () => {
				const result = {
					state: win.mw.loader.getState( nameArg ),
					ran: !!win[ nameArg ]
				};
				delete win[ nameArg ];
				doc.createElementNS = originalCreateElementNS;
				done( result );
			}, ( err ) => {
				doc.createElementNS = originalCreateElementNS;
				done( { error: String( err ) } );
			} );
		} catch ( err ) {
			doc.createElementNS = originalCreateElementNS;
			done( { error: String( err ) } );
		}
	}, source, support, moduleName );

describe( 'Math polyfills skipFunction', () => {
	before( async () => {
		await BlankPage.open();
		skipSource = fs.readFileSync(
			path.resolve( specDir, '../../../modules/ext.math.polyfills.skip.js' ),
			'utf-8'
		);
	} );

	it( 'returns true when all MathML features are supported', async () => {
		const result = await evaluateSkip( skipSource, {
			createElementNS: true,
			menclose: true,
			columnAlign: true,
			href: true
		} );
		await expect( result ).toBe( true );
	} );

	it( 'returns false when a required feature is missing', async () => {
		const result = await evaluateSkip( skipSource, {
			createElementNS: true,
			menclose: true,
			columnAlign: false,
			href: true
		} );
		await expect( result ).toBe( false );
	} );

	it( 'skips loading when the skipFunction returns true', async () => {
		const result = await evaluateSkipInLoader( skipSource, {
			menclose: true,
			columnAlign: true,
			href: true
		}, 'ext.math.polyfills.skip-true-test' );
		await expect( result.error ).toBeUndefined();
		await expect( result.state ).toBe( 'ready' );
		await expect( result.ran ).toBe( false );
	} );

	it( 'loads when the skipFunction returns false', async () => {
		const result = await evaluateSkipInLoader( skipSource, {
			menclose: false,
			columnAlign: true,
			href: true
		}, 'ext.math.polyfills.skip-false-test' );
		await expect( result.error ).toBeUndefined();
		await expect( result.state ).toBe( 'ready' );
		await expect( result.ran ).toBe( true );
	} );
} );
