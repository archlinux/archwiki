import Page from 'wdio-mediawiki/Page';

class MathPage extends Page {

	get mathml() {
		return $( '.mwe-math-element' );
	}

}

export default new MathPage();
