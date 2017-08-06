<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

class ResourceLoaderGeSHiVisualEditorModule extends ResourceLoaderFileModule {

	protected $targets = array( 'desktop', 'mobile' );

	/**
	 * @param ResourceLoaderContext $context
	 * @return string JavaScript code
	 */
	public function getScript( ResourceLoaderContext $context ) {
		$scripts = parent::getScript( $context );

		return $scripts . Xml::encodeJsCall(
			've.dm.MWSyntaxHighlightNode.static.addPygmentsLanguages', array(
				$this->getPygmentsLanguages()
			),
			ResourceLoader::inDebugMode()
		) . Xml::encodeJsCall(
			've.dm.MWSyntaxHighlightNode.static.addGeshiToPygmentsMap', array(
				GeSHi::$compatibleLexers
			),
			ResourceLoader::inDebugMode()
		) . Xml::encodeJsCall(
			've.dm.MWSyntaxHighlightNode.static.addPygmentsToAceMap', array(
				SyntaxHighlightAce::getPygmentsToAceMap()
			),
			ResourceLoader::inDebugMode()
		);
	}

	/**
	 * Don't break debug mode by only showing file URLs
	 */
	public function getScriptURLsForDebug( ResourceLoaderContext $context ) {
		return ResourceLoaderModule::getScriptURLsForDebug( $context );
	}

	/**
	 * Get a full list of available langauges
	 * @return array
	 */
	private function getPygmentsLanguages() {
		$lexers = require __DIR__ . '/SyntaxHighlight_GeSHi.lexers.php';
		return $lexers;
	}

	public function enableModuleContentVersion() {
		return true;
	}
}
