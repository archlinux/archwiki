( function () {
	let userPageWidget = null,
		userPagePositionWidget = null,
		userPageTextWidget = null,
		talkPageWidget,
		talkPagePositionWidget,
		talkPageTextWidget,
		dropdownWidget = null,
		otherReasonWidget = null,
		isUserPageChecked = false;

	function updateNoticeOptions() {
		const isTalkPageChecked = talkPageWidget.isSelected();

		// Check for element existence due to T390774
		if ( userPagePositionWidget ) {
			isUserPageChecked = userPageWidget.isSelected();
			userPagePositionWidget.setDisabled( !isUserPageChecked );
		}
		if ( userPageTextWidget ) {
			userPageTextWidget.setDisabled( !isUserPageChecked );
		}

		talkPagePositionWidget.setDisabled( !isTalkPageChecked );
		talkPageTextWidget.setDisabled( !isTalkPageChecked );
	}

	if ( $( '#mw-htmlform-options' ).length > 0 ) {
		// Check for element existence due to T390774
		if ( $( '#mw-input-wpUserPageNotice' ).length > 0 ) {
			userPageWidget = OO.ui.infuse( $( '#mw-input-wpUserPageNotice' ) );
		}
		if ( $( '#mw-input-wpUserPageNoticePosition' ).length > 0 ) {
			userPagePositionWidget = OO.ui.infuse( $( '#mw-input-wpUserPageNoticePosition' ) );
		}
		if ( $( '#mw-input-wpUserPageNoticeText' ).length > 0 ) {
			userPageTextWidget = OO.ui.infuse( $( '#mw-input-wpUserPageNoticeText' ) );
		}
		talkPageWidget = OO.ui.infuse( $( '#mw-input-wpTalkPageNotice' ) );
		talkPagePositionWidget = OO.ui.infuse( $( '#mw-input-wpTalkPageNoticePosition' ) );
		talkPageTextWidget = OO.ui.infuse( $( '#mw-input-wpTalkPageNoticeText' ) );

		if ( userPageWidget ) {
			userPageWidget.on( 'change', updateNoticeOptions );
		}
		talkPageWidget.on( 'change', updateNoticeOptions );

		updateNoticeOptions();
	}

	/**
	 * Update the 'required' attribute on the free-text field when the dropdown is changed.
	 * If the value of the dropdown is 'other', the free-text field is required. Otherwise,
	 * it is not required.
	 */
	function updateRequiredAttributeOnOtherField() {
		const $otherReasonInputElement = $( 'input', otherReasonWidget.$element );
		const $requiredIndicator = $( '.oo-ui-indicator-required', otherReasonWidget.$element );
		if ( dropdownWidget.getValue() === 'other' ) {
			// Set the required property for native browser validation and
			// show the "required" OOUI indicator.
			$otherReasonInputElement.attr( 'required', 'required' );
			$requiredIndicator.show();
		} else {
			// Remove the required property and hide the "required" OOUI indicator.
			$otherReasonInputElement.removeAttr( 'required' );
			$requiredIndicator.hide();
		}
	}

	const $dropdownAndInput = $( '#mw-input-wpReason' );
	if ( $dropdownAndInput.length > 0 ) {
		const dropdownAndInputWidget = OO.ui.infuse( $dropdownAndInput );
		dropdownWidget = dropdownAndInputWidget.dropdowninput;
		otherReasonWidget = dropdownAndInputWidget.textinput;

		dropdownWidget.on( 'change', updateRequiredAttributeOnOtherField );

		updateRequiredAttributeOnOtherField();
	}
}() );
