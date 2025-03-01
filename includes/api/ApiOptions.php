<?php
/**
 * Copyright © 2012 Szymon Świerkosz beau@adres.pl
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
 */

namespace MediaWiki\Api;

use MediaWiki\MediaWikiServices;
use MediaWiki\Preferences\PreferencesFactory;
use MediaWiki\User\Options\UserOptionsManager;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * API module that facilitates the changing of user's preferences.
 * Requires API write mode to be enabled.
 *
 * @ingroup API
 */
class ApiOptions extends ApiOptionsBase {
	public function __construct(
		ApiMain $main,
		string $action,
		?UserOptionsManager $userOptionsManager = null,
		?PreferencesFactory $preferencesFactory = null
	) {
		/**
		 * This class is extended by GlobalPreferences extension.
		 * So it falls back to the global state.
		 */
		$services = MediaWikiServices::getInstance();
		$userOptionsManager ??= $services->getUserOptionsManager();
		$preferencesFactory ??= $services->getPreferencesFactory();
		parent::__construct( $main, $action, $userOptionsManager, $preferencesFactory );
	}

	protected function runHook( $user, $changes, $resetKinds ) {
		$this->getHookRunner()->onApiOptions( $this, $user, $changes, $resetKinds );
	}

	protected function shouldIgnoreKey( $key ) {
		$user = $this->getUserForUpdates();
		$manager = $this->getUserOptionsManager();
		if ( $this->getGlobalParam() === 'ignore' && $manager->isOptionGlobal( $user, $key ) ) {
			$this->addWarning( $this->msg( 'apiwarn-global-option-ignored', $key ) );
			return true;
		}
		return false;
	}

	protected function resetPreferences( array $kinds ) {
		$optionNames = $this->getPreferencesFactory()->getOptionNamesForReset(
			$this->getUserForUpdates(), $this->getContext(), $kinds );
		$this->getUserOptionsManager()->resetOptionsByName( $this->getUserForUpdates(), $optionNames );
	}

	protected function setPreference( $preference, $value ) {
		$globalUpdateType = [
			'ignore' => UserOptionsManager::GLOBAL_IGNORE,
			'update' => UserOptionsManager::GLOBAL_UPDATE,
			'override' => UserOptionsManager::GLOBAL_OVERRIDE
		][ $this->getGlobalParam() ];

		$this->getUserOptionsManager()->setOption(
			$this->getUserForUpdates(),
			$preference,
			$value,
			$globalUpdateType
		);
	}

	private function getGlobalParam() {
		return $this->extractRequestParams()['global'];
	}

	protected function commitChanges() {
		$this->getUserForUpdates()->saveSettings();
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/API:Options';
	}

	protected function getExamplesMessages() {
		return [
			'action=options&reset=&token=123ABC'
				=> 'apihelp-options-example-reset',
			'action=options&change=skin=vector|hideminor=1&token=123ABC'
				=> 'apihelp-options-example-change',
			'action=options&reset=&change=skin=monobook&optionname=nickname&' .
				'optionvalue=[[User:Beau|Beau]]%20([[User_talk:Beau|talk]])&token=123ABC'
				=> 'apihelp-options-example-complex',
		];
	}

	public function getAllowedParams() {
		return parent::getAllowedParams() + [
			'global' => [
				ParamValidator::PARAM_TYPE => [ 'ignore', 'update', 'override' ],
				ParamValidator::PARAM_DEFAULT => 'ignore'
			]
		];
	}
}

/** @deprecated class alias since 1.43 */
class_alias( ApiOptions::class, 'ApiOptions' );
