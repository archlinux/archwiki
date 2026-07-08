<?php
namespace MediaWiki\Skins\Vector\Components;

use MediaWiki\Language\Language;
use MediaWiki\Language\LanguageConverterFactory;
use MediaWiki\StubObject\StubUserLang;

/**
 * VectorComponentVariants component
 */
class VectorComponentVariants implements VectorComponent {
	/** @var Language|StubUserLang */
	private $pageLang;

	/**
	 * @param LanguageConverterFactory $languageConverterFactory
	 * @param array $menuData
	 * @param Language|StubUserLang $pageLang
	 * @param string $ariaLabel
	 */
	public function __construct(
		private readonly LanguageConverterFactory $languageConverterFactory,
		private array $menuData,
		$pageLang,
		private readonly string $ariaLabel,
	) {
		$this->pageLang = $pageLang;
	}

	/**
	 * Use the selected variant for the dropdown label
	 */
	private function getDropdownLabel(): string {
		$converter = $this->languageConverterFactory->getLanguageConverter( $this->pageLang );
		return $this->pageLang->getVariantname(
			$converter->getPreferredVariant()
		);
	}

	/**
	 * Get the variants dropdown data
	 * @return array
	 */
	private function getDropdownData() {
		$dropdown = new VectorComponentDropdown(
			'vector-variants-dropdown',
			$this->getDropdownLabel(),
			// Hide dropdown if menu is empty
			$this->menuData[ 'is-empty' ] ? 'emptyPortlet' : ''
		);
		$dropdownData = $dropdown->getTemplateData();
		$dropdownData['aria-label'] = $this->ariaLabel;
		return $dropdownData;
	}

	/**
	 * Get the variants menu data
	 * @return array
	 */
	private function getMenuDropdownData() {
		// Remove label from variants menu
		$this->menuData['label'] = null;
		$menu = new VectorComponentMenu( $this->menuData );
		return $menu->getTemplateData();
	}

	/**
	 * @inheritDoc
	 */
	public function getTemplateData(): array {
		return [
			'data-variants-dropdown' => $this->getDropdownData(),
			'data-variants-menu' => $this->getMenuDropdownData()
		];
	}
}
