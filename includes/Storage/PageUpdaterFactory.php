<?php
/**
 * A factory for DerivedPageDataUpdater instances.
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

namespace MediaWiki\Storage;

use JobQueueGroup;
use Language;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Content\Transform\ContentTransformer;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\Parsoid\ParsoidOutputAccess;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRenderer;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRoleRegistry;
use MediaWiki\User\TalkPageNotificationManager;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserNameUtils;
use MessageCache;
use ParserCache;
use Psr\Log\LoggerInterface;
use TitleFormatter;
use WANObjectCache;
use Wikimedia\Rdbms\ILBFactory;
use WikiPage;

/**
 * A factory for PageUpdater instances.
 *
 * @since 1.37
 * @ingroup Page
 */
class PageUpdaterFactory {

	/**
	 * Options that have to be present in the ServiceOptions object passed to the constructor.
	 * @note must include PageUpdater::CONSTRUCTOR_OPTIONS
	 * @internal
	 */
	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::ArticleCountMethod,
		MainConfigNames::RCWatchCategoryMembership,
		MainConfigNames::PageCreationLog,
		MainConfigNames::UseAutomaticEditSummaries,
		MainConfigNames::ManualRevertSearchRadius,
		MainConfigNames::UseRCPatrol,
		MainConfigNames::ParsoidCacheConfig,
	];

	/** @var RevisionStore */
	private $revisionStore;

	/** @var RevisionRenderer */
	private $revisionRenderer;

	/** @var SlotRoleRegistry */
	private $slotRoleRegistry;

	/** @var ParserCache */
	private $parserCache;

	/** @var JobQueueGroup */
	private $jobQueueGroup;

	/** @var MessageCache */
	private $messageCache;

	/** @var Language */
	private $contLang;

	/** @var ILBFactory */
	private $loadbalancerFactory;

	/** @var IContentHandlerFactory */
	private $contentHandlerFactory;

	/** @var HookContainer */
	private $hookContainer;

	/** @var EditResultCache */
	private $editResultCache;

	/** @var UserNameUtils */
	private $userNameUtils;

	/** @var LoggerInterface */
	private $logger;

	/** @var ServiceOptions */
	private $options;

	/** @var UserEditTracker */
	private $userEditTracker;

	/** @var UserGroupManager */
	private $userGroupManager;

	/** @var TitleFormatter */
	private $titleFormatter;

	/** @var ContentTransformer */
	private $contentTransformer;

	/** @var PageEditStash */
	private $pageEditStash;

	/** @var TalkPageNotificationManager */
	private $talkPageNotificationManager;

	/** @var WANObjectCache */
	private $mainWANObjectCache;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var string[] */
	private $softwareTags;

	/** @var ParsoidOutputAccess */
	private $parsoidOutputAccess;

	/**
	 * @param RevisionStore $revisionStore
	 * @param RevisionRenderer $revisionRenderer
	 * @param SlotRoleRegistry $slotRoleRegistry
	 * @param ParserCache $parserCache
	 * @param ParsoidOutputAccess $parsoidOutputAccess
	 * @param JobQueueGroup $jobQueueGroup
	 * @param MessageCache $messageCache
	 * @param Language $contLang
	 * @param ILBFactory $loadbalancerFactory
	 * @param IContentHandlerFactory $contentHandlerFactory
	 * @param HookContainer $hookContainer
	 * @param EditResultCache $editResultCache
	 * @param UserNameUtils $userNameUtils
	 * @param LoggerInterface $logger
	 * @param ServiceOptions $options
	 * @param UserEditTracker $userEditTracker
	 * @param UserGroupManager $userGroupManager
	 * @param TitleFormatter $titleFormatter
	 * @param ContentTransformer $contentTransformer
	 * @param PageEditStash $pageEditStash
	 * @param TalkPageNotificationManager $talkPageNotificationManager
	 * @param WANObjectCache $mainWANObjectCache
	 * @param PermissionManager $permissionManager
	 * @param WikiPageFactory $wikiPageFactory
	 * @param string[] $softwareTags
	 */
	public function __construct(
		RevisionStore $revisionStore,
		RevisionRenderer $revisionRenderer,
		SlotRoleRegistry $slotRoleRegistry,
		ParserCache $parserCache,
		ParsoidOutputAccess $parsoidOutputAccess,
		JobQueueGroup $jobQueueGroup,
		MessageCache $messageCache,
		Language $contLang,
		ILBFactory $loadbalancerFactory,
		IContentHandlerFactory $contentHandlerFactory,
		HookContainer $hookContainer,
		EditResultCache $editResultCache,
		UserNameUtils $userNameUtils,
		LoggerInterface $logger,
		ServiceOptions $options,
		UserEditTracker $userEditTracker,
		UserGroupManager $userGroupManager,
		TitleFormatter $titleFormatter,
		ContentTransformer $contentTransformer,
		PageEditStash $pageEditStash,
		TalkPageNotificationManager $talkPageNotificationManager,
		WANObjectCache $mainWANObjectCache,
		PermissionManager $permissionManager,
		WikiPageFactory $wikiPageFactory,
		array $softwareTags
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$this->revisionStore = $revisionStore;
		$this->revisionRenderer = $revisionRenderer;
		$this->slotRoleRegistry = $slotRoleRegistry;
		$this->parserCache = $parserCache;
		$this->parsoidOutputAccess = $parsoidOutputAccess;
		$this->jobQueueGroup = $jobQueueGroup;
		$this->messageCache = $messageCache;
		$this->contLang = $contLang;
		$this->loadbalancerFactory = $loadbalancerFactory;
		$this->contentHandlerFactory = $contentHandlerFactory;
		$this->hookContainer = $hookContainer;
		$this->editResultCache = $editResultCache;
		$this->userNameUtils = $userNameUtils;
		$this->logger = $logger;
		$this->options = $options;
		$this->userEditTracker = $userEditTracker;
		$this->userGroupManager = $userGroupManager;
		$this->titleFormatter = $titleFormatter;
		$this->contentTransformer = $contentTransformer;
		$this->pageEditStash = $pageEditStash;
		$this->talkPageNotificationManager = $talkPageNotificationManager;
		$this->mainWANObjectCache = $mainWANObjectCache;
		$this->permissionManager = $permissionManager;
		$this->softwareTags = $softwareTags;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * Return a PageUpdater for building an update to a page.
	 *
	 * @internal For now, most code should keep using WikiPage::newPageUpdater() instead.
	 * @note We can only start using this method everywhere when WikiPage::prepareContentForEdit()
	 * and WikiPage::getCurrentUpdate() have been removed. For now, the WikiPage instance is
	 * used to make the state of an ongoing edit available to hook handlers.
	 *
	 * @param PageIdentity $page
	 * @param UserIdentity $user
	 *
	 * @return PageUpdater
	 * @since 1.37
	 */
	public function newPageUpdater(
		PageIdentity $page,
		UserIdentity $user
	): PageUpdater {
		$page = $this->wikiPageFactory->newFromTitle( $page );

		return $this->newPageUpdaterForDerivedPageDataUpdater(
			$page,
			$user,
			$this->newDerivedPageDataUpdater( $page )
		);
	}

	/**
	 * Return a PageUpdater for building an update to a page, reusing the state of
	 * an existing DerivedPageDataUpdater.
	 *
	 * @param WikiPage $page
	 * @param UserIdentity $user
	 * @param DerivedPageDataUpdater $derivedPageDataUpdater
	 *
	 * @return PageUpdater
	 * @internal needed by WikiPage to back the WikiPage::newPageUpdater method.
	 *
	 * @since 1.37
	 */
	public function newPageUpdaterForDerivedPageDataUpdater(
		WikiPage $page,
		UserIdentity $user,
		DerivedPageDataUpdater $derivedPageDataUpdater
	): PageUpdater {
		$pageUpdater = new PageUpdater(
			$user,
			$page, // NOTE: eventually, PageUpdater should not know about WikiPage
			$derivedPageDataUpdater,
			$this->loadbalancerFactory->getMainLB(),
			$this->revisionStore,
			$this->slotRoleRegistry,
			$this->contentHandlerFactory,
			$this->hookContainer,
			$this->userEditTracker,
			$this->userGroupManager,
			$this->titleFormatter,
			new ServiceOptions(
				PageUpdater::CONSTRUCTOR_OPTIONS,
				$this->options
			),
			$this->softwareTags,
			$this->logger
		);

		$pageUpdater->setUsePageCreationLog(
			$this->options->get( MainConfigNames::PageCreationLog ) );
		$pageUpdater->setUseAutomaticEditSummaries(
			$this->options->get( MainConfigNames::UseAutomaticEditSummaries )
		);

		return $pageUpdater;
	}

	/**
	 * @param WikiPage $page
	 *
	 * @return DerivedPageDataUpdater
	 * @internal Needed by WikiPage to back the deprecated prepareContentForEdit() method.
	 * @note Avoid direct usage of DerivedPageDataUpdater.
	 * @see docs/pageupdater.md for more information.
	 */
	public function newDerivedPageDataUpdater( WikiPage $page ): DerivedPageDataUpdater {
		$derivedDataUpdater = new DerivedPageDataUpdater(
			$this->options,
			$page, // NOTE: eventually, PageUpdater should not know about WikiPage
			$this->revisionStore,
			$this->revisionRenderer,
			$this->slotRoleRegistry,
			$this->parserCache,
			$this->parsoidOutputAccess,
			$this->jobQueueGroup,
			$this->messageCache,
			$this->contLang,
			$this->loadbalancerFactory,
			$this->contentHandlerFactory,
			$this->hookContainer,
			$this->editResultCache,
			$this->userNameUtils,
			$this->contentTransformer,
			$this->pageEditStash,
			$this->talkPageNotificationManager,
			$this->mainWANObjectCache,
			$this->permissionManager
		);

		$derivedDataUpdater->setLogger( $this->logger );
		$derivedDataUpdater->setArticleCountMethod(
			$this->options->get( MainConfigNames::ArticleCountMethod ) );
		$derivedDataUpdater->setRcWatchCategoryMembership(
			$this->options->get( MainConfigNames::RCWatchCategoryMembership )
		);

		return $derivedDataUpdater;
	}

}
