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
 * @ingroup Pager
 */

namespace MediaWiki\Pager;

use ChangesList;
use ChangeTags;
use HtmlArmor;
use IContextSource;
use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\ChangeTags\ChangeTagsStore;
use MediaWiki\CommentFormatter\RowCommentFormatter;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Html\FormOptions;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Permissions\GroupPermissionsLookup;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityValue;
use RecentChange;
use stdClass;

/**
 * @internal For use by SpecialNewPages
 * @ingroup Pager
 */
class NewPagesPager extends ReverseChronologicalPager {

	/**
	 * @var FormOptions
	 */
	protected $opts;

	/** @var string[] */
	private $formattedComments = [];

	private GroupPermissionsLookup $groupPermissionsLookup;
	private HookRunner $hookRunner;
	private LinkBatchFactory $linkBatchFactory;
	private NamespaceInfo $namespaceInfo;
	private ChangeTagsStore $changeTagsStore;
	private RowCommentFormatter $rowCommentFormatter;
	private IContentHandlerFactory $contentHandlerFactory;

	/**
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param GroupPermissionsLookup $groupPermissionsLookup
	 * @param HookContainer $hookContainer
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param NamespaceInfo $namespaceInfo
	 * @param ChangeTagsStore $changeTagsStore
	 * @param RowCommentFormatter $rowCommentFormatter
	 * @param IContentHandlerFactory $contentHandlerFactory
	 * @param FormOptions $opts
	 */
	public function __construct(
		IContextSource $context,
		LinkRenderer $linkRenderer,
		GroupPermissionsLookup $groupPermissionsLookup,
		HookContainer $hookContainer,
		LinkBatchFactory $linkBatchFactory,
		NamespaceInfo $namespaceInfo,
		ChangeTagsStore $changeTagsStore,
		RowCommentFormatter $rowCommentFormatter,
		IContentHandlerFactory $contentHandlerFactory,
		FormOptions $opts
	) {
		parent::__construct( $context, $linkRenderer );
		$this->groupPermissionsLookup = $groupPermissionsLookup;
		$this->hookRunner = new HookRunner( $hookContainer );
		$this->linkBatchFactory = $linkBatchFactory;
		$this->namespaceInfo = $namespaceInfo;
		$this->changeTagsStore = $changeTagsStore;
		$this->rowCommentFormatter = $rowCommentFormatter;
		$this->contentHandlerFactory = $contentHandlerFactory;
		$this->opts = $opts;
	}

	public function getQueryInfo() {
		$rcQuery = RecentChange::getQueryInfo();

		$conds = [];
		$conds['rc_new'] = 1;

		$username = $this->opts->getValue( 'username' );
		$user = Title::makeTitleSafe( NS_USER, $username );

		$size = abs( intval( $this->opts->getValue( 'size' ) ) );
		if ( $size > 0 ) {
			if ( $this->opts->getValue( 'size-mode' ) === 'max' ) {
				$conds[] = 'page_len <= ' . $size;
			} else {
				$conds[] = 'page_len >= ' . $size;
			}
		}

		if ( $user ) {
			$conds['actor_name'] = $user->getText();
		} elseif ( $this->canAnonymousUsersCreatePages() && $this->opts->getValue( 'hideliu' ) ) {
			# If anons cannot make new pages, don't "exclude logged in users"!
			$conds['actor_user'] = null;
		}

		$conds = array_merge( $conds, $this->getNamespaceCond() );

		# If this user cannot see patrolled edits or they are off, don't do dumb queries!
		if ( $this->opts->getValue( 'hidepatrolled' ) && $this->getUser()->useNPPatrol() ) {
			$conds['rc_patrolled'] = RecentChange::PRC_UNPATROLLED;
		}

		if ( $this->opts->getValue( 'hidebots' ) ) {
			$conds['rc_bot'] = 0;
		}

		if ( $this->opts->getValue( 'hideredirs' ) ) {
			$conds['page_is_redirect'] = 0;
		}

		// Allow changes to the New Pages query
		$tables = array_merge( $rcQuery['tables'], [ 'page' ] );
		$fields = array_merge( $rcQuery['fields'], [
			'length' => 'page_len', 'rev_id' => 'page_latest', 'page_namespace', 'page_title',
			'page_content_model',
		] );
		$join_conds = [ 'page' => [ 'JOIN', 'page_id=rc_cur_id' ] ] + $rcQuery['joins'];

		$this->hookRunner->onSpecialNewpagesConditions(
			$this, $this->opts, $conds, $tables, $fields, $join_conds );

		$info = [
			'tables' => $tables,
			'fields' => $fields,
			'conds' => $conds,
			'options' => [],
			'join_conds' => $join_conds
		];

		// Modify query for tags
		$this->changeTagsStore->modifyDisplayQuery(
			$info['tables'],
			$info['fields'],
			$info['conds'],
			$info['join_conds'],
			$info['options'],
			$this->opts['tagfilter'],
			$this->opts['tagInvert']
		);

		return $info;
	}

	private function canAnonymousUsersCreatePages() {
		return $this->groupPermissionsLookup->groupHasPermission( '*', 'createpage' ) ||
			$this->groupPermissionsLookup->groupHasPermission( '*', 'createtalk' );
	}

	// Based on ContribsPager.php
	private function getNamespaceCond() {
		$namespace = $this->opts->getValue( 'namespace' );
		if ( $namespace === 'all' || $namespace === '' ) {
			return [];
		}

		$namespace = intval( $namespace );
		if ( $namespace < NS_MAIN ) {
			// Negative namespaces are invalid
			return [];
		}

		$invert = $this->opts->getValue( 'invert' );
		$associated = $this->opts->getValue( 'associated' );

		$eq_op = $invert ? '!=' : '=';
		$bool_op = $invert ? 'AND' : 'OR';

		$dbr = $this->getDatabase();
		$selectedNS = $dbr->addQuotes( $namespace );
		if ( !$associated ) {
			return [ "rc_namespace $eq_op $selectedNS" ];
		}

		$associatedNS = $dbr->addQuotes(
			$this->namespaceInfo->getAssociated( $namespace )
		);
		return [
			"rc_namespace $eq_op $selectedNS " .
			$bool_op .
			" rc_namespace $eq_op $associatedNS"
		];
	}

	public function getIndexField() {
		return [ [ 'rc_timestamp', 'rc_id' ] ];
	}

	public function formatRow( $row ) {
		$title = Title::newFromRow( $row );

		// Revision deletion works on revisions,
		// so cast our recent change row to a revision row.
		$revRecord = $this->revisionFromRcResult( $row, $title );

		$classes = [];
		$attribs = [ 'data-mw-revid' => $row->rev_id ];

		$lang = $this->getLanguage();
		$dm = $lang->getDirMark();

		$spanTime = Html::element( 'span', [ 'class' => 'mw-newpages-time' ],
			$lang->userTimeAndDate( $row->rc_timestamp, $this->getUser() )
		);
		$linkRenderer = $this->getLinkRenderer();
		$time = $linkRenderer->makeKnownLink(
			$title,
			new HtmlArmor( $spanTime ),
			[],
			[ 'oldid' => $row->rc_this_oldid ]
		);

		$query = $title->isRedirect() ? [ 'redirect' => 'no' ] : [];

		$plink = $linkRenderer->makeKnownLink(
			$title,
			null,
			[ 'class' => 'mw-newpages-pagename' ],
			$query
		);
		$linkArr = [];
		$linkArr[] = $linkRenderer->makeKnownLink(
			$title,
			$this->msg( 'hist' )->text(),
			[ 'class' => 'mw-newpages-history' ],
			[ 'action' => 'history' ]
		);
		if ( $this->contentHandlerFactory->getContentHandler( $title->getContentModel() )
			->supportsDirectEditing()
		) {
			$linkArr[] = $linkRenderer->makeKnownLink(
				$title,
				$this->msg( 'editlink' )->text(),
				[ 'class' => 'mw-newpages-edit' ],
				[ 'action' => 'edit' ]
			);
		}
		$links = $this->msg( 'parentheses' )->rawParams( $this->getLanguage()
			->pipeList( $linkArr ) )->escaped();

		$length = Html::rawElement(
			'span',
			[ 'class' => 'mw-newpages-length' ],
			$this->msg( 'brackets' )->rawParams(
				$this->msg( 'nbytes' )->numParams( $row->length )->escaped()
			)->escaped()
		);

		$ulink = Linker::revUserTools( $revRecord );
		$rc = RecentChange::newFromRow( $row );
		if ( ChangesList::userCan( $rc, RevisionRecord::DELETED_COMMENT, $this->getAuthority() ) ) {
			$comment = $this->formattedComments[$rc->mAttribs['rc_id']];
		} else {
			$comment = '<span class="comment">' . $this->msg( 'rev-deleted-comment' )->escaped() . '</span>';
		}
		if ( ChangesList::isDeleted( $rc, RevisionRecord::DELETED_COMMENT ) ) {
			$deletedClass = 'history-deleted';
			if ( ChangesList::isDeleted( $rc, RevisionRecord::DELETED_RESTRICTED ) ) {
				$deletedClass .= ' mw-history-suppressed';
			}
			$comment = '<span class="' . $deletedClass . ' comment">' . $comment . '</span>';
		}

		if ( $this->getUser()->useNPPatrol() && !$row->rc_patrolled ) {
			$classes[] = 'not-patrolled';
		}

		# Add a class for zero byte pages
		if ( $row->length == 0 ) {
			$classes[] = 'mw-newpages-zero-byte-page';
		}

		# Tags, if any.
		if ( isset( $row->ts_tags ) ) {
			[ $tagDisplay, $newClasses ] = ChangeTags::formatSummaryRow(
				$row->ts_tags,
				'newpages',
				$this->getContext()
			);
			$classes = array_merge( $classes, $newClasses );
		} else {
			$tagDisplay = '';
		}

		# Display the old title if the namespace/title has been changed
		$oldTitleText = '';
		$oldTitle = Title::makeTitle( $row->rc_namespace, $row->rc_title );

		if ( !$title->equals( $oldTitle ) ) {
			$oldTitleText = $oldTitle->getPrefixedText();
			$oldTitleText = Html::rawElement(
				'span',
				[ 'class' => 'mw-newpages-oldtitle' ],
				$this->msg( 'rc-old-title' )->params( $oldTitleText )->escaped()
			);
		}

		$ret = "{$time} {$dm}{$plink} {$links} {$dm}{$length} {$dm}{$ulink} {$comment} "
			. "{$tagDisplay} {$oldTitleText}";

		// Let extensions add data
		$this->hookRunner->onNewPagesLineEnding(
			$this, $ret, $row, $classes, $attribs );
		$attribs = array_filter( $attribs,
			[ Sanitizer::class, 'isReservedDataAttribute' ],
			ARRAY_FILTER_USE_KEY
		);

		if ( $classes ) {
			$attribs['class'] = $classes;
		}

		return Html::rawElement( 'li', $attribs, $ret ) . "\n";
	}

	/**
	 * @param stdClass $result Result row from recent changes
	 * @param Title $title
	 * @return RevisionRecord
	 */
	protected function revisionFromRcResult( stdClass $result, Title $title ): RevisionRecord {
		$revRecord = new MutableRevisionRecord( $title );
		$revRecord->setVisibility( (int)$result->rc_deleted );

		$user = new UserIdentityValue(
			(int)$result->rc_user,
			$result->rc_user_text
		);
		$revRecord->setUser( $user );

		return $revRecord;
	}

	protected function doBatchLookups() {
		$linkBatch = $this->linkBatchFactory->newLinkBatch();
		foreach ( $this->mResult as $row ) {
			$linkBatch->add( NS_USER, $row->rc_user_text );
			$linkBatch->add( NS_USER_TALK, $row->rc_user_text );
			$linkBatch->add( $row->page_namespace, $row->page_title );
		}
		$linkBatch->execute();

		$this->formattedComments = $this->rowCommentFormatter->formatRows(
			$this->mResult, 'rc_comment', 'rc_namespace', 'rc_title', 'rc_id', true
		);
	}

	protected function getStartBody() {
		return '<ul>';
	}

	protected function getEndBody() {
		return '</ul>';
	}
}

/**
 * Retain the old class name for backwards compatibility.
 * @deprecated since 1.41
 */
class_alias( NewPagesPager::class, 'NewPagesPager' );
