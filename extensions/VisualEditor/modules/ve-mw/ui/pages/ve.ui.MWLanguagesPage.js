/*!
 * VisualEditor user interface MWLanguagesPage class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * MediaWiki meta dialog Languages page.
 *
 * @class
 * @extends OO.ui.PageLayout
 *
 * @constructor
 * @param {string} name Unique symbolic name of page
 * @param {Object} [config] Configuration options
 * @param {jQuery} [config.$overlay] Overlay to render dropdowns in
 */
ve.ui.MWLanguagesPage = function VeUiMWLanguagesPage() {
	// Parent constructor
	ve.ui.MWLanguagesPage.super.apply( this, arguments );

	// Properties
	this.languagesFieldset = new OO.ui.FieldsetLayout( {
		label: ve.msg( 'visualeditor-dialog-meta-languages-label' ),
		icon: 'textLanguage'
	} );

	// Initialization
	this.languagesFieldset.$element.append(
		$( '<span>' )
			.text( ve.msg( 'visualeditor-dialog-meta-languages-readonlynote' ) )
	);
	this.$element.append( this.languagesFieldset.$element );

	this.getAllLanguageItems().then( this.onLoadLanguageData.bind( this ) );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWLanguagesPage, OO.ui.PageLayout );

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWLanguagesPage.prototype.setupOutlineItem = function () {
	this.outlineItem
		.setIcon( 'textLanguage' )
		.setLabel( ve.msg( 'visualeditor-dialog-meta-languages-section' ) );
};

ve.ui.MWLanguagesPage.prototype.onLoadLanguageData = function ( languages ) {
	const $languagesTable = $( '<table>' ),
		languageslength = languages.length;

	$languagesTable
		.addClass( 've-ui-mwLanguagesPage-languages-table' )
		.append( $( '<tr>' )
			.append(
				$( '<th>' )
					.text( ve.msg( 'visualeditor-dialog-meta-languages-code-label' ) )
			)
			.append(
				$( '<th>' )
					.text( ve.msg( 'visualeditor-dialog-meta-languages-name-label' ) )
			)
			.append(
				$( '<th>' )
					.text( ve.msg( 'visualeditor-dialog-meta-languages-link-label' ) )
			)
		);

	for ( let i = 0; i < languageslength; i++ ) {
		languages[ i ].safelang = languages[ i ].lang;
		languages[ i ].dir = 'auto';
		if ( $.uls ) {
			// site codes don't always represent official language codes
			// using real language code instead of a dummy ('redirect' in ULS' terminology)
			languages[ i ].safelang = $.uls.data.isRedirect( languages[ i ].lang ) || languages[ i ].lang;
			languages[ i ].dir = ve.init.platform.getLanguageDirection( languages[ i ].safelang );
		}
		$languagesTable.append(
			$( '<tr>' ).append(
				$( '<td>' ).text( languages[ i ].lang ),
				$( '<td>' ).text( languages[ i ].langname ).add( $( '<td>' ).text( languages[ i ].title ) )
					.attr( {
						lang: languages[ i ].safelang,
						dir: languages[ i ].dir
					} )
			)
		);
	}

	this.languagesFieldset.$element.append( $languagesTable );
};

/**
 * Handle language items being loaded.
 *
 * @param {Object} response API response
 * @return {Array}
 */
ve.ui.MWLanguagesPage.prototype.onAllLanguageItemsSuccess = function ( response ) {
	const languages = [],
		langlinks = OO.getProp( response, 'query', 'pages', 0, 'langlinks' );
	if ( langlinks ) {
		for ( let i = 0, iLen = langlinks.length; i < iLen; i++ ) {
			languages.push( {
				lang: langlinks[ i ].lang,
				langname: langlinks[ i ].autonym,
				title: langlinks[ i ].title,
				metaItem: null
			} );
		}
	}
	return languages;
};

/**
 * Get array of language items from meta list
 *
 * @return {jQuery.Promise}
 */
ve.ui.MWLanguagesPage.prototype.getAllLanguageItems = function () {
	// TODO: Detect paging token if results exceed limit
	return ve.init.target.getContentApi().get( {
		action: 'query',
		prop: 'langlinks',
		llprop: 'autonym',
		lllimit: 500,
		titles: ve.init.target.getPageName()
	} ).then(
		this.onAllLanguageItemsSuccess.bind( this ),
		this.onAllLanguageItemsError.bind( this )
	);
};

/**
 * Handle language items failing to be loaded.
 *
 * TODO: This error function should probably not be empty.
 */
ve.ui.MWLanguagesPage.prototype.onAllLanguageItemsError = function () {};

ve.ui.MWLanguagesPage.prototype.getFieldsets = function () {
	return [
		this.languagesFieldset
	];
};
