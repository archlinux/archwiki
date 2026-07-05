ve.test.utils.EditCheck = {};

ve.test.utils.EditCheck.dummyController = { taggedFragments: {} };

// Edit check environment extends MW environment:
ve.test.utils.newEditCheckEnvironment = function ( env = {} ) {
	return ve.test.utils.newMwEnvironment( ve.extendObject( {}, env, {
		beforeEach() {
			this.originalConfig = mw.editcheck.config;
			mw.editcheck.config = {};
			if ( env.beforeEach ) {
				env.beforeEach.call( this );
			}
		},
		afterEach() {
			mw.editcheck.config = this.originalConfig;
			if ( env.afterEach ) {
				env.afterEach.call( this );
			}
		}
	} ) );
};
