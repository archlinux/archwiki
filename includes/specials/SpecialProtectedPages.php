<?php
/**
 * Implements Special:Protectedpages
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

namespace MediaWiki\Specials;

use HTMLForm;
use HTMLMultiSelectField;
use HTMLSelectNamespace;
use HTMLSizeFilterField;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CommentFormatter\RowCommentFormatter;
use MediaWiki\CommentStore\CommentStore;
use MediaWiki\MainConfigNames;
use MediaWiki\Pager\ProtectedPagesPager;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\SpecialPage\SpecialPage;
use UserCache;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * A special page that lists protected pages
 *
 * @ingroup SpecialPage
 */
class SpecialProtectedPages extends SpecialPage {
	protected $IdLevel = 'level';
	protected $IdType = 'type';

	private LinkBatchFactory $linkBatchFactory;
	private IConnectionProvider $dbProvider;
	private CommentStore $commentStore;
	private UserCache $userCache;
	private RowCommentFormatter $rowCommentFormatter;
	private RestrictionStore $restrictionStore;

	/**
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param IConnectionProvider $dbProvider
	 * @param CommentStore $commentStore
	 * @param UserCache $userCache
	 * @param RowCommentFormatter $rowCommentFormatter
	 * @param RestrictionStore $restrictionStore
	 */
	public function __construct(
		LinkBatchFactory $linkBatchFactory,
		IConnectionProvider $dbProvider,
		CommentStore $commentStore,
		UserCache $userCache,
		RowCommentFormatter $rowCommentFormatter,
		RestrictionStore $restrictionStore
	) {
		parent::__construct( 'Protectedpages' );
		$this->linkBatchFactory = $linkBatchFactory;
		$this->dbProvider = $dbProvider;
		$this->commentStore = $commentStore;
		$this->userCache = $userCache;
		$this->rowCommentFormatter = $rowCommentFormatter;
		$this->restrictionStore = $restrictionStore;
	}

	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();
		$this->getOutput()->addModuleStyles( 'mediawiki.special' );
		$this->addHelpLink( 'Help:Protected_pages' );

		$request = $this->getRequest();
		$type = $request->getVal( $this->IdType );
		$level = $request->getVal( $this->IdLevel );
		$sizetype = $request->getVal( 'size-mode' );
		$size = $request->getIntOrNull( 'size' );
		$ns = $request->getIntOrNull( 'namespace' );

		$filters = $request->getArray( 'wpfilters', [] );
		$indefOnly = in_array( 'indefonly', $filters );
		$cascadeOnly = in_array( 'cascadeonly', $filters );
		$noRedirect = in_array( 'noredirect', $filters );

		$pager = new ProtectedPagesPager(
			$this->getContext(),
			$this->commentStore,
			$this->linkBatchFactory,
			$this->getLinkRenderer(),
			$this->dbProvider,
			$this->rowCommentFormatter,
			$this->userCache,
			[],
			$type,
			$level,
			$ns,
			$sizetype,
			$size,
			$indefOnly,
			$cascadeOnly,
			$noRedirect
		);

		$this->getOutput()->addHTML( $this->showOptions(
			$type,
			$level,
			$filters
		) );

		if ( $pager->getNumRows() ) {
			$this->getOutput()->addModuleStyles( 'mediawiki.interface.helpers.styles' );
			$this->getOutput()->addParserOutputContent( $pager->getFullOutput() );
		} else {
			$this->getOutput()->addWikiMsg( 'protectedpagesempty' );
		}
	}

	/**
	 * @param string $type Restriction type
	 * @param string $level Restriction level
	 * @param array $filters Filters set for the pager: indefOnly,
	 *   cascadeOnly, noRedirect
	 * @return string Input form
	 */
	protected function showOptions( $type, $level, $filters ) {
		$formDescriptor = [
			'namespace' => [
				'class' => HTMLSelectNamespace::class,
				'name' => 'namespace',
				'id' => 'namespace',
				'cssclass' => 'namespaceselector',
				'all' => '',
				'label' => $this->msg( 'namespace' )->text(),
			],
			'typemenu' => $this->getTypeMenu( $type ),
			'levelmenu' => $this->getLevelMenu( $level ),
			'filters' => [
				'class' => HTMLMultiSelectField::class,
				'label' => $this->msg( 'protectedpages-filters' )->text(),
				'flatlist' => true,
				'options-messages' => [
					'protectedpages-indef' => 'indefonly',
					'protectedpages-cascade' => 'cascadeonly',
					'protectedpages-noredirect' => 'noredirect',
				],
				'default' => $filters,
			],
			'sizelimit' => [
				'class' => HTMLSizeFilterField::class,
				'name' => 'size',
			]
		];
		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() )
			->setMethod( 'get' )
			->setWrapperLegendMsg( 'protectedpages' )
			->setSubmitTextMsg( 'protectedpages-submit' );

		return $htmlForm->prepareForm()->getHTML( false );
	}

	/**
	 * Creates the input label of the restriction type
	 * @param string $pr_type Protection type
	 * @return array
	 */
	protected function getTypeMenu( $pr_type ) {
		$m = []; // Temporary array
		$options = [];

		// First pass to load the log names
		foreach ( $this->restrictionStore->listAllRestrictionTypes( true ) as $type ) {
			// Messages: restriction-edit, restriction-move, restriction-create, restriction-upload
			$text = $this->msg( "restriction-$type" )->text();
			$m[$text] = $type;
		}

		// Third pass generates sorted XHTML content
		foreach ( $m as $text => $type ) {
			$options[$text] = $type;
		}

		return [
			'type' => 'select',
			'options' => $options,
			'label' => $this->msg( 'restriction-type' )->text(),
			'name' => $this->IdType,
			'id' => $this->IdType,
		];
	}

	/**
	 * Creates the input label of the restriction level
	 * @param string $pr_level Protection level
	 * @return array
	 */
	protected function getLevelMenu( $pr_level ) {
		$options = [ 'restriction-level-all' => 0 ];

		// Load the log names as options
		foreach ( $this->getConfig()->get( MainConfigNames::RestrictionLevels ) as $type ) {
			if ( $type != '' && $type != '*' ) {
				// Messages: restriction-level-sysop, restriction-level-autoconfirmed
				$options["restriction-level-$type"] = $type;
			}
		}

		return [
			'type' => 'select',
			'options-messages' => $options,
			'label-message' => 'restriction-level',
			'name' => $this->IdLevel,
			'id' => $this->IdLevel
		];
	}

	protected function getGroupName() {
		return 'maintenance';
	}
}

/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.41
 */
class_alias( SpecialProtectedPages::class, 'SpecialProtectedpages' );
