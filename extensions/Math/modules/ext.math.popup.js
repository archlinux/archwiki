const previewType = 'math';
const formulaSelector = '.mwe-math-element[data-qid] img';
const linkSelector = '.mwe-math-element mrow[href][title]';
const selector = [ formulaSelector, linkSelector ].join( ', ' );
const restApi = new mw.Rest();
const pageApi = new mw.Api();
// Values from extension Popups to keep similar behaviour
// - EXTRACT_LENGTH in extensions/Popups/src/constants.js
// - bracketedPixelRatio in extensions/Popups/src/bracketedPixelRatio.js
const extractLength = 525;
const bracketedDevicePixelRatio = function () {
	const dpr = window.devicePixelRatio || 1;
	if ( dpr > 1.5 ) {
		return 2;
	}
	if ( dpr > 1 ) {
		return 1.5;
	}
	return 1;
};
const fetch = function ( qid ) {
	return restApi.get( '/math/v0/popup/html/' + qid, {}, {
		Accept: 'application/json; charset=utf-8',
		'Accept-Language': mw.config.language
	} );
};
const fetchPagePreview = function ( title ) {
	const thumbnailSize = 320 * Math.max( bracketedDevicePixelRatio(), 1.5 );
	return pageApi.get( {
		action: 'query',
		prop: 'info|extracts|pageimages',
		formatversion: 2,
		redirects: true,
		exintro: true,
		explaintext: true,
		exsectionformat: 'plain',
		exchars: extractLength,
		piprop: 'thumbnail',
		pithumbsize: thumbnailSize,
		pilicense: 'any',
		inprop: 'url',
		titles: title.getPrefixedDb(),
		smaxage: 300, // cache for 5 minuntes
		maxage: 300,
		uselang: 'content'
	}, {
		headers: {
			'X-Analytics': 'preview=1',
			'Accept-Language': mw.config.get( 'wgPageContentLanguage' )
		}
	} );
};
const extractPageFromResponse = function ( data ) {
	if ( data && data.query && data.query.pages && data.query.pages.length ) {
		return data.query.pages[ 0 ];
	}
	return null;
};
const getQidStr = function ( parent ) {
	if ( parent.getAttribute( 'href' ) ) {
		const href = parent.getAttribute( 'href' );
		const match = href.match( /qid=(Q\d+)/ );
		if ( match ) {
			return match[ 1 ];
		}
	}
	return null;
};
const getQidForElement = function ( el ) {
	if ( !el.matches( formulaSelector ) ) {
		return null;
	}
	const parent = el.closest( '.mwe-math-element' );
	let qidstr = getQidStr( parent );
	if ( parent.dataset.qid ) {
		qidstr = parent.dataset.qid;
	}
	return qidstr;
};
const fetchPreviewForTitle = function ( title, el ) {
	const deferred = $.Deferred();
	let qidstr = getQidForElement( el );
	// Preview for MathML
	if ( !qidstr || ( qidstr.match( /Q\d+/g ) === null ) ) {
		fetchPagePreview( title ).then( ( data ) => {
			const page = extractPageFromResponse( data );
			if ( !page || page.missing ) {
				deferred.reject();
				return;
			}
			const extract = page.extract ? [ document.createTextNode( page.extract ) ] : undefined;
			const model = {
				title: page.title,
				url: page.canonicalurl,
				languageCode: page.pagelanguagehtmlcode,
				languageDirection: page.pagelanguagedir,
				extract,
				type: previewType,
				thumbnail: page.thumbnail,
				pageId: page.pageid
			};
			deferred.resolve( model );
		}, () => deferred.reject() );
		return deferred.promise();
	}
	// Preview for SVG
	qidstr = qidstr.slice( 1 );
	fetch( qidstr ).then( ( body ) => {
		const model = {
			title: body.title,
			url: body.canonicalurl,
			languageCode: body.pagelanguagehtmlcode,
			languageDirection: body.pagelanguagedir,
			extract: body.extract,
			type: previewType,
			thumbnail: undefined,
			pageId: body.pageId
		};
		deferred.resolve( model );
	}, () => deferred.reject() );
	return deferred.promise();
};
// popups require offsetHeight and offsetWidth attributes
[].forEach.call(
	document.querySelectorAll( selector ),
	( node ) => {
		// temporary hack to enable popup T380079
		node.href = node.getAttribute( 'href' ) || node.baseURI;
		if ( typeof node.offsetWidth === 'undefined' ) {
			node.offsetWidth = node.getBoundingClientRect().width || 1;
		}
		if ( typeof node.offsetHeight === 'undefined' ) {
			node.offsetHeight = node.getBoundingClientRect().height || 1;
		}
	}
);

const mathDisabledByUser = mw.user.isNamed() && mw.user.options.get( 'math-popups' ) !== '1';
const mathAppliesToThisPage = document.querySelectorAll( selector ).length > 0;

module.exports = !mathAppliesToThisPage || mathDisabledByUser ? null : {
	type: previewType,
	selector,
	gateway: {
		fetch,
		fetchPreviewForTitle
	}
};
