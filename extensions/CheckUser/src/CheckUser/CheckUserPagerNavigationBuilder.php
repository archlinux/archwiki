<?php

namespace MediaWiki\Extension\CheckUser\CheckUser;

use MediaWiki\Extension\CheckUser\CheckUser\Pagers\AbstractCheckUserPager;
use MediaWiki\Extension\CheckUser\Services\TokenQueryManager;
use MediaWiki\Html\FormOptions;
use MediaWiki\Html\Html;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Navigation\PagerNavigationBuilder;
use MediaWiki\Request\WebRequest;
use MediaWiki\Session\CsrfTokenSet;
use MediaWiki\User\UserIdentity;

class CheckUserPagerNavigationBuilder extends PagerNavigationBuilder {

	public function __construct(
		MessageLocalizer $messageLocalizer,
		private readonly TokenQueryManager $tokenQueryManager,
		private readonly CsrfTokenSet $csrfTokenSet,
		private readonly WebRequest $request,
		private readonly FormOptions $opts,
		private readonly UserIdentity $target,
	) {
		parent::__construct( $messageLocalizer );
	}

	/** @inheritDoc */
	protected function makeLink(
		?array $query,
		?string $class,
		string $text,
		?string $tooltip,
		?string $rel = null
	): string {
		if ( $query === null ) {
			return Html::element(
				'span',
				[
					'class' => $class,
				],
				$text
			);
		}
		$query = array_merge( $this->linkQuery, $query );
		$fields = array_filter(
			AbstractCheckUserPager::TOKEN_MANAGED_FIELDS,
			$this->opts->validateName( ... )
		);
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
			[ 'id' => 'wpEditToken', 'class' => 'mw-checkuser-paging-links-edit-token' ]
		) ];
		$formFields[] = Html::hidden(
			'token',
			$this->tokenQueryManager->updateToken( $this->request, $fieldData ),
			[ 'class' => 'mw-checkuser-paging-links-token' ]
		);

		// Append filter fields to the form, as these are not managed through the token and therefore need to
		// be set on each POST request (otherwise they will revert back to their default value).
		$filterFields = array_filter(
			array_keys( AbstractCheckUserPager::FILTER_FIELDS ),
			$this->opts->validateName( ... )
		);
		foreach ( $filterFields as $field ) {
			$formFields[] = Html::hidden( $field, $this->opts->getValue( $field ) );
		}

		$formFields[] = Html::submitButton(
			$text,
			[
				'class' => $class . ' mw-checkuser-paging-links',
			]
		);
		return Html::rawElement(
			'form',
			[
				'method' => 'post',
				'class' => 'mw-checkuser-paging-links-form',
				'rel' => $rel,
				'title' => $tooltip,
			],
			implode( '', $formFields )
		);
	}
}
