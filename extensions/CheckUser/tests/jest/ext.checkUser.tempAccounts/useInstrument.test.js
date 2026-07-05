'use strict';

const useInstrument = require( '../../../modules/ext.checkUser.tempAccounts/useInstrument.js' );

describe( 'useInstrument', () => {
	it( 'should record events', () => {
		// Mock EventLogging infrastructure
		const submitInteraction = jest.fn();
		const newInstrument = jest.fn( () => ( {
			submitInteraction
		} ) );
		mw.eventLog = { newInstrument };

		const logEvent = useInstrument();

		logEvent( 'session_end' );
		logEvent( 'session_start', { sessionLength: 3600 } );

		expect( newInstrument ).toHaveBeenCalledTimes( 1 );

		expect( submitInteraction ).toHaveBeenCalledTimes( 2 );
		expect( submitInteraction ).toHaveBeenNthCalledWith( 1, 'session_end', {} );
		expect( submitInteraction ).toHaveBeenNthCalledWith( 2, 'session_start', {
			// eslint-disable-next-line camelcase
			action_context: JSON.stringify( { session_length: 3600 } )
		} );
	} );

	it( 'should not try to record events if EventLogging is unavailable', () => {
		// Simulate EventLogging being unavailable
		mw.eventLog = undefined;

		const logEvent = useInstrument();

		expect( () => {
			logEvent( 'session_end' );
		} ).not.toThrow();
	} );
} );
