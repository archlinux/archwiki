'use strict';
const Page = require( 'wdio-mediawiki/Page' );

class MathPage extends Page {

	get mathml() {
		return $( '.mwe-math-element' );
	}

}
module.exports = new MathPage();
