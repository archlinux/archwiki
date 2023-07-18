( function () {
	'use strict';
	var previewType = 'math';
	var api = new mw.Rest();
	var isValidId = function ( qid ) {
		return qid.match( /Q\d+/g ) === null;
	};
	var fetch = function ( qid ) {
		return api.get( '/math/v0/popup/html/' + qid, {}, {
			Accept: 'application/json; charset=utf-8',
			'Accept-Language': mw.config.language
		} );
	};
	var fetchPreviewForTitle = function ( title, el ) {
		var deferred = $.Deferred();
		var qidstr = el.parentNode.parentNode.dataset.qid;
		if ( isValidId( qidstr ) ) {
			return deferred.reject();
		}
		qidstr = qidstr.slice( 1 );
		fetch( qidstr ).then( function ( body ) {
			var model = {
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
	// popups require title attributes
	document.querySelectorAll( '.mwe-math-element[data-qid] img' )
		.forEach( function ( node ) {
			if ( isValidId( node.parentNode.parentNode.dataset.qid ) ) {
				node.dataset.title = 'math-unique-identifier';
			}
		} );
	module.exports = {
		type: previewType,
		selector: '.mwe-math-element[data-qid] img',
		gateway: {
			fetch: fetch,
			fetchPreviewForTitle: fetchPreviewForTitle
		}
	};
}() );
