// Instead of mocking wvui, we ensure it matches the wvui-search module
// i.e. https://github.com/wikimedia/mediawiki/blob/master/resources/src/wvui/wvui-search.js
// @ts-ignore
module.exports = require( '@wikimedia/wvui/dist/commonjs2/wvui-search.commonjs2.js' ).default;
