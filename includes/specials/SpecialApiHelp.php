<?php
/**
 * Implements Special:ApiHelp
 *
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
 * @ingroup SpecialPage
 */

use MediaWiki\Html\Html;
use MediaWiki\Utils\UrlUtils;

/**
 * Special page to redirect to API help pages, for situations where linking to
 * the api.php endpoint is not wanted.
 *
 * @ingroup SpecialPage
 */
class SpecialApiHelp extends UnlistedSpecialPage {

	/** @var UrlUtils */
	private $urlUtils;

	/**
	 * @param UrlUtils $urlUtils
	 */
	public function __construct(
		UrlUtils $urlUtils
	) {
		parent::__construct( 'ApiHelp' );
		$this->urlUtils = $urlUtils;
	}

	public function execute( $par ) {
		if ( empty( $par ) ) {
			$par = 'main';
		}

		// These come from transclusions
		$request = $this->getRequest();
		$options = [
			'action' => 'help',
			'nolead' => true,
			'submodules' => $request->getCheck( 'submodules' ),
			'recursivesubmodules' => $request->getCheck( 'recursivesubmodules' ),
			'title' => $request->getVal( 'title', $this->getPageTitle( '$1' )->getPrefixedText() ),
		];

		// These are for linking from wikitext, since url parameters are a pain
		// to do.
		while ( true ) {
			if ( str_starts_with( $par, 'sub/' ) ) {
				$par = substr( $par, 4 );
				$options['submodules'] = 1;
				continue;
			}

			if ( str_starts_with( $par, 'rsub/' ) ) {
				$par = substr( $par, 5 );
				$options['recursivesubmodules'] = 1;
				continue;
			}

			$moduleName = $par;
			break;
		}

		if ( !$this->including() ) {
			unset( $options['nolead'], $options['title'] );
			// @phan-suppress-next-line PhanPossiblyUndeclaredVariable False positive
			$options['modules'] = $moduleName;
			$link = wfAppendQuery( (string)$this->urlUtils->expand( wfScript( 'api' ), PROTO_CURRENT ), $options );
			$this->getOutput()->redirect( $link );
			return;
		}

		$main = new ApiMain( $this->getContext(), false );
		try {
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable,PhanPossiblyUndeclaredVariable False positive
			$module = $main->getModuleFromPath( $moduleName );
		} catch ( ApiUsageException $ex ) {
			$this->getOutput()->addHTML( Html::rawElement( 'span', [ 'class' => 'error' ],
				// @phan-suppress-next-line PhanPossiblyUndeclaredVariable False positive
				$this->msg( 'apihelp-no-such-module', $moduleName )->inContentLanguage()->parse()
			) );
			return;
		}

		ApiHelp::getHelp( $this->getContext(), $module, $options );
	}

	public function isIncludable() {
		return true;
	}
}
