<?php

/**
 * Copyright 2014
 *
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
 *
 * @file
 */

use MediaWiki\Revision\SlotRenderingProvider;

class GadgetDefinitionContentHandler extends JsonContentHandler {
	public function __construct() {
		parent::__construct( 'GadgetDefinition' );
	}

	/**
	 * @param Title $title
	 * @return bool
	 */
	public function canBeUsedOn( Title $title ) {
		return $title->inNamespace( NS_GADGET_DEFINITION );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return GadgetDefinitionContent::class;
	}

	public function makeEmptyContent() {
		$class = $this->getContentClass();
		return new $class( FormatJson::encode( $this->getDefaultMetadata(), "\t" ) );
	}

	public function getDefaultMetadata() {
		return [
			'settings' => [
				'rights' => [],
				'default' => false,
				'hidden' => false,
				'skins' => [],
				'category' => ''
			],
			'module' => [
				'scripts' => [],
				'styles' => [],
				'peers' => [],
				'dependencies' => [],
				'messages' => [],
				'type' => '',
			],
		];
	}

	/**
	 * @param Title $title The title of the page to supply the updates for.
	 * @param string $role The role (slot) in which the content is being used.
	 * @return DeferrableUpdate[] A list of DeferrableUpdate objects for putting information
	 *        about this content object somewhere.
	 */
	public function getDeletionUpdates( Title $title, $role ) {
		return array_merge(
			parent::getDeletionUpdates( $title, $role ),
			[ new GadgetDefinitionDeletionUpdate( $title ) ]
		);
	}

	/**
	 * @param Title $title The title of the page to supply the updates for.
	 * @param Content $content The content to generate data updates for.
	 * @param string $role The role (slot) in which the content is being used.
	 * @param SlotRenderingProvider $slotOutput A provider that can be used to gain access to
	 *        a ParserOutput of $content by calling $slotOutput->getSlotParserOutput( $role, false ).
	 * @return DeferrableUpdate[] A list of DeferrableUpdate objects for putting information
	 *        about this content object somewhere.
	 */
	public function getSecondaryDataUpdates(
		Title $title,
		Content $content,
		$role,
		SlotRenderingProvider $slotOutput
	) {
		return array_merge(
			parent::getSecondaryDataUpdates( $title, $content, $role, $slotOutput ),
			[ new GadgetDefinitionSecondaryDataUpdate( $title ) ]
		);
	}
}
