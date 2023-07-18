<?php

namespace MediaWiki\Widget;

/**
 * Namespace input widget. Displays a dropdown box with the choice of available namespaces.
 *
 * @copyright 2011-2015 MediaWiki Widgets Team and others; see AUTHORS.txt
 * @license MIT
 */
class NamespaceInputWidget extends \OOUI\DropdownInputWidget {
	/** @var string */
	protected $includeAllValue;
	/** @var bool */
	protected $userLang;
	/** @var int[] */
	protected $exclude;

	/**
	 * @param array $config Configuration options
	 *   - string $config['includeAllValue'] If specified, add a "all namespaces" option to the
	 *     namespace dropdown, and use this as the input value for it
	 *   - bool $config['userLang'] Display namespaces in user language
	 *   - int[] $config['exclude'] List of namespace numbers to exclude from the selector
	 */
	public function __construct( array $config = [] ) {
		// Configuration initialization
		$config['options'] = $this->getNamespaceDropdownOptions( $config );

		parent::__construct( $config );

		// Properties
		$this->includeAllValue = $config['includeAllValue'] ?? null;
		$this->userLang = $config['userLang'] ?? false;
		$this->exclude = $config['exclude'] ?? [];

		// Initialization
		$this->addClasses( [ 'mw-widget-namespaceInputWidget' ] );
	}

	protected function getNamespaceDropdownOptions( array $config ) {
		$namespaceOptionsParams = [
			'all' => $config['includeAllValue'] ?? null,
			'in-user-lang' => $config['userLang'] ?? false,
			'exclude' => $config['exclude'] ?? null
		];
		$namespaceOptions = \MediaWiki\Html\Html::namespaceSelectorOptions( $namespaceOptionsParams );

		$options = [];
		foreach ( $namespaceOptions as $id => $name ) {
			$options[] = [
				'data' => (string)$id,
				'label' => $name,
			];
		}

		return $options;
	}

	protected function getJavaScriptClassName() {
		return 'mw.widgets.NamespaceInputWidget';
	}

	public function getConfig( &$config ) {
		$config['includeAllValue'] = $this->includeAllValue;
		$config['userLang'] = $this->userLang;
		$config['exclude'] = $this->exclude;
		// Skip DropdownInputWidget's getConfig(), we don't need 'options' config
		$config['dropdown']['$overlay'] = true;
		return \OOUI\InputWidget::getConfig( $config );
	}
}
