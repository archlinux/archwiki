/**
 * Compares `window.location.host`, and `URL.host`. Equivalence
 * tests internal linkage, a mismatch may indicate an external link. Interwiki links are
 * considered external.
 *
 * This function only indicates internal in the sense of being on the same host or not. It has
 * no knowledge of [[Link]] vs [Link] links.
 *
 * On https://meta.wikimedia.org/wiki/Foo, the following links would be considered *internal*
 * and return `true`:
 *
 *     https://meta.wikimedia.org/
 *     https://meta.wikimedia.org/wiki/Bar
 *     https://meta.wikimedia.org/w/index.php?title=Bar
 *
 * Similarly, the following links would be considered *not* internal and return `false`:
 *
 *     https://archive.org/
 *     https://foo.wikimedia.org/
 *     https://en.wikipedia.org/
 *     https://en.wikipedia.org/wiki/Bar
 *
 * @ignore
 * @param {URL} url
 * @param {URL|Location} reference
 * @return {boolean}
 */
function isInternal( url ) {
	try {
		// URL can throw exceptions (T264914, T66884)
		return url.host === isInternal.base.host;
	} catch ( e ) {
		return false;
	}
}

// Allow overriding for qunit tests
isInternal.base = location;

module.exports = {
	isInternal
};
