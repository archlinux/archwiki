const cacheKey = 'dt-thanks';

/**
 * Thank a comment item
 *
 * @param {CommentItem} commentItem Comment item
 * @return {jQuery.Promise} Resolves when thanks successfully sent, rejects on error
 */
function thankComment( commentItem ) {
	// TODO: Add recipient gender for messages
	const recipientGender = 'unknown';
	return OO.ui.confirm( mw.msg( 'thanks-confirmation2', mw.user ), {
		actions: [
			{
				action: 'accept',
				label: mw.msg( 'thanks-button-thank', mw.user, recipientGender ),
				flags: [ 'primary', 'progressive' ]
			},
			{
				action: 'cancel',
				label: mw.msg( 'cancel' ),
				flags: 'safe'
			}
		]
	} ).then( ( confirmed ) => {
		if ( !confirmed ) {
			return $.Deferred().reject().promise();
		}

		const api = require( './controller.js' ).getApi();

		return api.postWithToken( 'csrf', {
			action: 'discussiontoolsthank',
			// We don't need to store the correct transcluded comment page
			// for a thank, any page the comment appears on will do.
			page: mw.config.get( 'wgRelevantPageName' ),
			commentid: commentItem.id
		} ).then( () => {
			mw.notify( mw.msg( 'thanks-thanked-notice', commentItem.author, recipientGender, mw.user ), { type: 'success' } );
			cacheThanked( commentItem );
		}, ( code, data ) => {
			mw.notify( api.getErrorMessage( data ), { type: 'error' } );
			return $.Deferred().reject().promise();
		} );
	} );
}

function isThanked( threadItem ) {
	const cache = mw.storage.getObject( cacheKey ) || {};
	return cache[ threadItem.id ];
}

function cacheThanked( threadItem ) {
	const cache = mw.storage.getObject( cacheKey ) || {};
	cache[ threadItem.id ] = true;
	mw.storage.setObject( cacheKey, cache );
}

mw.hook( 'discussionToolsOverflowMenuOnChoose' ).add( ( id, menuItem, threadItem ) => {
	// TODO: Add recipient gender for messages
	const recipientGender = 'unknown';
	if ( id === 'thank' ) {
		thankComment( threadItem ).then( () => {
			menuItem.setLabel( mw.msg( 'thanks-button-thanked', mw.user, recipientGender ) );
			menuItem.setDisabled( true );
		} );
	}
} );

mw.hook( 'discussionToolsOverflowMenuOnAddItem' ).add( ( id, menuItem, threadItem ) => {
	// TODO: Add recipient gender for messages
	const recipientGender = 'unknown';
	if ( id === 'thank' && isThanked( threadItem ) ) {
		menuItem.setLabel( mw.msg( 'thanks-button-thanked', mw.user, recipientGender ) );
		menuItem.setDisabled( true );
	}
} );
