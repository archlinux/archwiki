( function () {
	const ns = 'http://www.w3.org/1998/Math/MathML';

	function canCreateMathElement() {
		return typeof document === 'object' && typeof document.createElementNS === 'function';
	}

	function supportsMencloseNotation() {
		const el = document.createElementNS( ns, 'menclose' );
		return 'notation' in el;
	}

	function supportsColumnAlign() {
		const table = document.createElementNS( ns, 'mtable' );
		const cell = document.createElementNS( ns, 'mtd' );
		return 'columnalign' in table || 'columnAlign' in table ||
			'columnalign' in cell || 'columnAlign' in cell;
	}

	function supportsMathMLHref() {
		const el = document.createElementNS( ns, 'mrow' );
		if ( !( 'href' in el ) ) {
			return false;
		}
		el.setAttribute( 'href', '#math-href' );
		return typeof el.href === 'string' && el.href.includes( '#math-href' );
	}

	function applyHrefPolyfill() {
		if ( !document.querySelectorAll ) {
			return;
		}
		[].forEach.call(
			document.querySelectorAll( '.mwe-math-element mrow[href]' ),
			( el ) => {
				el.style.cursor = 'pointer';
				el.tabIndex = 0;
				el.setAttribute( 'role', 'link' );
				el.addEventListener( 'click', ( event ) => {
					document.location = event.currentTarget.getAttribute( 'href' );
				} );
				el.addEventListener( 'keydown', ( event ) => {
					if ( event.key === 'Enter' ) {
						document.location = event.currentTarget.getAttribute( 'href' );
					}
				} );
				el.addEventListener( 'mouseover', ( event ) => {
					event.currentTarget.style.textDecoration = 'solid underline';
				} );
				el.addEventListener( 'mouseout', ( event ) => {
					event.currentTarget.style.textDecoration = '';
				} );
				return el;
			}
		);
	}

	if ( !canCreateMathElement() ) {
		return;
	}

	const needsMenclose = !supportsMencloseNotation();
	const needsColumnAlign = !supportsColumnAlign();
	const needsHref = !supportsMathMLHref();

	const root = document.documentElement;
	if ( root && root.classList ) {
		if ( needsMenclose ) {
			root.classList.add( 'mw-math-polyfill-menclose' );
		}
		if ( needsColumnAlign ) {
			root.classList.add( 'mw-math-polyfill-columnalign' );
		}
	}

	if ( needsHref ) {
		applyHrefPolyfill();
	}
}() );
