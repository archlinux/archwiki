<template>
	<details class="reading-survey" :open="showSurvey" @toggle="showSurvey = $event.target.open">
		<summary class="reading-survey__title">
			<strong>{{ $i18n( 'vector-readability-survey-title' ).text() }}</strong>
		</summary>
		<p v-i18n-html:vector-readability-survey-description></p>
		<div class="reading-survey__options">
			<label>Font size:</label>
			<input v-model="fontSize" type="range" min="12" max="24">
			<span> {{ fontSize }} </span>

			<label>Line height:</label>
			<input v-model="lineHeight" type="range" min="16" max="42" step="1">
			<span> {{ lineHeight }} </span>

			<label>Paragraph spacing:</label>
			<input v-model="verticalMargins" type="range" min="6" max="36" step="1">
			<span> {{ verticalMargins }} </span>
		</div>

		<p @click="optOut" v-i18n-html:vector-readability-survey-optout></p>

		<div class="reading-survey__buttons">
			<button class="cdx-button" @click="resetSavedCSSVals">
				{{ $i18n( 'vector-readability-survey-reset' ).text() }}
			</button>
			<button class="cdx-button" @click="randomize">
				{{ $i18n( 'vector-readability-survey-randomize' ).text() }}
			</button>
			<a v-bind:href="feedbackUrl"
				class="cdx-button cdx-button--fake-button cdx-button--fake-button--enabled cdx-button--action-progressive cdx-button--weight-primary cdx-button--size-medium cdx-button--framed">{{
					$i18n( 'vector-readability-survey-share' ).text() }}</a>
		</div>

	</details>
</template>

<script>
/* eslint-disable */
// @ts-nocheck
const rootEl = document.querySelector(':root');
const OPT_OUT_HREF = "#close-vector-survey";
const CSS_VAR_MAP = {
	storageKey: 'wm-reading-survey',
	bodyClass: 'wm-reading-survey-enabled',
	bodyTransitionClass: 'wm-reading-survey-transitions',
	fontSize: {
		storageKey: 'fontSize',
		cssProp: 'font-size',
		cssTarget: '.vector-body',
		cssCustomProp: '--reading-survey-font-size'
	},
	lineHeight: {
		storageKey: 'lineHeight',
		cssProp: 'line-height',
		cssTarget: '.vector-body',
		cssCustomProp: '--reading-survey-line-height'
	},
	verticalMargins: {
		storageKey: 'verticalMargins',
		cssProp: 'margin-bottom',
		cssTarget: '.vector-body p',
		cssCustomProp: '--reading-survey-vertical-margins'
	}
}

module.exports = exports = {
	name: "VectorReadingSurvey",
	compatConfig: { MODE: 3 },
	compilerOptions: { whitespace: 'condense' },
	data: function () {
		const dataObj = {};
		dataObj[ 'fontSize' ] = this.getSavedCSSVal( CSS_VAR_MAP.fontSize.storageKey )
			|| this.getDefaultCSSVal( CSS_VAR_MAP.fontSize.cssTarget, CSS_VAR_MAP.fontSize.cssProp );

		dataObj[ 'lineHeight' ] = this.getSavedCSSVal( CSS_VAR_MAP.lineHeight.storageKey )
			|| this.getDefaultCSSVal(CSS_VAR_MAP.lineHeight.cssTarget, CSS_VAR_MAP.lineHeight.cssProp);

		dataObj[ 'verticalMargins' ] = this.getSavedCSSVal(CSS_VAR_MAP.verticalMargins.storageKey )
			|| this.getDefaultCSSVal( CSS_VAR_MAP.verticalMargins.cssTarget, CSS_VAR_MAP.verticalMargins.cssProp );

		const showVal = this.getSavedShowVal();
		dataObj[ 'showSurvey' ] = ( showVal === undefined ) ? true : showVal;
		return dataObj
	},
	computed: {
		feedbackUrl: function () {
			const feedbackUrlParams = new URLSearchParams( [
				[ 'action', 'submit' ],
				[ 'section', 'new' ],
				[ 'preload', 'Reading/Web/Accessibility_for_reading/Community_Prototype_Testing/preload' ],
				[ 'preloadparams[]', this.fontSize ],
				[ 'preloadparams[]', this.lineHeight ],
				[ 'preloadparams[]', this.verticalMargins ],
				[ 'preloadparams[]', window.location.href ],
				[ 'preloadparams[]', `${window.innerWidth} x ${window.innerHeight}` ],
				[ 'preloadtitle', ( mw.user.getName() ) ? 'User: ' + mw.user.getName() : '' ]
			] );
			return "https://www.mediawiki.org/wiki/Reading/Web/Accessibility_for_reading/Community_Prototype_Testing/Feedback?" + feedbackUrlParams.toString();
		}
	},
	methods: {
		getDefaultCSSVal: function (selector, cssProp) {
			const domEl = document.querySelector(selector);
			const domElStyles = window.getComputedStyle(domEl);
			const domElCSSProp = domElStyles.getPropertyValue(cssProp);
			console.log(selector, domElCSSProp);
			return parseInt(domElCSSProp);
		},
		getSavedCSSVal: function (name) {
			const savedVal = mw.storage.getObject(CSS_VAR_MAP.storageKey);
			if (savedVal && savedVal[name]) {
				return parseInt(savedVal[name]);
			}
		},
		getSavedShowVal: function() {
			const savedVal = mw.storage.getObject(CSS_VAR_MAP.storageKey);
			if ( savedVal && ( savedVal['showSurvey'] !== undefined ) ) {
				return savedVal['showSurvey'];
			}
		},
		saveShowVal: function() {
			const storageObject = mw.storage.getObject(CSS_VAR_MAP.storageKey);
			storageObject[ 'showSurvey' ] = this.showSurvey;
			mw.storage.setObject(CSS_VAR_MAP.storageKey, storageObject, 604800);
		},
		setCSSVal: function (name, val) {
			rootEl.style.setProperty(name, val + 'px');
			this.saveCSSVals();
		},
		saveCSSVals: function () {
			const storageObject = {};
			storageObject[ CSS_VAR_MAP.fontSize.storageKey ] = this.fontSize;
			storageObject[ CSS_VAR_MAP.lineHeight.storageKey ] = this.lineHeight;
			storageObject[ CSS_VAR_MAP.verticalMargins.storageKey ] = this.verticalMargins;
			storageObject[ 'showSurvey' ] = this.showSurvey;
			mw.storage.setObject(CSS_VAR_MAP.storageKey, storageObject, 604800);
		},
		resetSavedCSSVals: function () {
			mw.storage.remove( CSS_VAR_MAP.storageKey );
			document.documentElement.classList.remove( CSS_VAR_MAP.bodyClass );
			document.documentElement.classList.remove( CSS_VAR_MAP.bodyTransitionClass );

			this.fontSize = this.getDefaultCSSVal( CSS_VAR_MAP.fontSize.cssTarget, CSS_VAR_MAP.fontSize.cssProp );
			this.lineHeight = this.getDefaultCSSVal( CSS_VAR_MAP.lineHeight.cssTarget, CSS_VAR_MAP.lineHeight.cssProp );
			this.verticalMargins = this.getDefaultCSSVal( CSS_VAR_MAP.verticalMargins.cssTarget, CSS_VAR_MAP.verticalMargins.cssProp );
		},
		randomize: function () {
			this.fontSize = Math.floor( Math.random() * (24 - 12 + 1) + 12 );
			this.lineHeight = Math.floor( Math.random() * (42 - 16 + 1) + 16 );
			this.verticalMargins = Math.floor( Math.random() * (36 - 6 + 1) + 6 );
		},
		optOut: function ( e ) {
			const href = e.target.getAttribute( 'href' );
			if ( href === OPT_OUT_HREF ) {
				e.preventDefault();
				const api = new mw.Api();
				api.saveOption( 'vector-typography-survey', 0 )
				.then( function() {
					mw.storage.remove( CSS_VAR_MAP.storageKey );
					location.reload();
				});
			}
		}
	},
	watch: {
		fontSize: function ( newVal ) { this.setCSSVal( CSS_VAR_MAP.fontSize.cssCustomProp, newVal ) },
		lineHeight: function ( newVal ) { this.setCSSVal( CSS_VAR_MAP.lineHeight.cssCustomProp, newVal ) },
		verticalMargins: function ( newVal ) { this.setCSSVal( CSS_VAR_MAP.verticalMargins.cssCustomProp, newVal ) },
		showSurvey: function() { this.saveShowVal() }
	},
	mounted: function () {
		document.documentElement.classList.add( CSS_VAR_MAP.bodyClass );
		this.setCSSVal( CSS_VAR_MAP.fontSize.cssCustomProp, this.fontSize );
		this.setCSSVal( CSS_VAR_MAP.lineHeight.cssCustomProp, this.lineHeight );
		this.setCSSVal( CSS_VAR_MAP.verticalMargins.cssCustomProp, this.verticalMargins );
	},
	updated: function () {
		document.documentElement.classList.add( CSS_VAR_MAP.bodyClass );
		document.documentElement.classList.add( CSS_VAR_MAP.bodyTransitionClass );
	}
};
</script>

<style>
html.wm-reading-survey-enabled {
	--reading-survey-font-size: 14px;
	--reading-survey-line-height: 22px;
	--reading-survey-vertical-margins: 7px;
}

html.wm-reading-survey-enabled .vector-body {
	font-size: var( --reading-survey-font-size );
	line-height: var( --reading-survey-line-height );
}

html.wm-reading-survey-transitions .vector-body {
	transition: font-size 300ms, line-height 300ms;
}

html.wm-reading-survey-enabled .vector-body p {
	margin-bottom: var( --reading-survey-vertical-margins );
	margin-top: var( --reading-survey-vertical-margins );
}

html.wm-reading-survey-transitions .vector-body p {
	transition: margin 300ms;
}

.reading-survey {
	position: fixed;
	bottom: 0;
	right: 80px;
	width: 350px;

	background-color: #fff;
	border: 1px solid #a2a9b1;
	border-radius: 2px;
	padding: 24px 12px;

	font-size: 14px;
}

.reading-survey p {
	margin: 1em 0;
}

.reading-survey__title {
	font-weight: bold;
	font-size: 16px;
}

.reading-survey__options {
	display: grid;
	grid-template-columns: 1fr 2fr 0.5fr;
	grid-gap: 16px;
	margin: 24px;
}

.reading-survey__buttons {
	display: flex;
	flex-wrap: wrap;
	gap: 12px;
	justify-content: space-around;
}

.reading-survey__buttons .cdx-button {
	flex-grow: 1;
}
</style>
