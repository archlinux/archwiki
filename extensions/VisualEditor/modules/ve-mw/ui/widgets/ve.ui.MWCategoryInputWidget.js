/*!
 * VisualEditor UserInterface MWCategoryInputWidget class.
 *
 * @copyright See AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/**
 * Creates an ve.ui.MWCategoryInputWidget object.
 *
 * @class
 * @extends OO.ui.TextInputWidget
 * @mixes OO.ui.mixin.LookupElement
 *
 * @constructor
 * @param {ve.ui.MWCategoryWidget} categoryWidget
 * @param {Object} [config] Configuration options
 * @param {jQuery} [config.$overlay] Overlay to render dropdowns in
 * @param {mw.Api} [config.api] API object to use, uses Target#getContentApi if not specified
 */
ve.ui.MWCategoryInputWidget = function VeUiMWCategoryInputWidget( categoryWidget, config ) {
	// Config initialization
	config = ve.extendObject( {
		placeholder: ve.msg( 'visualeditor-dialog-meta-categories-input-placeholder' )
	}, config );

	// Parent constructor
	ve.ui.MWCategoryInputWidget.super.call( this, config );

	// Mixin constructors
	OO.ui.mixin.LookupElement.call( this, config );

	// Properties
	this.categoryWidget = categoryWidget;
	this.api = config.api || ve.init.target.getContentApi();

	this.$input.attr( 'aria-label', ve.msg( 'visualeditor-dialog-meta-categories-input-placeholder' ) );

	// Initialization
	this.$element.addClass( 've-ui-mwCategoryInputWidget' );
	this.lookupMenu.$element.addClass( 've-ui-mwCategoryInputWidget-menu' );
	this.lookupMenu.$element.attr( 'aria-label', ve.msg( 'visualeditor-dialog-meta-categories-data-label' ) );
};

/* Inheritance */

OO.inheritClass( ve.ui.MWCategoryInputWidget, OO.ui.TextInputWidget );

OO.mixinClass( ve.ui.MWCategoryInputWidget, OO.ui.mixin.LookupElement );

/* Events */

/**
 * A category was chosen
 *
 * @event ve.ui.MWCategoryInputWidget#choose
 * @param {OO.ui.MenuOptionWidget} item Chosen item
 */

/* Methods */

/**
 * @inheritdoc
 */
ve.ui.MWCategoryInputWidget.prototype.getLookupRequest = function () {
	let title = mw.Title.newFromText( this.value );
	if ( title && title.getNamespaceId() === mw.config.get( 'wgNamespaceIds' ).category ) {
		title = title.getMainText();
	} else {
		title = this.value;
	}
	return this.api.get( {
		action: 'query',
		generator: 'allcategories',
		gacmin: 1,
		gacprefix: title,
		prop: 'categoryinfo',
		redirects: ''
	} );
};

/**
 * @inheritdoc
 */
ve.ui.MWCategoryInputWidget.prototype.getLookupCacheDataFromResponse = function ( data ) {
	const result = [],
		linkCacheUpdate = {},
		query = data.query || {};

	( query.pages || [] ).forEach( ( categoryPage ) => {
		result.push( mw.Title.newFromText( categoryPage.title ).getMainText() );
		linkCacheUpdate[ categoryPage.title ] = {
			missing: Object.prototype.hasOwnProperty.call( categoryPage, 'missing' ),
			hidden: (
				categoryPage.categoryinfo &&
				Object.prototype.hasOwnProperty.call( categoryPage.categoryinfo, 'missing' )
			)
		};
	} );

	( query.redirects || [] ).forEach( ( redirect ) => {
		if ( !Object.prototype.hasOwnProperty.call( linkCacheUpdate, redirect.to ) ) {
			linkCacheUpdate[ redirect.to ] = ve.init.platform.linkCache.getCached( redirect.to ) ||
				{ missing: false, redirectFrom: [ redirect.from ] };
		}
		if (
			linkCacheUpdate[ redirect.to ].redirectFrom &&
			!linkCacheUpdate[ redirect.to ].redirectFrom.includes( redirect.from )
		) {
			linkCacheUpdate[ redirect.to ].redirectFrom.push( redirect.from );
		} else {
			linkCacheUpdate[ redirect.to ].redirectFrom = [ redirect.from ];
		}
	} );

	ve.init.platform.linkCache.set( linkCacheUpdate );

	return result;
};

/**
 * @inheritdoc
 */
ve.ui.MWCategoryInputWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
	const itemWidgets = [],
		existingCategoryItems = [],
		matchingCategoryItems = [],
		hiddenCategoryItems = [],
		newCategoryItems = [],
		existingCategories = this.categoryWidget.getCategories(),
		linkCacheUpdate = {};

	let canonicalQueryValue = mw.Title.newFromText( this.value ),
		prefixedCanonicalQueryValue = mw.Title.newFromText(
			this.value,
			mw.config.get( 'wgNamespaceIds' ).category
		);

	prefixedCanonicalQueryValue = prefixedCanonicalQueryValue && prefixedCanonicalQueryValue.getPrefixedText();

	// Invalid titles end up with canonicalQueryValue being null.
	if ( canonicalQueryValue ) {
		canonicalQueryValue = canonicalQueryValue.getMainText();
	}

	let exactMatch = false;
	data.forEach( ( suggestedCategory ) => {
		const suggestedCategoryTitle = mw.Title.newFromText(
				suggestedCategory,
				mw.config.get( 'wgNamespaceIds' ).category
			).getPrefixedText(),
			suggestedCacheEntry = ve.init.platform.linkCache.getCached( suggestedCategoryTitle );
		if ( canonicalQueryValue === suggestedCategory ) {
			exactMatch = true;
		}
		if ( !suggestedCacheEntry ) {
			linkCacheUpdate[ suggestedCategoryTitle ] = { missing: false };
		}
		if (
			!existingCategories.includes( suggestedCategory )
		) {
			if ( suggestedCacheEntry && suggestedCacheEntry.hidden ) {
				hiddenCategoryItems.push( suggestedCategory );
			} else {
				matchingCategoryItems.push( suggestedCategory );
			}
		}
	} );

	// Existing categories
	existingCategories.forEach( ( existingCategory, i ) => {
		if ( existingCategory === canonicalQueryValue ) {
			exactMatch = true;
		}
		if ( i < existingCategories.length - 1 && existingCategory.lastIndexOf( canonicalQueryValue, 0 ) === 0 ) {
			// Verify that item starts with category.value
			existingCategoryItems.push( existingCategory );
		}
	} );

	// New category
	if ( !exactMatch && canonicalQueryValue ) {
		newCategoryItems.push( canonicalQueryValue );
		linkCacheUpdate[ prefixedCanonicalQueryValue ] = { missing: true };
	}

	ve.init.platform.linkCache.set( linkCacheUpdate );

	// Add sections for non-empty groups. Each section consists of an id, a label and items
	[
		{
			id: 'newCategory',
			label: ve.msg( 'visualeditor-dialog-meta-categories-input-newcategorylabel' ),
			items: newCategoryItems
		},
		{
			id: 'inArticle',
			label: ve.msg( 'visualeditor-dialog-meta-categories-input-movecategorylabel' ),
			items: existingCategoryItems
		},
		{
			id: 'matchingCategories',
			label: ve.msg( 'visualeditor-dialog-meta-categories-input-matchingcategorieslabel' ),
			items: matchingCategoryItems
		},
		{
			id: 'hiddenCategories',
			label: ve.msg( 'visualeditor-dialog-meta-categories-input-hiddencategorieslabel' ),
			items: hiddenCategoryItems
		}
	].forEach( ( sectionData ) => {
		if ( sectionData.items.length ) {
			itemWidgets.push( new OO.ui.MenuSectionOptionWidget( {
				data: sectionData.id,
				label: sectionData.label
			} ) );
			sectionData.items.forEach( ( categoryItem ) => {
				itemWidgets.push( this.getCategoryWidgetFromName( categoryItem ) );
			} );
		}
	} );

	return itemWidgets;
};

/**
 * @inheritdoc
 * @fires ve.ui.MWCategoryInputWidget#choose
 */
ve.ui.MWCategoryInputWidget.prototype.onLookupMenuChoose = function ( item ) {
	this.emit( 'choose', item );

	// Reset input
	this.setValue( '' );
};

/**
 * Take a category name and turn it into a menu item widget, following redirects.
 *
 * @param {string} name Category name
 * @return {OO.ui.MenuOptionWidget} Menu item widget to be shown
 */
ve.ui.MWCategoryInputWidget.prototype.getCategoryWidgetFromName = function ( name ) {
	const cachedData = ve.init.platform.linkCache.getCached( mw.Title.newFromText(
		name,
		mw.config.get( 'wgNamespaceIds' ).category
	).getPrefixedText() );
	let optionWidget, labelText;
	if ( cachedData && cachedData.redirectFrom ) {
		labelText = mw.Title.newFromText( cachedData.redirectFrom[ 0 ] ).getMainText();
		optionWidget = new OO.ui.MenuOptionWidget( {
			data: name,
			autoFitLabel: false,
			label: $( '<span>' )
				.text( labelText )
				.append(
					$( '<br>' ),
					$( document.createTextNode( '↳ ' ) ),
					$( '<span>' ).text( mw.Title.newFromText( name ).getMainText() )
				)
		} );
	} else {
		labelText = name;
		optionWidget = new OO.ui.MenuOptionWidget( {
			data: name,
			label: name
		} );
	}
	optionWidget.$element.attr( 'title', labelText );
	return optionWidget;
};
