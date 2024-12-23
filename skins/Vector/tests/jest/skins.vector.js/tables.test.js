const tables = require( '../../../resources/skins.vector.js/tables.js' ).init;

describe( 'tables', () => {
	test( 'wraps table with div', () => {
		document.body.innerHTML = `
			<div class="mw-parser-output">
				<table class="wikitable">
					<tbody><tr><th>table table table</th></tr></tbody>
				</table>
			</div>
		`;
		tables();

		expect( document.body.innerHTML ).toMatchSnapshot();
	} );

	test( 'wraps multiple table with div', () => {
		document.body.innerHTML = `
			<div class="mw-parser-output">
				<table class="wikitable">
					<tbody><tr><th>table table table</th></tr></tbody>
				</table>
				<table class="wikitable">
				<table class="wikitable">
					<tbody><tr><th>table table table</th></tr></tbody>
				</table>
				<table class="wikitable">
					<tbody><tr><th>table table table</th></tr></tbody>
				</table>
			</div>
		`;
		tables();

		expect( document.body.innerHTML ).toMatchSnapshot();
	} );

	test( 'doesnt wrap nested tables', () => {
		document.body.innerHTML = `
			<div class="mw-parser-output">
				<table class="wikitable">
					<tbody>
						<tr><th>table table table</th></tr>
						<tr><td><table class="wikitable"><tbody><tr><th>table table table</th></tr></tbody></table><td></tr>
					</tbody>
				</table>
			</div>
		`;
		tables();

		expect( document.body.innerHTML ).toMatchSnapshot();
	} );

	test( 'doesnt wrap tables that are not wikitables', () => {
		document.body.innerHTML = `
			<div class="mw-parser-output">
				<table>
					<tbody>
						<tr><th>table table table</th></tr>
						<tr><td><table><tbody><tr><th>table table table</th></tr></tbody></table><td></tr>
					</tbody>
				</table>
			<div>
		`;
		tables();

		expect( document.body.innerHTML ).toMatchSnapshot();
	} );

	test( 'doesnt wrap tables that already have noresize', () => {
		document.body.innerHTML = `
			<div class="mw-parser-output">
				<div class="noresize">
					<table class="wikitable">
						<tbody>
							<tr><th>table table table</th></tr>
						</tbody>
					</table>
				</div>
			</div>
		`;
		tables();

		expect( document.body.innerHTML ).toMatchSnapshot();
	} );

	test( 'doesnt wrap tables that are already wrapped', () => {
		document.body.innerHTML = `
			<div class="mw-parser-output">
				<div>
					<table class="wikitable">
						<tbody>
							<tr><th>table table table</th></tr>
						</tbody>
					</table>
				</div>
			</div>
		`;
		tables();

		expect( document.body.innerHTML ).toMatchSnapshot();
	} );

	test( 'doesnt wrap floated tables', () => {
		document.body.innerHTML = `
			<div class="mw-parser-output">
				<table class="wikitable" style="float:right">
					<tbody>
						<tr><th>table table table</th></tr>
					</tbody>
				</table>
			<div>
		`;
		tables();

		expect( document.body.innerHTML ).toMatchSnapshot();
	} );
} );
