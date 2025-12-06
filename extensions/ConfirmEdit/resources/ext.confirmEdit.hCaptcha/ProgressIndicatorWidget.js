/**
 * A Codex ProgressIndicator component implementation.
 */
class ProgressIndicatorWidget {
	/**
	 * Initialize the widget.
	 *
	 * @param {string} label aria-label to set on the backing <progress> element
	 */
	constructor( label ) {
		const $progress = $( '<progress>' )
			.attr( {
				class: 'cdx-progress-indicator__indicator__progress',
				'aria-label': label
			} );

		const $indicator = $( '<div>' )
			.addClass( 'cdx-progress-indicator__indicator' )
			.append( $progress );

		this.$element = $( '<div>' )
			.addClass( 'cdx-progress-indicator' )
			.append( $indicator );
	}
}

module.exports = ProgressIndicatorWidget;
