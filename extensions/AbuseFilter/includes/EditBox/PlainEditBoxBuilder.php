<?php

namespace MediaWiki\Extension\AbuseFilter\EditBox;

use MediaWiki\Html\Html;

/**
 * Class responsible for building a plain text filter edit box
 */
class PlainEditBoxBuilder extends EditBoxBuilder {
	/**
	 * @inheritDoc
	 */
	public function getEditBox( string $rules, bool $isUserAllowed, bool $externalForm ): string {
		$rules = rtrim( $rules ) . "\n";
		$editorAttribs = [
			'name' => 'wpFilterRules',
			'id' => 'wpFilterRules',
			// Rules are in English
			'dir' => 'ltr',
			'cols' => 40,
			'rows' => 15,
		];
		if ( !$isUserAllowed ) {
			$editorAttribs['readonly'] = 'readonly';
		}
		if ( $externalForm ) {
			$editorAttribs['form'] = 'wpFilterForm';
		}
		return Html::element( 'textarea', $editorAttribs, $rules );
	}

}
