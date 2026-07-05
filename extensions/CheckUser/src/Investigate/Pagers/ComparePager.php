<?php

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Pager
 */

namespace MediaWiki\Extension\CheckUser\Investigate\Pagers;

use DateTime;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\CheckUser\Investigate\Services\CompareService;
use MediaWiki\Extension\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\Extension\CheckUser\Services\TokenQueryManager;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Page\LinkBatchFactory;
use MediaWiki\Pager\TablePager;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\IPUtils;
use Wikimedia\Rdbms\FakeResultWrapper;

class ComparePager extends TablePager {
	/** @var array */
	private $fieldNames;

	/**
	 * Holds a cache of iphex => action-count to avoid
	 * recurring queries to the database for the same ip
	 *
	 * @var array
	 */
	private $ipTotalActions;

	/**
	 * Targets whose results should not be included in the investigation.
	 * Targets in this list may or may not also be in the $targets list.
	 * Either way, no activity related to these targets will appear in the
	 * results.
	 *
	 * @var string[]
	 */
	private $excludeTargets;

	/**
	 * If set, makes the pager to not show temporary accounts in the results.
	 */
	private readonly bool $excludeTempAccounts;

	/**
	 * Targets that have been added to the investigation but that are not
	 * present in $excludeTargets. These are the targets that will actually
	 * be investigated.
	 *
	 * @var string[]
	 */
	private $filteredTargets;

	/** @var string */
	private $start;

	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		private readonly TokenQueryManager $tokenQueryManager,
		DurationManager $durationManager,
		private readonly CompareService $compareService,
		private readonly UserFactory $userFactory,
		private readonly LinkBatchFactory $linkBatchFactory,
	) {
		parent::__construct( $context, $linkRenderer );

		$tokenData = $tokenQueryManager->getDataFromRequest( $context->getRequest() );
		$this->mOffset = $tokenData['offset'] ?? '';
		$this->excludeTempAccounts = $tokenData['FilterTempAccounts'] ?? false;
		$this->excludeTargets = $tokenData['exclude-targets'] ?? [];
		$this->filteredTargets = array_diff(
			$tokenData['targets'] ?? [],
			$this->excludeTargets
		);

		$this->start = $durationManager->getTimestampFromRequest( $context->getRequest() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getTableClass() {
		$sortableClass = $this->mIsFirst && $this->mIsLast ? 'sortable' : '';
		return implode( ' ', [
			parent::getTableClass(),
			$sortableClass,
			'ext-checkuser-investigate-table',
			'ext-checkuser-investigate-table-compare',
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function getCellAttrs( $field, $value ) {
		$attributes = parent::getCellAttrs( $field, $value );
		$attributes['class'] ??= '';

		$row = $this->mCurrentRow;
		switch ( $field ) {
			case 'ip_hex':
				$ip = IPUtils::formatHex( $value );
				foreach ( $this->filteredTargets as $target ) {
					if ( IPUtils::isIPAddress( $target ) && IPUtils::isInRange( $ip, $target ) ) {
						// If the current IP is either in the range of a filtered target or is the filtered target,
						// then mark the cell as a target.
						$attributes['class'] .= ' ext-checkuser-compare-table-cell-target';
						break;
					}
				}
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-interactive';
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-pinnable';
				$attributes['class'] .= ' ext-checkuser-compare-table-cell-ip-target';
				$attributes['data-field'] = $field;
				$attributes['data-value'] = $ip;
				$attributes['data-sort-value'] = $value;
				$attributes['data-actions'] = $row->total_actions;
				$attributes['data-all-actions'] = $this->ipTotalActions[$value];
				break;
			case 'user_text':
				// Use the IP as the $row->user_text if the actor ID is NULL and the IP is not NULL (T353953).
				if ( $row->actor === null && $row->ip_hex !== null ) {
					$value = IPUtils::formatHex( $row->ip_hex );
				}
				// Hide the username if it is hidden from the current authority.
				$user = $this->userFactory->newFromName( $value );
				$userIsHidden = $user !== null && $user->isHidden() && !$this->getAuthority()->isAllowed( 'hideuser' );
				if ( $userIsHidden ) {
					$value = $this->msg( 'rev-deleted-user' )->text();
				}
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-interactive';
				if ( !IPUtils::isIpAddress( $value ) ) {
					if ( !$userIsHidden ) {
						$attributes['class'] .= ' ext-checkuser-compare-table-cell-user-target';
					}
					if ( in_array( $value, $this->filteredTargets ) ) {
						$attributes['class'] .= ' ext-checkuser-compare-table-cell-target';
					}
					$attributes['data-field'] = $field;
					$attributes['data-value'] = $value;
				}
				// Store the sort value as an attribute, to avoid using the table cell contents
				// as the sort value, since UI elements are added to the table cell.
				$attributes['data-sort-value'] = $value;
				break;
			case 'agent':
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-interactive';
				$attributes['class'] .= ' ext-checkuser-investigate-table-cell-pinnable';
				$attributes['class'] .= ' ext-checkuser-compare-table-cell-user-agent';
				$attributes['data-field'] = $field;
				$attributes['data-value'] = $value;
				// Store the sort value as an attribute, to avoid using the table cell contents
				// as the sort value, since UI elements are added to the table cell.
				$attributes['data-sort-value'] = $value;
				break;
			case 'activity':
				$attributes['class'] .= ' ext-checkuser-compare-table-cell-activity';
				$start = new DateTime( $row->first_action );
				$end = new DateTime( $row->last_action );
				$attributes['data-sort-value'] = $start->format( 'Ymd' ) . $end->format( 'Ymd' );
				break;
		}

		// Add each cell to the tab index.
		$attributes['tabindex'] = 0;

		return $attributes;
	}

	/**
	 * @param string $name
	 * @param string|null $value
	 * @return string
	 */
	public function formatValue( $name, $value ) {
		$language = $this->getLanguage();
		$row = $this->mCurrentRow;

		switch ( $name ) {
			case 'user_text':
				// Use the IP as the $row->user_text if the actor ID is NULL and the IP is not NULL (T353953).
				if ( $row->actor === null && $row->ip_hex !== null ) {
					$value = IPUtils::formatHex( $row->ip_hex );
				}
				'@phan-var string $value';
				// Hide the username if it is hidden from the current authority.
				$user = $this->userFactory->newFromName( $value );
				if ( $user !== null && $user->isHidden() && !$this->getAuthority()->isAllowed( 'hideuser' ) ) {
					return $this->msg( 'rev-deleted-user' )->escaped();
				}
				if ( IPUtils::isValid( $value ) ) {
					$formatted = $this->msg( 'checkuser-investigate-compare-table-cell-unregistered' )->escaped();
				} else {
					$formatted = Linker::userLink( $row->user ?? 0, $value );
				}
				break;
			case 'ip_hex':
				$formatted = Html::element(
					'span',
					[ 'class' => "ext-checkuser-compare-table-cell-ip" ],
					IPUtils::formatHex( $value )
				);

				// get other actions
				$otherActions = '';
				if ( !isset( $this->ipTotalActions[$value] ) ) {
					$this->ipTotalActions[$value] = $this->compareService->getTotalActionsFromIP( $value );
				}

				if ( $this->ipTotalActions[$value] ) {
					$otherActions = Html::rawElement(
						'span',
						[],
						$this->msg(
							'checkuser-investigate-compare-table-cell-other-actions',
							$this->ipTotalActions[$value]
						)->parse()
					);
				}

				$formatted .= Html::rawElement(
					'div',
					[],
					$this->msg(
						'checkuser-investigate-compare-table-cell-actions',
						$row->total_actions
					)->parse() . ' ' . $otherActions
				);

				break;
			case 'agent':
				$formatted = htmlspecialchars( $value ?? '' );
				break;
			case 'activity':
				$firstAction = $language->userDate( $row->first_action, $this->getUser() );
				$lastAction = $language->userDate( $row->last_action, $this->getUser() );
				$formatted = htmlspecialchars( $firstAction . ' - ' . $lastAction );
				break;
			default:
				$formatted = '';
		}

		return $formatted;
	}

	/**
	 * @inheritDoc
	 */
	public function getIndexField() {
		return [ [ 'user_text', 'ip_hex', 'agent' ] ];
	}

	/**
	 * @inheritDoc
	 */
	public function getFieldNames() {
		if ( $this->fieldNames === null ) {
			$this->fieldNames = [
				'user_text' => 'checkuser-investigate-compare-table-header-username',
				'ip_hex' => 'checkuser-investigate-compare-table-header-ip',
				'agent' => 'checkuser-investigate-compare-table-header-useragent',
				'activity' => 'checkuser-investigate-compare-table-header-activity',
			];
			foreach ( $this->fieldNames as &$val ) {
				$val = $this->msg( $val )->text();
			}
		}
		return $this->fieldNames;
	}

	/**
	 * @inheritDoc
	 *
	 * Handle special case where all targets are filtered.
	 */
	public function doQuery() {
		// If there are no targets, there is no need to run the query and an empty result can be used.
		if ( $this->filteredTargets === [] ) {
			$this->mResult = new FakeResultWrapper( [] );
			$this->mQueryDone = true;
			return;
		}

		parent::doQuery();
	}

	/**
	 * @inheritDoc
	 */
	protected function doBatchLookups() {
		$lb = $this->linkBatchFactory->newLinkBatch();
		$lb->setCaller( __METHOD__ );

		foreach ( $this->mResult as $row ) {
			$username = $row->user_text;
			if ( $username === null && $row->ip_hex !== null ) {
				$username = IPUtils::formatHex( $row->ip_hex );
			}
			$lb->addUser( new UserIdentityValue( $row->user ?? 0, $username ?? '' ) );
		}

		$lb->execute();
	}

	/**
	 * @inheritDoc
	 */
	public function getQueryInfo() {
		return $this->compareService->getQueryInfo(
			$this->filteredTargets,
			$this->excludeTargets,
			$this->excludeTempAccounts,
			$this->start
		);
	}

	/**
	 * Check if we have incomplete data for any of the targets.
	 *
	 * @return string[] Targets whose limits were exceeded (if any)
	 */
	public function getTargetsOverLimit(): array {
		return $this->compareService->getTargetsOverLimit(
			$this->filteredTargets,
			$this->excludeTargets,
			$this->start
		);
	}

	/**
	 * @inheritDoc
	 */
	public function isFieldSortable( $field ) {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getDefaultSort() {
		return '';
	}

	/**
	 * @inheritDoc
	 *
	 * Conceal the offset which may reveal private data.
	 */
	public function getPagingQueries() {
		return $this->tokenQueryManager->getPagingQueries(
			$this->getRequest(),
			parent::getPagingQueries()
		);
	}
}
