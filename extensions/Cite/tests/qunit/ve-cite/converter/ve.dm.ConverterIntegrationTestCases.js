'use strict';

/*!
 * VisualEditor Cite specific test cases for the Converter.  The normalizedBody in these tests
 * should match the HTML for Parsoid of the html2wt tests in visualEditorHtml2WtTests.txt
 *
 * @copyright 2011-2025 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */

ve.dm.ConverterIntegrationTestCases = {};

ve.dm.ConverterIntegrationTestCases.cases = {
	'mw:Reference: Simple reference re-use (T296044)': {
		// Wikitext:
		// Foo<ref name="bar">[[Bar]]</ref> Baz<ref name="bar" />
		body: ve.dm.example.singleLine`
			<p>
				Foo
				<sup about="#mwt1" class="mw-ref reference" data-mw='{"name":"ref","body":{"html":"
				<a rel=\\"mw:WikiLink\\" href=\\"./Bar\\">Bar
				</a>"},"attrs":{"name":"bar"}}' id="cite_ref-bar-1-1" rel="dc:references" typeof="mw:Extension/ref">
					<a href="#cite_note-bar-1"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></a>
				</sup>
				 Baz
				<sup about="#mwt2" class="mw-ref reference" data-mw='{"name":"ref","attrs":{"name":"bar"}}' id="cite_ref-bar-1-3" rel="dc:references" typeof="mw:Extension/ref">
					<a href="#cite_note-bar-1"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></a>
				</sup>
			</p>
		`,
		fromDataBody: ve.dm.example.singleLine`
			<p>
				Foo
				<sup data-mw='{"name":"ref","body":{"html":"
				<a rel=\\"mw:WikiLink\\" href=\\"./Bar\\">Bar
				</a>"},"attrs":{"name":"bar"}}' typeof="mw:Extension/ref">
				</sup>
				 Baz
				<sup data-mw='{"name":"ref","attrs":{"name":"bar"}}' typeof="mw:Extension/ref">
				</sup>
			</p>
		`,
		clipboardBody: ve.dm.example.singleLine`
			<p>
				Foo
				<sup typeof="mw:Extension/ref" data-mw='{"name":"ref","body":{"html":"
				<a href=\\"./Bar\\" rel=\\"mw:WikiLink\\">Bar
				</a>"},"attrs":{"name":"bar"}}' class="mw-ref reference">
					<a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a>
				</sup>
				 Baz
				<sup typeof="mw:Extension/ref" data-mw='{"name":"ref","attrs":{"name":"bar"}}' class="mw-ref reference">
					<a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a>
				</sup>
			</p>
		`,
		data: [
			{ type: 'paragraph' },
			'F', 'o', 'o',
			{
				type: 'mwReference',
				attributes: {
					listIndex: 0,
					listGroup: 'mwReference/',
					listKey: 'literal/bar',
					refGroup: '',
					mw: {
						name: 'ref',
						body: { html: '<a rel="mw:WikiLink" href="./Bar">Bar</a>' },
						attrs: { name: 'bar' }
					},
					originalMw: '{"name":"ref","body":{"html":"<a rel=\\"mw:WikiLink\\" href=\\"./Bar\\">Bar</a>"},"attrs":{"name":"bar"}}',
					contentsUsed: true
				}
			},
			{ type: '/mwReference' },
			' ', 'B', 'a', 'z',
			{
				type: 'mwReference',
				attributes: {
					listIndex: 0,
					listGroup: 'mwReference/',
					listKey: 'literal/bar',
					refGroup: '',
					mw: { name: 'ref', attrs: { name: 'bar' } },
					originalMw: '{"name":"ref","attrs":{"name":"bar"}}',
					contentsUsed: false
				}
			},
			{ type: '/mwReference' },
			{ type: '/paragraph' },
			{ type: 'internalList' },
			{ type: 'internalItem', attributes: { originalHtml: '<a rel="mw:WikiLink" href="./Bar">Bar</a>' } },
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			[
				'B',
				[ {
					type: 'link/mwInternal',
					attributes: {
						title: 'Bar',
						normalizedTitle: 'Bar',
						lookupTitle: 'Bar'
					}
				} ]
			],
			[
				'a',
				[ {
					type: 'link/mwInternal',
					attributes: {
						title: 'Bar',
						normalizedTitle: 'Bar',
						lookupTitle: 'Bar'
					}
				} ]
			],
			[
				'r',
				[ {
					type: 'link/mwInternal',
					attributes: {
						title: 'Bar',
						normalizedTitle: 'Bar',
						lookupTitle: 'Bar'
					}
				} ]
			],
			{ type: '/paragraph' },
			{ type: '/internalItem' },
			{ type: '/internalList' }
		]
	},
	'mw:Reference with comment': {
		body: ve.dm.example.singleLine`
			<p>
				<sup about="#mwt2" class="mw-ref reference"
				 data-mw='{"name":"ref","body":
				{"html":"Foo<!-- bar -->"},"attrs":{}}'
				 id="cite_ref-1-0" rel="dc:references" typeof="mw:Extension/ref">
					<a href="#cite_note-bar-1"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></a>
				</sup>
			</p>
		`,
		fromDataBody: ve.dm.example.singleLine`
			<p>
				<sup
				 data-mw='{"name":"ref","body":
				{"html":"Foo<!-- bar -->"},"attrs":{}}'
				 typeof="mw:Extension/ref"></sup>
			</p>
		`,
		clipboardBody: ve.dm.example.singleLine`
			<p>
				<sup typeof="mw:Extension/ref"
				 data-mw='{"attrs":{},"body":
			{"html":"Foo<span rel=\\"ve:Comment\\" data-ve-comment=\\" bar \\">&amp;nbsp;</span>"},"name":"ref"}'
			 class="mw-ref reference">
					<a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a>
				</sup>
			</p>
		`,
		previewBody: ve.dm.example.singleLine`
			<p>
				<sup typeof="mw:Extension/ref"
				 data-mw='{"attrs":{},"body":
				{"html":"Foo<!-- bar -->"},"name":"ref"}'
				 class="mw-ref reference">
					<a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a>
				</sup>
			</p>
		`,
		data: [
			{ type: 'paragraph' },
			{
				type: 'mwReference',
				attributes: {
					contentsUsed: true,
					listGroup: 'mwReference/',
					listIndex: 0,
					listKey: 'auto/0',
					mw: {
						attrs: {},
						body: {
							html: 'Foo<!-- bar -->'
						},
						name: 'ref'
					},
					originalMw: '{"name":"ref","body":{"html":"Foo<!-- bar -->"},"attrs":{}}',
					refGroup: ''
				}
			},
			{ type: '/mwReference' },
			{ type: '/paragraph' },
			{ type: 'internalList' },
			{ type: 'internalItem', attributes: { originalHtml: 'Foo<!-- bar -->' } },
			{
				internal: {
					generated: 'wrapper'
				},
				type: 'paragraph'
			},
			'F', 'o', 'o',
			{
				type: 'comment',
				attributes: {
					text: ' bar '
				}
			},
			{ type: '/comment' },
			{ type: '/paragraph' },
			{ type: '/internalItem' },
			{ type: '/internalList' }
		]
	}
};

// Wikitext:
// <ref name="bar">Body</ref>
// Text
// <ref name="bar" />
ve.dm.ConverterIntegrationTestCases.SimpleRefReuse = {
	data:
		[
			{
				type: 'paragraph',
				internal: {
					whitespace: [
						undefined,
						undefined,
						undefined,
						'\n'
					]
				}
			},
			{
				type: 'mwReference',
				attributes: {
					mw: {
						name: 'ref',
						attrs: {
							name: 'bar'
						},
						body: {
							id: 'mw-reference-text-cite_note-bar-1'
						}
					},
					originalMw: '{"name":"ref","attrs":{"name":"bar"},"body":{"id":"mw-reference-text-cite_note-bar-1"}}',
					listIndex: 0,
					listGroup: 'mwReference/',
					listKey: 'literal/bar',
					refGroup: '',
					contentsUsed: true,
					refListItemId: 'mw-reference-text-cite_note-bar-1'
				}
			},
			{
				type: '/mwReference'
			},
			'\n',
			'T',
			'e',
			'x',
			't',
			'\n',
			{
				type: 'mwReference',
				attributes: {
					mw: {
						name: 'ref',
						attrs: {
							name: 'bar'
						}
					},
					originalMw: '{"name":"ref","attrs":{"name":"bar"}}',
					listIndex: 0,
					listGroup: 'mwReference/',
					listKey: 'literal/bar',
					refGroup: '',
					contentsUsed: false
				}
			},
			{
				type: '/mwReference'
			},
			{
				type: '/paragraph'
			},
			{
				type: 'mwReferencesList',
				attributes: {
					mw: {
						name: 'references',
						attrs: {},
						autoGenerated: true
					},
					originalMw: '{"name":"references","attrs":{},"autoGenerated":true}',
					refGroup: '',
					listGroup: 'mwReference/',
					isResponsive: true,
					templateGenerated: false
				},
				internal: {
					whitespace: [
						'\n'
					]
				}
			},
			{
				type: '/mwReferencesList'
			},
			{
				type: 'internalList'
			},
			{
				type: 'internalItem',
				attributes: {
					originalHtml: 'Body'
				}
			},
			{
				type: 'paragraph',
				internal: {
					generated: 'wrapper'
				}
			},
			'B',
			'o',
			'd',
			'y',
			{
				type: '/paragraph'
			},
			{
				type: '/internalItem'
			},
			{
				type: '/internalList'
			}
		],
	body:
		'<p id="mwAg"><sup about="#mwt1" class="mw-ref reference" id="cite_ref-bar_1-0" rel="dc:references" typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-bar-1&quot;}}"><a href="./Example/DeleteReuse#cite_note-bar-1" id="mwAw"><span class="mw-reflink-text" id="mwBA"><span class="cite-bracket" id="mwBQ">[</span>1<span class="cite-bracket" id="mwBg">]</span></span></a></sup>\nText\n<sup about="#mwt2" class="mw-ref reference" id="cite_ref-bar_1-1" rel="dc:references" typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}"><a href="./Example/DeleteReuse#cite_note-bar-1" id="mwBw"><span class="mw-reflink-text" id="mwCA"><span class="cite-bracket" id="mwCQ">[</span>1<span class="cite-bracket" id="mwCg">]</span></span></a></sup></p>\n<div class="mw-references-wrap" typeof="mw:Extension/references" about="#mwt3" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;autoGenerated&quot;:true}" id="mwCw"><ol class="mw-references references" id="mwDA"><li about="#cite_note-bar-1" id="cite_note-bar-1" data-mw-footnote-number="1"><span rel="mw:referencedBy" class="mw-cite-backlink" id="mwDQ"><a href="./Example/DeleteReuse#cite_ref-bar_1-0" id="mwDg"><span class="mw-linkback-text" id="mwDw">1 </span></a><a href="./Example/DeleteReuse#cite_ref-bar_1-1" id="mwEA"><span class="mw-linkback-text" id="mwEQ">2 </span></a></span> <span id="mw-reference-text-cite_note-bar-1" class="mw-reference-text reference-text">Body</span></li>\n</ol></div>'
};

// Expect no Wikitext change
ve.dm.ConverterIntegrationTestCases.cases[ 'Simple ref reuse' ] = {
	data: ve.dm.ConverterIntegrationTestCases.SimpleRefReuse.data,
	body: ve.dm.ConverterIntegrationTestCases.SimpleRefReuse.body,
	fromDataBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-bar-1&quot;}}"></sup>\nText\n<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}"></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;autoGenerated&quot;:true}"><ol><li><span typeof="mw:Extension/ref" id="mw-reference-text-cite_note-bar-1">Body</span></li></ol></div>',
	normalizedBody:
		'<p id="mwAg"><sup about="#mwt1" class="mw-ref reference" id="cite_ref-bar_1-0" rel="dc:references" typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-bar-1&quot;}}"><a href="./Example/DeleteReuse#cite_note-bar-1" id="mwAw"><span class="mw-reflink-text" id="mwBA"><span class="cite-bracket" id="mwBQ">[</span>1<span class="cite-bracket" id="mwBg">]</span></span></a></sup>\nText\n<sup about="#mwt2" class="mw-ref reference" id="cite_ref-bar_1-1" rel="dc:references" typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}"><a href="./Example/DeleteReuse#cite_note-bar-1" id="mwBw"><span class="mw-reflink-text" id="mwCA"><span class="cite-bracket" id="mwCQ">[</span>1<span class="cite-bracket" id="mwCg">]</span></span></a></sup></p>\n<div class="mw-references-wrap" typeof="mw:Extension/references" about="#mwt3" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;autoGenerated&quot;:true}" id="mwCw"><ol class="mw-references references" id="mwDA"><li about="#cite_note-bar-1" id="cite_note-bar-1" data-mw-footnote-number="1"><span rel="mw:referencedBy" class="mw-cite-backlink" id="mwDQ"><a href="./Example/DeleteReuse#cite_ref-bar_1-0" id="mwDg"><span class="mw-linkback-text" id="mwDw">1 </span></a><a href="./Example/DeleteReuse#cite_ref-bar_1-1" id="mwEA"><span class="mw-linkback-text" id="mwEQ">2 </span></a></span> <span id="mw-reference-text-cite_note-bar-1" class="mw-reference-text reference-text">Body</span></li>\n</ol></div>',
	clipboardBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-bar-1&quot;,&quot;html&quot;:&quot;Body&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup>\nText\n<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;autoGenerated&quot;:true}"><ol class="mw-references references"><li style="--footnote-number: &quot;1.&quot;;"><span rel="mw:referencedBy"><a><span class="mw-linkback-text">1 </span></a><a><span class="mw-linkback-text">2 </span></a></span> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Body</p></span></div></span></li></ol></div>',
	previewBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-bar-1&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup>↵Text↵<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;autoGenerated&quot;:true}"><ol class="mw-references references"><li style="--footnote-number: &quot;1.&quot;;"><span rel="mw:referencedBy"><a><span class="mw-linkback-text">1 </span></a><a><span class="mw-linkback-text">2 </span></a></span> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Body</p></span></div></span></li></ol></div>',
	innerWhitespace:
		[
			undefined,
			undefined
		],
	preserveAnnotationDomElements:
		true
};

// Expected Wikitext after change:
// Text
// <ref name="bar">Body</ref>
ve.dm.ConverterIntegrationTestCases.cases[ 'Simple ref reuse ( delete ref )' ] = {
	data: ve.dm.ConverterIntegrationTestCases.SimpleRefReuse.data,
	body: ve.dm.ConverterIntegrationTestCases.SimpleRefReuse.body,
	modify:
		( model ) => {
			model.commit( ve.dm.Transaction.static.deserialize( [ 1, [ [ {
				type: 'mwReference',
				attributes: {
					mw: {
						name: 'ref',
						attrs: { name: 'bar' },
						body: { id: 'mw-reference-text-cite_note-bar-1' }
					},
					originalMw: '{"name":"ref","attrs":{"name":"bar"},"body":{"id":"mw-reference-text-cite_note-bar-1"}}',
					listIndex: 0,
					listGroup: 'mwReference/',
					listKey: 'literal/bar',
					refGroup: '',
					contentsUsed: true,
					refListItemId: 'mw-reference-text-cite_note-bar-1'
				},
				originalDomElementsHash: 'h5905f2e6efddeced'
			}, { type: '/mwReference' }, '\n' ], '' ], 20 ] ) );
		},
	fromDataBody:
		'<p>Text\n<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;},&quot;body&quot;:{&quot;html&quot;:&quot;Body&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;autoGenerated&quot;:true}"><ol><li><span typeof="mw:Extension/ref">Body</span></li></ol></div>',
	normalizedBody:
		'<p id="mwAg">Text\n<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;},&quot;body&quot;:{&quot;html&quot;:&quot;Body&quot;}}" class="mw-ref reference" about="#mwt2" id="cite_ref-bar_1-1" rel="dc:references"><a href="./Example/DeleteReuse#cite_note-bar-1" id="mwBw"><span class="mw-reflink-text" id="mwCA"><span class="cite-bracket" id="mwCQ">[</span>1<span class="cite-bracket" id="mwCg">]</span></span></a></sup></p>\n<div class="mw-references-wrap" typeof="mw:Extension/references" about="#mwt3" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;autoGenerated&quot;:true}" id="mwCw"><ol class="mw-references references" id="mwDA"><li about="#cite_note-bar-1" id="cite_note-bar-1" data-mw-footnote-number="1"><span rel="mw:referencedBy" class="mw-cite-backlink" id="mwDQ"><a href="./Example/DeleteReuse#cite_ref-bar_1-0" id="mwDg"><span class="mw-linkback-text" id="mwDw">1 </span></a><a href="./Example/DeleteReuse#cite_ref-bar_1-1" id="mwEA"><span class="mw-linkback-text" id="mwEQ">2 </span></a></span> <span id="mw-reference-text-cite_note-bar-1" class="mw-reference-text reference-text">Body</span></li>\n</ol></div>',
	clipboardBody:
		'<p>Text\n<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;},&quot;body&quot;:{&quot;html&quot;:&quot;Body&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;autoGenerated&quot;:true}"><ol class="mw-references references"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Body</p></span></div></span></li></ol></div>',
	previewBody:
		'<p>Text↵<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;autoGenerated&quot;:true}"><ol class="mw-references references"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Body</p></span></div></span></li></ol></div>',
	innerWhitespace:
		[
			undefined,
			undefined
		],
	preserveAnnotationDomElements:
		true
};

// Wikitext
// <ref>{{Cite|author=Miller|title=Foo}}</ref>
// <ref name="ldrTpl" />
// <references>
// <ref name="ldrTpl">{{Cite|author=Smith|title=Bar}}</ref>
// </references>
ve.dm.ConverterIntegrationTestCases.simpleTemplateInRefs = {
	data:
		[
			{
				type: 'paragraph',
				internal: {
					whitespace: [
						undefined,
						undefined,
						undefined,
						'\n'
					]
				}
			},
			{
				type: 'mwReference',
				attributes: {
					mw: {
						name: 'ref',
						attrs: {},
						body: {
							id: 'mw-reference-text-cite_note-1'
						}
					},
					originalMw: '{"name":"ref","attrs":{},"body":{"id":"mw-reference-text-cite_note-1"}}',
					listIndex: 0,
					listGroup: 'mwReference/',
					listKey: 'auto/0',
					refGroup: '',
					contentsUsed: true,
					refListItemId: 'mw-reference-text-cite_note-1'
				}
			},
			{
				type: '/mwReference'
			},
			'\n',
			{
				type: 'mwReference',
				attributes: {
					mw: {
						name: 'ref',
						attrs: {
							name: 'ldrTpl'
						}
					},
					originalMw: '{"name":"ref","attrs":{"name":"ldrTpl"}}',
					listIndex: 1,
					listGroup: 'mwReference/',
					listKey: 'literal/ldrTpl',
					refGroup: '',
					contentsUsed: false
				}
			},
			{
				type: '/mwReference'
			},
			{
				type: '/paragraph'
			},
			{
				type: 'mwReferencesList',
				attributes: {
					mw: {
						name: 'references',
						attrs: {},
						body: {
							html: "\n<sup about=\"#mwt5\" class=\"mw-ref reference\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-parsoid='{\"dsr\":[79,135,19,6]}' data-mw='{\"name\":\"ref\",\"attrs\":{\"name\":\"ldrTpl\"},\"body\":{\"id\":\"mw-reference-text-cite_note-ldrTpl-2\"}}'><a href=\"./Example/CiteTemplates#cite_note-ldrTpl-2\" data-parsoid=\"{}\"><span class=\"mw-reflink-text\" data-parsoid=\"{}\"><span class=\"cite-bracket\" data-parsoid=\"{}\">[</span>2<span class=\"cite-bracket\" data-parsoid=\"{}\">]</span></span></a></sup>\n"
						}
					},
					originalMw: "{\"name\":\"references\",\"attrs\":{},\"body\":{\"html\":\"\\n<sup about=\\\"#mwt5\\\" class=\\\"mw-ref reference\\\" rel=\\\"dc:references\\\" typeof=\\\"mw:Extension/ref\\\" data-parsoid='{\\\"dsr\\\":[79,135,19,6]}' data-mw='{\\\"name\\\":\\\"ref\\\",\\\"attrs\\\":{\\\"name\\\":\\\"ldrTpl\\\"},\\\"body\\\":{\\\"id\\\":\\\"mw-reference-text-cite_note-ldrTpl-2\\\"}}'><a href=\\\"./Example/CiteTemplates#cite_note-ldrTpl-2\\\" data-parsoid=\\\"{}\\\"><span class=\\\"mw-reflink-text\\\" data-parsoid=\\\"{}\\\"><span class=\\\"cite-bracket\\\" data-parsoid=\\\"{}\\\">[</span>2<span class=\\\"cite-bracket\\\" data-parsoid=\\\"{}\\\">]</span></span></a></sup>\\n\"}}",
					refGroup: '',
					listGroup: 'mwReference/',
					isResponsive: true,
					templateGenerated: false
				},
				internal: {
					whitespace: [
						'\n'
					]
				}
			},
			{
				type: 'paragraph',
				internal: {
					generated: 'wrapper',
					whitespace: [
						'\n',
						undefined,
						undefined,
						'\n'
					]
				}
			},
			{
				type: 'mwReference',
				attributes: {
					mw: {
						name: 'ref',
						attrs: {
							name: 'ldrTpl'
						},
						body: {
							id: 'mw-reference-text-cite_note-ldrTpl-2'
						}
					},
					originalMw: '{"name":"ref","attrs":{"name":"ldrTpl"},"body":{"id":"mw-reference-text-cite_note-ldrTpl-2"}}',
					listIndex: 1,
					listGroup: 'mwReference/',
					listKey: 'literal/ldrTpl',
					refGroup: '',
					contentsUsed: true,
					refListItemId: 'mw-reference-text-cite_note-ldrTpl-2'
				}
			},
			{
				type: '/mwReference'
			},
			{
				type: '/paragraph'
			},
			{
				type: '/mwReferencesList'
			},
			{
				type: 'internalList'
			},
			{
				type: 'internalItem',
				attributes: {
					originalHtml: '<a rel="mw:WikiLink" href="./Template:Cite?action=edit&amp;redlink=1" title="Template:Cite" about="#mwt1" typeof="mw:Transclusion mw:LocalizedAttrs" class="new" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Miller&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Foo&quot;}},&quot;i&quot;:0}}]}" data-mw-i18n="{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}" id="mwEA">Template:Cite</a>'
				}
			},
			{
				type: 'paragraph',
				internal: {
					generated: 'wrapper'
				}
			},
			{
				type: 'mwTransclusionInline',
				attributes: {
					mw: {
						parts: [
							{
								template: {
									target: {
										wt: 'Cite',
										href: './Template:Cite'
									},
									params: {
										author: {
											wt: 'Miller'
										},
										title: {
											wt: 'Foo'
										}
									},
									i: 0
								}
							}
						]
					},
					originalMw: '{"parts":[{"template":{"target":{"wt":"Cite","href":"./Template:Cite"},"params":{"author":{"wt":"Miller"},"title":{"wt":"Foo"}},"i":0}}]}'
				}
			},
			{
				type: '/mwTransclusionInline'
			},
			{
				type: '/paragraph'
			},
			{
				type: '/internalItem'
			},
			{
				type: 'internalItem',
				attributes: {
					originalHtml: '<a rel="mw:WikiLink" href="./Template:Cite?action=edit&amp;redlink=1" title="Template:Cite" about="#mwt4" typeof="mw:Transclusion mw:LocalizedAttrs" class="new" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Smith&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Bar&quot;}},&quot;i&quot;:0}}]}" data-mw-i18n="{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}" id="mwFA">Template:Cite</a>'
				}
			},
			{
				type: 'paragraph',
				internal: {
					generated: 'wrapper'
				}
			},
			{
				type: 'mwTransclusionInline',
				attributes: {
					mw: {
						parts: [
							{
								template: {
									target: {
										wt: 'Cite',
										href: './Template:Cite'
									},
									params: {
										author: {
											wt: 'Smith'
										},
										title: {
											wt: 'Bar'
										}
									},
									i: 0
								}
							}
						]
					},
					originalMw: '{"parts":[{"template":{"target":{"wt":"Cite","href":"./Template:Cite"},"params":{"author":{"wt":"Smith"},"title":{"wt":"Bar"}},"i":0}}]}'
				}
			},
			{
				type: '/mwTransclusionInline'
			},
			{
				type: '/paragraph'
			},
			{
				type: '/internalItem'
			},
			{
				type: '/internalList'
			}
		],
	body:
		"<p id=\"mwAg\"><sup about=\"#mwt2\" class=\"mw-ref reference\" id=\"cite_ref-1\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-mw=\"{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;}}\"><a href=\"./Example/CiteTemplates#cite_note-1\" id=\"mwAw\"><span class=\"mw-reflink-text\" id=\"mwBA\"><span class=\"cite-bracket\" id=\"mwBQ\">[</span>1<span class=\"cite-bracket\" id=\"mwBg\">]</span></span></a></sup>\n<sup about=\"#mwt3\" class=\"mw-ref reference\" id=\"cite_ref-ldrTpl_2-0\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-mw=\"{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;ldrTpl&quot;}}\"><a href=\"./Example/CiteTemplates#cite_note-ldrTpl-2\" id=\"mwBw\"><span class=\"mw-reflink-text\" id=\"mwCA\"><span class=\"cite-bracket\" id=\"mwCQ\">[</span>2<span class=\"cite-bracket\" id=\"mwCg\">]</span></span></a></sup></p>\n<div class=\"mw-references-wrap\" typeof=\"mw:Extension/references\" about=\"#mwt6\" data-mw=\"{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup about=\\&quot;#mwt5\\&quot; class=\\&quot;mw-ref reference\\&quot; rel=\\&quot;dc:references\\&quot; typeof=\\&quot;mw:Extension/ref\\&quot; data-parsoid='{\\&quot;dsr\\&quot;:[79,135,19,6]}' data-mw='{\\&quot;name\\&quot;:\\&quot;ref\\&quot;,\\&quot;attrs\\&quot;:{\\&quot;name\\&quot;:\\&quot;ldrTpl\\&quot;},\\&quot;body\\&quot;:{\\&quot;id\\&quot;:\\&quot;mw-reference-text-cite_note-ldrTpl-2\\&quot;}}'&gt;&lt;a href=\\&quot;./Example/CiteTemplates#cite_note-ldrTpl-2\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;[&lt;/span&gt;2&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}\" id=\"mwCw\"><ol class=\"mw-references references\" id=\"mwDA\"><li about=\"#cite_note-1\" id=\"cite_note-1\"><span class=\"mw-cite-backlink\" id=\"mwDQ\"><a href=\"./Example/CiteTemplates#cite_ref-1\" rel=\"mw:referencedBy\" id=\"mwDg\"><span class=\"mw-linkback-text\" id=\"mwDw\">↑ </span></a></span> <span id=\"mw-reference-text-cite_note-1\" class=\"mw-reference-text reference-text\"><a rel=\"mw:WikiLink\" href=\"./Template:Cite?action=edit&amp;redlink=1\" title=\"Template:Cite\" about=\"#mwt1\" typeof=\"mw:Transclusion mw:LocalizedAttrs\" class=\"new\" data-mw=\"{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Miller&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Foo&quot;}},&quot;i&quot;:0}}]}\" data-mw-i18n=\"{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}\" id=\"mwEA\">Template:Cite</a></span></li>\n<li about=\"#cite_note-ldrTpl-2\" id=\"cite_note-ldrTpl-2\"><span class=\"mw-cite-backlink\" id=\"mwEQ\"><a href=\"./Example/CiteTemplates#cite_ref-ldrTpl_2-0\" rel=\"mw:referencedBy\" id=\"mwEg\"><span class=\"mw-linkback-text\" id=\"mwEw\">↑ </span></a></span> <span id=\"mw-reference-text-cite_note-ldrTpl-2\" class=\"mw-reference-text reference-text\"><a rel=\"mw:WikiLink\" href=\"./Template:Cite?action=edit&amp;redlink=1\" title=\"Template:Cite\" about=\"#mwt4\" typeof=\"mw:Transclusion mw:LocalizedAttrs\" class=\"new\" data-mw=\"{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Smith&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Bar&quot;}},&quot;i&quot;:0}}]}\" data-mw-i18n=\"{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}\" id=\"mwFA\">Template:Cite</a></span></li>\n</ol></div>"
};

// Expect no Wikitext change
ve.dm.ConverterIntegrationTestCases.cases[ 'Simple template used in refs' ] = {
	data: ve.dm.ConverterIntegrationTestCases.simpleTemplateInRefs.data,
	body: ve.dm.ConverterIntegrationTestCases.simpleTemplateInRefs.body,
	fromDataBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;,&quot;html&quot;:&quot;&lt;span typeof=\\&quot;mw:Transclusion\\&quot; data-mw=\\&quot;{&amp;quot;parts&amp;quot;:[{&amp;quot;template&amp;quot;:{&amp;quot;target&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Cite&amp;quot;,&amp;quot;href&amp;quot;:&amp;quot;./Template:Cite&amp;quot;},&amp;quot;params&amp;quot;:{&amp;quot;author&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Miller&amp;quot;},&amp;quot;title&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Foo&amp;quot;}},&amp;quot;i&amp;quot;:0}}]}\\&quot;&gt;&lt;/span&gt;&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup>\n<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;ldrTpl&quot;}}"></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;ldrTpl&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-ldrTpl-2&amp;quot;,&amp;quot;html&amp;quot;:&amp;quot;&amp;lt;span typeof=\\\\&amp;quot;mw:Transclusion\\\\&amp;quot; data-mw=\\\\&amp;quot;{&amp;amp;quot;parts&amp;amp;quot;:[{&amp;amp;quot;template&amp;amp;quot;:{&amp;amp;quot;target&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Cite&amp;amp;quot;,&amp;amp;quot;href&amp;amp;quot;:&amp;amp;quot;./Template:Cite&amp;amp;quot;},&amp;amp;quot;params&amp;amp;quot;:{&amp;amp;quot;author&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Smith&amp;amp;quot;},&amp;amp;quot;title&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Bar&amp;amp;quot;}},&amp;amp;quot;i&amp;amp;quot;:0}}]}\\\\&amp;quot;&amp;gt;&amp;lt;/span&amp;gt;&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;2&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol><li><span typeof="mw:Extension/ref" id="mw-reference-text-cite_note-1"><span typeof="mw:Transclusion" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Miller&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Foo&quot;}},&quot;i&quot;:0}}]}"></span></span></li><li><span typeof="mw:Extension/ref" id="mw-reference-text-cite_note-ldrTpl-2"><span typeof="mw:Transclusion" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Smith&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Bar&quot;}},&quot;i&quot;:0}}]}"></span></span></li></ol></div>',
	normalizedBody:
		"<p id=\"mwAg\"><sup about=\"#mwt2\" class=\"mw-ref reference\" id=\"cite_ref-1\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-mw=\"{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;}}\"><a href=\"./Example/CiteTemplates#cite_note-1\" id=\"mwAw\"><span class=\"mw-reflink-text\" id=\"mwBA\"><span class=\"cite-bracket\" id=\"mwBQ\">[</span>1<span class=\"cite-bracket\" id=\"mwBg\">]</span></span></a></sup>\n<sup about=\"#mwt3\" class=\"mw-ref reference\" id=\"cite_ref-ldrTpl_2-0\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-mw=\"{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;ldrTpl&quot;}}\"><a href=\"./Example/CiteTemplates#cite_note-ldrTpl-2\" id=\"mwBw\"><span class=\"mw-reflink-text\" id=\"mwCA\"><span class=\"cite-bracket\" id=\"mwCQ\">[</span>2<span class=\"cite-bracket\" id=\"mwCg\">]</span></span></a></sup></p>\n<div class=\"mw-references-wrap\" typeof=\"mw:Extension/references\" about=\"#mwt6\" data-mw=\"{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup about=\\&quot;#mwt5\\&quot; class=\\&quot;mw-ref reference\\&quot; rel=\\&quot;dc:references\\&quot; typeof=\\&quot;mw:Extension/ref\\&quot; data-parsoid='{\\&quot;dsr\\&quot;:[79,135,19,6]}' data-mw='{\\&quot;name\\&quot;:\\&quot;ref\\&quot;,\\&quot;attrs\\&quot;:{\\&quot;name\\&quot;:\\&quot;ldrTpl\\&quot;},\\&quot;body\\&quot;:{\\&quot;id\\&quot;:\\&quot;mw-reference-text-cite_note-ldrTpl-2\\&quot;}}'&gt;&lt;a href=\\&quot;./Example/CiteTemplates#cite_note-ldrTpl-2\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;[&lt;/span&gt;2&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}\" id=\"mwCw\"><ol class=\"mw-references references\" id=\"mwDA\"><li about=\"#cite_note-1\" id=\"cite_note-1\"><span class=\"mw-cite-backlink\" id=\"mwDQ\"><a href=\"./Example/CiteTemplates#cite_ref-1\" rel=\"mw:referencedBy\" id=\"mwDg\"><span class=\"mw-linkback-text\" id=\"mwDw\">↑ </span></a></span> <span id=\"mw-reference-text-cite_note-1\" class=\"mw-reference-text reference-text\"><a rel=\"mw:WikiLink\" href=\"./Template:Cite?action=edit&amp;redlink=1\" title=\"Template:Cite\" about=\"#mwt1\" typeof=\"mw:Transclusion mw:LocalizedAttrs\" class=\"new\" data-mw=\"{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Miller&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Foo&quot;}},&quot;i&quot;:0}}]}\" data-mw-i18n=\"{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}\" id=\"mwEA\">Template:Cite</a></span></li>\n<li about=\"#cite_note-ldrTpl-2\" id=\"cite_note-ldrTpl-2\"><span class=\"mw-cite-backlink\" id=\"mwEQ\"><a href=\"./Example/CiteTemplates#cite_ref-ldrTpl_2-0\" rel=\"mw:referencedBy\" id=\"mwEg\"><span class=\"mw-linkback-text\" id=\"mwEw\">↑ </span></a></span> <span id=\"mw-reference-text-cite_note-ldrTpl-2\" class=\"mw-reference-text reference-text\"><a rel=\"mw:WikiLink\" href=\"./Template:Cite?action=edit&amp;redlink=1\" title=\"Template:Cite\" about=\"#mwt4\" typeof=\"mw:Transclusion mw:LocalizedAttrs\" class=\"new\" data-mw=\"{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Smith&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Bar&quot;}},&quot;i&quot;:0}}]}\" data-mw-i18n=\"{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}\" id=\"mwFA\">Template:Cite</a></span></li>\n</ol></div>",
	clipboardBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;,&quot;html&quot;:&quot;&lt;span typeof=\\&quot;mw:Transclusion\\&quot; data-mw=\\&quot;{&amp;quot;parts&amp;quot;:[{&amp;quot;template&amp;quot;:{&amp;quot;target&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Cite&amp;quot;,&amp;quot;href&amp;quot;:&amp;quot;./Template:Cite&amp;quot;},&amp;quot;params&amp;quot;:{&amp;quot;author&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Miller&amp;quot;},&amp;quot;title&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Foo&amp;quot;}},&amp;quot;i&amp;quot;:0}}]}\\&quot; data-ve-no-generated-contents=\\&quot;true\\&quot;&gt;&amp;nbsp;&lt;/span&gt;&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup>\n<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;ldrTpl&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>2<span class="cite-bracket">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;ldrTpl&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-ldrTpl-2&amp;quot;,&amp;quot;html&amp;quot;:&amp;quot;&amp;lt;span typeof=\\\\&amp;quot;mw:Transclusion\\\\&amp;quot; data-mw=\\\\&amp;quot;{&amp;amp;quot;parts&amp;amp;quot;:[{&amp;amp;quot;template&amp;amp;quot;:{&amp;amp;quot;target&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Cite&amp;amp;quot;,&amp;amp;quot;href&amp;amp;quot;:&amp;amp;quot;./Template:Cite&amp;amp;quot;},&amp;amp;quot;params&amp;amp;quot;:{&amp;amp;quot;author&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Smith&amp;amp;quot;},&amp;amp;quot;title&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Bar&amp;amp;quot;}},&amp;amp;quot;i&amp;amp;quot;:0}}]}\\\\&amp;quot; data-ve-no-generated-contents=\\\\&amp;quot;true\\\\&amp;quot;&amp;gt;&amp;amp;nbsp;&amp;lt;/span&amp;gt;&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;2&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol class="mw-references references"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper"><span class="ve-ce-leafNode ve-ce-generatedContentNode-generating ve-ce-focusableNode ve-ce-mwTransclusionNode" contenteditable="false"></span></p></span></div></span></li><li style="--footnote-number: &quot;2.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper"><span class="ve-ce-leafNode ve-ce-generatedContentNode-generating ve-ce-focusableNode ve-ce-mwTransclusionNode" contenteditable="false"></span></p></span></div></span></li></ol></div>',
	previewBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup>↵<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;ldrTpl&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>2<span class="cite-bracket">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;ldrTpl&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-ldrTpl-2&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;2&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol class="mw-references references"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper"><span class="ve-ce-leafNode ve-ce-generatedContentNode-generating ve-ce-focusableNode ve-ce-mwTransclusionNode" contenteditable="false"></span></p></span></div></span></li><li style="--footnote-number: &quot;2.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper"><span class="ve-ce-leafNode ve-ce-generatedContentNode-generating ve-ce-focusableNode ve-ce-mwTransclusionNode" contenteditable="false"></span></p></span></div></span></li></ol></div>',
	innerWhitespace:
		[
			undefined,
			undefined
		],
	preserveAnnotationDomElements:
		true
};

// Expected Wikitext after change:
// <ref>{{Cite|author=Miller|title=Foo New}}</ref>
// <ref name="ldrTpl" />
// <references>
// <ref name="ldrTpl">{{Cite|author=Smith|title=Bar New}}</ref>
// </references>
ve.dm.ConverterIntegrationTestCases.cases[ 'Simple template in refs ( edits on template parameters )' ] = {
	data: ve.dm.ConverterIntegrationTestCases.simpleTemplateInRefs.data,
	body: ve.dm.ConverterIntegrationTestCases.simpleTemplateInRefs.body,
	modify:
		( model ) => {
			model.commit( ve.dm.Transaction.static.deserialize( [ 15, [ [ { type: 'paragraph', internal: { generated: 'wrapper', metaItems: [] } }, { type: 'mwTransclusionInline', attributes: { mw: { parts: [ { template: { target: { wt: 'Cite', href: './Template:Cite' }, params: { author: { wt: 'Miller' }, title: { wt: 'Foo' } }, i: 0 } } ] }, originalMw: '{"parts":[{"template":{"target":{"wt":"Cite","href":"./Template:Cite"},"params":{"author":{"wt":"Miller"},"title":{"wt":"Foo"}},"i":0}}]}' }, originalDomElementsHash: 'h9f955be85e91fd8f' }, { type: '/mwTransclusionInline' }, { type: '/paragraph' } ], '' ], 8 ] ) );
			model.commit( ve.dm.Transaction.static.deserialize( [ 14, [ [ { type: 'internalItem', attributes: { originalHtml: '<a rel="mw:WikiLink" href="./Template:Cite?action=edit&amp;redlink=1" title="Template:Cite" about="#mwt1" typeof="mw:Transclusion mw:LocalizedAttrs" class="new" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Miller&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Foo&quot;}},&quot;i&quot;:0}}]}" data-mw-i18n="{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}" id="mwEA">Template:Cite</a>' } }, { type: '/internalItem' }, { type: 'internalItem', attributes: { originalHtml: '<a rel="mw:WikiLink" href="./Template:Cite?action=edit&amp;redlink=1" title="Template:Cite" about="#mwt4" typeof="mw:Transclusion mw:LocalizedAttrs" class="new" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Smith&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Bar&quot;}},&quot;i&quot;:0}}]}" data-mw-i18n="{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}" id="mwFA">Template:Cite</a>' } }, { type: 'paragraph', internal: { generated: 'wrapper', metaItems: [] } }, { type: 'mwTransclusionInline', attributes: { mw: { parts: [ { template: { target: { wt: 'Cite', href: './Template:Cite' }, params: { author: { wt: 'Smith' }, title: { wt: 'Bar' } }, i: 0 } } ] }, originalMw: '{"parts":[{"template":{"target":{"wt":"Cite","href":"./Template:Cite"},"params":{"author":{"wt":"Smith"},"title":{"wt":"Bar"}},"i":0}}]}' }, originalDomElementsHash: 'h22c504c521f00956' }, { type: '/mwTransclusionInline' }, { type: '/paragraph' }, { type: '/internalItem' } ], [ { type: 'internalItem', attributes: { originalHtml: '<a rel="mw:WikiLink" href="./Template:Cite?action=edit&amp;redlink=1" title="Template:Cite" about="#mwt1" typeof="mw:Transclusion mw:LocalizedAttrs" class="new" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Miller&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Foo&quot;}},&quot;i&quot;:0}}]}" data-mw-i18n="{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}" id="mwEA">Template:Cite</a>' } }, { type: 'paragraph', internal: { generated: 'wrapper' } }, { type: 'mwTransclusionInline', attributes: { mw: { parts: [ { template: { target: { wt: 'Cite', href: './Template:Cite' }, params: { author: { wt: 'Miller' }, title: { wt: 'Foo New' } }, i: 0 } } ] }, originalMw: '{"parts":[{"template":{"target":{"wt":"Cite","href":"./Template:Cite"},"params":{"author":{"wt":"Miller"},"title":{"wt":"Foo"}},"i":0}}]}' }, originalDomElementsHash: 'h9f955be85e91fd8f' }, { type: '/mwTransclusionInline' }, { type: '/paragraph' }, { type: '/internalItem' }, { type: 'internalItem', attributes: { originalHtml: '<a rel="mw:WikiLink" href="./Template:Cite?action=edit&amp;redlink=1" title="Template:Cite" about="#mwt4" typeof="mw:Transclusion mw:LocalizedAttrs" class="new" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Smith&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Bar&quot;}},&quot;i&quot;:0}}]}" data-mw-i18n="{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}" id="mwFA">Template:Cite</a>' } }, { type: 'paragraph', internal: { generated: 'wrapper' } }, { type: 'mwTransclusionInline', attributes: { mw: { parts: [ { template: { target: { wt: 'Cite', href: './Template:Cite' }, params: { author: { wt: 'Smith' }, title: { wt: 'Bar' } }, i: 0 } } ] }, originalMw: '{"parts":[{"template":{"target":{"wt":"Cite","href":"./Template:Cite"},"params":{"author":{"wt":"Smith"},"title":{"wt":"Bar"}},"i":0}}]}' }, originalDomElementsHash: 'h22c504c521f00956' }, { type: '/mwTransclusionInline' }, { type: '/paragraph' }, { type: '/internalItem' } ] ], 1 ] ) );
			model.commit( ve.dm.Transaction.static.deserialize( [ 21, [ [ { type: 'paragraph', internal: { generated: 'wrapper' } }, { type: 'mwTransclusionInline', attributes: { mw: { parts: [ { template: { target: { wt: 'Cite', href: './Template:Cite' }, params: { author: { wt: 'Smith' }, title: { wt: 'Bar' } }, i: 0 } } ] }, originalMw: '{"parts":[{"template":{"target":{"wt":"Cite","href":"./Template:Cite"},"params":{"author":{"wt":"Smith"},"title":{"wt":"Bar"}},"i":0}}]}' }, originalDomElementsHash: 'h22c504c521f00956' }, { type: '/mwTransclusionInline' }, { type: '/paragraph' } ], '' ], 2 ] ) );
			model.commit( ve.dm.Transaction.static.deserialize( [ 14, [ [ { type: 'internalItem', attributes: { originalHtml: '<a rel="mw:WikiLink" href="./Template:Cite?action=edit&amp;redlink=1" title="Template:Cite" about="#mwt1" typeof="mw:Transclusion mw:LocalizedAttrs" class="new" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Miller&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Foo&quot;}},&quot;i&quot;:0}}]}" data-mw-i18n="{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}" id="mwEA">Template:Cite</a>' } }, { type: 'paragraph', internal: { generated: 'wrapper' } }, { type: 'mwTransclusionInline', attributes: { mw: { parts: [ { template: { target: { wt: 'Cite', href: './Template:Cite' }, params: { author: { wt: 'Miller' }, title: { wt: 'Foo New' } }, i: 0 } } ] }, originalMw: '{"parts":[{"template":{"target":{"wt":"Cite","href":"./Template:Cite"},"params":{"author":{"wt":"Miller"},"title":{"wt":"Foo"}},"i":0}}]}' }, originalDomElementsHash: 'h9f955be85e91fd8f' }, { type: '/mwTransclusionInline' }, { type: '/paragraph' }, { type: '/internalItem' }, { type: 'internalItem', attributes: { originalHtml: '<a rel="mw:WikiLink" href="./Template:Cite?action=edit&amp;redlink=1" title="Template:Cite" about="#mwt4" typeof="mw:Transclusion mw:LocalizedAttrs" class="new" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Smith&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Bar&quot;}},&quot;i&quot;:0}}]}" data-mw-i18n="{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}" id="mwFA">Template:Cite</a>' } }, { type: '/internalItem' } ], [ { type: 'internalItem', attributes: { originalHtml: '<a rel="mw:WikiLink" href="./Template:Cite?action=edit&amp;redlink=1" title="Template:Cite" about="#mwt1" typeof="mw:Transclusion mw:LocalizedAttrs" class="new" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Miller&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Foo&quot;}},&quot;i&quot;:0}}]}" data-mw-i18n="{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}" id="mwEA">Template:Cite</a>' } }, { type: 'paragraph', internal: { generated: 'wrapper' } }, { type: 'mwTransclusionInline', attributes: { mw: { parts: [ { template: { target: { wt: 'Cite', href: './Template:Cite' }, params: { author: { wt: 'Miller' }, title: { wt: 'Foo New' } }, i: 0 } } ] }, originalMw: '{"parts":[{"template":{"target":{"wt":"Cite","href":"./Template:Cite"},"params":{"author":{"wt":"Miller"},"title":{"wt":"Foo"}},"i":0}}]}' }, originalDomElementsHash: 'h9f955be85e91fd8f' }, { type: '/mwTransclusionInline' }, { type: '/paragraph' }, { type: '/internalItem' }, { type: 'internalItem', attributes: { originalHtml: '<a rel="mw:WikiLink" href="./Template:Cite?action=edit&amp;redlink=1" title="Template:Cite" about="#mwt4" typeof="mw:Transclusion mw:LocalizedAttrs" class="new" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Smith&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Bar&quot;}},&quot;i&quot;:0}}]}" data-mw-i18n="{&quot;title&quot;:{&quot;lang&quot;:&quot;x-page&quot;,&quot;key&quot;:&quot;red-link-title&quot;,&quot;params&quot;:[&quot;Template:Cite&quot;]}}" id="mwFA">Template:Cite</a>' } }, { type: 'paragraph', internal: { generated: 'wrapper' } }, { type: 'mwTransclusionInline', attributes: { mw: { parts: [ { template: { target: { wt: 'Cite', href: './Template:Cite' }, params: { author: { wt: 'Smith' }, title: { wt: 'Bar New' } }, i: 0 } } ] }, originalMw: '{"parts":[{"template":{"target":{"wt":"Cite","href":"./Template:Cite"},"params":{"author":{"wt":"Smith"},"title":{"wt":"Bar"}},"i":0}}]}' }, originalDomElementsHash: 'h22c504c521f00956' }, { type: '/mwTransclusionInline' }, { type: '/paragraph' }, { type: '/internalItem' } ] ], 1 ] ) );
		},
	fromDataBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;,&quot;html&quot;:&quot;&lt;span typeof=\\&quot;mw:Transclusion\\&quot; data-mw=\\&quot;{&amp;quot;parts&amp;quot;:[{&amp;quot;template&amp;quot;:{&amp;quot;target&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Cite&amp;quot;,&amp;quot;href&amp;quot;:&amp;quot;./Template:Cite&amp;quot;},&amp;quot;params&amp;quot;:{&amp;quot;author&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Miller&amp;quot;},&amp;quot;title&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Foo New&amp;quot;}},&amp;quot;i&amp;quot;:0}}]}\\&quot;&gt;&lt;/span&gt;&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup>\n<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;ldrTpl&quot;}}"></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;ldrTpl&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-ldrTpl-2&amp;quot;,&amp;quot;html&amp;quot;:&amp;quot;&amp;lt;span typeof=\\\\&amp;quot;mw:Transclusion\\\\&amp;quot; data-mw=\\\\&amp;quot;{&amp;amp;quot;parts&amp;amp;quot;:[{&amp;amp;quot;template&amp;amp;quot;:{&amp;amp;quot;target&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Cite&amp;amp;quot;,&amp;amp;quot;href&amp;amp;quot;:&amp;amp;quot;./Template:Cite&amp;amp;quot;},&amp;amp;quot;params&amp;amp;quot;:{&amp;amp;quot;author&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Smith&amp;amp;quot;},&amp;amp;quot;title&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Bar New&amp;amp;quot;}},&amp;amp;quot;i&amp;amp;quot;:0}}]}\\\\&amp;quot;&amp;gt;&amp;lt;/span&amp;gt;&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;2&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol><li><span typeof="mw:Extension/ref" id="mw-reference-text-cite_note-1"><span typeof="mw:Transclusion" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Miller&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Foo New&quot;}},&quot;i&quot;:0}}]}"></span></span></li><li><span typeof="mw:Extension/ref" id="mw-reference-text-cite_note-ldrTpl-2"><span typeof="mw:Transclusion" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Smith&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Bar New&quot;}},&quot;i&quot;:0}}]}"></span></span></li></ol></div>',
	normalizedBody:
		'<p id="mwAg"><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;,&quot;html&quot;:&quot;&lt;a typeof=\\&quot;mw:Transclusion\\&quot; data-mw=\\&quot;{&amp;quot;parts&amp;quot;:[{&amp;quot;template&amp;quot;:{&amp;quot;target&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Cite&amp;quot;,&amp;quot;href&amp;quot;:&amp;quot;./Template:Cite&amp;quot;},&amp;quot;params&amp;quot;:{&amp;quot;author&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Miller&amp;quot;},&amp;quot;title&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Foo New&amp;quot;}},&amp;quot;i&amp;quot;:0}}]}\\&quot; id=\\&quot;mwEA\\&quot;&gt;&lt;/a&gt;&quot;}}" class="mw-ref reference" about="#mwt2" id="cite_ref-1" rel="dc:references"><a href="./Example/CiteTemplates#cite_note-1" id="mwAw"><span class="mw-reflink-text" id="mwBA"><span class="cite-bracket" id="mwBQ">[</span>1<span class="cite-bracket" id="mwBg">]</span></span></a></sup>\n<sup about="#mwt3" class="mw-ref reference" id="cite_ref-ldrTpl_2-0" rel="dc:references" typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;ldrTpl&quot;}}"><a href="./Example/CiteTemplates#cite_note-ldrTpl-2" id="mwBw"><span class="mw-reflink-text" id="mwCA"><span class="cite-bracket" id="mwCQ">[</span>2<span class="cite-bracket" id="mwCg">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;ldrTpl&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-ldrTpl-2&amp;quot;,&amp;quot;html&amp;quot;:&amp;quot;&amp;lt;a typeof=\\\\&amp;quot;mw:Transclusion\\\\&amp;quot; data-mw=\\\\&amp;quot;{&amp;amp;quot;parts&amp;amp;quot;:[{&amp;amp;quot;template&amp;amp;quot;:{&amp;amp;quot;target&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Cite&amp;amp;quot;,&amp;amp;quot;href&amp;amp;quot;:&amp;amp;quot;./Template:Cite&amp;amp;quot;},&amp;amp;quot;params&amp;amp;quot;:{&amp;amp;quot;author&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Smith&amp;amp;quot;},&amp;amp;quot;title&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Bar New&amp;amp;quot;}},&amp;amp;quot;i&amp;amp;quot;:0}}]}\\\\&amp;quot; id=\\\\&amp;quot;mwFA\\\\&amp;quot;&amp;gt;&amp;lt;/a&amp;gt;&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot; about=\\&quot;#mwt5\\&quot; rel=\\&quot;dc:references\\&quot; data-parsoid=\\&quot;{&amp;quot;dsr&amp;quot;:[79,135,19,6]}\\&quot;&gt;&lt;a href=\\&quot;./Example/CiteTemplates#cite_note-ldrTpl-2\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;[&lt;/span&gt;2&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol><li><span typeof="mw:Extension/ref" id="mw-reference-text-cite_note-1"><a typeof="mw:Transclusion" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Miller&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Foo New&quot;}},&quot;i&quot;:0}}]}" id="mwEA"></a></span></li><li><span typeof="mw:Extension/ref" id="mw-reference-text-cite_note-ldrTpl-2"><a typeof="mw:Transclusion" data-mw="{&quot;parts&quot;:[{&quot;template&quot;:{&quot;target&quot;:{&quot;wt&quot;:&quot;Cite&quot;,&quot;href&quot;:&quot;./Template:Cite&quot;},&quot;params&quot;:{&quot;author&quot;:{&quot;wt&quot;:&quot;Smith&quot;},&quot;title&quot;:{&quot;wt&quot;:&quot;Bar New&quot;}},&quot;i&quot;:0}}]}" id="mwFA"></a></span></li></ol></div>',
	clipboardBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;,&quot;html&quot;:&quot;&lt;span typeof=\\&quot;mw:Transclusion\\&quot; data-mw=\\&quot;{&amp;quot;parts&amp;quot;:[{&amp;quot;template&amp;quot;:{&amp;quot;target&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Cite&amp;quot;,&amp;quot;href&amp;quot;:&amp;quot;./Template:Cite&amp;quot;},&amp;quot;params&amp;quot;:{&amp;quot;author&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Miller&amp;quot;},&amp;quot;title&amp;quot;:{&amp;quot;wt&amp;quot;:&amp;quot;Foo New&amp;quot;}},&amp;quot;i&amp;quot;:0}}]}\\&quot; data-ve-no-generated-contents=\\&quot;true\\&quot;&gt;&amp;nbsp;&lt;/span&gt;&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup>\n<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;ldrTpl&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>2<span class="cite-bracket">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;ldrTpl&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-ldrTpl-2&amp;quot;,&amp;quot;html&amp;quot;:&amp;quot;&amp;lt;span typeof=\\\\&amp;quot;mw:Transclusion\\\\&amp;quot; data-mw=\\\\&amp;quot;{&amp;amp;quot;parts&amp;amp;quot;:[{&amp;amp;quot;template&amp;amp;quot;:{&amp;amp;quot;target&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Cite&amp;amp;quot;,&amp;amp;quot;href&amp;amp;quot;:&amp;amp;quot;./Template:Cite&amp;amp;quot;},&amp;amp;quot;params&amp;amp;quot;:{&amp;amp;quot;author&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Smith&amp;amp;quot;},&amp;amp;quot;title&amp;amp;quot;:{&amp;amp;quot;wt&amp;amp;quot;:&amp;amp;quot;Bar New&amp;amp;quot;}},&amp;amp;quot;i&amp;amp;quot;:0}}]}\\\\&amp;quot; data-ve-no-generated-contents=\\\\&amp;quot;true\\\\&amp;quot;&amp;gt;&amp;amp;nbsp;&amp;lt;/span&amp;gt;&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;2&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol class="mw-references references"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper"><span class="ve-ce-leafNode ve-ce-generatedContentNode-generating ve-ce-focusableNode ve-ce-mwTransclusionNode" contenteditable="false"></span></p></span></div></span></li><li style="--footnote-number: &quot;2.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper"><span class="ve-ce-leafNode ve-ce-generatedContentNode-generating ve-ce-focusableNode ve-ce-mwTransclusionNode" contenteditable="false"></span></p></span></div></span></li></ol></div>',
	previewBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;id&quot;:&quot;mw-reference-text-cite_note-1&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup>↵<sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;ldrTpl&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>2<span class="cite-bracket">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;ldrTpl&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-ldrTpl-2&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;2&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol class="mw-references references"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper"><span class="ve-ce-leafNode ve-ce-generatedContentNode-generating ve-ce-focusableNode ve-ce-mwTransclusionNode" contenteditable="false"></span></p></span></div></span></li><li style="--footnote-number: &quot;2.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper"><span class="ve-ce-leafNode ve-ce-generatedContentNode-generating ve-ce-focusableNode ve-ce-mwTransclusionNode" contenteditable="false"></span></p></span></div></span></li></ol></div>',
	innerWhitespace:
		[
			undefined,
			undefined
		],
	preserveAnnotationDomElements:
		true
};

// Wikitext:
// <ref name="a" /> <ref name="a" group="g" /> <ref name="b" group="g" />
// <references>
// <ref name="a">Default group content</ref>
// </references>
// <references group="g">
// <ref name="a">Custom group content</ref>
// <ref name="b">Custom group content other</ref>
// </references>
ve.dm.ConverterIntegrationTestCases.listDefinedRefsWithGroups = {
	data:
		[
			{
				type: 'paragraph',
				internal: {
					whitespace: [
						undefined,
						undefined,
						undefined,
						'\n'
					]
				}
			},
			{
				type: 'mwReference',
				attributes: {
					mw: {
						name: 'ref',
						attrs: {
							name: 'a'
						}
					},
					originalMw: '{"name":"ref","attrs":{"name":"a"}}',
					listIndex: 0,
					listGroup: 'mwReference/',
					listKey: 'literal/a',
					refGroup: '',
					contentsUsed: false
				}
			},
			{
				type: '/mwReference'
			},
			' ',
			{
				type: 'mwReference',
				attributes: {
					mw: {
						name: 'ref',
						attrs: {
							name: 'a',
							group: 'g'
						}
					},
					originalMw: '{"name":"ref","attrs":{"name":"a","group":"g"}}',
					listIndex: 1,
					listGroup: 'mwReference/g',
					listKey: 'literal/a',
					refGroup: 'g',
					contentsUsed: false
				}
			},
			{
				type: '/mwReference'
			},
			' ',
			{
				type: 'mwReference',
				attributes: {
					mw: {
						name: 'ref',
						attrs: {
							name: 'b',
							group: 'g'
						}
					},
					originalMw: '{"name":"ref","attrs":{"name":"b","group":"g"}}',
					listIndex: 2,
					listGroup: 'mwReference/g',
					listKey: 'literal/b',
					refGroup: 'g',
					contentsUsed: false
				}
			},
			{
				type: '/mwReference'
			},
			{
				type: '/paragraph'
			},
			{
				type: 'mwReferencesList',
				attributes: {
					mw: {
						name: 'references',
						attrs: {},
						body: {
							html: "\n<sup about=\"#mwt4\" class=\"mw-ref reference\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-parsoid='{\"dsr\":[84,125,14,6]}' data-mw='{\"name\":\"ref\",\"attrs\":{\"name\":\"a\"},\"body\":{\"id\":\"mw-reference-text-cite_note-a-1\"}}'><a href=\"./TestPage#cite_note-a-1\" data-parsoid=\"{}\"><span class=\"mw-reflink-text\" data-parsoid=\"{}\"><span class=\"cite-bracket\" data-parsoid=\"{}\">[</span>1<span class=\"cite-bracket\" data-parsoid=\"{}\">]</span></span></a></sup>\n"
						}
					},
					originalMw: "{\"name\":\"references\",\"attrs\":{},\"body\":{\"html\":\"\\n<sup about=\\\"#mwt4\\\" class=\\\"mw-ref reference\\\" rel=\\\"dc:references\\\" typeof=\\\"mw:Extension/ref\\\" data-parsoid='{\\\"dsr\\\":[84,125,14,6]}' data-mw='{\\\"name\\\":\\\"ref\\\",\\\"attrs\\\":{\\\"name\\\":\\\"a\\\"},\\\"body\\\":{\\\"id\\\":\\\"mw-reference-text-cite_note-a-1\\\"}}'><a href=\\\"./TestPage#cite_note-a-1\\\" data-parsoid=\\\"{}\\\"><span class=\\\"mw-reflink-text\\\" data-parsoid=\\\"{}\\\"><span class=\\\"cite-bracket\\\" data-parsoid=\\\"{}\\\">[</span>1<span class=\\\"cite-bracket\\\" data-parsoid=\\\"{}\\\">]</span></span></a></sup>\\n\"}}",
					refGroup: '',
					listGroup: 'mwReference/',
					isResponsive: true,
					templateGenerated: false
				},
				internal: {
					whitespace: [
						'\n',
						undefined,
						undefined,
						'\n'
					]
				}
			},
			{
				type: 'paragraph',
				internal: {
					generated: 'wrapper',
					whitespace: [
						'\n',
						undefined,
						undefined,
						'\n'
					]
				}
			},
			{
				type: 'mwReference',
				attributes: {
					mw: {
						name: 'ref',
						attrs: {
							name: 'a'
						},
						body: {
							id: 'mw-reference-text-cite_note-a-1'
						}
					},
					originalMw: '{"name":"ref","attrs":{"name":"a"},"body":{"id":"mw-reference-text-cite_note-a-1"}}',
					listIndex: 0,
					listGroup: 'mwReference/',
					listKey: 'literal/a',
					refGroup: '',
					contentsUsed: true,
					refListItemId: 'mw-reference-text-cite_note-a-1'
				}
			},
			{
				type: '/mwReference'
			},
			{
				type: '/paragraph'
			},
			{
				type: '/mwReferencesList'
			},
			{
				type: 'mwReferencesList',
				attributes: {
					mw: {
						name: 'references',
						attrs: {
							group: 'g'
						},
						body: {
							html: "\n<sup about=\"#mwt6\" class=\"mw-ref reference\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-parsoid='{\"dsr\":[163,203,14,6]}' data-mw='{\"name\":\"ref\",\"attrs\":{\"name\":\"a\"},\"body\":{\"id\":\"mw-reference-text-cite_note-a-2\"}}'><a href=\"./TestPage#cite_note-a-2\" data-mw-group=\"g\" data-parsoid=\"{}\"><span class=\"mw-reflink-text\" data-parsoid=\"{}\"><span class=\"cite-bracket\" data-parsoid=\"{}\">[</span>g 1<span class=\"cite-bracket\" data-parsoid=\"{}\">]</span></span></a></sup>\n<sup about=\"#mwt7\" class=\"mw-ref reference\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-parsoid='{\"dsr\":[204,250,14,6]}' data-mw='{\"name\":\"ref\",\"attrs\":{\"name\":\"b\"},\"body\":{\"id\":\"mw-reference-text-cite_note-b-3\"}}'><a href=\"./TestPage#cite_note-b-3\" data-mw-group=\"g\" data-parsoid=\"{}\"><span class=\"mw-reflink-text\" data-parsoid=\"{}\"><span class=\"cite-bracket\" data-parsoid=\"{}\">[</span>g 2<span class=\"cite-bracket\" data-parsoid=\"{}\">]</span></span></a></sup>\n"
						}
					},
					originalMw: "{\"name\":\"references\",\"attrs\":{\"group\":\"g\"},\"body\":{\"html\":\"\\n<sup about=\\\"#mwt6\\\" class=\\\"mw-ref reference\\\" rel=\\\"dc:references\\\" typeof=\\\"mw:Extension/ref\\\" data-parsoid='{\\\"dsr\\\":[163,203,14,6]}' data-mw='{\\\"name\\\":\\\"ref\\\",\\\"attrs\\\":{\\\"name\\\":\\\"a\\\"},\\\"body\\\":{\\\"id\\\":\\\"mw-reference-text-cite_note-a-2\\\"}}'><a href=\\\"./TestPage#cite_note-a-2\\\" data-mw-group=\\\"g\\\" data-parsoid=\\\"{}\\\"><span class=\\\"mw-reflink-text\\\" data-parsoid=\\\"{}\\\"><span class=\\\"cite-bracket\\\" data-parsoid=\\\"{}\\\">[</span>g 1<span class=\\\"cite-bracket\\\" data-parsoid=\\\"{}\\\">]</span></span></a></sup>\\n<sup about=\\\"#mwt7\\\" class=\\\"mw-ref reference\\\" rel=\\\"dc:references\\\" typeof=\\\"mw:Extension/ref\\\" data-parsoid='{\\\"dsr\\\":[204,250,14,6]}' data-mw='{\\\"name\\\":\\\"ref\\\",\\\"attrs\\\":{\\\"name\\\":\\\"b\\\"},\\\"body\\\":{\\\"id\\\":\\\"mw-reference-text-cite_note-b-3\\\"}}'><a href=\\\"./TestPage#cite_note-b-3\\\" data-mw-group=\\\"g\\\" data-parsoid=\\\"{}\\\"><span class=\\\"mw-reflink-text\\\" data-parsoid=\\\"{}\\\"><span class=\\\"cite-bracket\\\" data-parsoid=\\\"{}\\\">[</span>g 2<span class=\\\"cite-bracket\\\" data-parsoid=\\\"{}\\\">]</span></span></a></sup>\\n\"}}",
					refGroup: 'g',
					listGroup: 'mwReference/g',
					isResponsive: true,
					templateGenerated: false
				},
				internal: {
					whitespace: [
						'\n'
					]
				}
			},
			{
				type: 'paragraph',
				internal: {
					generated: 'wrapper',
					whitespace: [
						'\n',
						undefined,
						undefined,
						'\n'
					]
				}
			},
			{
				type: 'mwReference',
				attributes: {
					mw: {
						name: 'ref',
						attrs: {
							name: 'a'
						},
						body: {
							id: 'mw-reference-text-cite_note-a-2'
						}
					},
					originalMw: '{"name":"ref","attrs":{"name":"a"},"body":{"id":"mw-reference-text-cite_note-a-2"}}',
					listIndex: 1,
					listGroup: 'mwReference/g',
					listKey: 'literal/a',
					refGroup: 'g',
					contentsUsed: true,
					refListItemId: 'mw-reference-text-cite_note-a-2'
				}
			},
			{
				type: '/mwReference'
			},
			'\n',
			{
				type: 'mwReference',
				attributes: {
					mw: {
						name: 'ref',
						attrs: {
							name: 'b'
						},
						body: {
							id: 'mw-reference-text-cite_note-b-3'
						}
					},
					originalMw: '{"name":"ref","attrs":{"name":"b"},"body":{"id":"mw-reference-text-cite_note-b-3"}}',
					listIndex: 2,
					listGroup: 'mwReference/g',
					listKey: 'literal/b',
					refGroup: 'g',
					contentsUsed: true,
					refListItemId: 'mw-reference-text-cite_note-b-3'
				}
			},
			{
				type: '/mwReference'
			},
			{
				type: '/paragraph'
			},
			{
				type: '/mwReferencesList'
			},
			{
				type: 'internalList'
			},
			{
				type: 'internalItem',
				attributes: {
					originalHtml: 'Default group content'
				}
			},
			{
				type: 'paragraph',
				internal: {
					generated: 'wrapper'
				}
			},
			'D',
			'e',
			'f',
			'a',
			'u',
			'l',
			't',
			' ',
			'g',
			'r',
			'o',
			'u',
			'p',
			' ',
			'c',
			'o',
			'n',
			't',
			'e',
			'n',
			't',
			{
				type: '/paragraph'
			},
			{
				type: '/internalItem'
			},
			{
				type: 'internalItem',
				attributes: {
					originalHtml: 'Custom group content'
				}
			},
			{
				type: 'paragraph',
				internal: {
					generated: 'wrapper'
				}
			},
			'C',
			'u',
			's',
			't',
			'o',
			'm',
			' ',
			'g',
			'r',
			'o',
			'u',
			'p',
			' ',
			'c',
			'o',
			'n',
			't',
			'e',
			'n',
			't',
			{
				type: '/paragraph'
			},
			{
				type: '/internalItem'
			},
			{
				type: 'internalItem',
				attributes: {
					originalHtml: 'Custom group content other'
				}
			},
			{
				type: 'paragraph',
				internal: {
					generated: 'wrapper'
				}
			},
			'C',
			'u',
			's',
			't',
			'o',
			'm',
			' ',
			'g',
			'r',
			'o',
			'u',
			'p',
			' ',
			'c',
			'o',
			'n',
			't',
			'e',
			'n',
			't',
			' ',
			'o',
			't',
			'h',
			'e',
			'r',
			{
				type: '/paragraph'
			},
			{
				type: '/internalItem'
			},
			{
				type: '/internalList'
			}
		],
	body:
		"<p id=\"mwAg\"><sup about=\"#mwt1\" class=\"mw-ref reference\" id=\"cite_ref-a_1-0\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-mw=\"{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;}}\"><a href=\"./TestPage#cite_note-a-1\" id=\"mwAw\"><span class=\"mw-reflink-text\" id=\"mwBA\"><span class=\"cite-bracket\" id=\"mwBQ\">[</span>1<span class=\"cite-bracket\" id=\"mwBg\">]</span></span></a></sup> <sup about=\"#mwt2\" class=\"mw-ref reference\" id=\"cite_ref-a_2-0\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-mw=\"{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;,&quot;group&quot;:&quot;g&quot;}}\"><a href=\"./TestPage#cite_note-a-2\" data-mw-group=\"g\" id=\"mwBw\"><span class=\"mw-reflink-text\" id=\"mwCA\"><span class=\"cite-bracket\" id=\"mwCQ\">[</span>g 1<span class=\"cite-bracket\" id=\"mwCg\">]</span></span></a></sup> <sup about=\"#mwt3\" class=\"mw-ref reference\" id=\"cite_ref-b_3-0\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-mw=\"{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;b&quot;,&quot;group&quot;:&quot;g&quot;}}\"><a href=\"./TestPage#cite_note-b-3\" data-mw-group=\"g\" id=\"mwCw\"><span class=\"mw-reflink-text\" id=\"mwDA\"><span class=\"cite-bracket\" id=\"mwDQ\">[</span>g 2<span class=\"cite-bracket\" id=\"mwDg\">]</span></span></a></sup></p>\n<div class=\"mw-references-wrap\" typeof=\"mw:Extension/references\" about=\"#mwt5\" data-mw=\"{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup about=\\&quot;#mwt4\\&quot; class=\\&quot;mw-ref reference\\&quot; rel=\\&quot;dc:references\\&quot; typeof=\\&quot;mw:Extension/ref\\&quot; data-parsoid='{\\&quot;dsr\\&quot;:[84,125,14,6]}' data-mw='{\\&quot;name\\&quot;:\\&quot;ref\\&quot;,\\&quot;attrs\\&quot;:{\\&quot;name\\&quot;:\\&quot;a\\&quot;},\\&quot;body\\&quot;:{\\&quot;id\\&quot;:\\&quot;mw-reference-text-cite_note-a-1\\&quot;}}'&gt;&lt;a href=\\&quot;./TestPage#cite_note-a-1\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;[&lt;/span&gt;1&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}\" id=\"mwDw\"><ol class=\"mw-references references\" id=\"mwEA\"><li about=\"#cite_note-a-1\" id=\"cite_note-a-1\"><span class=\"mw-cite-backlink\" id=\"mwEQ\"><a href=\"./TestPage#cite_ref-a_1-0\" rel=\"mw:referencedBy\" id=\"mwEg\"><span class=\"mw-linkback-text\" id=\"mwEw\">↑ </span></a></span> <span id=\"mw-reference-text-cite_note-a-1\" class=\"mw-reference-text reference-text\">Default group content</span></li>\n</ol></div>\n<div class=\"mw-references-wrap\" typeof=\"mw:Extension/references\" about=\"#mwt8\" data-mw=\"{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g&quot;},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup about=\\&quot;#mwt6\\&quot; class=\\&quot;mw-ref reference\\&quot; rel=\\&quot;dc:references\\&quot; typeof=\\&quot;mw:Extension/ref\\&quot; data-parsoid='{\\&quot;dsr\\&quot;:[163,203,14,6]}' data-mw='{\\&quot;name\\&quot;:\\&quot;ref\\&quot;,\\&quot;attrs\\&quot;:{\\&quot;name\\&quot;:\\&quot;a\\&quot;},\\&quot;body\\&quot;:{\\&quot;id\\&quot;:\\&quot;mw-reference-text-cite_note-a-2\\&quot;}}'&gt;&lt;a href=\\&quot;./TestPage#cite_note-a-2\\&quot; data-mw-group=\\&quot;g\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;[&lt;/span&gt;g 1&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&lt;sup about=\\&quot;#mwt7\\&quot; class=\\&quot;mw-ref reference\\&quot; rel=\\&quot;dc:references\\&quot; typeof=\\&quot;mw:Extension/ref\\&quot; data-parsoid='{\\&quot;dsr\\&quot;:[204,250,14,6]}' data-mw='{\\&quot;name\\&quot;:\\&quot;ref\\&quot;,\\&quot;attrs\\&quot;:{\\&quot;name\\&quot;:\\&quot;b\\&quot;},\\&quot;body\\&quot;:{\\&quot;id\\&quot;:\\&quot;mw-reference-text-cite_note-b-3\\&quot;}}'&gt;&lt;a href=\\&quot;./TestPage#cite_note-b-3\\&quot; data-mw-group=\\&quot;g\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;[&lt;/span&gt;g 2&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}\" id=\"mwFA\"><ol class=\"mw-references references\" data-mw-group=\"g\" id=\"mwFQ\"><li about=\"#cite_note-a-2\" id=\"cite_note-a-2\"><span class=\"mw-cite-backlink\" id=\"mwFg\"><a href=\"./TestPage#cite_ref-a_2-0\" data-mw-group=\"g\" rel=\"mw:referencedBy\" id=\"mwFw\"><span class=\"mw-linkback-text\" id=\"mwGA\">↑ </span></a></span> <span id=\"mw-reference-text-cite_note-a-2\" class=\"mw-reference-text reference-text\" data-mw-group=\"g\">Custom group content</span></li>\n<li about=\"#cite_note-b-3\" id=\"cite_note-b-3\"><span class=\"mw-cite-backlink\" id=\"mwGQ\"><a href=\"./TestPage#cite_ref-b_3-0\" data-mw-group=\"g\" rel=\"mw:referencedBy\" id=\"mwGg\"><span class=\"mw-linkback-text\" id=\"mwGw\">↑ </span></a></span> <span id=\"mw-reference-text-cite_note-b-3\" class=\"mw-reference-text reference-text\" data-mw-group=\"g\">Custom group content other</span></li>\n</ol></div>"
};

// Expect no Wikitext change
ve.dm.ConverterIntegrationTestCases.cases[ 'List-defined references with default and custom group' ] = {
	data: ve.dm.ConverterIntegrationTestCases.listDefinedRefsWithGroups.data,
	body: ve.dm.ConverterIntegrationTestCases.listDefinedRefsWithGroups.body,
	fromDataBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;}}"></sup> <sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;,&quot;group&quot;:&quot;g&quot;}}"></sup> <sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;b&quot;,&quot;group&quot;:&quot;g&quot;}}"></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;a&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-a-1&amp;quot;}}\\&quot;&gt;&lt;/sup&gt;\\n&quot;}}"><ol><li><span typeof="mw:Extension/ref" id="mw-reference-text-cite_note-a-1">Default group content</span></li></ol></div>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g&quot;},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;a&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-a-2&amp;quot;}}\\&quot;&gt;&lt;/sup&gt;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;b&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-b-3&amp;quot;}}\\&quot;&gt;&lt;/sup&gt;\\n&quot;}}"><ol><li><span typeof="mw:Extension/ref" id="mw-reference-text-cite_note-a-2">Custom group content</span></li><li><span typeof="mw:Extension/ref" id="mw-reference-text-cite_note-b-3">Custom group content other</span></li></ol></div>',
	normalizedBody:
		"<p id=\"mwAg\"><sup about=\"#mwt1\" class=\"mw-ref reference\" id=\"cite_ref-a_1-0\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-mw=\"{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;}}\"><a href=\"./TestPage#cite_note-a-1\" id=\"mwAw\"><span class=\"mw-reflink-text\" id=\"mwBA\"><span class=\"cite-bracket\" id=\"mwBQ\">[</span>1<span class=\"cite-bracket\" id=\"mwBg\">]</span></span></a></sup> <sup about=\"#mwt2\" class=\"mw-ref reference\" id=\"cite_ref-a_2-0\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-mw=\"{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;,&quot;group&quot;:&quot;g&quot;}}\"><a href=\"./TestPage#cite_note-a-2\" data-mw-group=\"g\" id=\"mwBw\"><span class=\"mw-reflink-text\" id=\"mwCA\"><span class=\"cite-bracket\" id=\"mwCQ\">[</span>g 1<span class=\"cite-bracket\" id=\"mwCg\">]</span></span></a></sup> <sup about=\"#mwt3\" class=\"mw-ref reference\" id=\"cite_ref-b_3-0\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-mw=\"{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;b&quot;,&quot;group&quot;:&quot;g&quot;}}\"><a href=\"./TestPage#cite_note-b-3\" data-mw-group=\"g\" id=\"mwCw\"><span class=\"mw-reflink-text\" id=\"mwDA\"><span class=\"cite-bracket\" id=\"mwDQ\">[</span>g 2<span class=\"cite-bracket\" id=\"mwDg\">]</span></span></a></sup></p>\n<div class=\"mw-references-wrap\" typeof=\"mw:Extension/references\" about=\"#mwt5\" data-mw=\"{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup about=\\&quot;#mwt4\\&quot; class=\\&quot;mw-ref reference\\&quot; rel=\\&quot;dc:references\\&quot; typeof=\\&quot;mw:Extension/ref\\&quot; data-parsoid='{\\&quot;dsr\\&quot;:[84,125,14,6]}' data-mw='{\\&quot;name\\&quot;:\\&quot;ref\\&quot;,\\&quot;attrs\\&quot;:{\\&quot;name\\&quot;:\\&quot;a\\&quot;},\\&quot;body\\&quot;:{\\&quot;id\\&quot;:\\&quot;mw-reference-text-cite_note-a-1\\&quot;}}'&gt;&lt;a href=\\&quot;./TestPage#cite_note-a-1\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;[&lt;/span&gt;1&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}\" id=\"mwDw\"><ol class=\"mw-references references\" id=\"mwEA\"><li about=\"#cite_note-a-1\" id=\"cite_note-a-1\"><span class=\"mw-cite-backlink\" id=\"mwEQ\"><a href=\"./TestPage#cite_ref-a_1-0\" rel=\"mw:referencedBy\" id=\"mwEg\"><span class=\"mw-linkback-text\" id=\"mwEw\">↑ </span></a></span> <span id=\"mw-reference-text-cite_note-a-1\" class=\"mw-reference-text reference-text\">Default group content</span></li>\n</ol></div>\n<div class=\"mw-references-wrap\" typeof=\"mw:Extension/references\" about=\"#mwt8\" data-mw=\"{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g&quot;},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup about=\\&quot;#mwt6\\&quot; class=\\&quot;mw-ref reference\\&quot; rel=\\&quot;dc:references\\&quot; typeof=\\&quot;mw:Extension/ref\\&quot; data-parsoid='{\\&quot;dsr\\&quot;:[163,203,14,6]}' data-mw='{\\&quot;name\\&quot;:\\&quot;ref\\&quot;,\\&quot;attrs\\&quot;:{\\&quot;name\\&quot;:\\&quot;a\\&quot;},\\&quot;body\\&quot;:{\\&quot;id\\&quot;:\\&quot;mw-reference-text-cite_note-a-2\\&quot;}}'&gt;&lt;a href=\\&quot;./TestPage#cite_note-a-2\\&quot; data-mw-group=\\&quot;g\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;[&lt;/span&gt;g 1&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&lt;sup about=\\&quot;#mwt7\\&quot; class=\\&quot;mw-ref reference\\&quot; rel=\\&quot;dc:references\\&quot; typeof=\\&quot;mw:Extension/ref\\&quot; data-parsoid='{\\&quot;dsr\\&quot;:[204,250,14,6]}' data-mw='{\\&quot;name\\&quot;:\\&quot;ref\\&quot;,\\&quot;attrs\\&quot;:{\\&quot;name\\&quot;:\\&quot;b\\&quot;},\\&quot;body\\&quot;:{\\&quot;id\\&quot;:\\&quot;mw-reference-text-cite_note-b-3\\&quot;}}'&gt;&lt;a href=\\&quot;./TestPage#cite_note-b-3\\&quot; data-mw-group=\\&quot;g\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;[&lt;/span&gt;g 2&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}\" id=\"mwFA\"><ol class=\"mw-references references\" data-mw-group=\"g\" id=\"mwFQ\"><li about=\"#cite_note-a-2\" id=\"cite_note-a-2\"><span class=\"mw-cite-backlink\" id=\"mwFg\"><a href=\"./TestPage#cite_ref-a_2-0\" data-mw-group=\"g\" rel=\"mw:referencedBy\" id=\"mwFw\"><span class=\"mw-linkback-text\" id=\"mwGA\">↑ </span></a></span> <span id=\"mw-reference-text-cite_note-a-2\" class=\"mw-reference-text reference-text\" data-mw-group=\"g\">Custom group content</span></li>\n<li about=\"#cite_note-b-3\" id=\"cite_note-b-3\"><span class=\"mw-cite-backlink\" id=\"mwGQ\"><a href=\"./TestPage#cite_ref-b_3-0\" data-mw-group=\"g\" rel=\"mw:referencedBy\" id=\"mwGg\"><span class=\"mw-linkback-text\" id=\"mwGw\">↑ </span></a></span> <span id=\"mw-reference-text-cite_note-b-3\" class=\"mw-reference-text reference-text\" data-mw-group=\"g\">Custom group content other</span></li>\n</ol></div>",
	clipboardBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup> <sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;,&quot;group&quot;:&quot;g&quot;}}" class="mw-ref reference"><a data-mw-group="g"><span class="mw-reflink-text"><span class="cite-bracket">[</span>g 1<span class="cite-bracket">]</span></span></a></sup> <sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;b&quot;,&quot;group&quot;:&quot;g&quot;}}" class="mw-ref reference"><a data-mw-group="g"><span class="mw-reflink-text"><span class="cite-bracket">[</span>g 2<span class="cite-bracket">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;a&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-a-1&amp;quot;,&amp;quot;html&amp;quot;:&amp;quot;Default group content&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;1&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol class="mw-references references"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Default group content</p></span></div></span></li></ol></div>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g&quot;},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;a&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-a-2&amp;quot;,&amp;quot;html&amp;quot;:&amp;quot;Custom group content&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a data-mw-group=\\&quot;g\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;g 1&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;b&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-b-3&amp;quot;,&amp;quot;html&amp;quot;:&amp;quot;Custom group content other&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a data-mw-group=\\&quot;g\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;g 2&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol class="mw-references references" data-mw-group="g"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy" data-mw-group="g"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Custom group content</p></span></div></span></li><li style="--footnote-number: &quot;2.&quot;;"><a rel="mw:referencedBy" data-mw-group="g"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Custom group content other</p></span></div></span></li></ol></div>',
	previewBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup> <sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;,&quot;group&quot;:&quot;g&quot;}}" class="mw-ref reference"><a data-mw-group="g"><span class="mw-reflink-text"><span class="cite-bracket">[</span>g 1<span class="cite-bracket">]</span></span></a></sup> <sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;b&quot;,&quot;group&quot;:&quot;g&quot;}}" class="mw-ref reference"><a data-mw-group="g"><span class="mw-reflink-text"><span class="cite-bracket">[</span>g 2<span class="cite-bracket">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;a&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-a-1&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;1&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol class="mw-references references"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Default group content</p></span></div></span></li></ol></div>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g&quot;},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;a&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-a-2&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a data-mw-group=\\&quot;g\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;g 1&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;↵&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;b&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-b-3&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a data-mw-group=\\&quot;g\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;g 2&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol class="mw-references references" data-mw-group="g"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy" data-mw-group="g"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Custom group content</p></span></div></span></li><li style="--footnote-number: &quot;2.&quot;;"><a rel="mw:referencedBy" data-mw-group="g"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Custom group content other</p></span></div></span></li></ol></div>',
	innerWhitespace:
		[
			undefined,
			undefined
		],
	preserveAnnotationDomElements:
		true
};

// Expected Wikitext after change:
// <ref name="a" /> <ref name="a" group="g" /> <ref name="b" group="g" />
// <references>
// <ref name="a">Default group content</ref>
// </references>
// <references group="g">
// <ref name="a">Custom group content NEW</ref>
// <ref name="b">Custom group content other</ref>
// </references>
ve.dm.ConverterIntegrationTestCases.cases[ 'List-defined references with default and custom group ( edit custom ldr )' ] = {
	data: ve.dm.ConverterIntegrationTestCases.listDefinedRefsWithGroups.data,
	body: ve.dm.ConverterIntegrationTestCases.listDefinedRefsWithGroups.body,
	modify:
		( model ) => {
			model.commit( ve.dm.Transaction.static.deserialize( [ 52, [ [ { type: 'paragraph', internal: { generated: 'wrapper', metaItems: [] } }, 'C', 'u', 's', 't', 'o', 'm', ' ', 'g', 'r', 'o', 'u', 'p', ' ', 'c', 'o', 'n', 't', 'e', 'n', 't', { type: '/paragraph' } ], '' ], 32 ] ) );
			model.commit( ve.dm.Transaction.static.deserialize( [ 26, [ [ { type: 'internalItem', attributes: { originalHtml: 'Default group content' } }, { type: 'paragraph', internal: { generated: 'wrapper', metaItems: [] } }, 'D', 'e', 'f', 'a', 'u', 'l', 't', ' ', 'g', 'r', 'o', 'u', 'p', ' ', 'c', 'o', 'n', 't', 'e', 'n', 't', { type: '/paragraph' }, { type: '/internalItem' }, { type: 'internalItem', attributes: { originalHtml: 'Custom group content' } }, { type: '/internalItem' }, { type: 'internalItem', attributes: { originalHtml: 'Custom group content other' } }, { type: 'paragraph', internal: { generated: 'wrapper', metaItems: [] } }, 'C', 'u', 's', 't', 'o', 'm', ' ', 'g', 'r', 'o', 'u', 'p', ' ', 'c', 'o', 'n', 't', 'e', 'n', 't', ' ', 'o', 't', 'h', 'e', 'r', { type: '/paragraph' }, { type: '/internalItem' } ], [ { type: 'internalItem', attributes: { originalHtml: 'Default group content' } }, { type: 'paragraph', internal: { generated: 'wrapper' } }, 'D', 'e', 'f', 'a', 'u', 'l', 't', ' ', 'g', 'r', 'o', 'u', 'p', ' ', 'c', 'o', 'n', 't', 'e', 'n', 't', { type: '/paragraph' }, { type: '/internalItem' }, { type: 'internalItem', attributes: { originalHtml: 'Custom group content' } }, { type: 'paragraph', internal: { generated: 'wrapper' } }, 'C', 'u', 's', 't', 'o', 'm', ' ', 'g', 'r', 'o', 'u', 'p', ' ', 'c', 'o', 'n', 't', 'e', 'n', 't', ' ', 'N', 'E', 'W', { type: '/paragraph' }, { type: '/internalItem' }, { type: 'internalItem', attributes: { originalHtml: 'Custom group content other' } }, { type: 'paragraph', internal: { generated: 'wrapper' } }, 'C', 'u', 's', 't', 'o', 'm', ' ', 'g', 'r', 'o', 'u', 'p', ' ', 'c', 'o', 'n', 't', 'e', 'n', 't', ' ', 'o', 't', 'h', 'e', 'r', { type: '/paragraph' }, { type: '/internalItem' } ] ], 1 ] ) );
		},
	fromDataBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;}}"></sup> <sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;,&quot;group&quot;:&quot;g&quot;}}"></sup> <sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;b&quot;,&quot;group&quot;:&quot;g&quot;}}"></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;a&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-a-1&amp;quot;}}\\&quot;&gt;&lt;/sup&gt;\\n&quot;}}"><ol><li><span typeof="mw:Extension/ref" id="mw-reference-text-cite_note-a-1">Default group content</span></li></ol></div>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g&quot;},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;a&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-a-2&amp;quot;,&amp;quot;html&amp;quot;:&amp;quot;Custom group content NEW&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a data-mw-group=\\&quot;g\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;g 1&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;b&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-b-3&amp;quot;}}\\&quot;&gt;&lt;/sup&gt;\\n&quot;}}"><ol><li><span typeof="mw:Extension/ref" id="mw-reference-text-cite_note-a-2">Custom group content NEW</span></li><li><span typeof="mw:Extension/ref" id="mw-reference-text-cite_note-b-3">Custom group content other</span></li></ol></div>',
	normalizedBody:
		"<p id=\"mwAg\"><sup about=\"#mwt1\" class=\"mw-ref reference\" id=\"cite_ref-a_1-0\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-mw=\"{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;}}\"><a href=\"./TestPage#cite_note-a-1\" id=\"mwAw\"><span class=\"mw-reflink-text\" id=\"mwBA\"><span class=\"cite-bracket\" id=\"mwBQ\">[</span>1<span class=\"cite-bracket\" id=\"mwBg\">]</span></span></a></sup> <sup about=\"#mwt2\" class=\"mw-ref reference\" id=\"cite_ref-a_2-0\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-mw=\"{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;,&quot;group&quot;:&quot;g&quot;}}\"><a href=\"./TestPage#cite_note-a-2\" data-mw-group=\"g\" id=\"mwBw\"><span class=\"mw-reflink-text\" id=\"mwCA\"><span class=\"cite-bracket\" id=\"mwCQ\">[</span>g 1<span class=\"cite-bracket\" id=\"mwCg\">]</span></span></a></sup> <sup about=\"#mwt3\" class=\"mw-ref reference\" id=\"cite_ref-b_3-0\" rel=\"dc:references\" typeof=\"mw:Extension/ref\" data-mw=\"{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;b&quot;,&quot;group&quot;:&quot;g&quot;}}\"><a href=\"./TestPage#cite_note-b-3\" data-mw-group=\"g\" id=\"mwCw\"><span class=\"mw-reflink-text\" id=\"mwDA\"><span class=\"cite-bracket\" id=\"mwDQ\">[</span>g 2<span class=\"cite-bracket\" id=\"mwDg\">]</span></span></a></sup></p>\n<div class=\"mw-references-wrap\" typeof=\"mw:Extension/references\" about=\"#mwt5\" data-mw=\"{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup about=\\&quot;#mwt4\\&quot; class=\\&quot;mw-ref reference\\&quot; rel=\\&quot;dc:references\\&quot; typeof=\\&quot;mw:Extension/ref\\&quot; data-parsoid='{\\&quot;dsr\\&quot;:[84,125,14,6]}' data-mw='{\\&quot;name\\&quot;:\\&quot;ref\\&quot;,\\&quot;attrs\\&quot;:{\\&quot;name\\&quot;:\\&quot;a\\&quot;},\\&quot;body\\&quot;:{\\&quot;id\\&quot;:\\&quot;mw-reference-text-cite_note-a-1\\&quot;}}'&gt;&lt;a href=\\&quot;./TestPage#cite_note-a-1\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;[&lt;/span&gt;1&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}\" id=\"mwDw\"><ol class=\"mw-references references\" id=\"mwEA\"><li about=\"#cite_note-a-1\" id=\"cite_note-a-1\"><span class=\"mw-cite-backlink\" id=\"mwEQ\"><a href=\"./TestPage#cite_ref-a_1-0\" rel=\"mw:referencedBy\" id=\"mwEg\"><span class=\"mw-linkback-text\" id=\"mwEw\">↑ </span></a></span> <span id=\"mw-reference-text-cite_note-a-1\" class=\"mw-reference-text reference-text\">Default group content</span></li>\n</ol></div>\n<div typeof=\"mw:Extension/references\" data-mw=\"{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g&quot;},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;a&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-a-2&amp;quot;,&amp;quot;html&amp;quot;:&amp;quot;Custom group content NEW&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot; about=\\&quot;#mwt6\\&quot; rel=\\&quot;dc:references\\&quot; data-parsoid=\\&quot;{&amp;quot;dsr&amp;quot;:[163,203,14,6]}\\&quot;&gt;&lt;a data-mw-group=\\&quot;g\\&quot; href=\\&quot;./TestPage#cite_note-a-2\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;[&lt;/span&gt;g 1&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&lt;sup about=\\&quot;#mwt7\\&quot; class=\\&quot;mw-ref reference\\&quot; rel=\\&quot;dc:references\\&quot; typeof=\\&quot;mw:Extension/ref\\&quot; data-parsoid=\\&quot;{&amp;quot;dsr&amp;quot;:[204,250,14,6]}\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;b&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-b-3&amp;quot;}}\\&quot;&gt;&lt;a href=\\&quot;./TestPage#cite_note-b-3\\&quot; data-mw-group=\\&quot;g\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;[&lt;/span&gt;g 2&lt;span class=\\&quot;cite-bracket\\&quot; data-parsoid=\\&quot;{}\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}\"><ol><li><span typeof=\"mw:Extension/ref\" id=\"mw-reference-text-cite_note-a-2\">Custom group content NEW</span></li><li><span typeof=\"mw:Extension/ref\" id=\"mw-reference-text-cite_note-b-3\">Custom group content other</span></li></ol></div>",
	clipboardBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup> <sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;,&quot;group&quot;:&quot;g&quot;}}" class="mw-ref reference"><a data-mw-group="g"><span class="mw-reflink-text"><span class="cite-bracket">[</span>g 1<span class="cite-bracket">]</span></span></a></sup> <sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;b&quot;,&quot;group&quot;:&quot;g&quot;}}" class="mw-ref reference"><a data-mw-group="g"><span class="mw-reflink-text"><span class="cite-bracket">[</span>g 2<span class="cite-bracket">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;a&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-a-1&amp;quot;,&amp;quot;html&amp;quot;:&amp;quot;Default group content&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;1&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol class="mw-references references"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Default group content</p></span></div></span></li></ol></div>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g&quot;},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;a&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-a-2&amp;quot;,&amp;quot;html&amp;quot;:&amp;quot;Custom group content NEW&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a data-mw-group=\\&quot;g\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;g 1&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;b&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-b-3&amp;quot;,&amp;quot;html&amp;quot;:&amp;quot;Custom group content other&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a data-mw-group=\\&quot;g\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;g 2&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol class="mw-references references" data-mw-group="g"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy" data-mw-group="g"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Custom group content NEW</p></span></div></span></li><li style="--footnote-number: &quot;2.&quot;;"><a rel="mw:referencedBy" data-mw-group="g"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Custom group content other</p></span></div></span></li></ol></div>',
	previewBody:
		'<p><sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;}}" class="mw-ref reference"><a><span class="mw-reflink-text"><span class="cite-bracket">[</span>1<span class="cite-bracket">]</span></span></a></sup> <sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;a&quot;,&quot;group&quot;:&quot;g&quot;}}" class="mw-ref reference"><a data-mw-group="g"><span class="mw-reflink-text"><span class="cite-bracket">[</span>g 1<span class="cite-bracket">]</span></span></a></sup> <sup typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;b&quot;,&quot;group&quot;:&quot;g&quot;}}" class="mw-ref reference"><a data-mw-group="g"><span class="mw-reflink-text"><span class="cite-bracket">[</span>g 2<span class="cite-bracket">]</span></span></a></sup></p>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;a&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-a-1&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;1&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol class="mw-references references"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Default group content</p></span></div></span></li></ol></div>\n<div typeof="mw:Extension/references" data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g&quot;},&quot;body&quot;:{&quot;html&quot;:&quot;\\n&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;a&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-a-2&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a data-mw-group=\\&quot;g\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;g 1&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;↵&lt;sup typeof=\\&quot;mw:Extension/ref\\&quot; data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;name&amp;quot;:&amp;quot;b&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;id&amp;quot;:&amp;quot;mw-reference-text-cite_note-b-3&amp;quot;}}\\&quot; class=\\&quot;mw-ref reference\\&quot;&gt;&lt;a data-mw-group=\\&quot;g\\&quot;&gt;&lt;span class=\\&quot;mw-reflink-text\\&quot;&gt;&lt;span class=\\&quot;cite-bracket\\&quot;&gt;[&lt;/span&gt;g 2&lt;span class=\\&quot;cite-bracket\\&quot;&gt;]&lt;/span&gt;&lt;/span&gt;&lt;/a&gt;&lt;/sup&gt;\\n&quot;}}"><ol class="mw-references references" data-mw-group="g"><li style="--footnote-number: &quot;1.&quot;;"><a rel="mw:referencedBy" data-mw-group="g"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Custom group content NEW</p></span></div></span></li><li style="--footnote-number: &quot;2.&quot;;"><a rel="mw:referencedBy" data-mw-group="g"><span class="mw-linkback-text">↑ </span></a> <span class="reference-text"><div class="mw-content-ltr ve-ui-previewElement ve-ui-mwPreviewElement mw-body-content mw-parser-output"><span class="ve-ce-branchNode ve-ce-internalItemNode"><p class="ve-ce-branchNode ve-ce-contentBranchNode ve-ce-paragraphNode ve-ce-generated-wrapper">Custom group content other</p></span></div></span></li></ol></div>',
	innerWhitespace:
		[
			undefined,
			undefined
		],
	preserveAnnotationDomElements:
		true
};
