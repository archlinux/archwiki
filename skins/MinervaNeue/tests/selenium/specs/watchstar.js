import { iAmViewingAnUnwatchedPage } from '../features/step_definitions/create_page_api_steps.js';
import {
	iShouldSeeAToastNotificationWithMessage,
	iAmLoggedIntoTheMobileWebsite
} from '../features/step_definitions/common_steps.js';
import {
	theWatchstarShouldBeSelected,
	iClickTheWatchstar,
	iShouldSeeTheWatchstarPopupWithMessage
} from '../features/step_definitions/watch_steps.js';

// @chrome @smoke @test2.m.wikipedia.org @login @vagrant
describe( 'Manage Watchlist', () => {

	beforeEach( async () => {
		await iAmLoggedIntoTheMobileWebsite();
	} );

	it( 'Add an article to the watchlist', async () => {
		await iAmViewingAnUnwatchedPage();
		await iClickTheWatchstar();
		// Either a popup or notification, depending on the core version.
		if ( !await iShouldSeeTheWatchstarPopupWithMessage( 'added' ) ) {
			await iShouldSeeAToastNotificationWithMessage( 'added' );
		}
		await theWatchstarShouldBeSelected();
	} );
} );
