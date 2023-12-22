<?php
/**
 * Gadgets extension - lets users select custom javascript gadgets
 *
 * For more info see https://www.mediawiki.org/wiki/Extension:Gadgets
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Kinzler, brightbyte.de
 * @copyright © 2007 Daniel Kinzler
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\Gadgets;

use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentity;
use ResourceLoader;
use Skin;

/**
 * Wrapper for one gadget.
 */
class Gadget {
	/**
	 * Increment this when changing class structure
	 */
	public const GADGET_CLASS_VERSION = 15;

	public const CACHE_TTL = 86400;

	/** @var string[] */
	private $scripts = [];
	/** @var string[] */
	private $styles = [];
	/** @var string[] */
	private $datas = [];
	/** @var string[] */
	private $dependencies = [];
	/** @var string[] */
	private $peers = [];
	/** @var string[] */
	private $messages = [];
	/** @var string|null */
	private $name;
	/** @var string|null */
	private $definition;
	/** @var bool */
	private $resourceLoaded = false;
	/** @var bool */
	private $requiresES6 = false;
	/** @var string[] */
	private $requiredRights = [];
	/** @var string[] */
	private $requiredActions = [];
	/** @var string[] */
	private $requiredSkins = [];
	/** @var int[] */
	private $requiredNamespaces = [];
	/** @var string[] */
	private $requiredContentModels = [];
	/** @var string[] used in Gadget::isTargetSupported */
	private $targets = [ 'desktop', 'mobile' ];
	/** @var bool */
	private $onByDefault = false;
	/** @var bool */
	private $hidden = false;
	/** @var bool */
	private $package = false;
	/** @var string */
	private $type = '';
	/** @var string|null */
	private $category;
	/** @var bool */
	private $supportsUrlLoad = false;

	public function __construct( array $options ) {
		foreach ( $options as $member => $option ) {
			switch ( $member ) {
				case 'scripts':
				case 'styles':
				case 'datas':
				case 'dependencies':
				case 'peers':
				case 'messages':
				case 'name':
				case 'definition':
				case 'resourceLoaded':
				case 'requiresES6':
				case 'requiredRights':
				case 'requiredActions':
				case 'requiredSkins':
				case 'requiredNamespaces':
				case 'requiredContentModels':
				case 'targets':
				case 'onByDefault':
				case 'type':
				case 'hidden':
				case 'package':
				case 'category':
				case 'supportsUrlLoad':
					$this->{$member} = $option;
					break;
				default:
					throw new InvalidArgumentException( "Unrecognized '$member' parameter" );
			}
		}
	}

	/**
	 * Create a serialized array based on the metadata in a GadgetDefinitionContent object,
	 * from which a Gadget object can be constructed.
	 *
	 * @param string $id
	 * @param array $data
	 * @return array
	 */
	public static function serializeDefinition( string $id, array $data ): array {
		$prefixGadgetNs = static function ( $page ) {
			return 'Gadget:' . $page;
		};
		return [
			'category' => $data['settings']['category'],
			'datas' => array_map( $prefixGadgetNs, $data['module']['datas'] ),
			'dependencies' => $data['module']['dependencies'],
			'hidden' => $data['settings']['hidden'],
			'messages' => $data['module']['messages'],
			'name' => $id,
			'onByDefault' => $data['settings']['default'],
			'package' => $data['settings']['package'],
			'peers' => $data['module']['peers'],
			'requiredActions' => $data['settings']['actions'],
			'requiredContentModels' => $data['settings']['contentModels'],
			'requiredNamespaces' => $data['settings']['namespaces'],
			'requiredRights' => $data['settings']['rights'],
			'requiredSkins' => $data['settings']['skins'],
			'requiresES6' => $data['settings']['requiresES6'],
			'resourceLoaded' => true,
			'scripts' => array_map( $prefixGadgetNs, $data['module']['scripts'] ),
			'styles' => array_map( $prefixGadgetNs, $data['module']['styles'] ),
			'supportsUrlLoad' => $data['settings']['supportsUrlLoad'],
			'targets' => $data['settings']['targets'],
			'type' => $data['module']['type'],
		];
	}

	/**
	 * Serialize to an array
	 * @return array
	 */
	public function toArray(): array {
		return [
			'category' => $this->category,
			'datas' => $this->datas,
			'dependencies' => $this->dependencies,
			'hidden' => $this->hidden,
			'messages' => $this->messages,
			'name' => $this->name,
			'onByDefault' => $this->onByDefault,
			'package' => $this->package,
			'peers' => $this->peers,
			'requiredActions' => $this->requiredActions,
			'requiredContentModels' => $this->requiredContentModels,
			'requiredNamespaces' => $this->requiredNamespaces,
			'requiredRights' => $this->requiredRights,
			'requiredSkins' => $this->requiredSkins,
			'requiresES6' => $this->requiresES6,
			'resourceLoaded' => $this->resourceLoaded,
			'scripts' => $this->scripts,
			'styles' => $this->styles,
			'supportsUrlLoad' => $this->supportsUrlLoad,
			'targets' => $this->targets,
			'type' => $this->type,
			// Legacy  (specific to MediaWikiGadgetsDefinitionRepo)
			'definition' => $this->definition,
		];
	}

	/**
	 * Get a placeholder object to use if a gadget doesn't exist
	 *
	 * @param string $id name
	 * @return Gadget
	 */
	public static function newEmptyGadget( $id ) {
		return new self( [ 'name' => $id ] );
	}

	/**
	 * Whether the provided gadget id is valid
	 *
	 * @param string $id
	 * @return bool
	 */
	public static function isValidGadgetID( $id ) {
		return strlen( $id ) > 0 && ResourceLoader::isValidModuleName( self::getModuleName( $id ) );
	}

	/**
	 * @return string Gadget name
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return string Message key
	 */
	public function getDescriptionMessageKey() {
		return "gadget-{$this->getName()}";
	}

	/**
	 * @return string Gadget description parsed into HTML
	 */
	public function getDescription() {
		return wfMessage( $this->getDescriptionMessageKey() )->parse();
	}

	/**
	 * @return string Wikitext of gadget description
	 */
	public function getRawDescription() {
		return wfMessage( $this->getDescriptionMessageKey() )->plain();
	}

	/**
	 * @return string Name of category (aka section) our gadget belongs to. Empty string if none.
	 */
	public function getCategory() {
		return $this->category;
	}

	/**
	 * @param string $id Name of gadget
	 * @return string Name of ResourceLoader module for the gadget
	 */
	public static function getModuleName( $id ) {
		return "ext.gadget.{$id}";
	}

	/**
	 * Checks whether this gadget is enabled for given user
	 *
	 * @param UserIdentity $user user to check against
	 * @return bool
	 */
	public function isEnabled( UserIdentity $user ) {
		$userOptionsLookup = MediaWikiServices::getInstance()->getUserOptionsLookup();
		return (bool)$userOptionsLookup->getOption( $user, "gadget-{$this->name}", $this->onByDefault );
	}

	/**
	 * Checks whether given user has permissions to use this gadget
	 *
	 * @param Authority $user The user to check against
	 * @return bool
	 */
	public function isAllowed( Authority $user ) {
		if ( count( $this->requiredRights ) ) {
			return $user->isAllowedAll( ...$this->requiredRights );
		}
		return true;
	}

	/**
	 * @return bool Whether this gadget is on by default for everyone
	 *  (but can be disabled in preferences)
	 */
	public function isOnByDefault() {
		return $this->onByDefault;
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		return $this->hidden;
	}

	/**
	 * @return bool
	 */
	public function isPackaged(): bool {
		// A packaged gadget needs to have a main script, so there must be at least one script
		return $this->package && $this->supportsResourceLoader() && count( $this->scripts ) > 0;
	}

	/**
	 * Whether to load the gadget on a given page action.
	 *
	 * @param string $action Action name
	 * @return bool
	 */
	public function isActionSupported( string $action ): bool {
		if ( count( $this->requiredActions ) === 0 ) {
			return true;
		}
		// Don't require specifying 'submit' action in addition to 'edit'
		if ( $action === 'submit' ) {
			$action = 'edit';
		}
		return in_array( $action, $this->requiredActions, true );
	}

	/**
	 * Whether to load the gadget on pages in a given namespace ID.
	 *
	 * @param int $namespace Namespace ID
	 * @return bool
	 */
	public function isNamespaceSupported( int $namespace ) {
		return ( count( $this->requiredNamespaces ) === 0
			|| in_array( $namespace, $this->requiredNamespaces )
		);
	}

	/**
	 * Check whether the gadget should load on the mobile domain based on its definition.
	 *
	 * @return bool
	 */
	public function isTargetSupported( bool $isMobileView ): bool {
		if ( $isMobileView ) {
			return in_array( 'mobile', $this->targets, true );
		} else {
			return in_array( 'desktop', $this->targets, true );
		}
	}

	/**
	 * Check if this gadget is compatible with a skin
	 *
	 * @param Skin $skin
	 * @return bool
	 */
	public function isSkinSupported( Skin $skin ) {
		return ( count( $this->requiredSkins ) === 0
			|| in_array( $skin->getSkinName(), $this->requiredSkins, true )
		);
	}

	/**
	 * Check if this gadget is compatible with the given content model
	 *
	 * @param string $contentModel The content model ID
	 * @return bool
	 */
	public function isContentModelSupported( string $contentModel ) {
		return ( count( $this->requiredContentModels ) === 0
			|| in_array( $contentModel, $this->requiredContentModels )
		);
	}

	/**
	 * @return bool Whether the gadget can be loaded with `?withgadget` query parameter.
	 */
	public function supportsUrlLoad() {
		return $this->supportsUrlLoad;
	}

	/**
	 * @return bool Whether all of this gadget's JS components support ResourceLoader
	 */
	public function supportsResourceLoader() {
		return $this->resourceLoaded;
	}

	/**
	 * @return bool Whether this gadget requires ES6
	 */
	public function requiresES6(): bool {
		return $this->requiresES6 && !$this->onByDefault;
	}

	/**
	 * @return bool Whether this gadget has resources that can be loaded via ResourceLoader
	 */
	public function hasModule() {
		return (
			count( $this->styles ) + ( $this->supportsResourceLoader() ? count( $this->scripts ) : 0 )
		) > 0;
	}

	/**
	 * @return string Definition for this gadget from MediaWiki:gadgets-definition
	 */
	public function getDefinition() {
		return $this->definition;
	}

	/**
	 * @return array Array of pages with JS (including namespace)
	 */
	public function getScripts() {
		return $this->scripts;
	}

	/**
	 * @return array Array of pages with CSS (including namespace)
	 */
	public function getStyles() {
		return $this->styles;
	}

	/**
	 * @return array Array of pages with JSON (including namespace)
	 */
	public function getJSONs(): array {
		return $this->isPackaged() ? $this->datas : [];
	}

	/**
	 * @return array Array of all of this gadget's resources
	 */
	public function getScriptsAndStyles() {
		return array_merge( $this->scripts, $this->styles, $this->getJSONs() );
	}

	/**
	 * Returns list of scripts that don't support ResourceLoader
	 * @return string[]
	 */
	public function getLegacyScripts() {
		if ( $this->supportsResourceLoader() ) {
			return [];
		}
		return $this->scripts;
	}

	/**
	 * Returns names of resources this gadget depends on
	 * @return string[]
	 */
	public function getDependencies() {
		return $this->dependencies;
	}

	/**
	 * Get list of extra modules that should be loaded when this gadget is enabled
	 *
	 * Primary use case is to allow a Gadget that includes JavaScript to also load
	 * a (usually, hidden) styles-type module to be applied to the page. Dependencies
	 * don't work for this use case as those would not be part of page rendering.
	 *
	 * @return string[]
	 */
	public function getPeers() {
		return $this->peers;
	}

	/**
	 * @return array
	 */
	public function getMessages() {
		return $this->messages;
	}

	/**
	 * Returns array of permissions required by this gadget
	 * @return string[]
	 */
	public function getRequiredRights() {
		return $this->requiredRights;
	}

	/**
	 * Returns array of page actions on which the gadget loads
	 * @return string[]
	 */
	public function getRequiredActions() {
		return $this->requiredActions;
	}

	/**
	 * Returns array of namespaces in which this gadget loads
	 * @return int[]
	 */
	public function getRequiredNamespaces() {
		return $this->requiredNamespaces;
	}

	/**
	 * Returns array of skins where this gadget works
	 * @return string[]
	 */
	public function getRequiredSkins() {
		return $this->requiredSkins;
	}

	/**
	 * Returns array of content models where this gadget works
	 * @return string[]
	 */
	public function getRequiredContentModels() {
		return $this->requiredContentModels;
	}

	/**
	 * Returns the load type of this Gadget's ResourceLoader module
	 * @return string 'styles' or 'general'
	 */
	public function getType() {
		if ( $this->type === 'styles' || $this->type === 'general' ) {
			return $this->type;
		}
		// Similar to ResourceLoaderWikiModule default
		if ( $this->styles && !$this->scripts && !$this->dependencies ) {
			return 'styles';
		}

		return 'general';
	}
}
