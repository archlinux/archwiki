// Attach click handlers to carousel items once the DOM is ready.
// Deferred to avoid side effects during module load (which interferes
// with the QUnit test environment).
$( () => {
	const { Config } = require( 'mmv.bootstrap' );
	const carouselItems = Array.from( document.querySelectorAll( '.mmv-carousel__item' ) );

	carouselItems.forEach( ( item ) => {
		const title = item.dataset.mmvTitle;
		if ( !title ) {
			return;
		}

		const link = item.querySelector( 'a' );
		if ( !link ) {
			return;
		}

		link.addEventListener( 'click', ( e ) => {
			if ( e.button !== 0 || e.altKey || e.ctrlKey || e.shiftKey || e.metaKey ) {
				return;
			}
			if ( !Config.isMediaViewerEnabledOnClick() ) {
				return;
			}
			e.preventDefault();
			location.hash = Config.getMediaHash( new mw.Title( title ) );
		} );
	} );
} );
