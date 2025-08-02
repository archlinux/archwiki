'use strict';

QUnit.dump.maxDepth = 999;

// List all test files here.
require( './ext.checkUser/checkuser/getUsersBlockForm.test.js' );
require( './ext.checkUser/checkuser/checkUserHelper/utils.test.js' );
require( './ext.checkUser/checkuser/checkUserHelper/buildUserElement.test.js' );
require( './ext.checkUser/checkuser/checkUserHelper/createTableText.test.js' );
require( './ext.checkUser/checkuser/checkUserHelper/createTable.test.js' );
require( './ext.checkUser/checkuser/checkUserHelper/generateData.test.js' );
require( './ext.checkUser.clientHints/index.test.js' );
require( './ext.checkUser.ipInfo.hooks/ext.ipinfo.infobox.widget.test.js' );
require( './ext.checkUser/investigate/blockform.test.js' );
require( './ext.checkUser.tempAccounts/ipRevealUtils.test.js' );
require( './ext.checkUser.tempAccounts/ipReveal.test.js' );
require( './ext.checkUser.tempAccounts/initOnLoad.test.js' );
require( './ext.checkUser.tempAccounts/initOnHook.test.js' );
require( './ext.checkUser.tempAccounts/rest.test.js' );
require( './ext.checkUser.tempAccounts/SpecialBlock.test.js' );
require( './ext.checkUser.tempAccounts/SpecialContributions.test.js' );
require( './ext.checkUser.tempAccounts/BlockDetailsPopupButtonWidget.test.js' );
