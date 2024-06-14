<?php
/**
 * Entry point implementation for all %Action API queries, handled by ApiMain
 * and ApiBase subclasses.
 *
 * @see /api.php The corresponding HTTP entry point.
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
 * @ingroup entrypoint
 * @ingroup API
 */

namespace MediaWiki\Api;

use ApiMain;
use LogicException;
use MediaWiki\Context\RequestContext;
use MediaWiki\EntryPointEnvironment;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Logger\LegacyLogger;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiEntryPoint;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Throwable;

/**
 * Implementation of the API entry point, for web browser navigations, usually via an
 * Action or SpecialPage subclass.
 *
 * This is used by bots to fetch content and information about the wiki,
 * its pages, and its users. See <https://www.mediawiki.org/wiki/API> for more
 * information.
 *
 * @see /api.php The corresponding HTTP entry point.
 * @internal
 */
class ApiEntryPoint extends MediaWikiEntryPoint {

	public function __construct(
		RequestContext $context,
		EntryPointEnvironment $environment,
		MediaWikiServices $services
	) {
		parent::__construct(
			$context,
			$environment,
			$services
		);
	}

	/**
	 * Overwritten to narrow the return type to RequestContext
	 * @return RequestContext
	 */
	protected function getContext(): RequestContext {
		/** @var RequestContext $context */
		$context = parent::getContext();

		// @phan-suppress-next-line PhanTypeMismatchReturnSuperType see $context in the constructor
		return $context;
	}

	/**
	 * Executes a request to the action API.
	 *
	 * It begins by constructing a new ApiMain using the parameter passed to it
	 * as an argument in the URL ('?action='). It then invokes "execute()" on the
	 * ApiMain object instance, which produces output in the format specified in
	 * the URL.
	 */
	protected function execute() {
		global $wgTitle;

		$context = $this->getContext();
		$request = $this->getRequest();
		$apiRequestLog = $this->getConfig( MainConfigNames::APIRequestLog );

		$starttime = microtime( true );

		$services = $this->getServiceContainer();

		// PATH_INFO can be used for stupid things. We don't support it for api.php at
		// all, so error out if it's present. (T128209)
		$pathInfo = $this->environment->getServerInfo( 'PATH_INFO', '' );
		if ( $pathInfo != '' ) {
			$correctUrl = wfAppendQuery(
				wfScript( 'api' ),
				$request->getQueryValuesOnly()
			);
			$correctUrl = (string)$services->getUrlUtils()->expand(
				$correctUrl,
				PROTO_CANONICAL
			);
			$this->header(
				"Location: $correctUrl",
				true,
				301
			);
			$this->print(
				'This endpoint does not support "path info", i.e. extra text ' .
				'between "api.php" and the "?". Remove any such text and try again.'
			);
			$this->exit( 1 );
		}

		// Set a dummy $wgTitle, because $wgTitle == null breaks various things
		// In a perfect world this wouldn't be necessary
		$wgTitle = Title::makeTitle(
			NS_SPECIAL,
			'Badtitle/dummy title for API calls set in api.php'
		);

		// RequestContext will read from $wgTitle, but it will also whine about it.
		// In a perfect world this wouldn't be necessary either.
		$context->setTitle( $wgTitle );

		try {
			// Construct an ApiMain with the arguments passed via the URL. What we get back
			// is some form of an ApiMain, possibly even one that produces an error message,
			// but we don't care here, as that is handled by the constructor.
			$processor = new ApiMain(
				$context,
				true,
				false
			);

			// Last chance hook before executing the API
			( new HookRunner( $services->getHookContainer() ) )->onApiBeforeMain( $processor );
			if ( !$processor instanceof ApiMain ) {
				throw new LogicException(
					'ApiBeforeMain hook set $processor to a non-ApiMain class'
				);
			}
		} catch ( Throwable $e ) {
			// Crap. Try to report the exception in API format to be friendly to clients.
			ApiMain::handleApiBeforeMainException( $e );
			$processor = false;
		}

		// Process data & print results
		if ( $processor ) {
			$processor->execute();
		}

		// Log what the user did, for book-keeping purposes.
		$endtime = microtime( true );

		// Log the request
		if ( $apiRequestLog ) {
			$items = [
				wfTimestamp( TS_MW ),
				$endtime - $starttime,
				$request->getIP(),
				$request->getHeader( 'User-agent' )
			];
			$items[] = $request->wasPosted() ? 'POST' : 'GET';
			if ( $processor ) {
				try {
					$manager = $processor->getModuleManager();
					$module = $manager->getModule(
						$request->getRawVal( 'action' ),
						'action'
					);
				} catch ( Throwable $ex ) {
					$module = null;
				}
				if ( !$module || $module->mustBePosted() ) {
					$items[] = "action=" . $request->getRawVal( 'action' );
				} else {
					$items[] = wfArrayToCgi( $request->getValues() );
				}
			} else {
				$items[] = "failed in ApiBeforeMain";
			}
			LegacyLogger::emit(
				implode(
					',',
					$items
				) . "\n",
				$apiRequestLog
			);
			wfDebug( "Logged API request to $apiRequestLog" );
		}
	}
}
