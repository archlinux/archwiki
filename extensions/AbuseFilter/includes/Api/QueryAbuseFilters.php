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
 */

namespace MediaWiki\Extension\AbuseFilter\Api;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * Query module to list abuse filter details.
 *
 * @copyright 2009 Alex Z. <mrzmanwiki AT gmail DOT com>
 * Based mostly on code by Bryan Tong Minh and Roan Kattouw
 *
 * @ingroup API
 * @ingroup Extensions
 */
class QueryAbuseFilters extends ApiQueryBase {

	private AbuseFilterPermissionManager $afPermManager;
	private FilterLookup $filterLookup;

	public function __construct(
		ApiQuery $query,
		string $moduleName,
		AbuseFilterPermissionManager $afPermManager,
		FilterLookup $filterLookup
	) {
		parent::__construct( $query, $moduleName, 'abf' );
		$this->afPermManager = $afPermManager;
		$this->filterLookup = $filterLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->checkUserRightsAny( 'abusefilter-view' );

		$params = $this->extractRequestParams();

		$prop = array_fill_keys( $params['prop'], true );
		$fld_id = isset( $prop['id'] );
		$fld_desc = isset( $prop['description'] );
		$fld_pattern = isset( $prop['pattern'] );
		$fld_actions = isset( $prop['actions'] );
		$fld_hits = isset( $prop['hits'] );
		$fld_comments = isset( $prop['comments'] );
		$fld_user = isset( $prop['lasteditor'] );
		$fld_time = isset( $prop['lastedittime'] );
		$fld_status = isset( $prop['status'] );
		$fld_private = isset( $prop['private'] );
		$fld_protected = isset( $prop['protected'] );

		$result = $this->getResult();

		// Use the SelectQueryBuilder from the FilterLookup service as a base so that we can construct
		// Filter objects from the rows got in the query.
		$this->getQueryBuilder()->queryInfo(
			$this->filterLookup->getAbuseFilterQueryBuilder( $this->getDB() )->getQueryInfo()
		);

		$this->addOption( 'LIMIT', $params['limit'] + 1 );

		$this->addWhereRange( 'af_id', $params['dir'], $params['startid'], $params['endid'] );

		if ( $params['show'] !== null ) {
			$show = array_fill_keys( $params['show'], true );

			/* Check for conflicting parameters. */
			if ( ( isset( $show['enabled'] ) && isset( $show['!enabled'] ) )
				|| ( isset( $show['deleted'] ) && isset( $show['!deleted'] ) )
				|| ( isset( $show['private'] ) && isset( $show['!private'] ) )
			) {
				$this->dieWithError( 'apierror-show' );
			}

			$dbr = $this->getDb();
			$this->addWhereIf( $dbr->expr( 'af_enabled', '=', 0 ), isset( $show['!enabled'] ) );
			$this->addWhereIf( $dbr->expr( 'af_enabled', '!=', 0 ), isset( $show['enabled'] ) );
			$this->addWhereIf( $dbr->expr( 'af_deleted', '=', 0 ), isset( $show['!deleted'] ) );
			$this->addWhereIf( $dbr->expr( 'af_deleted', '!=', 0 ), isset( $show['deleted'] ) );
			$this->addWhereIf(
				$dbr->bitAnd( 'af_hidden', Flags::FILTER_HIDDEN ) . ' = 0',
				isset( $show['!private'] )
			);
			$this->addWhereIf(
				$dbr->bitAnd( 'af_hidden', Flags::FILTER_HIDDEN ) . ' != 0',
				isset( $show['private'] )
			);
			$this->addWhereIf(
				$dbr->bitAnd( 'af_hidden', Flags::FILTER_USES_PROTECTED_VARS ) . ' != 0',
				isset( $show['!protected'] )
			);
			$this->addWhereIf(
				$dbr->bitAnd( 'af_hidden', Flags::FILTER_USES_PROTECTED_VARS ) . ' = 0',
				isset( $show['!protected'] )
			);
		}

		$res = $this->select( __METHOD__ );

		$showhidden = $this->afPermManager->canViewPrivateFilters( $this->getAuthority() );

		$count = 0;
		foreach ( $res as $row ) {
			// FilterLookup::filterFromRow will override af_actions, so we need to define the callback to generate
			// the data. We do not need to define anything other than the names because we only call
			// AbstractFilter::getActionNames.
			$actions = array_flip( explode( ',', $row->af_actions ) );
			$filter = $this->filterLookup->filterFromRow( $row, $actions );
			if ( ++$count > $params['limit'] ) {
				// We've had enough
				$this->setContinueEnumParameter( 'startid', $filter->getID() );
				break;
			}

			// Hide the pattern and non-public comments from the API response if the user would not
			// be able to open the editor for the filter.
			$canViewExtendedDetailsAboutFilter = ( !$filter->isHidden() || $showhidden );

			if ( $filter->isProtected() && $canViewExtendedDetailsAboutFilter ) {
				$canViewExtendedDetailsAboutFilter = $this->afPermManager
					->canViewProtectedVariablesInFilter( $this->getAuthority(), $filter )
					->isGood();
			}

			$entry = [];
			if ( $fld_id ) {
				$entry['id'] = $filter->getID();
			}
			if ( $fld_desc ) {
				$entry['description'] = $filter->getName();
			}
			if ( $fld_pattern && $canViewExtendedDetailsAboutFilter ) {
				$entry['pattern'] = $filter->getRules();
			}
			if ( $fld_actions ) {
				$entry['actions'] = implode( ',', $filter->getActionsNames() );
			}
			if ( $fld_hits ) {
				$entry['hits'] = $filter->getHitCount();
			}
			if ( $fld_comments && $canViewExtendedDetailsAboutFilter ) {
				$entry['comments'] = $filter->getComments();
			}
			if ( $fld_user ) {
				$entry['lasteditor'] = $filter->getLastEditInfo()->getUserName();
			}
			if ( $fld_time ) {
				$entry['lastedittime'] = ConvertibleTimestamp::convert(
					TS_ISO_8601, $filter->getLastEditInfo()->getTimestamp()
				);
			}
			if ( $fld_private && $filter->isHidden() ) {
				$entry['private'] = '';
			}
			if ( $fld_protected && $filter->isProtected() ) {
				$entry['protected'] = '';
			}
			if ( $fld_status ) {
				if ( $filter->isEnabled() ) {
					$entry['enabled'] = '';
				}
				if ( $filter->isDeleted() ) {
					$entry['deleted'] = '';
				}
			}
			if ( $entry ) {
				$fit = $result->addValue( [ 'query', $this->getModuleName() ], null, $entry );
				if ( !$fit ) {
					$this->setContinueEnumParameter( 'startid', $filter->getID() );
					break;
				}
			}
		}
		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'filter' );
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'startid' => [
				ParamValidator::PARAM_TYPE => 'integer'
			],
			'endid' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'dir' => [
				ParamValidator::PARAM_TYPE => [
					'older',
					'newer'
				],
				ParamValidator::PARAM_DEFAULT => 'newer',
				ApiBase::PARAM_HELP_MSG => 'api-help-param-direction',
			],
			'show' => [
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_TYPE => [
					'enabled',
					'!enabled',
					'deleted',
					'!deleted',
					'private',
					'!private',
					'protected',
					'!protected',
				],
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 10,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'prop' => [
				ParamValidator::PARAM_DEFAULT => 'id|description|actions|status',
				ParamValidator::PARAM_TYPE => [
					'id',
					'description',
					'pattern',
					'actions',
					'hits',
					'comments',
					'lasteditor',
					'lastedittime',
					'status',
					'private',
					'protected',
				],
				ParamValidator::PARAM_ISMULTI => true
			]
		];
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&list=abusefilters&abfshow=enabled|!private'
				=> 'apihelp-query+abusefilters-example-1',
			'action=query&list=abusefilters&abfprop=id|description|pattern'
				=> 'apihelp-query+abusefilters-example-2',
		];
	}
}
