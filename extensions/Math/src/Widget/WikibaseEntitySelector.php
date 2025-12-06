<?php

namespace MediaWiki\Extension\Math\Widget;

use MediaWiki\Logger\LoggerFactory;
use OOUI\SearchInputWidget;

class WikibaseEntitySelector extends SearchInputWidget {

	/** @var string */
	protected $paramType = 'item';

	// This should be \Wikimedia\ParamValidator\ParamValidator::PARAM_TYPE of the target API
	private const ALLOWED_TYPES = [ 'entity-schema', 'form', 'item', 'lexeme', 'property', 'sense' ];

	public function __construct( array $config = [] ) {
		// @phan-suppress-next-line PhanUndeclaredStaticMethod https://github.com/phan/phan/issues/5044
		parent::__construct( $config );
		$paramType = $config['paramType'] ?? false;
		if ( $paramType ) {
			if ( in_array( $paramType, self::ALLOWED_TYPES, true ) ) {
				$this->paramType = $paramType;
			} else {
				LoggerFactory::getInstance( 'Math' )->warning(
					'Invalid type', [ $paramType ] );
			}
		}
		$this->addClasses( [ 'mw-math-widget-wb-entity-selector' ] );
	}

	protected function getJavaScriptClassName(): string {
		return 'mw.widgets.MathWbEntitySelector';
	}

	/** @inheritDoc */
	public function getConfig( &$config ) {
		$config['paramType'] = $this->paramType;
		return parent::getConfig( $config );
	}
}
