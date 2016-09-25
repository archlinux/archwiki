<?php
/**
 * Resource loader module providing extra data from the server to Cite.
 *
 * Temporary hack for T93800
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2016 Cite VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see MIT-LICENSE.txt
 */

class CiteDataModule extends ResourceLoaderModule {

	/* Protected Members */

	protected $origin = self::ORIGIN_USER_SITEWIDE;
	protected $targets = array( 'desktop', 'mobile' );

	/* Methods */

	public function getScript( ResourceLoaderContext $context ) {
		$citationDefinition = json_decode(
			$context->msg( 'visualeditor-cite-tool-definition.json' )
				->inContentLanguage()
				->plain()
		);

		$citationTools = array();
		if ( is_array( $citationDefinition ) ) {
			foreach ( $citationDefinition as $tool ) {
				if ( !isset( $tool->title ) ) {
					$tool->title = $context->msg( 'visualeditor-cite-tool-name-' . $tool->name )
						->text();
				}
				$citationTools[] = $tool;
			}
		}

		return
			've.init.platform.addMessages(' . FormatJson::encode(
				array(
					'visualeditor-cite-tool-definition.json' => json_encode( $citationTools )
				),
				ResourceLoader::inDebugMode()
			) . ');';
	}

	public function getDependencies( ResourceLoaderContext $context = null ) {
		return array(
			'ext.visualEditor.base',
			'ext.visualEditor.mediawiki',
		);
	}

	public function getDefinitionSummary( ResourceLoaderContext $context ) {
		$summary = parent::getDefinitionSummary( $context );
		$summary[] = array(
			'script' => $this->getScript( $context ),
		);
		return $summary;
	}
}
