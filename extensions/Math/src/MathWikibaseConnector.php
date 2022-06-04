<?php

namespace MediaWiki\Extension\Math;

use DataValues\StringValue;
use InvalidArgumentException;
use Language;
use MediaWiki\Logger\LoggerFactory;
use MWException;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\StatementList;
use Wikibase\Lib\Store\RevisionedUnresolvedRedirectException;
use Wikibase\Lib\Store\StorageException;

/**
 * A class that connects with the local instance of wikibase to fetch
 * information from single items. There is always only one instance of this class.
 *
 * @see MathWikibaseConnector::getInstance()    to get an instance of the class
 */
class MathWikibaseConnector {
	/**
	 * @var MathWikibaseConfig
	 */
	private $config;

	/**
	 * @param MathWikibaseConfig $config
	 */
	public function __construct( MathWikibaseConfig $config ) {
		$this->config = $config;
	}

	/**
	 * @param string $qid
	 * @param string $langCode the language to fetch data
	 *          (may fallback if requested language does not exist)
	 *
	 * @return MathWikibaseInfo the object may be empty if no information can be fetched.
	 * @throws InvalidArgumentException if the language code does not exist or the given
	 * id does not exist
	 */
	public function fetchWikibaseFromId( $qid, $langCode ) {
		try {
			$lang = Language::factory( $langCode );
		} catch ( MWException $e ) {
			throw new InvalidArgumentException( "Invalid language code specified." );
		}

		$langLookupFactory = $this->config->getLabelLookupFactory();
		$langLookup = $langLookupFactory->newLabelDescriptionLookup( $lang );

		$idParser = $this->config->getIdParser();
		$entityRevisionLookup = $this->config->getEntityRevisionLookup();

		try {
			$entityId = $idParser->parse( $qid ); // exception if the given ID is invalid
			$entityRevision = $entityRevisionLookup->getEntityRevision( $entityId );
		} catch ( EntityIdParsingException $e ) {
			throw new InvalidArgumentException( "Invalid Wikibase ID." );
		} catch ( RevisionedUnresolvedRedirectException | StorageException $e ) {
			throw new InvalidArgumentException( "Non-existing Wikibase ID." );
		}

		if ( !$entityId || !$entityRevision ) {
			throw new InvalidArgumentException( "Non-existing Wikibase ID." );
		}

		$entity = $entityRevision->getEntity();
		$output = new MathWikibaseInfo( $entityId );

		if ( $entity instanceof Item ) {
			$this->fetchLabelDescription( $output, $langLookup );
			$this->fetchStatements( $output, $entity, $langLookup );
			return $output;
		} else { // we only allow Wikibase items
			throw new InvalidArgumentException( "The specified Wikibase ID does not represented an item." );
		}
	}

	/**
	 * Fetches only label and description from an entity.
	 * @param MathWikibaseInfo $output the entity id of the entity
	 * @param LabelDescriptionLookup $langLookup a lookup handler to fetch right languages
	 * @return MathWikibaseInfo filled up with label and description
	 */
	private function fetchLabelDescription(
		MathWikibaseInfo $output,
		LabelDescriptionLookup $langLookup ) {
		$label = $langLookup->getLabel( $output->getId() );
		$desc = $langLookup->getDescription( $output->getId() );

		if ( $label ) {
			$output->setLabel( $label->getText() );
		}

		if ( $desc ) {
			$output->setDescription( $desc->getText() );
		}

		return $output;
	}

	/**
	 * Fetches 'has part' statements from a given item element with a defined lookup object for
	 * the languages.
	 * @param MathWikibaseInfo $output the output element
	 * @param Item $item item to fetch statements from
	 * @param LabelDescriptionLookup $langLookup
	 * @return MathWikibaseInfo the updated $output object
	 */
	private function fetchStatements(
		MathWikibaseInfo $output,
		Item $item,
		LabelDescriptionLookup $langLookup ) {
		$statements = $item->getStatements();

		$hasPartStatements = $statements->getByPropertyId( $this->config->getPropertyIdHasPart() );
		$this->fetchHasPartSnaks( $output, $hasPartStatements, $langLookup );

		$symbolStatement = $statements->getByPropertyId( $this->config->getPropertyIdDefiningFormula() );
		if ( $symbolStatement->count() < 1 ) { // if it's not a formula, it might be a symbol
			$symbolStatement = $statements->getByPropertyId( $this->config->getPropertyIdQuantitySymbol() );
		}
		$this->fetchSymbol( $output, $symbolStatement );
		return $output;
	}

	/**
	 * Fetches the symbol or defining formula from a statement list and adds the symbol to the
	 * given info object
	 * @param MathWikibaseInfo $output
	 * @param StatementList $statements
	 * @return MathWikibaseInfo updated object
	 */
	private function fetchSymbol( MathWikibaseInfo $output, StatementList $statements ) {
		foreach ( $statements as $statement ) {
			$snak = $statement->getMainSnak();
			if ( $snak instanceof PropertyValueSnak && $this->isQualifierDefinien( $snak ) ) {
				$dataVal = $snak->getDataValue();
				$symbol = new StringValue( $dataVal->getValue() );
				$output->setSymbol( $symbol );
				return $output;
			}
		}

		return $output;
	}

	/**
	 * Fetches single snaks from 'has part' statements
	 *
	 * @param MathWikibaseInfo $output
	 * @param StatementList $statements the 'has part' statements
	 * @param LabelDescriptionLookup $langLookup
	 * @return MathWikibaseInfo
	 * @todo refactor this method once Wikibase has a more convenient way to handle snaks
	 */
	private function fetchHasPartSnaks(
		MathWikibaseInfo $output,
		StatementList $statements,
		LabelDescriptionLookup $langLookup ) {
		foreach ( $statements as $statement ) {
			$snaks = $statement->getAllSnaks();
			$innerInfo = null;
			$symbol = null;

			foreach ( $snaks as $snak ) {
				if ( $snak instanceof PropertyValueSnak ) {
					if ( $this->isQualifierDefinien( $snak ) ) {
						$dataVal = $snak->getDataValue();
						$symbol = new StringValue( $dataVal->getValue() );
					} elseif ( $snak->getPropertyId()->equals( $this->config->getPropertyIdHasPart() ) ) {
						$dataVal = $snak->getDataValue();
						$entityIdValue = $dataVal->getValue();
						if ( $entityIdValue instanceof EntityIdValue ) {
							$innerEntityId = $entityIdValue->getEntityId();
							$innerInfo = new MathWikibaseInfo( $innerEntityId );
							$this->fetchLabelDescription( $innerInfo, $langLookup );
							$url = $this->fetchPageUrl( $innerEntityId );
							if ( $url ) {
								$innerInfo->setUrl( $url );
							}
						}
					}
				}
			}

			if ( $innerInfo && $symbol ) {
				$innerInfo->setSymbol( $symbol );
				$output->addHasPartElement( $innerInfo );
			}
		}

		return $output;
	}

	/**
	 * Fetch the page url for a given entity id.
	 * @param EntityId $entityId
	 * @return string|false
	 */
	private function fetchPageUrl( EntityId $entityId ) {
		try {
			$entityRevisionLookup = $this->config->getEntityRevisionLookup();
			$entityRevision = $entityRevisionLookup->getEntityRevision( $entityId );
			$innerEntity = $entityRevision->getEntity();
			if ( $innerEntity instanceof Item ) {
				if ( $this->config->hasSite() ) {
					$site = $this->config->getSite();
					$globalID = $site->getGlobalId();
					if ( $innerEntity->hasLinkToSite( $globalID ) ) {
						$siteLink = $innerEntity->getSiteLink( $globalID );
						return $site->getPageUrl( $siteLink->getPageName() );
					}
				}
			}
			return false;
		} catch ( StorageException $e ) {
			$logger = LoggerFactory::getInstance( 'Math' );
			$logger->warning(
				"Cannot fetch URL for EntityId " . $entityId . ". Reason: " . $e->getMessage()
			);
			return false;
		}
	}

	/**
	 * @param Snak $snak
	 * @return bool true if the given snak is either a defining formula or a quantity symbol
	 */
	private function isQualifierDefinien( Snak $snak ) {
		return $snak->getPropertyId()->equals( $this->config->getPropertyIdQuantitySymbol() ) ||
			$snak->getPropertyId()->equals( $this->config->getPropertyIdDefiningFormula() );
	}

	/**
	 * @param string $qID
	 * @return string
	 */
	public static function buildURL( $qID ) {
		return WikibaseClient::getRepoLinker()
			->getEntityUrl( new ItemId( $qID ) );
	}
}
