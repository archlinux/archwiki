var scrollLeftStyle = null;

function testScrollLeftStyle() {
	var definer, $definer;
	if ( scrollLeftStyle !== null ) {
		return scrollLeftStyle;
	}
	// Detect which scrollLeft style the browser uses
	// Adapted from <https://github.com/othree/jquery.rtl-scroll-type>.
	// Original code copyright 2012 Wei-Ko Kao, licensed under the MIT License.
	// Adaptation copied from OO.ui.Element.static.getScrollLeft
	$definer = $( '<div>' ).attr( {
		dir: 'rtl',
		style: 'font-size: 14px; width: 4px; height: 1px; position: absolute; top: -1000px; overflow: scroll;'
	} ).text( 'ABCD' );
	$definer.appendTo( document.body );
	definer = $definer[ 0 ];
	if ( definer.scrollLeft > 0 ) {
		// Safari, Chrome
		scrollLeftStyle = 'default';
	} else {
		definer.scrollLeft = 1;
		if ( definer.scrollLeft === 0 ) {
			// Firefox, old Opera
			scrollLeftStyle = 'negative';
		} else {
			// Internet Explorer, Edge
			scrollLeftStyle = 'reverse';
		}
	}
	$definer.remove();
	return scrollLeftStyle;
}

/**
 * When tabs are present and one is selected, scroll the selected tab into view.
 *
 * @return {void}
 */
function initTabsScrollPosition() {
	var selectedTab, tabContainer, $tabContainer, maxScrollLeft, leftMostChild, rightMostChild,
		dir, widthDiff, tabPosition, containerPosition, left, increaseScrollLeft,
		// eslint-disable-next-line no-jquery/no-global-selector
		$selectedTab = $( '.minerva__tab.selected' );

	/**
	 * Set tabContainer.scrollLeft, with adjustments for browser inconsistencies in RTL
	 *
	 * @param {number} sl New .scrollLeft value, in 'default' (WebKit) style
	 */
	function setScrollLeft( sl ) {
		if ( dir === 'ltr' ) {
			tabContainer.scrollLeft = sl;
			return;
		}

		if ( testScrollLeftStyle() === 'reverse' ) {
			sl = maxScrollLeft - sl;
		} else if ( testScrollLeftStyle() === 'negative' ) {
			sl = -( maxScrollLeft - sl );
		}
		tabContainer.scrollLeft = sl;
	}

	if ( $selectedTab.length !== 1 ) {
		return;
	}
	selectedTab = $selectedTab.get( 0 );
	$tabContainer = $selectedTab.closest( '.minerva__tab-container' );
	tabContainer = $tabContainer.get( 0 );
	maxScrollLeft = tabContainer.scrollWidth - tabContainer.clientWidth;
	dir = $tabContainer.css( 'direction' ) || 'ltr';
	leftMostChild = dir === 'ltr' ? tabContainer.firstElementChild : tabContainer.lastElementChild;
	rightMostChild = dir === 'ltr' ? tabContainer.lastElementChild : tabContainer.firstElementChild;
	// If the tab is wider than the container (doesn't fit), this value will be negative
	widthDiff = tabContainer.clientWidth - selectedTab.clientWidth;

	if ( selectedTab === leftMostChild ) {
		// The left-most tab is selected. If the tab fits, scroll all the way to the left.
		// If the tab doesn't fit, align its start edge with the container's start edge.
		if ( dir === 'ltr' || widthDiff >= 0 ) {
			setScrollLeft( 0 );
		} else {
			setScrollLeft( -widthDiff );
		}
	} else if ( selectedTab === rightMostChild ) {
		// The right-most tab is selected. If the tab fits, scroll all the way to the right.
		// If the tab doesn't fit, align its start edge with the container's start edge.
		if ( dir === 'rtl' || widthDiff >= 0 ) {
			setScrollLeft( maxScrollLeft );
		} else {
			setScrollLeft( maxScrollLeft + widthDiff );
		}
	} else {
		// The selected tab is not the left-most or right-most, it's somewhere in the middle
		tabPosition = $selectedTab.position();
		containerPosition = $tabContainer.position();
		// Position of the left edge of $selectedTab relative to the left edge of $tabContainer
		left = tabPosition.left - containerPosition.left;
		// Because the calculations above use the existing .scrollLeft from the browser,
		// we should not use setScrollLeft() here. Instead, we rely on the fact that scrollLeft
		// increases to the left in the 'default' and 'negative' modes, and to the right in
		// the 'reverse' mode, so we can add/subtract a delta to/from scrollLeft accordingly.
		if ( widthDiff >= 0 ) {
			// The tab fits, center it
			increaseScrollLeft = left - widthDiff / 2;
		} else if ( dir === 'ltr' ) {
			// The tab doesn't fit (LTR), align its left edge with the container's left edge
			increaseScrollLeft = left;
		} else {
			// The tab doesn't fit (RTL), align its right edge with the container's right edge
			increaseScrollLeft = left - widthDiff;
		}
		tabContainer.scrollLeft += increaseScrollLeft *
			( testScrollLeftStyle() === 'reverse' ? -1 : 1 );
	}
}

module.exports = {
	initTabsScrollPosition: initTabsScrollPosition
};
