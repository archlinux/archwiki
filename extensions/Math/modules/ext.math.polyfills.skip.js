const ns = 'http://www.w3.org/1998/Math/MathML';
if ( typeof document !== 'object' || typeof document.createElementNS !== 'function' ) {
	return false;
}

const menclose = document.createElementNS( ns, 'menclose' );
const hasMenclose = 'notation' in menclose;

const table = document.createElementNS( ns, 'mtable' );
const cell = document.createElementNS( ns, 'mtd' );
const hasColumnAlign = 'columnalign' in table || 'columnAlign' in table ||
	'columnalign' in cell || 'columnAlign' in cell;

const href = document.createElementNS( ns, 'mrow' );
let hasHref = false;
if ( 'href' in href ) {
	href.setAttribute( 'href', '#math-href' );
	hasHref = typeof href.href === 'string' && href.href.includes( '#math-href' );
}

return hasMenclose && hasColumnAlign && hasHref;
