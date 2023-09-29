/* eslint-disable es-x/no-array-prototype-findindex, no-extend-native, eqeqeq, no-bitwise */

/**
 * Array.prototype.findIndex polyfill
 *
 * Based on the example from the MDN docs, re-written for MW house style.
 * Implementation follows the spec here:
 * https://tc39.es/ecma262/#sec-array.prototype.findindex
 *
 * author Mozilla Contributors
 * license CC0 per https://developer.mozilla.org/en-US/docs/MDN/About#copyrights_and_licenses
 *
 * https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Array/findIndex
 */
if ( !Array.prototype.findIndex ) {
	Object.defineProperty( Array.prototype, 'findIndex', {
		value: function ( predicate ) {
			var o, len, thisArg, k, kValue;

			// 1. Let O be ? ToObject(this value).
			if ( this == null ) {
				throw new TypeError( '"this" is null or not defined' );
			}

			o = Object( this );

			// 2. Let len be ? ToLength(? Get(O, "length")).
			len = o.length >>> 0;

			// 3. If IsCallable(predicate) is false, throw a TypeError exception.
			if ( typeof predicate !== 'function' ) {
				throw new TypeError( 'predicate must be a function' );
			}

			// 4. If thisArg was supplied, let T be thisArg; else let T be undefined.
			thisArg = arguments[ 1 ];

			// 5. Let k be 0.
			k = 0;

			// 6. Repeat, while k < len
			while ( k < len ) {
				// a. Let Pk be ! ToString(k).
				// b. Let kValue be ? Get(O, Pk).
				// c. Let testResult be ToBoolean(? Call(predicate, T, « kValue, k, O »)).
				// d. If testResult is true, return k.
				kValue = o[ k ];
				if ( predicate.call( thisArg, kValue, k, o ) ) {
					return k;
				}
				// e. Increase k by 1.
				k++;
			}

			// 7. Return -1.
			return -1;
		},
		configurable: true,
		writable: true
	} );
}
