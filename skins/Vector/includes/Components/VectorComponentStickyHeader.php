<?php
namespace MediaWiki\Skins\Vector\Components;

use MediaWiki\Skins\Vector\Hooks;
use Message;
use MessageLocalizer;

/**
 * VectorComponentStickyHeader component
 */
class VectorComponentStickyHeader implements VectorComponent {
	private const TALK_ICON = [
		'href' => '#',
		'id' => 'ca-talk-sticky-header',
		'event' => 'talk-sticky-header',
		'icon' => 'wikimedia-speechBubbles',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon'
	];
	private const SUBJECT_ICON = [
		'href' => '#',
		'id' => 'ca-subject-sticky-header',
		'event' => 'subject-sticky-header',
		'icon' => 'wikimedia-article',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon'
	];
	private const HISTORY_ICON = [
		'href' => '#',
		'id' => 'ca-history-sticky-header',
		'event' => 'history-sticky-header',
		'icon' => 'wikimedia-history',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon'
	];
	// Event and icon will be updated depending on watchstar state
	private const WATCHSTAR_ICON = [
		'href' => '#',
		'id' => 'ca-watchstar-sticky-header',
		'event' => 'watch-sticky-header',
		'icon' => 'wikimedia-star',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon mw-watchlink'
	];
	private const EDIT_VE_ICON = [
		'href' => '#',
		'id' => 'ca-ve-edit-sticky-header',
		'event' => 've-edit-sticky-header',
		'icon' => 'wikimedia-edit',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon'
	];
	private const EDIT_WIKITEXT_ICON = [
		'href' => '#',
		'id' => 'ca-edit-sticky-header',
		'event' => 'wikitext-edit-sticky-header',
		'icon' => 'wikimedia-wikiText',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon'
	];
	private const EDIT_PROTECTED_ICON = [
		'href' => '#',
		'id' => 'ca-viewsource-sticky-header',
		'event' => 've-edit-protected-sticky-header',
		'icon' => 'wikimedia-editLock',
		'is-quiet' => true,
		'tabindex' => '-1',
		'class' => 'sticky-header-icon'
	];

	/** @var MessageLocalizer */
	private $localizer;
	/** @var VectorComponent */
	private $search;
	/** @var VectorComponent|null */
	private $langButton;
	/** @var bool */
	private $includeEditIcons;

	/**
	 * @param MessageLocalizer $localizer
	 * @param VectorComponent $searchBox
	 * @param VectorComponent|null $langButton
	 * @param bool $includeEditIcons whether to include edit icons in the result
	 */
	public function __construct(
		MessageLocalizer $localizer,
		VectorComponent $searchBox,
		$langButton = null,
		$includeEditIcons = false
	) {
		$this->search = $searchBox;
		$this->langButton = $langButton;
		$this->includeEditIcons = $includeEditIcons;
		$this->localizer = $localizer;
	}

	/**
	 * @param mixed $key
	 * @return Message
	 */
	private function msg( $key ): Message {
		return $this->localizer->msg( $key );
	}

	/**
	 * Creates button data for the "Add section" button in the sticky header
	 *
	 * @return array
	 */
	private function getAddSectionButtonData() {
		return [
			'href' => '#',
			'id' => 'ca-addsection-sticky-header',
			'event' => 'addsection-sticky-header',
			'html-vector-button-icon' => Hooks::makeIcon( 'wikimedia-speechBubbleAdd-progressive' ),
			'label' => $this->msg( [ 'vector-2022-action-addsection', 'skin-action-addsection' ] )->text(),
			'is-quiet' => true,
			'tabindex' => '-1',
			'class' => 'sticky-header-icon mw-ui-primary mw-ui-progressive'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		$icons = [
			self::TALK_ICON,
			self::SUBJECT_ICON,
			self::HISTORY_ICON,
			self::WATCHSTAR_ICON,
		];
		if ( $this->includeEditIcons ) {
			$icons[] = self::EDIT_WIKITEXT_ICON;
			$icons[] = self::EDIT_PROTECTED_ICON;
			$icons[] = self::EDIT_VE_ICON;
		}
		$buttons = [
			$this->getAddSectionButtonData()
		];
		if ( $this->langButton ) {
			$buttons[] = $this->langButton->getTemplateData();
		}
		$searchBoxData = $this->search->getTemplateData();

		return [
			'data-icons' => $icons,
			'data-buttons' => $buttons,
			'data-button-start' => [
				'label' => $this->msg( 'search' ),
				'icon' => 'wikimedia-search',
				'is-quiet' => true,
				'tabindex' => '-1',
				'class' => 'vector-sticky-header-search-toggle',
				'event' => 'ui.' . $searchBoxData['form-id'] . '.icon'
			],
			'data-search' => $searchBoxData,
		];
	}
}
