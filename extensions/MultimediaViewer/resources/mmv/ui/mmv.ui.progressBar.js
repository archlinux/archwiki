/*
 * This file is part of the MediaWiki extension MediaViewer.
 *
 * MediaViewer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * MediaViewer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MediaViewer.  If not, see <http://www.gnu.org/licenses/>.
 */

const UiElement = require( './mmv.ui.js' );

/**
 * A progress bar for the loading of the image.
 */
class ProgressBar extends UiElement {
	/**
	 * @param {jQuery} $container
	 */
	constructor( $container ) {
		super( $container );
		this.init();
	}

	/**
	 * Initializes the progress display at the top of the panel.
	 */
	init() {
		this.$progress = $( '<div>' )
			.addClass( 'mw-mmv-progress empty' )
			.appendTo( this.$container );
		this.$percent = $( '<div>' )
			.addClass( 'mw-mmv-progress-percent' )
			.appendTo( this.$progress );
	}

	empty() {
		this.hide();
	}

	/**
	 * Hides the bar, resets it to 0 and stops any animation in progress.
	 */
	hide() {
		this.$progress.addClass( 'empty' );
		this.$percent.css( { width: 0 } );
	}

	/**
	 * Handles the progress display when a percentage of progress is received
	 *
	 * @param {number} percent a number between 0 and 100
	 */
	animateTo( percent ) {
		this.$progress.removeClass( 'empty' );

		if ( percent === 100 ) {
			// When a 100% update comes in, we make sure that the bar is visible, we animate
			// fast to 100 and we hide the bar
			this.$percent.css( { width: `${ percent }%` } );
			this.hide();
		} else {
			// When any other % update comes in, we make sure the bar is visible
			// and we animate to the right position
			this.$percent.css( { width: `${ percent }%` } );
		}
	}

	/**
	 * Goes to the given percent (originally without animation)
	 *
	 * @param {number} percent a number between 0 and 100
	 */
	jumpTo( percent ) {
		this.animateTo( percent );
	}
}

module.exports = ProgressBar;
