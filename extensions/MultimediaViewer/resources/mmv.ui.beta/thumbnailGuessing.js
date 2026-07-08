/**
 * Thumbnail URL guessing utilities for the beta viewer.
 * Adapted from mmv.provider.GuessedThumbnailInfo.
 *
 * Rewrites an existing thumbnail URL to request a different size, avoiding
 * an API round-trip. This works reliably on WMF wikis where thumbnails are
 * generated on demand via the 404 handler.
 *
 * @module mmv.ui.beta.thumbnailGuessing
 */

const { ThumbnailWidthCalculator } = require( 'mmv.common' );
const calculator = new ThumbnailWidthCalculator();

/** @typedef {import('./types').LightboxImage} LightboxImage */

/**
 * Get a larger thumbnail URL for the given image, if possible.
 *
 * @param {LightboxImage} image A LightboxImage object
 * @return {string|undefined} A URL for a larger thumbnail, or undefined if
 *   we can't guess one (falls back to image.src)
 */
function getLargerThumbnailUrl( image ) {
	if ( !image || !image.src ) {
		return undefined;
	}

	const originalWidth = image.originalWidth;
	const originalHeight = image.originalHeight;

	// If we don't know the original dimensions, we can't calculate the optimal size
	if ( !originalWidth || !originalHeight || isNaN( originalWidth ) || isNaN( originalHeight ) ) {
		return undefined;
	}

	const thumbWidths = calculator.calculateWidths(
		window.innerWidth, window.innerHeight,
		originalWidth, originalHeight
	);

	const parsed = mw.util.parseImageUrl( image.src );
	if ( !parsed || !parsed.resizeUrl ) {
		return undefined;
	}

	return parsed.resizeUrl( thumbWidths.real );
}

module.exports = { getLargerThumbnailUrl };
