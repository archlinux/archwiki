<?php

namespace MediaWiki\Extension\AbuseFilter\EditBox;

use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\KeywordsManager;
use MediaWiki\Extension\AbuseFilter\Parser\AbuseFilterTokenizer;
use MediaWiki\Extension\AbuseFilter\Parser\FilterEvaluator;
use MediaWiki\Html\Html;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use OOUI\Widget;

/**
 * Class responsible for building filter edit boxes with both the CodeMirror and the plain version
 */
class CodeMirrorEditBoxBuilder extends EditBoxBuilder {

	/**
	 * @inheritDoc
	 * @param PlainEditBoxBuilder $plainBuilder
	 */
	public function __construct(
		AbuseFilterPermissionManager $afPermManager,
		KeywordsManager $keywordsManager,
		MessageLocalizer $messageLocalizer,
		Authority $authority,
		OutputPage $output,
		private readonly PlainEditBoxBuilder $plainBuilder
	) {
		parent::__construct( $afPermManager, $keywordsManager, $messageLocalizer, $authority, $output );
	}

	/**
	 * @inheritDoc
	 */
	protected function getEditBox( string $rules, bool $isUserAllowed, bool $externalForm ): string {
		$rules = rtrim( $rules ) . "\n";

		$attribs = [
			// Rules are in English
			'dir' => 'ltr',
			'name' => 'wpCodeMirrorFilterEditor',
			'id' => 'wpCodeMirrorFilterEditor',
			'class' => 'mw-abusefilter-editor'
		];
		$rulesContainer = Html::element( 'div', $attribs, $rules );
		$editorConfig = $this->getCodeMirrorConfig( $isUserAllowed );
		$this->output->addJsConfigVars( 'abuseFilterHighlighterConfig', $editorConfig );
		return $rulesContainer . $this->plainBuilder->getEditBox( $rules, $isUserAllowed, $externalForm );
	}

	/**
	 * @inheritDoc
	 */
	protected function getEditorControls(): Widget {
		$base = parent::getEditorControls();
		$switchEditor = new ButtonWidget(
			[
				'label' => $this->localizer->msg( 'abusefilter-edit-switch-editor' )->text(),
				'id' => 'mw-abusefilter-switcheditor'
			]
		);
		return new Widget( [
			'content' => new HorizontalLayout( [
				'items' => [ $switchEditor, $base ]
			] )
		] );
	}

	/**
	 * Extract values for syntax highlight
	 *
	 * @param bool $canEdit
	 * @return array
	 */
	private function getCodeMirrorConfig( bool $canEdit ): array {
		$values = $this->keywordsManager->getBuilderValues();
		$deprecatedVars = $this->keywordsManager->getDeprecatedVariables();

		$builderVariables = implode( '|', array_keys( $values['vars'] ) );
		$builderFunctions = implode( '|', array_keys( FilterEvaluator::FUNCTIONS ) );
		// AbuseFilterTokenizer::KEYWORDS also includes constants (true, false and null),
		// but CodeMirror redefines these constants afterwards so this will not be an issue
		$builderKeywords = implode( '|', AbuseFilterTokenizer::KEYWORDS );
		$deprecatedVariables = implode( '|', array_keys( $deprecatedVars ) );
		$disabledVariables = implode( '|', array_keys( $this->keywordsManager->getDisabledVariables() ) );

		return [
			'variables' => $builderVariables,
			'functions' => $builderFunctions,
			'keywords' => $builderKeywords,
			'deprecated' => $deprecatedVariables,
			'disabled' => $disabledVariables,
			'cmReadOnly' => !$canEdit
		];
	}
}
