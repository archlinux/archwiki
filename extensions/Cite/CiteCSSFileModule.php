<?php
/**
 * ResourceLoaderFileModule for adding the content language Cite CSS
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2016 Cite VisualEditor Team and others; see AUTHORS.txt
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

class CiteCSSFileModule extends ResourceLoaderFileModule {
	public function __construct(
		$options = array(),
		$localBasePath = null,
		$remoteBasePath = null
	) {
		global $wgContLang;

		parent::__construct( $options, $localBasePath, $remoteBasePath );

		// Get the content language code, and all the fallbacks. The first that
		// has a ext.cite.style.<lang code>.css file present will be used.
		$langCodes = array_merge( array( $wgContLang->getCode() ),
			$wgContLang->getFallbackLanguages() );
		foreach ( $langCodes as $lang ) {
			$langStyleFile = 'ext.cite.style.' . $lang . '.css';
			$localPath = $this->getLocalPath( $langStyleFile );
			if ( file_exists( $localPath ) ) {
				$this->styles[] = $langStyleFile;
				break;
			}
		}
	}
}
