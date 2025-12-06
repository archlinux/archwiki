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

namespace MediaWiki\Extension\ConfirmEdit\Hooks;

use MediaWiki\Extension\ConfirmEdit\Hooks;

/**
 * This is a hook handler interface, see docs/Hooks.md in core.
 * Use the hook name "ConfirmEditCaptchaClass" to register handlers implementing this interface.
 *
 * @since 1.45
 * @ingroup Hooks
 */
interface ConfirmEditCaptchaClassHook {
	/**
	 * This hook is called when setting up the CAPTCHA instance for a particular action
	 *
	 * The hook can be invoked multiple times per request, and there is no guarantee that
	 * the $action specified will be the same throughout a given request.
	 *
	 * The $className returned by this hook will be used in determining which instance of
	 * SimpleCaptcha to instantiate.
	 *
	 * NOTE: All possible variations of $className for a request should be added to the
	 * $wgConfirmEditLoadedCaptchas config, with this defined early in the lifecycle of the request.
	 * This is necessary because this list is used to define which captchas to load
	 * and therefore if not loaded the functionality of that captcha may be broken.
	 *
	 * @param string $action Action user is performing, one of sendmail, createaccount,
	 *  badlogin, edit, create, addurl.
	 * @param string &$className Class name that will be used for instantiating a new
	 *  SimpleCaptcha instance
	 * @return bool|void True or no return value to continue or false to abort
	 * @see Hooks::getInstance()
	 */
	public function onConfirmEditCaptchaClass(
		$action,
		&$className
	);
}
