/**
 * A Codex Message component implementation specialized for the "error" type.
 */
class ErrorWidget {
	constructor() {
		const $icon = $( '<span>' ).addClass( 'cdx-message__icon' );

		this.$content = $( '<div>' ).addClass( 'cdx-message__content' );

		this.$element = $( '<div>' )
			.attr( {
				class: 'cdx-message cdx-message--block cdx-message--error',
				role: 'alert'
			} )
			.append( $icon )
			.append( this.$content );

		this.$element.hide();
	}

	/**
	 * Display the widget with the given message.
	 *
	 * @param {string} message The message to be shown (will be HTML-escaped)
	 */
	show( message ) {
		this.$content.text( message );
		this.$element.show();
	}

	hide() {
		this.$element.hide();
	}
}

module.exports = ErrorWidget;
