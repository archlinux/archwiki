<?php

namespace MediaWiki\Extension\AbuseFilter\EditBox;

use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\KeywordsManager;
use MessageLocalizer;
use OOUI\ButtonWidget;
use OOUI\DropdownInputWidget;
use OOUI\FieldLayout;
use OOUI\FieldsetLayout;
use OOUI\Widget;
use OutputPage;
use User;
use Xml;

/**
 * Base class for classes responsible for building filter edit boxes
 */
abstract class EditBoxBuilder {
	/** @var AbuseFilterPermissionManager */
	protected $afPermManager;

	/** @var KeywordsManager */
	protected $keywordsManager;

	/** @var MessageLocalizer */
	protected $localizer;

	/** @var User */
	protected $user;

	/** @var OutputPage */
	protected $output;

	/**
	 * @param AbuseFilterPermissionManager $afPermManager
	 * @param KeywordsManager $keywordsManager
	 * @param MessageLocalizer $messageLocalizer
	 * @param User $user
	 * @param OutputPage $output
	 */
	public function __construct(
		AbuseFilterPermissionManager $afPermManager,
		KeywordsManager $keywordsManager,
		MessageLocalizer $messageLocalizer,
		User $user,
		OutputPage $output
	) {
		$this->afPermManager = $afPermManager;
		$this->keywordsManager = $keywordsManager;
		$this->localizer = $messageLocalizer;
		$this->user = $user;
		$this->output = $output;
	}

	/**
	 * @param string $rules
	 * @param bool $addResultDiv
	 * @param bool $externalForm
	 * @param bool $needsModifyRights
	 * @param-taint $rules none
	 * @return string
	 */
	public function buildEditBox(
		string $rules,
		bool $addResultDiv = true,
		bool $externalForm = false,
		bool $needsModifyRights = true
	): string {
		$this->output->addModules( 'ext.abuseFilter.edit' );
		$this->output->enableOOUI();

		$isUserAllowed = $needsModifyRights ?
			$this->afPermManager->canEdit( $this->user ) :
			$this->afPermManager->canUseTestTools( $this->user );
		if ( !$isUserAllowed ) {
			$addResultDiv = false;
		}

		$output = $this->getEditBox( $rules, $isUserAllowed, $externalForm );

		if ( $isUserAllowed ) {
			$dropDown = $this->getSuggestionsDropdown();

			$formElements = [
				new FieldLayout( $dropDown ),
				new FieldLayout( $this->getEditorControls() )
			];

			$fieldSet = new FieldsetLayout( [
				'items' => $formElements,
				'classes' => [ 'mw-abusefilter-edit-buttons', 'mw-abusefilter-javascript-tools' ]
			] );

			$output .= $fieldSet;
		}

		if ( $addResultDiv ) {
			$output .= Xml::element(
				'div',
				[ 'id' => 'mw-abusefilter-syntaxresult', 'style' => 'display: none;' ],
				'&#160;'
			);
		}

		return $output;
	}

	/**
	 * @return DropdownInputWidget
	 */
	private function getSuggestionsDropdown(): DropdownInputWidget {
		$rawDropDown = $this->keywordsManager->getBuilderValues();

		// The array needs to be rearranged to be understood by OOUI. It comes with the format
		// [ group-msg-key => [ text-to-add => text-msg-key ] ] and we need it as
		// [ group-msg => [ text-msg => text-to-add ] ]
		// Also, the 'other' element must be the first one.
		$dropDownOptions = [ $this->localizer->msg( 'abusefilter-edit-builder-select' )->text() => 'other' ];
		foreach ( $rawDropDown as $group => $values ) {
			// Give grep a chance to find the usages:
			// abusefilter-edit-builder-group-op-arithmetic, abusefilter-edit-builder-group-op-comparison,
			// abusefilter-edit-builder-group-op-bool, abusefilter-edit-builder-group-misc,
			// abusefilter-edit-builder-group-funcs, abusefilter-edit-builder-group-vars
			$localisedGroup = $this->localizer->msg( "abusefilter-edit-builder-group-$group" )->text();
			$dropDownOptions[ $localisedGroup ] = array_flip( $values );
			$newKeys = array_map(
				function ( $key ) use ( $group ) {
					return $this->localizer->msg( "abusefilter-edit-builder-$group-$key" )->text();
				},
				array_keys( $dropDownOptions[ $localisedGroup ] )
			);
			$dropDownOptions[ $localisedGroup ] = array_combine(
				$newKeys,
				$dropDownOptions[ $localisedGroup ]
			);
		}

		$dropDownList = Xml::listDropDownOptionsOoui( $dropDownOptions );
		return new DropdownInputWidget( [
			'name' => 'wpFilterBuilder',
			'inputId' => 'wpFilterBuilder',
			'options' => $dropDownList
		] );
	}

	/**
	 * Get an additional widget that "controls" the editor, and is placed next to it
	 * Precondition: the user has full rights.
	 *
	 * @return Widget
	 */
	protected function getEditorControls(): Widget {
		return new ButtonWidget(
			[
				'label' => $this->localizer->msg( 'abusefilter-edit-check' )->text(),
				'id' => 'mw-abusefilter-syntaxcheck'
			]
		);
	}

	/**
	 * Generate the HTML for the actual edit box
	 *
	 * @param string $rules
	 * @param bool $isUserAllowed
	 * @param bool $externalForm
	 * @return string
	 */
	abstract protected function getEditBox( string $rules, bool $isUserAllowed, bool $externalForm ): string;

}
