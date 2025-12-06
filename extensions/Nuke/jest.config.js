// Adapted from CommunityRequests (https://w.wiki/EwsM)
module.exports = {
	// Automatically clear mock calls and instances between every test
	clearMocks: true,
	// Indicates whether the coverage information should be collected while executing the test
	collectCoverage: true,
	// An array of glob patterns indicating a set of files for
	//  which coverage information should be collected
	collectCoverageFrom: [
		'modules/**/components/*.{js,vue}'
	],
	// An array of regexp pattern strings used to skip coverage collection
	coveragePathIgnorePatterns: [
		'/node_modules/'
	],
	// Indicates which provider should be used to instrument code for coverage
	coverageProvider: 'v8',
	// An array of file extensions your modules use
	moduleFileExtensions: [
		'js',
		'json',
		'vue'
	],
	// A map from regular expressions to module names or to arrays of module names
	// that allow to stub out resources with a single module
	moduleNameMapper: {
		'icons.json$': '@wikimedia/codex-icons',
		'codex.js$': '@wikimedia/codex'
	},
	// The paths to modules that run some code to configure or
	// set up the testing environment before each test
	setupFiles: [
		'./tests/jest/jest.setup.js'
	],
	// The test environment that will be used for testing
	testEnvironment: 'jsdom',
	// Options that will be passed to the testEnvironment
	testEnvironmentOptions: {
		customExportConditions: [ 'node', 'node-addons' ]
	},
	// Ignore these directories when locating tests to run.
	testPathIgnorePatterns: [
		'<rootDir>/node_modules/'
	],
	// A map from regular expressions to paths to transformers
	transform: {
		'^.+\\.vue$': '<rootDir>/node_modules/@vue/vue3-jest'
	}
};
