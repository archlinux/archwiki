'use strict';

const ArticlePage = require( '../support/pages/article_page' );
const Util = require( 'wdio-mediawiki/Util' );
const { iClickTheOverlayCloseButton } = require( './common_steps' );

const iHaveNoNotifications = async () => {
	await ArticlePage.notifications_button_element.waitForDisplayed();
	// This is somewhat hacky, but we don't want this test making use of
	// Echo's APIs which may change
	await browser.execute( '$( () => { $( ".notification-count span" ).hide(); } );' );
};

const iClickOnTheNotificationIcon = async () => {
	await Util.waitForModuleState( 'skins.minerva.scripts' );
	await ArticlePage.notifications_button_element.waitForDisplayed();
	await ArticlePage.notifications_button_element.click();
};

const iClickTheNotificationsOverlayCloseButton = async () => {
	await iClickTheOverlayCloseButton();
};

module.exports = {
	iHaveNoNotifications, iClickOnTheNotificationIcon,
	iClickTheNotificationsOverlayCloseButton
};
