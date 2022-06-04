{
	const enableCirrusSearchLookup = function () {
		// Config will be reset by newMwEnvironment's teardown
		mw.config.set( 'wgVisualEditorConfig', ve.extendObject( {}, mw.config.get( 'wgVisualEditorConfig' ), {
			cirrusSearchLookup: true
		} ) );
	};

	QUnit.module( 've.ui.MWTemplateTitleInputWidget', ve.test.utils.newMwEnvironment() );

	QUnit.test( 'default prefixsearch', ( assert ) => {
		const widget = new ve.ui.MWTemplateTitleInputWidget(),
			query = 'a',
			apiParams = widget.getApiParams( query );

		assert.deepEqual( apiParams, {
			action: 'query',
			generator: 'prefixsearch',
			gpslimit: 10,
			gpsnamespace: 10,
			gpssearch: 'a',
			ppprop: 'disambiguation',
			prop: [ 'info', 'pageprops' ],
			redirects: true
		} );
	} );

	QUnit.test( 'CirrusSearch: all API parameters', ( assert ) => {
		enableCirrusSearchLookup();
		const widget = new ve.ui.MWTemplateTitleInputWidget(),
			query = 'a',
			apiParams = widget.getApiParams( query );

		assert.deepEqual( apiParams, {
			action: 'query',
			generator: 'search',
			gsrlimit: 10,
			gsrnamespace: 10,
			gsrprop: 'redirecttitle',
			gsrsearch: 'a*',
			ppprop: 'disambiguation',
			prop: [ 'info', 'pageprops' ],
			redirects: true
		} );
	} );

	QUnit.test( 'CirrusSearch: showRedirectTargets disabled', ( assert ) => {
		enableCirrusSearchLookup();
		const widget = new ve.ui.MWTemplateTitleInputWidget( { showRedirectTargets: false } ),
			apiParams = widget.getApiParams();

		assert.false( 'gsrprop' in apiParams );
	} );

	QUnit.test( 'CirrusSearch: prefixsearch behavior', ( assert ) => {
		enableCirrusSearchLookup();
		const widget = new ve.ui.MWTemplateTitleInputWidget();

		[
			{
				query: 'a',
				expected: 'a*'
			},
			{
				query: 'a ',
				expected: 'a '
			},
			{
				query: 'ü',
				expected: 'ü*'
			},
			{
				query: '3',
				expected: '3*'
			},
			{
				query: '!!',
				expected: '!!'
			},
			{
				query: 'Foo:',
				expected: 'Foo:'
			},
			{
				query: 'Foo:Bar',
				expected: 'Foo:Bar*'
			},
			{
				query: 'foo_',
				expected: 'foo_'
			},
			{
				query: 'foo-',
				expected: 'foo-'
			},
			{
				query: 'foo+',
				expected: 'foo+'
			},
			{
				query: 'foo/',
				expected: 'foo/'
			},
			{
				query: 'foo~',
				expected: 'foo~'
			},
			{
				query: 'foo*',
				expected: 'foo*'
			},
			{
				query: '(foo)',
				expected: '(foo)'
			},
			{
				query: '[foo]',
				expected: '[foo]'
			},
			{
				query: '{foo}',
				expected: '{foo}'
			},
			{
				query: '"foo"',
				expected: '"foo"'
			},
			{
				query: 'foß',
				expected: 'foß*'
			},
			{
				query: '中文字',
				expected: '中文字*'
			},
			{
				query: 'zhōngwénzì',
				expected: 'zhōngwénzì*'
			}
		].forEach( ( data ) => {
			const apiParams = widget.getApiParams( data.query );

			assert.strictEqual(
				apiParams.gsrsearch,
				data.expected,
				'Searching for ' + data.query
			);
		} );
	} );

	QUnit.test( 'CirrusSearch: redirect is forwarded to the TitleOptionWidget', ( assert ) => {
		enableCirrusSearchLookup();
		const widget = new ve.ui.MWTemplateTitleInputWidget(),
			originalData = { redirecttitle: 'Template:From' },
			data = widget.getOptionWidgetData( 'Template:To', { originalData } );

		assert.strictEqual( data.redirecttitle, 'Template:From' );
	} );

	QUnit.test( 'CirrusSearch: redirect appears in the description', ( assert ) => {
		enableCirrusSearchLookup();
		const widget = new ve.ui.MWTemplateTitleInputWidget();

		let option = widget.createOptionWidget( { redirecttitle: 'Template:From' } );
		assert.strictEqual(
			option.$element.find( '.ve-ui-mwTemplateTitleInputWidget-redirectedfrom' ).text(),
			'(redirectedfrom: From)'
		);

		widget.relative = false;
		option = widget.createOptionWidget( { redirecttitle: 'Template:From' } );
		assert.strictEqual(
			option.$element.find( '.ve-ui-mwTemplateTitleInputWidget-redirectedfrom' ).text(),
			'(redirectedfrom: Template:From)'
		);
	} );
}
