/*!
 * Parsoid utilities tests.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

QUnit.module( 've.utils.parsoid', ve.test.utils.newMwEnvironment() );

QUnit.test( 'reduplicateStyles/deduplicateStyles', ( assert ) => {
	// Test cases based on this page and the templates there:
	// https://en.wikipedia.beta.wmflabs.org/wiki/Table_templated
	const stylesCases = [
		{
			msg: 'styles are deduplicated',
			deduplicated: `<span about="#mwt1" typeof="mw:Transclusion" data-parsoid='{"pi":[[],[{"k":"1"},{"k":"2"}],[{"k":"1"},{"k":"2"}],[{"k":"1"},{"k":"2"}],[]],"dsr":[0,107,null,null]}' data-mw='{"parts":[{"template":{"target":{"wt":"table start","href":"./Template:Table_start"},"params":{},"i":0}},"\\n",{"template":{"target":{"wt":"table row","href":"./Template:Table_row"},"params":{"1":{"wt":"Hello"},"2":{"wt":"good"}},"i":1}},"\\n",{"template":{"target":{"wt":"table row","href":"./Template:Table_row"},"params":{"1":{"wt":"Goodbye"},"2":{"wt":"bad"}},"i":2}},"\\n",{"template":{"target":{"wt":"table row","href":"./Template:Table_row"},"params":{"1":{"wt":"Welcome"},"2":{"wt":"good"}},"i":3}},"\\n",{"template":{"target":{"wt":"table end","href":"./Template:Table_end"},"params":{},"i":4}}]}'>
</span><table class="wikitable" about="#mwt1">
<tbody><tr>
<td class="good"><style data-mw-deduplicate="TemplateStyles:r5432" typeof="mw:Extension/templatestyles" about="#mwt4" data-mw='{"name":"templatestyles","attrs":{"src":"Table row/styles.css"}}'>.mw-parser-output .good{background:#9EFF9E}.mw-parser-output .bad{background:#FFC7C7}</style>Hello</td></tr>
<tr>
<td class="bad"><link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r5432" about="#mwt7" typeof="mw:Extension/templatestyles" data-mw='{"name":"templatestyles","attrs":{"src":"Table row/styles.css"}}'/>Goodbye</td></tr>
<tr>
<td class="good"><link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r5432" about="#mwt10" typeof="mw:Extension/templatestyles" data-mw='{"name":"templatestyles","attrs":{"src":"Table row/styles.css"}}'/>Welcome</td></tr>
</tbody></table>`,
			reduplicated: `<span about="#mwt1" typeof="mw:Transclusion" data-parsoid='{"pi":[[],[{"k":"1"},{"k":"2"}],[{"k":"1"},{"k":"2"}],[{"k":"1"},{"k":"2"}],[]],"dsr":[0,107,null,null]}' data-mw='{"parts":[{"template":{"target":{"wt":"table start","href":"./Template:Table_start"},"params":{},"i":0}},"\\n",{"template":{"target":{"wt":"table row","href":"./Template:Table_row"},"params":{"1":{"wt":"Hello"},"2":{"wt":"good"}},"i":1}},"\\n",{"template":{"target":{"wt":"table row","href":"./Template:Table_row"},"params":{"1":{"wt":"Goodbye"},"2":{"wt":"bad"}},"i":2}},"\\n",{"template":{"target":{"wt":"table row","href":"./Template:Table_row"},"params":{"1":{"wt":"Welcome"},"2":{"wt":"good"}},"i":3}},"\\n",{"template":{"target":{"wt":"table end","href":"./Template:Table_end"},"params":{},"i":4}}]}'>
</span><table class="wikitable" about="#mwt1">
<tbody><tr>
<td class="good"><style data-mw-deduplicate="TemplateStyles:r5432" typeof="mw:Extension/templatestyles" about="#mwt4" data-mw='{"name":"templatestyles","attrs":{"src":"Table row/styles.css"}}'>.mw-parser-output .good{background:#9EFF9E}.mw-parser-output .bad{background:#FFC7C7}</style>Hello</td></tr>
<tr>
<td class="bad"><style data-mw-deduplicate="TemplateStyles:r5432" typeof="mw:Extension/templatestyles" about="#mwt7" data-mw='{"name":"templatestyles","attrs":{"src":"Table row/styles.css"}}'>.mw-parser-output .good{background:#9EFF9E}.mw-parser-output .bad{background:#FFC7C7}</style>Goodbye</td></tr>
<tr>
<td class="good"><style data-mw-deduplicate="TemplateStyles:r5432" typeof="mw:Extension/templatestyles" about="#mwt10" data-mw='{"name":"templatestyles","attrs":{"src":"Table row/styles.css"}}'>.mw-parser-output .good{background:#9EFF9E}.mw-parser-output .bad{background:#FFC7C7}</style>Welcome</td></tr>
</tbody></table>`
		},
		{
			msg: 'styles in fosterable positions are NOT deduplicated, but they are emptied',
			deduplicated: `<span about="#mwt1" typeof="mw:Transclusion" data-parsoid='{"pi":[[],[{"k":"1"},{"k":"2"}],[{"k":"1"},{"k":"2"}],[{"k":"1"},{"k":"2"}],[]],"dsr":[0,107,null,null]}' data-mw='{"parts":[{"template":{"target":{"wt":"table start","href":"./Template:Table_start"},"params":{},"i":0}},"\\n",{"template":{"target":{"wt":"table row","href":"./Template:Table_row"},"params":{"1":{"wt":"Hello"},"2":{"wt":"good"}},"i":1}},"\\n",{"template":{"target":{"wt":"table row","href":"./Template:Table_row"},"params":{"1":{"wt":"Goodbye"},"2":{"wt":"bad"}},"i":2}},"\\n",{"template":{"target":{"wt":"table row","href":"./Template:Table_row"},"params":{"1":{"wt":"Welcome"},"2":{"wt":"good"}},"i":3}},"\\n",{"template":{"target":{"wt":"table end","href":"./Template:Table_end"},"params":{},"i":4}}]}'>
</span><table class="wikitable" about="#mwt1">
<tbody><tr>
<style data-mw-deduplicate="TemplateStyles:r5432" typeof="mw:Extension/templatestyles" about="#mwt4" data-mw='{"name":"templatestyles","attrs":{"src":"Table row/styles.css"}}'>.mw-parser-output .good{background:#9EFF9E}.mw-parser-output .bad{background:#FFC7C7}</style>
<td class="good">Hello</td></tr>
<tr>
<style data-mw-deduplicate="TemplateStyles:r5432" typeof="mw:Extension/templatestyles" about="#mwt7" data-mw='{"name":"templatestyles","attrs":{"src":"Table row/styles.css"}}'></style>
<td class="bad">Goodbye</td></tr>
<tr>
<style data-mw-deduplicate="TemplateStyles:r5432" typeof="mw:Extension/templatestyles" about="#mwt10" data-mw='{"name":"templatestyles","attrs":{"src":"Table row/styles.css"}}'></style>
<td class="good">Welcome</td></tr>
</tbody></table>`,
			reduplicated: `<span about="#mwt1" typeof="mw:Transclusion" data-parsoid='{"pi":[[],[{"k":"1"},{"k":"2"}],[{"k":"1"},{"k":"2"}],[{"k":"1"},{"k":"2"}],[]],"dsr":[0,107,null,null]}' data-mw='{"parts":[{"template":{"target":{"wt":"table start","href":"./Template:Table_start"},"params":{},"i":0}},"\\n",{"template":{"target":{"wt":"table row","href":"./Template:Table_row"},"params":{"1":{"wt":"Hello"},"2":{"wt":"good"}},"i":1}},"\\n",{"template":{"target":{"wt":"table row","href":"./Template:Table_row"},"params":{"1":{"wt":"Goodbye"},"2":{"wt":"bad"}},"i":2}},"\\n",{"template":{"target":{"wt":"table row","href":"./Template:Table_row"},"params":{"1":{"wt":"Welcome"},"2":{"wt":"good"}},"i":3}},"\\n",{"template":{"target":{"wt":"table end","href":"./Template:Table_end"},"params":{},"i":4}}]}'>
</span><table class="wikitable" about="#mwt1">
<tbody><tr>
<style data-mw-deduplicate="TemplateStyles:r5432" typeof="mw:Extension/templatestyles" about="#mwt4" data-mw='{"name":"templatestyles","attrs":{"src":"Table row/styles.css"}}'>.mw-parser-output .good{background:#9EFF9E}.mw-parser-output .bad{background:#FFC7C7}</style>
<td class="good">Hello</td></tr>
<tr>
<style data-mw-deduplicate="TemplateStyles:r5432" typeof="mw:Extension/templatestyles" about="#mwt7" data-mw='{"name":"templatestyles","attrs":{"src":"Table row/styles.css"}}'>.mw-parser-output .good{background:#9EFF9E}.mw-parser-output .bad{background:#FFC7C7}</style>
<td class="bad">Goodbye</td></tr>
<tr>
<style data-mw-deduplicate="TemplateStyles:r5432" typeof="mw:Extension/templatestyles" about="#mwt10" data-mw='{"name":"templatestyles","attrs":{"src":"Table row/styles.css"}}'>.mw-parser-output .good{background:#9EFF9E}.mw-parser-output .bad{background:#FFC7C7}</style>
<td class="good">Welcome</td></tr>
</tbody></table>`
		}
	];

	stylesCases.forEach( ( caseItem ) => {
		const doc = ve.parseXhtml( caseItem.deduplicated );

		// Test that we can re-duplicate styles, which were de-duplicated in Parsoid HTML
		mw.libs.ve.reduplicateStyles( doc.body );
		assert.equalDomElement(
			doc.body,
			ve.parseXhtml( caseItem.reduplicated ).body,
			caseItem.msg + ' (reduplicated)'
		);

		// Test that we can de-duplicate styles again, producing a result identical to the Parsoid HTML
		mw.libs.ve.deduplicateStyles( doc.body );
		assert.equalDomElement(
			doc.body,
			ve.parseXhtml( caseItem.deduplicated ).body,
			caseItem.msg + ' (deduplicated)'
		);
	} );
} );
