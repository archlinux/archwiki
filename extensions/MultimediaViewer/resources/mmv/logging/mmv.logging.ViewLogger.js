/*
 * This file is part of the MediaWiki extension MultimediaViewer.
 *
 * MultimediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MultimediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MultimediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

const { recordVirtualViewBeaconURI } = require( '../config.json' );

/**
 * Tracks how long users are viewing images for
 */
class ViewLogger {
	/**
	 * @param {Object} windowObject Browser window object
	 */
	constructor( windowObject ) {
		/**
		 * Was the last image view logged or was logging skipped?
		 *
		 * @property {boolean}
		 */
		this.wasLastViewLogged = false;

		/**
		 * Record when the user started looking at the current image
		 *
		 * @property {number}
		 */
		this.viewStartTime = 0;

		/**
		 * How long the user has been looking at the current image
		 *
		 * @property {number}
		 */
		this.viewDuration = 0;

		/**
		 * The image URL to record a virtual view for
		 *
		 * @property {string}
		 */
		this.url = '';

		/**
		 * Browser window
		 *
		 * @property {Object}
		 */
		this.window = windowObject;
	}

	/**
	 * Tracks the unview event of the current image if appropriate
	 */
	unview() {
		if ( !this.wasLastViewLogged ) {
			return;
		}

		this.wasLastViewLogged = false;
	}

	/**
	 * Starts recording a viewing window for the current image
	 */
	startViewDuration() {
		this.viewStartTime = Date.now();
	}

	/**
	 * Stops recording the viewing window for the current image
	 */
	stopViewDuration() {
		if ( this.viewStartTime ) {
			this.viewDuration += Date.now() - this.viewStartTime;
			this.viewStartTime = 0;
		}
	}

	/**
	 * Records the amount of time the current image has been viewed
	 */
	recordViewDuration() {
		let url;

		this.stopViewDuration();

		if ( recordVirtualViewBeaconURI ) {
			try {
				url = new URL( recordVirtualViewBeaconURI, location );
				url.searchParams.set( 'duration', this.viewDuration );
				url.searchParams.set( 'uri', this.url );
			} catch ( e ) {
				// the URI is malformed. We cannot log it.
				return;
			}

			try {
				navigator.sendBeacon( url.toString() );
			} catch ( e ) {
				$.ajax( {
					type: 'HEAD',
					url: url.toString()
				} );
			}

			mw.log( 'Image has been viewed for ', this.viewDuration );
		}

		this.viewDuration = 0;

		this.unview();
	}

	/**
	 * Sets up the view tracking for the current image
	 *
	 * @param {string} url URL of the image to record a virtual view for
	 */
	attach( url ) {
		this.url = url;
		this.startViewDuration();

		$( this.window )
			.off( '.mmv-view-logger' )
			.on( 'beforeunload.mmv-view-logger', () => this.recordViewDuration() )
			.on( 'focus.mmv-view-logger', () => this.startViewDuration() )
			.on( 'blur.mmv-view-logger', () => this.stopViewDuration() );
	}

	/*
			* Stops listening to events
			*/
	unattach() {
		$( this.window ).off( '.mmv-view-logger' );
		this.stopViewDuration();
	}

	/**
	 * Tracks whether or not the image view event was logged or not (i.e. was it in the logging sample)
	 *
	 * @param {boolean} wasEventLogged Whether the image view event was logged
	 */
	setLastViewLogged( wasEventLogged ) {
		this.wasLastViewLogged = wasEventLogged;
	}
}

module.exports = ViewLogger;
