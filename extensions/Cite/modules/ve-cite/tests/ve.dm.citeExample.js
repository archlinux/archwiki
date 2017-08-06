/*!
 * VisualEditor DataModel Cite-specific example data sets.
 *
 * @copyright 2011-2017 Cite VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

ve.dm.citeExample = {};

ve.dm.citeExample.createExampleDocument = function ( name, store ) {
	return ve.dm.example.createExampleDocumentFromObject( name, store, ve.dm.citeExample );
};

ve.dm.citeExample.domToDataCases = {
	'mw:Reference': {
		// Wikitext:
		// Foo<ref name="bar" /> Baz<ref group="g1" name=":0">Quux</ref> Whee<ref name="bar">[[Bar]]</ref> Yay<ref group="g1">No name</ref> Quux<ref name="bar">Different content</ref> Foo<ref group="g1" name="foo" />
		// <references group="g1"><ref group="g1" name="foo">Ref in refs</ref></references>
		body:
			'<p>Foo' +
				'<span about="#mwt1" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" id="cite_ref-bar-1-0" rel="dc:references" typeof="mw:Extension/ref" data-parsoid="{}">' +
					'<a href="#cite_note-bar-1">[1]</a>' +
				'</span>' +
				' Baz' +
				'<span about="#mwt2" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Quux&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;:0&quot;}}" id="cite_ref-quux-2-0" rel="dc:references" typeof="mw:Extension/ref" data-parsoid="{}">' +
					'<a href="#cite_note-.3A0-2">[g1 1]</a>' +
				'</span>' +
				' Whee' +
				'<span about="#mwt3" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;' +
				'<a rel=\\&quot;mw:WikiLink\\&quot; href=\\&quot;./Bar\\&quot;>Bar' +
				'</a>&quot;},&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" id="cite_ref-bar-1-1" rel="dc:references" typeof="mw:Extension/ref" data-parsoid="{}">' +
					'<a href="#cite_note-bar-1">[1]</a>' +
				'</span>' +
				' Yay' +
				// This reference has .body.id instead of .body.html
				'<span about="#mwt4" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;id&quot;:&quot;mw-cite-3&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;}}" id="cite_ref-1-0" rel="dc:references" typeof="mw:Extension/ref" data-parsoid="{}">' +
					'<a href="#cite_note-3">[g1 2]</a>' +
				'</span>' +
				' Quux' +
				'<span about="#mwt5" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Different content&quot;},&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" id="cite_ref-bar-1-2" rel="dc:references" typeof="mw:Extension/ref" data-parsoid="{}">' +
					'<a href="#cite_note-bar-1">[1]</a>' +
				'</span>' +
				' Foo' +
				'<span about="#mwt6" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;foo&quot;}}" ' +
					'id="cite_ref-foo-4" rel="dc:references" typeof="mw:Extension/ref" data-parsoid="{}">' +
					'<a href="#cite_ref-foo-4">[g1 3]</a>' +
				'</span>' +
			'</p>' +
			// The HTML below is enriched to wrap reference contents in <span id="mw-cite-[...]">
			// which Parsoid doesn't do yet, but T88290 asks for
			'<ol class="references" typeof="mw:Extension/references" about="#mwt7" data-parsoid="{}"' +
				'data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;body&quot;:{' +
				'&quot;html&quot;:&quot;<span about=\\&quot;#mwt8\\&quot; class=\\&quot;reference\\&quot; ' +
				'data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;body&amp;quot;:{&amp;quot;html&amp;quot;:&amp;quot;Ref in refs&amp;quot;},' +
				'&amp;quot;attrs&amp;quot;:{&amp;quot;group&amp;quot;:&amp;quot;g1&amp;quot;,&amp;quot;name&amp;quot;:&amp;quot;foo&amp;quot;}}\\&quot; ' +
				'rel=\\&quot;dc:references\\&quot; typeof=\\&quot;mw:Extension/ref\\&quot;>' +
				'<a href=\\&quot;#cite_note-foo-3\\&quot;>[3]</a></span>&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;}}">' +
				'<li about="#cite_note-.3A0-2" id="cite_note-.3A0-2"><span rel="mw:referencedBy"><a href="#cite_ref-.3A0_2-0">↑</a></span> <span id="mw-cite-:0">Quux</span></li>' +
				'<li about="#cite_note-3" id="cite_note-3"><span rel="mw:referencedBy"><a href="#cite_ref-3">↑</a></span> <span id="mw-cite-3">No name</span></li>' +
				'<li about="#cite_note-foo-4" id="cite_note-foo-4"><span rel="mw:referencedBy"><a href="#cite_ref-foo_4-0">↑</a></span> <span id="mw-cite-foo">Ref in refs</span></li>' +
			'</ol>',
		fromDataBody:
			'<p>Foo' +
				'<span data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" typeof="mw:Extension/ref">' +
				'</span>' +
				' Baz' +
				'<span data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Quux&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;:0&quot;}}" typeof="mw:Extension/ref">' +
				'</span>' +
				' Whee' +
				'<span data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;' +
				'<a rel=\\&quot;mw:WikiLink\\&quot; href=\\&quot;./Bar\\&quot;>Bar' +
				'</a>&quot;},&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" typeof="mw:Extension/ref">' +
				'</span>' +
				' Yay' +
				'<span data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;id&quot;:&quot;mw-cite-3&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;}}" typeof="mw:Extension/ref">' +
				'</span>' +
				' Quux' +
				'<span data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Different content&quot;},&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}" typeof="mw:Extension/ref">' +
				'</span>' +
				' Foo' +
				'<span data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;foo&quot;}}" ' +
					'typeof="mw:Extension/ref">' +
				'</span>' +
			'</p>' +
			'<div typeof="mw:Extension/references" ' +
				'data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;},&quot;body&quot;:{' +
				'&quot;html&quot;:&quot;<span typeof=\\&quot;mw:Extension/ref\\&quot; ' +
				'data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;body&amp;quot;:{&amp;quot;html&amp;quot;:&amp;quot;Ref in refs&amp;quot;},' +
				'&amp;quot;attrs&amp;quot;:{&amp;quot;group&amp;quot;:&amp;quot;g1&amp;quot;,&amp;quot;name&amp;quot;:&amp;quot;foo&amp;quot;}}\\&quot;>' +
				'</span>&quot;}}">' +
			'</div>',
		clipboardBody:
			'<p>Foo' +
				'<span typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}">' +
					'<a><span class="mw-reflink-text">[1]</span></a>' +
				'</span>' +
				' Baz' +
				'<span typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Quux&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;:0&quot;}}">' +
					'<a><span class="mw-reflink-text">[g1 1]</span></a>' +
				'</span>' +
				' Whee' +
				'<span typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;' +
				'<a href=\\&quot;./Bar\\&quot; rel=\\&quot;mw:WikiLink\\&quot;>Bar' +
				'</a>&quot;},&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}">' +
					'<a><span class="mw-reflink-text">[1]</span></a>' +
				'</span>' +
				' Yay' +
				// This reference has .body.id instead of .body.html
				'<span typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;id&quot;:&quot;mw-cite-3&quot;,&quot;html&quot;:&quot;No name&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;}}">' +
					'<a><span class="mw-reflink-text">[g1 2]</span></a>' +
				'</span>' +
				' Quux' +
				'<span typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Different content&quot;},&quot;attrs&quot;:{&quot;name&quot;:&quot;bar&quot;}}">' +
					'<a><span class="mw-reflink-text">[1]</span></a>' +
				'</span>' +
				' Foo' +
				'<span typeof="mw:Extension/ref" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;foo&quot;}}">' +
					'<a><span class="mw-reflink-text">[g1 3]</span></a>' +
				'</span>' +
			'</p>' +
			// The HTML below is enriched to wrap reference contents in <span id="mw-cite-[...]">
			// which Parsoid doesn't do yet, but T88290 asks for
			'<div typeof="mw:Extension/references"' +
				'data-mw="{&quot;name&quot;:&quot;references&quot;,&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;},&quot;body&quot;:{' +
				'&quot;html&quot;:&quot;<span typeof=\\&quot;mw:Extension/ref\\&quot; ' +
				'data-mw=\\&quot;{&amp;quot;name&amp;quot;:&amp;quot;ref&amp;quot;,&amp;quot;attrs&amp;quot;:{&amp;quot;group&amp;quot;:&amp;quot;g1&amp;quot;,&amp;quot;name&amp;quot;:&amp;quot;foo&amp;quot;},&amp;quot;body&amp;quot;:{&amp;quot;html&amp;quot;:&amp;quot;Ref in refs&amp;quot;}}' +
				'\\&quot;><a><span class=\\&quot;mw-reflink-text\\&quot;>[g1 3]</span></a></span>&quot;}}">' +
			'</div>',
		head: '<base href="http://example.com" />',
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
					mw: { name: 'ref', attrs: { name: 'bar' } },
					originalMw: '{"name":"ref","attrs":{"name":"bar"}}',
					contentsUsed: false
				}
			},
			{ type: '/mwReference' },
			' ', 'B', 'a', 'z',
			{
				type: 'mwReference',
				attributes: {
					listIndex: 1,
					listGroup: 'mwReference/g1',
					listKey: 'literal/:0',
					refGroup: 'g1',
					mw: { name: 'ref', body: { html: 'Quux' }, attrs: { group: 'g1', name: ':0' } },
					originalMw: '{"name":"ref","body":{"html":"Quux"},"attrs":{"group":"g1","name":":0"}}',
					contentsUsed: true
				}
			},
			{ type: '/mwReference' },
			' ', 'W', 'h', 'e', 'e',
			{
				type: 'mwReference',
				attributes: {
					listIndex: 0,
					listGroup: 'mwReference/',
					listKey: 'literal/bar',
					refGroup: '',
					mw: { name: 'ref', body: { html: '<a rel="mw:WikiLink" href="./Bar">Bar</a>' }, attrs: { name: 'bar' } },
					originalMw: '{"name":"ref","body":{"html":"<a rel=\\"mw:WikiLink\\" href=\\"./Bar\\">Bar</a>"},"attrs":{"name":"bar"}}',
					contentsUsed: true
				}
			},
			{ type: '/mwReference' },
			' ', 'Y', 'a', 'y',
			{
				type: 'mwReference',
				attributes: {
					listIndex: 2,
					listGroup: 'mwReference/g1',
					listKey: 'auto/0',
					refGroup: 'g1',
					mw: { name: 'ref', body: { id: 'mw-cite-3' }, attrs: { group: 'g1' } },
					originalMw: '{"name":"ref","body":{"id":"mw-cite-3"},"attrs":{"group":"g1"}}',
					contentsUsed: true,
					refListItemId: 'mw-cite-3'
				}
			},
			{ type: '/mwReference' },
			' ', 'Q', 'u', 'u', 'x',
			{
				type: 'mwReference',
				attributes: {
					listIndex: 0,
					listGroup: 'mwReference/',
					listKey: 'literal/bar',
					refGroup: '',
					mw: { name: 'ref', body: { html: 'Different content' }, attrs: { name: 'bar' } },
					originalMw: '{"name":"ref","body":{"html":"Different content"},"attrs":{"name":"bar"}}',
					contentsUsed: false
				}
			},
			{ type: '/mwReference' },
			' ', 'F', 'o', 'o',
			{
				type: 'mwReference',
				attributes: {
					listGroup: 'mwReference/g1',
					listIndex: 3,
					listKey: 'literal/foo',
					refGroup: 'g1',
					mw: { name: 'ref', attrs: { group: 'g1', name: 'foo' } },
					originalMw: '{"name":"ref","attrs":{"group":"g1","name":"foo"}}',
					contentsUsed: false
				}
			},
			{ type: '/mwReference' },
			{ type: '/paragraph' },
			{
				type: 'mwReferencesList',
				attributes: {
					mw: {
						name: 'references',
						attrs: { group: 'g1' },
						body: {
							html: '<span about="#mwt8" class="reference" data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Ref in refs&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;foo&quot;}}" rel="dc:references" typeof="mw:Extension/ref"><a href="#cite_note-foo-3">[3]</a></span>'
						}
					},
					originalMw: '{"name":"references","body":{"html":"<span about=\\"#mwt8\\" class=\\"reference\\" data-mw=\\"{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:{&quot;html&quot;:&quot;Ref in refs&quot;},&quot;attrs&quot;:{&quot;group&quot;:&quot;g1&quot;,&quot;name&quot;:&quot;foo&quot;}}\\" rel=\\"dc:references\\" typeof=\\"mw:Extension/ref\\"><a href=\\"#cite_note-foo-3\\">[3]</a></span>"},"attrs":{"group":"g1"}}',
					listGroup: 'mwReference/g1',
					refGroup: 'g1'
				}
			},
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			{
				type: 'mwReference',
				attributes: {
					contentsUsed: true,
					listGroup: 'mwReference/g1',
					listIndex: 3,
					listKey: 'literal/foo',
					mw: { name: 'ref', attrs: { group: 'g1', name: 'foo' }, body: { html: 'Ref in refs' } },
					originalMw: '{"name":"ref","body":{"html":"Ref in refs"},"attrs":{"group":"g1","name":"foo"}}',
					refGroup: 'g1'
				}
			},
			{ type: '/mwReference' },
			{ type: '/paragraph' },
			{ type: '/mwReferencesList' },
			{ type: 'internalList' },
			{ type: 'internalItem', attributes: { originalHtml: '<a rel="mw:WikiLink" href="./Bar">Bar</a>' } },
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			[
				'B',
				[ {
					type: 'link/mwInternal',
					attributes: {
						title: 'Bar',
						origTitle: 'Bar',
						normalizedTitle: 'Bar',
						lookupTitle: 'Bar',
						hrefPrefix: './'
					}
				} ]
			],
			[
				'a',
				[ {
					type: 'link/mwInternal',
					attributes: {
						title: 'Bar',
						origTitle: 'Bar',
						normalizedTitle: 'Bar',
						lookupTitle: 'Bar',
						hrefPrefix: './'
					}
				} ]
			],
			[
				'r',
				[ {
					type: 'link/mwInternal',
					attributes: {
						title: 'Bar',
						origTitle: 'Bar',
						normalizedTitle: 'Bar',
						lookupTitle: 'Bar',
						hrefPrefix: './'
					}
				} ]
			],
			{ type: '/paragraph' },
			{ type: '/internalItem' },
			{ type: 'internalItem', attributes: { originalHtml: 'Quux' } },
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			'Q', 'u', 'u', 'x',
			{ type: '/paragraph' },
			{ type: '/internalItem' },
			{ type: 'internalItem', attributes: { originalHtml: 'No name' } },
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			'N', 'o', ' ', 'n', 'a', 'm', 'e',
			{ type: '/paragraph' },
			{ type: '/internalItem' },
			{ type: 'internalItem', attributes: { originalHtml: 'Ref in refs' } },
			{ type: 'paragraph', internal: { generated: 'wrapper' } },
			'R', 'e', 'f', ' ', 'i', 'n', ' ', 'r', 'e', 'f', 's',
			{ type: '/paragraph' },
			{ type: '/internalItem' },
			{ type: '/internalList' }
		]
	},
	'mw:Reference with metadata': {
		body: '<p><span about="#mwt2" class="reference" ' +
			'data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:' +
			'{&quot;html&quot;:&quot;Foo<!-- bar -->&quot;},&quot;attrs&quot;:{}}" ' +
			'id="cite_ref-1-0" rel="dc:references" typeof="mw:Extension/ref" data-parsoid="{}">' +
			'<a href="#cite_note-bar-1" data-parsoid="{}">[1]</a></span></p>',
		fromDataBody: '<p><span ' +
			'data-mw="{&quot;name&quot;:&quot;ref&quot;,&quot;body&quot;:' +
			'{&quot;html&quot;:&quot;Foo<!-- bar -->&quot;},&quot;attrs&quot;:{}}" ' +
			'typeof="mw:Extension/ref"></span></p>',
		clipboardBody: '<p><span typeof="mw:Extension/ref" ' +
			'data-mw="{&quot;attrs&quot;:{},&quot;body&quot;:' +
			'{&quot;html&quot;:&quot;Foo<span rel=\\&quot;ve:Comment\\&quot; data-ve-comment=\\&quot; bar \\&quot;>&amp;nbsp;</span>&quot;},&quot;name&quot;:&quot;ref&quot;}" ' +
			'>' +
			'<a><span class="mw-reflink-text">[1]</span></a></span></p>',
		head: '<base href="http://example.com" />',
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

ve.dm.citeExample.references = [
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
				body: { html: 'No name 1' },
				name: 'ref'
			},
			originalMw: '{"name":"ref","body":{"html":"No name 1"},"attrs":{}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	{ type: '/paragraph' },
	{ type: 'paragraph' },
	'F', 'o', 'o',
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/',
			listIndex: 1,
			listKey: 'literal/bar',
			mw: {
				attrs: { name: 'bar' },
				body: { html: 'Bar' },
				name: 'ref'
			},
			originalMw: '{"body":{"html":""},"attrs":{"name":"bar"}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	' ', 'B', 'a', 'z',
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/',
			listIndex: 2,
			listKey: 'literal/:3',
			mw: {
				attrs: { name: ':3' },
				body: { html: 'Quux' },
				name: 'ref'
			},
			originalMw: '{"name":"ref","body":{"html":"Quux"},"attrs":{"name":":3"}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	' ', 'W', 'h', 'e', 'e',
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: false,
			listGroup: 'mwReference/',
			listIndex: 1,
			listKey: 'literal/bar',
			mw: {
				attrs: { name: 'bar' },
				name: 'ref'
			},
			originalMw: '{"body":{"html":""},"attrs":{"name":"bar"}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	' ', 'Y', 'a', 'y',
	{ type: '/paragraph' },
	{ type: 'paragraph' },
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/',
			listIndex: 3,
			listKey: 'auto/1',
			mw: {
				attrs: {},
				body: { html: 'No name 2' },
				name: 'ref'
			},
			originalMw: '{"name":"ref","body":{"html":"No name 2"},"attrs":{}}',
			refGroup: ''
		}
	},
	{ type: '/mwReference' },
	{
		type: 'mwReference',
		attributes: {
			contentsUsed: true,
			listGroup: 'mwReference/foo',
			listIndex: 4,
			listKey: 'auto/2',
			mw: {
				attrs: { group: 'foo' },
				body: { html: 'No name 3' },
				name: 'ref'
			},
			originalMw: '{"name":"ref","body":{"html":"No name 3"},"attrs":{"group":"foo"}}',
			refGroup: 'foo'
		}
	},
	{ type: '/mwReference' },
	{ type: '/paragraph' },
	{
		type: 'mwReferencesList',
		// originalDomElements: HTML,
		attributes: {
			mw: {
				name: 'references',
				attrs: { group: 'g1' }
			},
			originalMw: '{"name":"references","attrs":{"group":"g1"}"}',
			listGroup: 'mwReference/',
			refGroup: ''
		}
	},
	{ type: '/mwReferencesList' },
	{ type: 'internalList' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'N', 'o', ' ', 'n', 'a', 'm', 'e', ' ', '1',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'B', 'a', 'r',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'Q', 'u', 'u', 'x',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'N', 'o', ' ', 'n', 'a', 'm', 'e', ' ', '2',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: 'internalItem' },
	{ type: 'paragraph' },
	'N', 'o', ' ', 'n', 'a', 'm', 'e', ' ', '3',
	{ type: '/paragraph' },
	{ type: '/internalItem' },
	{ type: '/internalList' }
];

ve.dm.citeExample.complexInternalData = [
	// 0
	{ type: 'alienMeta', originalDomElements: $( '<!-- before -->' ).toArray() },
	{ type: '/alienMeta' },
	{ type: 'paragraph' },
	'F', [ 'o', [ ve.dm.example.bold ] ], [ 'o', [ ve.dm.example.italic ] ],
	// 4
	{ type: 'mwReference', attributes: {
		mw: {},
		about: '#mwt1',
		listIndex: 0,
		listGroup: 'mwReference/',
		listKey: 'auto/0',
		refGroup: '',
		contentsUsed: true
	} },
	// 5
	{ type: '/mwReference' },
	// 6
	{ type: '/paragraph' },
	{ type: 'alienMeta', originalDomElements: $( '<!-- after -->' ).toArray() },
	{ type: '/alienMeta' },
	// 7
	{ type: 'internalList' },
	// 8
	{ type: 'internalItem' },
	// 9
	{ type: 'paragraph', internal: { generated: 'wrapper' } },
	'R', [ 'e', [ ve.dm.example.bold ] ], 'f',
	// 13
	{ type: 'alienMeta', originalDomElements: $( '<!-- reference -->' ).toArray() },
	{ type: '/alienMeta' },
	'e', [ 'r', [ ve.dm.example.italic ] ], [ 'e', [ ve.dm.example.italic ] ],
	// 16
	{ type: 'mwReference', attributes: {
		mw: {},
		about: '#mwt2',
		listIndex: 1,
		listGroup: 'mwReference/',
		listKey: 'foo',
		refGroup: '',
		contentsUsed: true
	} },
	// 17
	{ type: '/mwReference' },
	'n', 'c', 'e',
	// 21
	{ type: '/paragraph' },
	// 22
	{ type: '/internalItem' },
	// 23
	{ type: 'internalItem' },
	{ type: 'alienMeta', originalDomElements: $( '<!-- beginning -->' ).toArray() },
	{ type: '/alienMeta' },
	// 24
	{ type: 'preformatted' },
	{ type: 'alienMeta', originalDomElements: $( '<!-- inside -->' ).toArray() },
	{ type: '/alienMeta' },
	// 25
	{ type: 'mwEntity', attributes: { character: '€' } },
	// 26
	{ type: '/mwEntity' },
	'2', '5', '0',
	{ type: 'alienMeta', originalDomElements: $( '<!-- inside2 -->' ).toArray() },
	{ type: '/alienMeta' },
	// 30
	{ type: '/preformatted' },
	{ type: 'alienMeta', originalDomElements: $( '<!-- end -->' ).toArray() },
	{ type: '/alienMeta' },
	// 31
	{ type: '/internalItem' },
	// 32
	{ type: '/internalList' }
	// 33
];

ve.dm.citeExample.complexInternalData.internalItems = [
	{ group: 'mwReference', key: null, body: 'First reference' },
	{ group: 'mwReference', key: 'foo', body: 'Table in ref: <table><tr><td>because I can</td></tr></table>' }
];

ve.dm.citeExample.complexInternalData.internalListNextUniqueNumber = 1;
