<?php

namespace MediaWiki\Extension\DiscussionTools;

use FormSpecialPage;
use Html;
use HTMLForm;

class SpecialFindComment extends FormSpecialPage {

	private ThreadItemStore $threadItemStore;
	private ThreadItemFormatter $threadItemFormatter;

	public function __construct(
		ThreadItemStore $threadItemStore,
		ThreadItemFormatter $threadItemFormatter
	) {
		parent::__construct( 'FindComment' );
		$this->threadItemStore = $threadItemStore;
		$this->threadItemFormatter = $threadItemFormatter;
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
		return [
			'idorname' => [
				'label-message' => 'discussiontools-findcomment-label-idorname',
				'name' => 'idorname',
				'type' => 'text',
				'required' => true,
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	protected function getSubpageField() {
		return 'idorname';
	}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @inheritDoc
	 */
	public function requiresPost() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	protected function getShowAlways() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	protected function alterForm( HTMLForm $form ) {
		$form->setWrapperLegend( true );
		$form->setSubmitTextMsg( 'discussiontools-findcomment-label-search' );
	}

	private $idOrName;

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$this->idOrName = $data['idorname'];
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function onSuccess() {
		$out = $this->getOutput();
		$results = false;

		if ( $this->idOrName ) {
			$byId = $this->threadItemStore->findNewestRevisionsById( $this->idOrName );
			if ( $byId ) {
				$this->displayItems( $byId, 'discussiontools-findcomment-results-id' );
				$results = true;
			}

			$byName = $this->threadItemStore->findNewestRevisionsByName( $this->idOrName );
			if ( $byName ) {
				$this->displayItems( $byName, 'discussiontools-findcomment-results-name' );
				$results = true;
			}
		}

		if ( $results ) {
			$out->addHTML(
				$this->msg( 'discussiontools-findcomment-gotocomment', $this->idOrName )->parseAsBlock() );
		} else {
			$out->addHTML(
				$this->msg( 'discussiontools-findcomment-noresults' )->parseAsBlock() );
		}
	}

	/**
	 * @param array $threadItems
	 * @param string $msgKey
	 */
	private function displayItems( array $threadItems, string $msgKey ) {
		$out = $this->getOutput();

		$list = [];
		foreach ( $threadItems as $item ) {
			$line = $this->threadItemFormatter->formatLine( $item, $this );
			$list[] = Html::rawElement( 'li', [], $line );
		}

		$out->addHTML( $this->msg( $msgKey, count( $list ) )->parseAsBlock() );
		$out->addHTML( Html::rawElement( 'ul', [], implode( '', $list ) ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'discussiontools-findcomment-title' )->text();
	}
}
