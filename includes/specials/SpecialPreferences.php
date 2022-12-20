<?php
/**
 * Implements Special:Preferences
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

use MediaWiki\MediaWikiServices;
use MediaWiki\Preferences\PreferencesFactory;
use MediaWiki\User\UserOptionsManager;

/**
 * A special page that allows users to change their preferences
 *
 * @ingroup SpecialPage
 */
class SpecialPreferences extends SpecialPage {

	/** @var PreferencesFactory */
	private $preferencesFactory;

	/** @var UserOptionsManager */
	private $userOptionsManager;

	/**
	 * @param PreferencesFactory|null $preferencesFactory
	 * @param UserOptionsManager|null $userOptionsManager
	 */
	public function __construct(
		PreferencesFactory $preferencesFactory = null,
		UserOptionsManager $userOptionsManager = null
	) {
		parent::__construct( 'Preferences' );
		// This class is extended and therefore falls back to global state - T265924
		$services = MediaWikiServices::getInstance();
		$this->preferencesFactory = $preferencesFactory ?? $services->getPreferencesFactory();
		$this->userOptionsManager = $userOptionsManager ?? $services->getUserOptionsManager();
	}

	public function doesWrites() {
		return true;
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();
		$out = $this->getOutput();
		$out->disallowUserJs(); # Prevent hijacked user scripts from sniffing passwords etc.

		$this->requireNamedUser( 'prefsnologintext2' );
		$this->checkReadOnly();

		if ( $par == 'reset' ) {
			$this->showResetForm();

			return;
		}

		$out->addModules( 'mediawiki.special.preferences.ooui' );
		$out->addModuleStyles( [
			'mediawiki.special.preferences.styles.ooui',
			'mediawiki.widgets.TagMultiselectWidget.styles',
		] );
		$out->addModuleStyles( 'oojs-ui-widgets.styles' );

		$session = $this->getRequest()->getSession();
		if ( $session->get( 'specialPreferencesSaveSuccess' ) ) {
			// Remove session data for the success message
			$session->remove( 'specialPreferencesSaveSuccess' );
			$out->addModuleStyles( 'mediawiki.notification.convertmessagebox.styles' );

			$out->addHTML(
				Html::successBox(
					Html::element(
						'p',
						[],
						$this->msg( 'savedprefs' )->text()
					),
					'mw-preferences-messagebox mw-notify-success'
				)
			);
		}

		$this->addHelpLink( 'Help:Preferences' );

		// Load the user from the primary DB to reduce CAS errors on double post (T95839)
		if ( $this->getRequest()->wasPosted() ) {
			$user = $this->getUser()->getInstanceForUpdate() ?: $this->getUser();
		} else {
			$user = $this->getUser();
		}

		$htmlForm = $this->getFormObject( $user, $this->getContext() );
		$sectionTitles = $htmlForm->getPreferenceSections();

		$prefTabs = [];
		foreach ( $sectionTitles as $key ) {
			$prefTabs[] = [
				'name' => $key,
				'label' => $htmlForm->getLegend( $key ),
			];
		}
		$out->addJsConfigVars( 'wgPreferencesTabs', $prefTabs );

		$htmlForm->show();
	}

	/**
	 * Get the preferences form to use.
	 * @param User $user
	 * @param IContextSource $context
	 * @return PreferencesFormOOUI|HTMLForm
	 */
	protected function getFormObject( $user, IContextSource $context ) {
		$form = $this->preferencesFactory->getForm( $user, $context, PreferencesFormOOUI::class );
		return $form;
	}

	protected function showResetForm() {
		if ( !$this->getAuthority()->isAllowed( 'editmyoptions' ) ) {
			throw new PermissionsError( 'editmyoptions' );
		}

		$this->getOutput()->addWikiMsg( 'prefs-reset-intro' );

		$desc = [
			'confirm' => [
				'type' => 'check',
				'label-message' => 'prefs-reset-confirm',
				'required' => true,
			],
		];
		// TODO: disable the submit button if the checkbox is not checked
		HTMLForm::factory( 'ooui', $desc, $this->getContext(), 'prefs-restore' )
			->setTitle( $this->getPageTitle( 'reset' ) ) // Reset subpage
			->setSubmitTextMsg( 'restoreprefs' )
			->setSubmitDestructive()
			->setSubmitCallback( [ $this, 'submitReset' ] )
			->suppressReset()
			->showCancel()
			->setCancelTarget( $this->getPageTitle() )
			->show();
	}

	public function submitReset( $formData ) {
		if ( !$this->getAuthority()->isAllowed( 'editmyoptions' ) ) {
			throw new PermissionsError( 'editmyoptions' );
		}

		$user = $this->getUser()->getInstanceForUpdate();
		$this->userOptionsManager->resetOptions( $user, $this->getContext(), 'all' );
		$user->saveSettings();

		// Set session data for the success message
		$this->getRequest()->getSession()->set( 'specialPreferencesSaveSuccess', 1 );

		$url = $this->getPageTitle()->getFullUrlForRedirect();
		$this->getOutput()->redirect( $url );

		return true;
	}

	protected function getGroupName() {
		return 'users';
	}
}
