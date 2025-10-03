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

namespace MediaWiki\Extension\OATHAuth\Auth;

use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Message\Message;

/**
 * Represents the module switching UI. Will only be present when there is an alternative module to
 * switch to.
 * @author Taavi Väänänen
 */
class TwoFactorModuleSelectAuthenticationRequest extends AuthenticationRequest {

	/** The module being rendered in the current UI (as in IModule::getName()). */
	public string $currentModule;

	/**
	 * The new module selected by the user (as in IModule::getName()). Initially null, returning
	 * a TwoFactorModuleSelectAuthenticationRequest with this field set means the 2FA check will
	 * start again with the new module (other returned requests will be ignored).
	 */
	public ?string $newModule = null;

	/**
	 * The list of modules the user can switch to, in an internal name => display name format
	 * (as in IModule::getName() / IModule::getDisplayName()). Includes the current module.
	 * @var array<string,Message>
	 */
	public array $allowedModules;

	/**
	 * @param string $currentModule See {@link ::$currentModule}.
	 * @param array $allowedModules See {@link ::$allowedModules}.
	 */
	public function __construct( string $currentModule, array $allowedModules ) {
		$this->currentModule = $currentModule;
		$this->allowedModules = $allowedModules;
	}

	/** @inheritDoc */
	public function getFieldInfo() {
		$availableModules = array_diff_key( $this->allowedModules, [ $this->currentModule => true ] );
		$availableModules = [ '' => wfMessage( 'oathauth-auth-switch-module-keep' ) ] + $availableModules;

		return [
			'newModule' => [
				'type' => 'select',
				'options' => $availableModules,
				'value' => '',
				'optional' => true,
				'label' => wfMessage( 'oathauth-auth-switch-module-label' ),
				'help' => wfMessage( 'oathauth-auth-switch-module-help' ),
			],
		];
	}

	/** @inheritDoc */
	public function getMetadata() {
		return [
			'currentModule' => $this->currentModule,
			'allowedModules' => array_keys( $this->allowedModules ),
			'moduleDescriptions' => array_map( static fn ( Message $msg ) => $msg->text(), $this->allowedModules ),
		];
	}

	/** @inheritDoc */
	public function loadFromSubmission( array $data ) {
		// Be nice to API users and don't require them to submit an empty string when not switching.
		// The default implementation would result in this object getting discarded when the
		// newModule field is not present at all, but we still need it to preserve currentModule
		// (via the session) so we know which 2FA module to call continueAuthentication() one.
		$data += [ 'newModule' => '' ];
		$ret = parent::loadFromSubmission( $data );
		$this->newModule = $this->newModule ?: null;
		return $ret;
	}

	/** @inheritDoc */
	public static function __set_state( $data ) {
		$ret = new static( '', [] );
		foreach ( $data as $k => $v ) {
			$ret->$k = $v;
		}
		return $ret;
	}

}
