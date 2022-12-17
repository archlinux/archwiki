<?php

/**
 * Processors read associated arrays and register
 * whatever is required
 *
 * @since 1.25
 */
interface Processor {

	/**
	 * Main entry point, processes the information
	 * provided.
	 * Callers should call "callback" after calling
	 * this function.
	 *
	 * @param string $path Absolute path of JSON file
	 * @param array $info
	 * @param int $version manifest_version for info
	 */
	public function extractInfo( $path, array $info, $version );

	/**
	 * @return array With following keys:
	 *     'globals' - variables to be set to $GLOBALS
	 *     'defines' - constants to define
	 *     'callbacks' - functions to be executed by the registry
	 *     'credits' - metadata to be stored by registry
	 *     'attributes' - registration info which isn't a global variable
	 */
	public function getExtractedInfo();

	/**
	 * Get the requirements for the provided info
	 *
	 * @since 1.26
	 * @param array $info
	 * @param bool $includeDev
	 * @return array Where keys are the name to have a constraint on,
	 * 		like 'MediaWiki'. Values are a constraint string like "1.26.1".
	 */
	public function getRequirements( array $info, $includeDev );

	/**
	 * Get the path for additional autoloaders, e.g. the one of Composer.
	 *
	 * @deprecated since 1.39, use getExtractedAutoloadInfo instead
	 *
	 * @param string $dir
	 * @param array $info
	 * @return array Containing the paths for autoloader file(s).
	 * @since 1.27
	 */
	public function getExtraAutoloaderPaths( $dir, array $info );

	/**
	 * Returns the extracted autoload info.
	 * The autoload info is returned as an associative array with three keys:
	 * - files: a list of files to load, for use with Autoloader::loadFile()
	 * - classes: a map of class names to files, for use with Autoloader::registerClass()
	 * - namespaces: a map of namespace names to directories, for use
	 *   with Autoloader::registerNamespace()
	 *
	 * @since 1.39
	 *
	 * @param bool $includeDev
	 *
	 * @return array[] The autoload info.
	 */
	public function getExtractedAutoloadInfo( bool $includeDev = false ): array;
}
