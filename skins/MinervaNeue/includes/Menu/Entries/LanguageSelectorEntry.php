<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace MediaWiki\Minerva\Menu\Entries;

use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Title\Title;

/**
 * Model for a menu entry that represents a language selector for current title
 */
class LanguageSelectorEntry implements IMenuEntry {

	/**
	 * @param Title $title Current Title
	 * @param bool $doesPageHaveLanguages Whether the page is also available in other
	 * languages or variants
	 * @param MessageLocalizer $messageLocalizer Used for translation texts
	 * @param bool $isButton
	 * @param string $label Menu entry label and title
	 */
	public function __construct(
		private readonly Title $title,
		private readonly bool $doesPageHaveLanguages,
		private readonly MessageLocalizer $messageLocalizer,
		$isButton = false,
		private readonly string $label = 'mobile-frontend-language-article-heading',
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getName(): string {
		return 'language-selector';
	}

	/**
	 * @inheritDoc
	 */
	public function getCSSClasses(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function getComponents(): array {
		$switcherLink = false;
		$switcherClasses = 'language-selector';

		if ( $this->doesPageHaveLanguages ) {
			$switcherLink = '#p-lang';
		} else {
			$switcherClasses .= ' disabled';
		}
		$msg = $this->messageLocalizer->msg( $this->label );

		return [
			[
				'tag-name' => 'a',
				'isButton' => true,
				'classes' => $switcherClasses,
				'label' => $msg,
				'data-icon' => [ 'icon' => 'language' ],
				'array-attributes' => [
					[
						'key' => 'href',
						'value' => $switcherLink,
					],
					[
						'key' => 'data-mw',
						'value' => 'interface',
					],
					[
						'key' => 'data-event-name',
						'value' => 'menu.languages',
					],
					[
						'key' => 'title',
						'value' => $msg,
					],
				],
			],
		];
	}
}
