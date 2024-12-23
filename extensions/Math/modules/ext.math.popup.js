( function () {
	'use strict';
	const previewType = 'math';
	const selector = '.mwe-math-element[href*="qid"], .mwe-math-element[data-qid] img';
	const api = new mw.Rest();
	const fetch = function ( qid ) {
		return api.get( '/math/v0/popup/html/' + qid, {}, {
			Accept: 'application/json; charset=utf-8',
			'Accept-Language': mw.config.language
		} );
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
	const fetchPreviewForTitle = function ( title, el ) {
		const deferred = $.Deferred();
		const parent = el.closest( '.mwe-math-element' );
		let qidstr = getQidStr( parent );
		if ( parent.dataset.qid ) {
			qidstr = parent.dataset.qid;
		}
		if ( !qidstr || ( qidstr.match( /Q\d+/g ) === null ) ) {
			return deferred.reject();
		}
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
		} );
		return deferred.promise();
	};
	// popups require offsetHeight and offsetWidth attributes
	[].forEach.call(
		document.querySelectorAll( selector ),
		( node ) => {
			// temporary hack to enable popup T380079
			node.href = node.baseURI;
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
}() );
