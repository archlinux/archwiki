interface MwApi {
	saveOption( name: string, value: unknown ): JQuery.Promise<any>;
}

type MwApiConstructor = new( options?: Object ) => MwApi;

interface MwUri {
	query: Record<string, unknown>;
	toString(): string;
}

interface MwEventLog {
	eventInSample( population: Object ): () => boolean;
	inSample( num: Number ): () => boolean;
	logEvent( schema: string, data: Object ): () => void;
}

type UriConstructor = new( uri: string ) => MwUri;

interface MediaWiki {
	eventLog?: MwEventLog,
	util: {
		/**
		 * @param {string} id of portlet
		 */
		showPortlet( id: string ): () => void;
		/**
		 * @param {string} id of portlet
		 */
		hidePortlet( id: string ): () => void;
		/**
		 * @param {string} id of portlet
		 * @return {bool}
		 */
		isPortletVisible( id: string ): () => boolean,
		/**
		 * Return a wrapper function that is debounced for the given duration.
		 *
		 * When it is first called, a timeout is scheduled. If before the timer
		 * is reached the wrapper is called again, it gets rescheduled for the
		 * same duration from now until it stops being called. The original function
		 * is called from the "tail" of such chain, with the last set of arguments.
		 *
		 * @since 1.34
		 * @param {number} delay Time in milliseconds
		 * @param {Function} callback
		 * @return {Function}
		 */
		debounce(delay: number, callback: Function): () => void;
	};
	Api: MwApiConstructor;
	config: {
		get( configKey: string|null, fallback?: any|null ): string;
		set( configKey: string|null, value: any|null ): void;
	},
	loader: {
		/**
		 * Execute a function after one or more modules are ready.
		 * 
		 * @param moduleName 
		 * @param {Function} ready Callback to execute when all dependencies are
		 * ready.
		 * @param {Function} after Callback to execute if one or more dependencies
		 * failed.
		 */
		using( moduleName: string|null, ready?: Function, error?: Function ): JQuery.Promise<any>;
		
		/**
		 * Load a given resourceLoader module.
		 * 
		 * @param moduleName 
		 */
		 load( moduleName: string|null ): () => void;
		/**
		 * Get the loading state of the module. 
		 * On of 'registered', 'loaded', 'loading', 'ready', 'error', or 'missing'.
		 * 
		 * @param moduleName 
		 */
		getState( moduleName: string|null ): string; 
	}, 
	/**
	 * Loads the specified i18n message string. 
	 * Shortcut for `mw.message( key, parameters... ).text()`.
	 * 
	 * @param messageName i18n message name
	 */
	msg( messageName: string|null ): string;

	/**
	 * Get current timestamp
	 */
	now(): number,

	/**
	 * Track an analytic event.
	 *
	 * See https://gerrit.wikimedia.org/g/mediawiki/core/+/d7fe1ff0fe52735b1f41e91879c9617b376e807d/resources/src/mediawiki.base/mediawiki.base.js#375.
	 *
	 * @param topic The topic name
	 * @param [data] The data describing the event
	 */
	track(topic: string, data?: Record<string, unknown>|number|string): void;

	Uri: UriConstructor;
}

declare const mw: MediaWiki;
