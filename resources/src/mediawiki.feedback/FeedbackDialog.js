/**
 * FeedbackDialog
 *
 * @class mw.Feedback.Dialog
 * @extends OO.ui.ProcessDialog
 *
 * @constructor
 * @param {Object} config Configuration object
 */
function FeedbackDialog( config ) {
	// Parent constructor
	FeedbackDialog.parent.call( this, config );

	this.status = '';
	this.feedbackPageTitle = null;
	// Initialize
	this.$element.addClass( 'mwFeedback-Dialog' );
}

OO.inheritClass( FeedbackDialog, OO.ui.ProcessDialog );

/* Static properties */
FeedbackDialog.static.name = 'mwFeedbackDialog';
FeedbackDialog.static.title = mw.msg( 'feedback-dialog-title' );
FeedbackDialog.static.size = 'medium';
FeedbackDialog.static.actions = [
	{
		action: 'submit',
		label: mw.msg( 'feedback-submit' ),
		flags: [ 'primary', 'progressive' ]
	},
	{
		action: 'external',
		label: mw.msg( 'feedback-external-bug-report-button' ),
		flags: 'progressive'
	},
	{
		action: 'cancel',
		label: mw.msg( 'feedback-cancel' ),
		flags: 'safe'
	}
];

/**
 * @inheritdoc
 */
FeedbackDialog.prototype.initialize = function () {
	var feedbackSubjectFieldLayout, feedbackMessageFieldLayout,
		feedbackFieldsetLayout, termsOfUseLabel;

	// Parent method
	FeedbackDialog.parent.prototype.initialize.call( this );

	this.feedbackPanel = new OO.ui.PanelLayout( {
		scrollable: false,
		expanded: false,
		padded: true
	} );

	// Feedback form
	this.feedbackMessageLabel = new OO.ui.LabelWidget( {
		classes: [ 'mw-feedbackDialog-welcome-message' ]
	} );
	this.feedbackSubjectInput = new OO.ui.TextInputWidget( {
		indicator: 'required'
	} );
	this.feedbackMessageInput = new OO.ui.MultilineTextInputWidget( {
		autosize: true
	} );
	feedbackSubjectFieldLayout = new OO.ui.FieldLayout( this.feedbackSubjectInput, {
		label: mw.msg( 'feedback-subject' )
	} );
	feedbackMessageFieldLayout = new OO.ui.FieldLayout( this.feedbackMessageInput, {
		label: mw.msg( 'feedback-message' )
	} );
	feedbackFieldsetLayout = new OO.ui.FieldsetLayout( {
		items: [ feedbackSubjectFieldLayout, feedbackMessageFieldLayout ],
		classes: [ 'mw-feedbackDialog-feedback-form' ]
	} );

	// Useragent terms of use
	this.useragentCheckbox = new OO.ui.CheckboxInputWidget();
	this.useragentFieldLayout = new OO.ui.FieldLayout( this.useragentCheckbox, {
		classes: [ 'mw-feedbackDialog-feedback-terms' ],
		align: 'inline'
	} );

	termsOfUseLabel = new OO.ui.LabelWidget( {
		classes: [ 'mw-feedbackDialog-feedback-termsofuse' ],
		label: $( '<p>' ).append( mw.msg( 'feedback-termsofuse' ) )
	} );

	this.feedbackPanel.$element.append(
		this.feedbackMessageLabel.$element,
		feedbackFieldsetLayout.$element,
		this.useragentFieldLayout.$element,
		termsOfUseLabel.$element
	);

	// Events
	this.feedbackSubjectInput.connect( this, { change: 'validateFeedbackForm' } );
	this.feedbackMessageInput.connect( this, { change: 'validateFeedbackForm' } );
	this.feedbackMessageInput.connect( this, { change: 'updateSize' } );
	this.useragentCheckbox.connect( this, { change: 'validateFeedbackForm' } );

	this.$body.append( this.feedbackPanel.$element );
};

/**
 * Validate the feedback form
 */
FeedbackDialog.prototype.validateFeedbackForm = function () {
	var isValid = (
		(
			!this.useragentMandatory ||
			this.useragentCheckbox.isSelected()
		) &&
		this.feedbackSubjectInput.getValue()
	);

	this.actions.setAbilities( { submit: isValid } );
};

/**
 * @inheritdoc
 */
FeedbackDialog.prototype.getBodyHeight = function () {
	return this.feedbackPanel.$element.outerHeight( true );
};

/**
 * @inheritdoc
 */
FeedbackDialog.prototype.getSetupProcess = function ( data ) {
	return FeedbackDialog.parent.prototype.getSetupProcess.call( this, data )
		.next( function () {
			// Get the URL of the target page, we want to use that in links in the intro
			// and in the success dialog
			var dialog = this;
			if ( data.foreignApi ) {
				return data.foreignApi.get( {
					action: 'query',
					prop: 'info',
					inprop: 'url',
					formatversion: 2,
					titles: data.settings.title.getPrefixedText()
				} ).then( function ( response ) {
					dialog.feedbackPageUrl = OO.getProp( response, 'query', 'pages', 0, 'canonicalurl' );
				} );
			} else {
				this.feedbackPageUrl = data.settings.title.getUrl();
			}
		}, this )
		.next( function () {
			var $link,
				settings = data.settings;
			data.contents = data.contents || {};

			// Prefill subject/message
			this.feedbackSubjectInput.setValue( data.contents.subject );
			this.feedbackMessageInput.setValue( data.contents.message );

			this.status = '';
			this.messagePosterPromise = settings.messagePosterPromise;
			this.setBugReportLink( settings.bugsTaskSubmissionLink );
			this.feedbackPageTitle = settings.title;
			this.feedbackPageName = settings.title.getMainText();

			// Useragent checkbox
			if ( settings.useragentCheckbox.show ) {
				this.useragentFieldLayout.setLabel( settings.useragentCheckbox.message );
			}

			this.useragentMandatory = settings.useragentCheckbox.mandatory;
			this.useragentFieldLayout.toggle( settings.useragentCheckbox.show );

			$link = $( '<a>' )
				.attr( 'href', this.feedbackPageUrl )
				.attr( 'target', '_blank' )
				.text( this.feedbackPageName );
			this.feedbackMessageLabel.setLabel(
				mw.message( 'feedback-dialog-intro', $link ).parseDom()
			);

			this.validateFeedbackForm();
		}, this );
};

/**
 * @inheritdoc
 */
FeedbackDialog.prototype.getReadyProcess = function ( data ) {
	return FeedbackDialog.parent.prototype.getReadyProcess.call( this, data )
		.next( function () {
			this.feedbackSubjectInput.focus();
		}, this );
};

/**
 * @inheritdoc
 */
FeedbackDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'cancel' ) {
		return new OO.ui.Process( function () {
			this.close( { action: action } );
		}, this );
	} else if ( action === 'external' ) {
		return new OO.ui.Process( function () {
			// Open in a new window
			window.open( this.getBugReportLink(), '_blank' );
			// Close the dialog
			this.close();
		}, this );
	} else if ( action === 'submit' ) {
		return new OO.ui.Process( function () {
			var fb = this,
				userAgentMessage = ':' +
					'<small>' +
					mw.msg( 'feedback-useragent' ) +
					' ' +
					mw.html.escape( navigator.userAgent ) +
					'</small>\n\n',
				subject = this.feedbackSubjectInput.getValue(),
				message = this.feedbackMessageInput.getValue();

			// Add user agent if checkbox is selected
			if ( this.useragentCheckbox.isSelected() ) {
				message = userAgentMessage + message;
			}

			// Post the message
			return this.messagePosterPromise.then( function ( poster ) {
				return fb.postMessage( poster, subject, message );
			}, function () {
				fb.status = 'error4';
				mw.log.warn( 'Feedback report failed because MessagePoster could not be fetched' );
			} ).then( function () {
				fb.close();
			}, function () {
				return fb.getErrorMessage();
			} );
		}, this );
	}
	// Fallback to parent handler
	return FeedbackDialog.parent.prototype.getActionProcess.call( this, action );
};

/**
 * Returns an error message for the current status.
 *
 * @private
 *
 * @return {OO.ui.Error}
 */
FeedbackDialog.prototype.getErrorMessage = function () {
	if ( this.$statusFromApi ) {
		return new OO.ui.Error( this.$statusFromApi );
	}
	// The following messages can be used here:
	// * feedback-error1
	// * feedback-error4
	return new OO.ui.Error( mw.msg( 'feedback-' + this.status ) );
};

/**
 * Posts the message
 *
 * @private
 *
 * @param {mw.messagePoster.MessagePoster} poster Poster implementation used to leave feedback
 * @param {string} subject Subject of message
 * @param {string} message Body of message
 * @return {jQuery.Promise} Promise representing success of message posting action
 */
FeedbackDialog.prototype.postMessage = function ( poster, subject, message ) {
	var fb = this;

	return poster.post(
		subject,
		message
	).then( function () {
		fb.status = 'submitted';
	}, function ( mainCode, secondaryCode, details ) {
		if ( mainCode === 'api-fail' ) {
			if ( secondaryCode === 'http' ) {
				fb.status = 'error3';
				// ajax request failed
				mw.log.warn( 'Feedback report failed with HTTP error: ' + details.textStatus );
			} else {
				fb.status = 'error2';
				mw.log.warn( 'Feedback report failed with API error: ' + secondaryCode );
			}
			fb.$statusFromApi = ( new mw.Api() ).getErrorMessage( details );
		} else {
			fb.status = 'error1';
		}
	} );
};

/**
 * @inheritdoc
 */
FeedbackDialog.prototype.getTeardownProcess = function ( data ) {
	return FeedbackDialog.parent.prototype.getTeardownProcess.call( this, data )
		.first( function () {
			this.emit( 'submit', this.status, this.feedbackPageName, this.feedbackPageUrl );
			// Cleanup
			this.status = '';
			this.feedbackPageTitle = null;
			this.feedbackSubjectInput.setValue( '' );
			this.feedbackMessageInput.setValue( '' );
			this.useragentCheckbox.setSelected( false );
		}, this );
};

/**
 * Set the bug report link
 *
 * @param {string} link Link to the external bug report form
 */
FeedbackDialog.prototype.setBugReportLink = function ( link ) {
	this.bugReportLink = link;
};

/**
 * Get the bug report link
 *
 * @return {string} Link to the external bug report form
 */
FeedbackDialog.prototype.getBugReportLink = function () {
	return this.bugReportLink;
};

module.exports = FeedbackDialog;
