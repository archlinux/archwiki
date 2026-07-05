/**
 * Check whether a jQuery event represents a plain left click, without any modifiers
 *
 * @param {jQuery.Event} e
 * @return {boolean} Whether it was an unmodified left click
 */
function isUnmodifiedLeftClick( e ) {
	return e.which === OO.ui.MouseButtons.LEFT && !( e.shiftKey || e.altKey || e.ctrlKey || e.metaKey );
}

module.exports = {
	isUnmodifiedLeftClick: isUnmodifiedLeftClick
};
