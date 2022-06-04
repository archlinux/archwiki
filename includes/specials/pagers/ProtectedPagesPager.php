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

use MediaWiki\Cache\LinkBatchFactory;
use MediaWiki\CommentFormatter\RowCommentFormatter;
use MediaWiki\Linker\LinkRenderer;
use Wikimedia\Rdbms\ILoadBalancer;

class ProtectedPagesPager extends TablePager {

	public $mConds;
	private $type, $level, $namespace, $sizetype, $size, $indefonly, $cascadeonly, $noredirect;

	/** @var CommentStore */
	private $commentStore;

	/** @var LinkBatchFactory */
	private $linkBatchFactory;

	/** @var UserCache */
	private $userCache;

	/** @var RowCommentFormatter */
	private $rowCommentFormatter;

	/** @var string[] */
	private $formattedComments = [];

	/**
	 * @param IContextSource $context
	 * @param CommentStore $commentStore
	 * @param LinkBatchFactory $linkBatchFactory
	 * @param LinkRenderer $linkRenderer
	 * @param ILoadBalancer $loadBalancer
	 * @param RowCommentFormatter $rowCommentFormatter
	 * @param UserCache $userCache
	 * @param array $conds
	 * @param string $type
	 * @param string $level
	 * @param int $namespace
	 * @param string $sizetype
	 * @param int $size
	 * @param bool $indefonly
	 * @param bool $cascadeonly
	 * @param bool $noredirect
	 */
	public function __construct(
		IContextSource $context,
		CommentStore $commentStore,
		LinkBatchFactory $linkBatchFactory,
		LinkRenderer $linkRenderer,
		ILoadBalancer $loadBalancer,
		RowCommentFormatter $rowCommentFormatter,
		UserCache $userCache,
		$conds,
		$type,
		$level,
		$namespace,
		$sizetype,
		$size,
		$indefonly,
		$cascadeonly,
		$noredirect
	) {
		// Set database before parent constructor to avoid setting it there with wfGetDB
		$this->mDb = $loadBalancer->getConnectionRef( ILoadBalancer::DB_REPLICA );
		parent::__construct( $context, $linkRenderer );
		$this->commentStore = $commentStore;
		$this->linkBatchFactory = $linkBatchFactory;
		$this->rowCommentFormatter = $rowCommentFormatter;
		$this->userCache = $userCache;
		$this->mConds = $conds;
		$this->type = $type ?: 'edit';
		$this->level = $level;
		$this->namespace = $namespace;
		$this->sizetype = $sizetype;
		$this->size = intval( $size );
		$this->indefonly = (bool)$indefonly;
		$this->cascadeonly = (bool)$cascadeonly;
		$this->noredirect = (bool)$noredirect;
	}

	public function preprocessResults( $result ) {
		# Do a link batch query
		$lb = $this->linkBatchFactory->newLinkBatch();
		$userids = [];

		foreach ( $result as $row ) {
			$lb->add( $row->page_namespace, $row->page_title );
			if ( $row->actor_user !== null ) {
				$userids[] = $row->actor_user;
			}
		}

		// fill LinkBatch with user page and user talk
		if ( count( $userids ) ) {
			$this->userCache->doQuery( $userids, [], __METHOD__ );
			foreach ( $userids as $userid ) {
				$name = $this->userCache->getProp( $userid, 'name' );
				if ( $name !== false ) {
					$lb->add( NS_USER, $name );
					$lb->add( NS_USER_TALK, $name );
				}
			}
		}

		$lb->execute();

		// Format the comments
		$this->formattedComments = $this->rowCommentFormatter->formatRows( $result, 'log_comment' );
	}

	protected function getFieldNames() {
		static $headers = null;

		if ( $headers == [] ) {
			$headers = [
				'log_timestamp' => 'protectedpages-timestamp',
				'pr_page' => 'protectedpages-page',
				'pr_expiry' => 'protectedpages-expiry',
				'actor_user' => 'protectedpages-performer',
				'pr_params' => 'protectedpages-params',
				'log_comment' => 'protectedpages-reason',
			];
			foreach ( $headers as $key => $val ) {
				$headers[$key] = $this->msg( $val )->text();
			}
		}

		return $headers;
	}

	/**
	 * @param string $field
	 * @param string|null $value
	 * @return string HTML
	 * @throws MWException
	 */
	public function formatValue( $field, $value ) {
		/** @var stdClass $row */
		$row = $this->mCurrentRow;
		$linkRenderer = $this->getLinkRenderer();

		switch ( $field ) {
			case 'log_timestamp':
				// when timestamp is null, this is a old protection row
				if ( $value === null ) {
					$formatted = Html::rawElement(
						'span',
						[ 'class' => 'mw-protectedpages-unknown' ],
						$this->msg( 'protectedpages-unknown-timestamp' )->escaped()
					);
				} else {
					$formatted = htmlspecialchars( $this->getLanguage()->userTimeAndDate(
						$value, $this->getUser() ) );
				}
				break;

			case 'pr_page':
				$title = Title::makeTitleSafe( $row->page_namespace, $row->page_title );
				if ( !$title ) {
					$formatted = Html::element(
						'span',
						[ 'class' => 'mw-invalidtitle' ],
						Linker::getInvalidTitleDescription(
							$this->getContext(),
							$row->page_namespace,
							$row->page_title
						)
					);
				} else {
					$formatted = $linkRenderer->makeLink( $title );
				}
				if ( $row->page_len !== null ) {
					$formatted .= $this->getLanguage()->getDirMark() .
						' ' . Html::rawElement(
							'span',
							[ 'class' => 'mw-protectedpages-length' ],
							Linker::formatRevisionSize( $row->page_len )
						);
				}
				break;

			case 'pr_expiry':
				$formatted = htmlspecialchars( $this->getLanguage()->formatExpiry(
					$value, /* User preference timezone */true, 'infinity', $this->getUser() ) );
				$title = Title::makeTitleSafe( $row->page_namespace, $row->page_title );
				if ( $title && $this->getAuthority()->isAllowed( 'protect' ) ) {
					$changeProtection = $linkRenderer->makeKnownLink(
						$title,
						$this->msg( 'protect_change' )->text(),
						[],
						[ 'action' => 'unprotect' ]
					);
					$formatted .= ' ' . Html::rawElement(
							'span',
							[ 'class' => 'mw-protectedpages-actions' ],
							$this->msg( 'parentheses' )->rawParams( $changeProtection )->escaped()
						);
				}
				break;

			case 'actor_user':
				// when timestamp is null, this is a old protection row
				if ( $row->log_timestamp === null ) {
					$formatted = Html::rawElement(
						'span',
						[ 'class' => 'mw-protectedpages-unknown' ],
						$this->msg( 'protectedpages-unknown-performer' )->escaped()
					);
				} else {
					$username = $row->actor_name;
					if ( LogEventsList::userCanBitfield(
						$row->log_deleted,
						LogPage::DELETED_USER,
						$this->getUser()
					) ) {
						$formatted = Linker::userLink( (int)$value, $username )
							. Linker::userToolLinks( (int)$value, $username );
					} else {
						$formatted = $this->msg( 'rev-deleted-user' )->escaped();
					}
					if ( LogEventsList::isDeleted( $row, LogPage::DELETED_USER ) ) {
						$formatted = '<span class="history-deleted">' . $formatted . '</span>';
					}
				}
				break;

			case 'pr_params':
				$params = [];
				// Messages: restriction-level-sysop, restriction-level-autoconfirmed
				$params[] = $this->msg( 'restriction-level-' . $row->pr_level )->escaped();
				if ( $row->pr_cascade ) {
					$params[] = $this->msg( 'protect-summary-cascade' )->escaped();
				}
				$formatted = $this->getLanguage()->commaList( $params );
				break;

			case 'log_comment':
				// when timestamp is null, this is an old protection row
				if ( $row->log_timestamp === null ) {
					$formatted = Html::rawElement(
						'span',
						[ 'class' => 'mw-protectedpages-unknown' ],
						$this->msg( 'protectedpages-unknown-reason' )->escaped()
					);
				} else {
					if ( LogEventsList::userCanBitfield(
						$row->log_deleted,
						LogPage::DELETED_COMMENT,
						$this->getUser()
					) ) {
						$formatted = $this->formattedComments[$this->getResultOffset()];
					} else {
						$formatted = $this->msg( 'rev-deleted-comment' )->escaped();
					}
					if ( LogEventsList::isDeleted( $row, LogPage::DELETED_COMMENT ) ) {
						$formatted = '<span class="history-deleted">' . $formatted . '</span>';
					}
				}
				break;

			default:
				throw new MWException( "Unknown field '$field'" );
		}

		return $formatted;
	}

	public function getQueryInfo() {
		$dbr = $this->getDatabase();
		$conds = $this->mConds;
		$conds[] = 'pr_expiry > ' . $dbr->addQuotes( $dbr->timestamp() ) .
			' OR pr_expiry IS NULL';
		$conds[] = 'page_id=pr_page';
		$conds[] = 'pr_type=' . $dbr->addQuotes( $this->type );

		if ( $this->sizetype == 'min' ) {
			$conds[] = 'page_len>=' . $this->size;
		} elseif ( $this->sizetype == 'max' ) {
			$conds[] = 'page_len<=' . $this->size;
		}

		if ( $this->indefonly ) {
			$infinity = $dbr->addQuotes( $dbr->getInfinity() );
			$conds[] = "pr_expiry = $infinity OR pr_expiry IS NULL";
		}
		if ( $this->cascadeonly ) {
			$conds[] = 'pr_cascade = 1';
		}
		if ( $this->noredirect ) {
			$conds[] = 'page_is_redirect = 0';
		}

		if ( $this->level ) {
			$conds[] = 'pr_level=' . $dbr->addQuotes( $this->level );
		}
		if ( $this->namespace !== null ) {
			$conds[] = 'page_namespace=' . $dbr->addQuotes( $this->namespace );
		}

		$commentQuery = $this->commentStore->getJoin( 'log_comment' );

		return [
			'tables' => [
				'page', 'page_restrictions', 'log_search',
				'logparen' => [ 'logging', 'actor' ] + $commentQuery['tables'],
			],
			'fields' => [
				'pr_id',
				'page_namespace',
				'page_title',
				'page_len',
				'pr_type',
				'pr_level',
				'pr_expiry',
				'pr_cascade',
				'log_timestamp',
				'log_deleted',
				'actor_name',
				'actor_user'
			] + $commentQuery['fields'],
			'conds' => $conds,
			'join_conds' => [
				'log_search' => [
					'LEFT JOIN', [
						'ls_field' => 'pr_id', 'ls_value = ' . $dbr->buildStringCast( 'pr_id' )
					]
				],
				'logparen' => [
					'LEFT JOIN', [
						'ls_log_id = log_id'
					]
				],
				'actor' => [
					'JOIN', [
						'actor_id=log_actor'
					]
				]
			] + $commentQuery['joins']
		];
	}

	protected function getTableClass() {
		return parent::getTableClass() . ' mw-protectedpages';
	}

	public function getIndexField() {
		return 'pr_id';
	}

	public function getDefaultSort() {
		return 'pr_id';
	}

	protected function isFieldSortable( $field ) {
		// no index for sorting exists
		return false;
	}
}
