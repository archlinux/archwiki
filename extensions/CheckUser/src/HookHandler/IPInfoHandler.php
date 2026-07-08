<?php

namespace MediaWiki\Extension\CheckUser\HookHandler;

use LogicException;
use MediaWiki\Extension\CheckUser\GlobalContributions\CheckUserGlobalContributionsLookup;
use MediaWiki\IPInfo\Hook\IPInfoIPInfoHandlerHook;
use MediaWiki\Permissions\Authority;

class IPInfoHandler implements IPInfoIPInfoHandlerHook {
	public function __construct(
		private readonly CheckUserGlobalContributionsLookup $globalContributionsLookup,
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onIPInfoHandlerRun(
		string $target,
		Authority $performer,
		string $dataContext,
		array &$dataContainer
	): void {
		if ( $dataContext !== 'infobox' ) {
			return;
		}
		try {
			$globalContributionsCount = $this->globalContributionsLookup->getGlobalContributionsCount(
				$target,
				$performer
			);
			$dataContainer['ipinfo-source-checkuser'] = [
				'globalContributionsCount' => $globalContributionsCount,
			];
		} catch ( LogicException ) {
			// Do nothing if the count could not be found and passed through
			return;
		}
	}

}
