/**
 * @param {string} msg
 * @param {number} [height=200]
 * @return {string}
 */
const placeholder = ( msg, height ) => {
	return `<div style="width: 100%; height: ${height || 200}px; margin-bottom: 2px;
		font-size: 12px; padding: 8px; box-sizing: border-box;
		display: flex; background: #eee; align-items: center;justify-content: center;">${msg}</div>`;
};

/**
 * @param {string} html
 * @return {string}
 */
const portletAfter = ( html ) => {
	return `<div class="after-portlet after-portlet-tb">${html}</div>`;
};

const htmlUserLanguageAttributes = `dir="ltr" lang="en-GB"`;

export { placeholder, htmlUserLanguageAttributes, portletAfter };
