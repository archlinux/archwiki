<?php
namespace MediaWiki\Search;

use ILanguageConverter;
use ISearchResultSet;
use Language;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Title\Title;
use MediaWiki\User\UserNameUtils;
use RepoGroup;
use SearchNearMatchResultSet;
use SpecialPage;

/**
 * Service implementation of near match title search.
 */
class TitleMatcher {
	/**
	 * @internal For use by ServiceWiring.
	 * @var string[]
	 */
	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::EnableSearchContributorsByIP,
	];

	/**
	 * @var ServiceOptions
	 */
	private $options;

	/**
	 * Current language
	 * @var Language
	 */
	private $language;

	/**
	 * Current language converter
	 * @var ILanguageConverter
	 */
	private $languageConverter;

	/**
	 * @var HookRunner
	 */
	private $hookRunner;

	/**
	 * @var WikiPageFactory
	 */
	private $wikiPageFactory;

	/**
	 * @var UserNameUtils
	 */
	private $userNameUtils;

	/**
	 * @var RepoGroup
	 */
	private $repoGroup;

	/**
	 * @param ServiceOptions $options
	 * @param Language $contentLanguage
	 * @param LanguageConverterFactory $languageConverterFactory
	 * @param HookContainer $hookContainer
	 * @param WikiPageFactory $wikiPageFactory
	 * @param UserNameUtils $userNameUtils
	 * @param RepoGroup $repoGroup
	 */
	public function __construct(
		ServiceOptions $options,
		Language $contentLanguage,
		LanguageConverterFactory $languageConverterFactory,
		HookContainer $hookContainer,
		WikiPageFactory $wikiPageFactory,
		UserNameUtils $userNameUtils,
		RepoGroup $repoGroup
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->options = $options;

		$this->language = $contentLanguage;
		$this->languageConverter = $languageConverterFactory->getLanguageConverter( $contentLanguage );
		$this->hookRunner = new HookRunner( $hookContainer );
		$this->wikiPageFactory = $wikiPageFactory;
		$this->userNameUtils = $userNameUtils;
		$this->repoGroup = $repoGroup;
	}

	/**
	 * If an exact title match can be found, or a very slightly close match,
	 * return the title. If no match, returns NULL.
	 *
	 * @param string $searchterm
	 * @return Title
	 */
	public function getNearMatch( $searchterm ) {
		$title = $this->getNearMatchInternal( $searchterm );

		$this->hookRunner->onSearchGetNearMatchComplete( $searchterm, $title );
		return $title;
	}

	/**
	 * Do a near match (see SearchEngine::getNearMatch) and wrap it into a
	 * ISearchResultSet.
	 *
	 * @param string $searchterm
	 * @return ISearchResultSet
	 */
	public function getNearMatchResultSet( $searchterm ) {
		return new SearchNearMatchResultSet( $this->getNearMatch( $searchterm ) );
	}

	/**
	 * Really find the title match.
	 * @param string $searchterm
	 * @return null|Title
	 */
	protected function getNearMatchInternal( $searchterm ) {
		$allSearchTerms = [ $searchterm ];

		if ( $this->languageConverter->hasVariants() ) {
			$allSearchTerms = array_unique( array_merge(
				$allSearchTerms,
				$this->languageConverter->autoConvertToAllVariants( $searchterm )
			) );
		}

		$titleResult = null;
		if ( !$this->hookRunner->onSearchGetNearMatchBefore( $allSearchTerms, $titleResult ) ) {
			return $titleResult;
		}

		// Most of our handling here deals with finding a valid title for the search term,
		// but almost anything starting with '#' is "valid" and points to Main_Page#searchterm.
		// Rather than doing something completely wrong, do nothing.
		if ( $searchterm === '' || $searchterm[0] === '#' ) {
			return null;
		}

		foreach ( $allSearchTerms as $term ) {
			# Exact match? No need to look further.
			$title = Title::newFromText( $term );
			if ( $title === null ) {
				return null;
			}

			# Try files if searching in the Media: namespace
			if ( $title->getNamespace() === NS_MEDIA ) {
				$title = Title::makeTitle( NS_FILE, $title->getText() );
			}

			if ( $title->isSpecialPage() || $title->isExternal() || $title->exists() ) {
				return $title;
			}

			# See if it still otherwise has content is some sensible sense
			if ( $title->canExist() ) {
				$page = $this->wikiPageFactory->newFromTitle( $title );
				if ( $page->hasViewableContent() ) {
					return $title;
				}
			}

			if ( !$this->hookRunner->onSearchAfterNoDirectMatch( $term, $title ) ) {
				return $title;
			}

			# Now try all lower case (i.e. first letter capitalized)
			$title = Title::newFromText( $this->language->lc( $term ) );
			if ( $title && $title->exists() ) {
				return $title;
			}

			# Now try capitalized string
			$title = Title::newFromText( $this->language->ucwords( $term ) );
			if ( $title && $title->exists() ) {
				return $title;
			}

			# Now try all upper case
			$title = Title::newFromText( $this->language->uc( $term ) );
			if ( $title && $title->exists() ) {
				return $title;
			}

			# Now try Word-Caps-Breaking-At-Word-Breaks, for hyphenated names etc
			$title = Title::newFromText( $this->language->ucwordbreaks( $term ) );
			if ( $title && $title->exists() ) {
				return $title;
			}

			// Give hooks a chance at better match variants
			$title = null;
			// @phan-suppress-next-line PhanTypeMismatchArgument Type mismatch on pass-by-ref args
			if ( !$this->hookRunner->onSearchGetNearMatch( $term, $title ) ) {
				return $title;
			}
		}

		$title = Title::newFromText( $searchterm );

		# Entering an IP address goes to the contributions page
		if ( $this->options->get( MainConfigNames::EnableSearchContributorsByIP ) ) {
			if ( ( $title->getNamespace() === NS_USER && $this->userNameUtils->isIP( $title->getText() ) )
				|| $this->userNameUtils->isIP( trim( $searchterm ) ) ) {
				return SpecialPage::getTitleFor( 'Contributions', $title->getDBkey() );
			}
		}

		# Entering a user goes to the user page whether it's there or not
		if ( $title->getNamespace() === NS_USER ) {
			return $title;
		}

		# Go to images that exist even if there's no local page.
		# There may have been a funny upload, or it may be on a shared
		# file repository such as Wikimedia Commons.
		if ( $title->getNamespace() === NS_FILE ) {
			$image = $this->repoGroup->findFile( $title );
			if ( $image ) {
				return $title;
			}
		}

		# MediaWiki namespace? Page may be "implied" if not customized.
		# Just return it, with caps forced as the message system likes it.
		if ( $title->getNamespace() === NS_MEDIAWIKI ) {
			return Title::makeTitle( NS_MEDIAWIKI, $this->language->ucfirst( $title->getText() ) );
		}

		# Quoted term? Try without the quotes...
		$matches = [];
		if ( preg_match( '/^"([^"]+)"$/', $searchterm, $matches ) ) {
			return $this->getNearMatch( $matches[1] );
		}

		return null;
	}
}

/**
 * @deprecated since 1.40, remove in 1.41.
 */
class_alias( TitleMatcher::class, 'SearchNearMatcher' );
