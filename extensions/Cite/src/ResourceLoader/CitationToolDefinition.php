<?php

namespace Cite\ResourceLoader;

use MediaWiki\ResourceLoader as RL;

/**
 * Callback to deliver cite-tool-definition.json and related messages.
 *
 * Temporary hack since 2015 for T93800.
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */
class CitationToolDefinition {
	/**
	 * @param RL\Context $context
	 * @return string
	 */
	public static function makeScript( RL\Context $context ) {
		$citationDefinition = json_decode(
			$context->msg( 'cite-tool-definition.json' )
				->inContentLanguage()
				->plain()
		);

		if ( $citationDefinition === null ) {
			$citationDefinition = json_decode(
				$context->msg( 'visualeditor-cite-tool-definition.json' )
					->inContentLanguage()
					->plain()
			);
		}

		$citationTools = [];
		if ( is_array( $citationDefinition ) ) {
			foreach ( $citationDefinition as $tool ) {
				if ( !isset( $tool->title ) ) {
					$tool->title = $context->msg( 'visualeditor-cite-tool-name-' . $tool->name )
						->text();
				}
				$citationTools[] = $tool;
			}
		}

		// TODO: When this custom module is converted to adopt packageFiles, this data
		// can be exported via a callback as a virtual "tools.json" file. Then the JS
		// in MWReference.init.js can do `ve.ui.mwCitationTools = require( "./tools.json" )`

		// Limit and expose
		$limit = 5;
		$citationTools = array_slice( $citationTools, 0, $limit );
		return 've.ui.mwCitationTools = ' . $context->encodeJson( $citationTools ) . ';';
	}
}
