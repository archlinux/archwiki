<?php

namespace MediaWiki\Extension\Gadgets;

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
 * @author Siddharth VP
 * @file
 */

use OutputPage;
use Skin;
use User;

class GadgetLoadConditions {
	/** @var User */
	private $user;
	/** @var Skin */
	private $skin;
	/** @var string */
	private $action;
	/** @var int */
	private $namespace;
	/** @var string */
	private $contentModel;
	/** @var bool */
	private $isMobileView;
	/** @var string|null */
	private $withGadgetParam;

	/**
	 * @param OutputPage $out
	 */
	public function __construct( OutputPage $out, bool $isMobileView = false ) {
		$this->user = $out->getUser();
		$this->skin = $out->getSkin();
		$this->action = $out->getContext()->getActionName();
		$this->namespace = $out->getTitle()->getNamespace();
		$this->contentModel = $out->getTitle()->getContentModel();
		$this->withGadgetParam = $out->getRequest()->getRawVal( 'withgadget' );
		$this->isMobileView = $isMobileView;
	}

	/**
	 * @param Gadget $gadget
	 * @return bool
	 */
	public function check( Gadget $gadget ) {
		$urlLoad = $this->withGadgetParam === $gadget->getName() && $gadget->supportsUrlLoad();

		return ( $gadget->isEnabled( $this->user ) || $urlLoad )
			&& $gadget->isAllowed( $this->user )
			&& $gadget->isActionSupported( $this->action )
			&& $gadget->isSkinSupported( $this->skin )
			&& $gadget->isNamespaceSupported( $this->namespace )
			&& $gadget->isContentModelSupported( $this->contentModel )
			&& $gadget->isTargetSupported( $this->isMobileView );
	}
}
