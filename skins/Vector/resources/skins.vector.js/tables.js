const config = require( './config.json' );
const init = () => {
	if ( !config.VectorWrapTablesTemporary ) {
		return;
	}
	const tables = document.querySelectorAll( '.mw-parser-output > table.wikitable' );
	let numberBigTables = 0;
	Array.from( tables ).forEach( ( table ) => {
		const styles = window.getComputedStyle( table );
		const isFloat = styles.getPropertyValue( 'float' ) === 'right' || styles.getPropertyValue( 'float' ) === 'left';

		// Don't wrap tables within tables
		const parent = table.parentElement;
		if (
			parent &&
			!parent.matches( '.noresize' ) &&
			!parent.closest( 'table' ) &&
			!isFloat
		) {
			const tableRect = table.getBoundingClientRect();
			const tableWidth = tableRect && tableRect.width;
			const wrapper = document.createElement( 'div' );
			wrapper.classList.add( 'noresize' );
			parent.insertBefore( wrapper, table );
			wrapper.appendChild( table );

			if ( tableWidth > 948 ) {
				numberBigTables++;
			}
		}
	} );
	if ( numberBigTables > 0 ) {
		// @ts-ignore
		mw.errorLogger.logError(
			new Error( `T374493: ${ numberBigTables } tables wrapped` ),
			'error.web-team'
		);
	}
};

module.exports = {
	init
};
