{
	const toggleCirrusSearchLookup = ( enabled ) => mw.config.set( 'wgVisualEditorConfig', ve.extendObject( {}, mw.config.get( 'wgVisualEditorConfig' ), {
			cirrusSearchLookup: enabled !== false
		} ) );

	QUnit.module( 've.ui.MWTemplateTitleInputWidget', ve.test.utils.newMwEnvironment( {
		messages: {
			// Force `templateDataInstalled` condition
			'templatedata-doc-subpage': '(templatedata-doc-subpage)'
		},
		// Config will be reset by newMwEnvironment's teardown
		beforeEach: function () {
			this.server = this.sandbox.useFakeServer();
			toggleCirrusSearchLookup();
		}
	} ) );

	QUnit.test( 'default prefixsearch', ( assert ) => {
		toggleCirrusSearchLookup( false );

		const widget = new ve.ui.MWTemplateTitleInputWidget();
		const query = 'a';
		const apiParams = widget.getApiParams( query );

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
		const widget = new ve.ui.MWTemplateTitleInputWidget();
		const query = 'a';
		const apiParams = widget.getApiParams( query );

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
		const widget = new ve.ui.MWTemplateTitleInputWidget( { showRedirectTargets: false } ),
			apiParams = widget.getApiParams();

		assert.false( 'gsrprop' in apiParams );
	} );

	QUnit.test.each( 'CirrusSearch: prefixsearch behavior', [
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
	], ( assert, data ) => {
		const widget = new ve.ui.MWTemplateTitleInputWidget();
		const apiParams = widget.getApiParams( data.query );

		assert.strictEqual(
			apiParams.gsrsearch,
			data.expected,
			'Searching for ' + data.query
		);
	} );

	QUnit.test( 'CirrusSearch with prefixsearch fallback', async function ( assert ) {
		const api = new mw.Api();
		this.sandbox.stub( api, 'get' )
			.onFirstCall().returns( ve.createDeferred()
				.resolve( { query: {
					pages: [
						{ pageid: 101, title: 'B' },
						{ pageid: 102, title: 'A' },
						// Documentation subpage, expected to be stripped
						{ pageid: 103, title: 'A/(templatedata-doc-subpage)', index: 2 }
					],
					redirects: [
						// Alternative source for indexes, expected to be copied to the pages array
						{ from: '', to: 'B', index: 1 },
						{ from: '', to: 'A', index: 0 }
					]
				} } )
				.promise( { abort: () => {} } )
			)
			.onSecondCall().returns( ve.createDeferred()
				.resolve( { query: { pages: [
					// Duplicate found by CirrusSearch (above) and prefixsearch
					{ pageid: 102, title: 'A', index: 2 },
					// New prefixsearch matches, expected to be prepended, in order of relevance
					{ pageid: 201, title: 'D', index: 1 },
					{ pageid: 202, title: 'C', index: 0 }
				] } } )
			)
			.onThirdCall().returns( ve.createDeferred()
				.resolve( /* we can skip the templatedata request for this test */ )
			);

		const widget = new ve.ui.MWTemplateTitleInputWidget( { api, showDescriptions: true } );
		widget.setValue( 'something' );

		const response = await widget.getLookupRequest();
		assert.deepEqual( response.query.pages, [
			{ pageid: 202, title: 'C', index: -10 },
			{ pageid: 201, title: 'D', index: -9 },
			{ pageid: 102, title: 'A', index: 0 },
			{ pageid: 101, title: 'B', index: 1 }
		] );
	} );

	QUnit.test( 'CirrusSearch: redirect is forwarded to the TitleOptionWidget', ( assert ) => {
		const widget = new ve.ui.MWTemplateTitleInputWidget();
		const originalData = { redirecttitle: 'Template:From' };
		const data = widget.getOptionWidgetData( 'Template:To', { originalData } );

		assert.strictEqual( data.redirecttitle, 'Template:From' );
	} );

	QUnit.test( 'CirrusSearch: redirect appears in the description', ( assert ) => {
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
