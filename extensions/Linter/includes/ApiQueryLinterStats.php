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
 */
namespace MediaWiki\Linter;

use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Api\ApiResult;

class ApiQueryLinterStats extends ApiQueryBase {

	private TotalsLookup $totalsLookup;

	/**
	 * @param ApiQuery $queryModule
	 * @param string $moduleName
	 * @param TotalsLookup $totalsLookup
	 */
	public function __construct(
		ApiQuery $queryModule,
		string $moduleName,
		TotalsLookup $totalsLookup
	) {
		parent::__construct( $queryModule, $moduleName, 'lntrst' );
		$this->totalsLookup = $totalsLookup;
	}

	/**
	 * Add totals to output
	 */
	public function execute() {
		$totals = $this->totalsLookup->getTotals();
		ApiResult::setArrayType( $totals, 'assoc' );
		$this->getResult()->addValue( [ 'query', 'linterstats' ], 'totals', $totals );
	}

	/** @inheritDoc */
	public function getExamplesMessages() {
		return [
			'action=query&meta=linterstats' =>
				'apihelp-query+linterstats-example-1',
		];
	}
}
