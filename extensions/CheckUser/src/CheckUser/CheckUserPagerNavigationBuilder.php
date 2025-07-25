<?php

namespace MediaWiki\CheckUser\CheckUser;

use MediaWiki\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Html\FormOptions;
use MediaWiki\Html\Html;
use MediaWiki\Navigation\PagerNavigationBuilder;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\CsrfTokenSet;
use MediaWiki\User\UserIdentity;
use MessageLocalizer;

class CheckUserPagerNavigationBuilder extends PagerNavigationBuilder {
	private TokenQueryManager $tokenQueryManager;
	private UserIdentity $target;
	private WebRequest $request;
	private CsrfTokenSet $csrfTokenSet;

	/** @var FormOptions The submitted form data in a helper class. */
	private FormOptions $opts;

	public function __construct(
		MessageLocalizer $messageLocalizer,
		TokenQueryManager $tokenQueryManager,
		CsrfTokenSet $csrfTokenSet,
		WebRequest $request,
		FormOptions $opts,
		UserIdentity $target
	) {
		parent::__construct( $messageLocalizer );
		$this->opts = $opts;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->target = $target;
		$this->request = $request;
		$this->csrfTokenSet = $csrfTokenSet;
	}

	/** @inheritDoc */
	protected function makeLink(
		?array $query, ?string $class, string $text, ?string $tooltip, ?string $rel = null
	): string {
		if ( $query === null ) {
			return Html::element(
				'span',
				[
					'class' => $class
				],
				$text
			);
		}
		$query = array_merge( $this->linkQuery, $query );
		$opts = $this->opts;
		$fields = array_filter( AbstractCheckUserPager::TOKEN_MANAGED_FIELDS, static function ( $field ) use ( $opts ) {
			return $opts->validateName( $field );
		} );
		$fieldData = [];
		foreach ( $fields as $field ) {
			if ( !in_array( $field, [ 'dir', 'offset', 'limit' ] ) ) {
				$fieldData[$field] = $this->opts->getValue( $field );
			} else {
				// Never persist the dir, offset and limit
				// as the pagination links are responsible
				// for setting or not setting them.
				$fieldData[$field] = null;
			}
		}

		$fieldData['user'] = $this->target->getName();
		if ( $query ) {
			foreach ( $query as $queryItem => $queryValue ) {
				$fieldData[$queryItem] = $queryValue;
			}
		}
		$formFields = [ Html::hidden(
			'wpEditToken',
			$this->csrfTokenSet->getToken(),
			[ 'id' => 'wpEditToken' ]
		) ];
		$formFields[] = Html::hidden(
			'token',
			$this->tokenQueryManager->updateToken( $this->request, $fieldData )
		);
		$formFields[] = Html::submitButton(
			$text,
			[
				'class' => $class . ' mw-checkuser-paging-links'
			]
		);
		return Html::rawElement(
			'form',
			[
				'method' => 'post',
				'class' => 'mw-checkuser-paging-links-form',
				'rel' => $rel,
				'title' => $tooltip
			],
			implode( '', $formFields )
		);
	}
}
