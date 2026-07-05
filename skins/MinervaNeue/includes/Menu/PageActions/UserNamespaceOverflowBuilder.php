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
 *
 * @file
 */

namespace MediaWiki\Minerva\Menu\PageActions;

use MediaWiki\Context\IContextSource;
use MediaWiki\Minerva\LanguagesHelper;
use MediaWiki\Minerva\Menu\Entries\LanguageSelectorEntry;
use MediaWiki\Minerva\Menu\Group;
use MediaWiki\Minerva\Permissions\IMinervaPagePermissions;
use MediaWiki\Title\Title;

class UserNamespaceOverflowBuilder extends DefaultOverflowBuilder {

	public function __construct(
		Title $title,
		private readonly IContextSource $context,
		IMinervaPagePermissions $permissions,
		private readonly LanguagesHelper $languagesHelper,
	) {
		parent::__construct( $title, $context, $permissions );
	}

	/**
	 * @inheritDoc
	 */
	public function getGroup( array $toolbox, array $actions, bool $isBookmarkEnabled = false ): Group {
		$group = parent::getGroup( $toolbox, $actions, $isBookmarkEnabled );

		if ( $this->isAllowed( IMinervaPagePermissions::SWITCH_LANGUAGE ) ) {
			$group->prependEntry( new LanguageSelectorEntry(
				$this->getTitle(),
				$this->languagesHelper->doesTitleHasLanguagesOrVariants(
					$this->context->getOutput(),
					$this->getTitle()
				),
				$this->getMessageLocalizer(),
				false,
				'minerva-page-actions-language-switcher'
			) );
		}

		return $group;
	}
}
