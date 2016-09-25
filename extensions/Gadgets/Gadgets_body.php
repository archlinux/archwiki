<?php
/**
 * Gadgets extension - lets users select custom javascript gadgets
 *
 * For more info see http://mediawiki.org/wiki/Extension:Gadgets
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Kinzler, brightbyte.de
 * @copyright © 2007 Daniel Kinzler
 * @license GNU General Public Licence 2.0 or later
 */


/**
 * Wrapper for one gadget.
 */
class Gadget {
	/**
	 * Increment this when changing class structure
	 */
	const GADGET_CLASS_VERSION = 9;

	const CACHE_TTL = 86400;

	private $scripts = array(),
			$styles = array(),
			$dependencies = array(),
			$messages = array(),
			$name,
			$definition,
			$resourceLoaded = false,
			$requiredRights = array(),
			$requiredSkins = array(),
			$targets = array( 'desktop' ),
			$onByDefault = false,
			$hidden = false,
			$position = 'bottom',
			$category;

	public function __construct( array $options ) {
		foreach ( $options as $member => $option ) {
			switch ( $member ) {
				case 'scripts':
				case 'styles':
				case 'dependencies':
				case 'messages':
				case 'name':
				case 'definition':
				case 'resourceLoaded':
				case 'requiredRights':
				case 'requiredSkins':
				case 'targets':
				case 'onByDefault':
				case 'position':
				case 'hidden':
				case 'category':
					$this->{$member} = $option;
					break;
				default:
					throw new InvalidArgumentException( "Unrecognized '$member' parameter" );
			}
		}
	}

	/**
	 * Create a object based on the metadata in a GadgetDefinitionContent object
	 *
	 * @param string $id
	 * @param GadgetDefinitionContent $content
	 * @return Gadget
	 */
	public static function newFromDefinitionContent( $id, GadgetDefinitionContent $content ) {
		$data = $content->getAssocArray();
		$prefixGadgetNs = function ( $page ) {
			return 'Gadget:' . $page;
		};
		$info = array(
			'name' => $id,
			'resourceLoaded' => true,
			'requiredRights' => $data['settings']['rights'],
			'onByDefault' => $data['settings']['default'],
			'hidden' => $data['settings']['hidden'],
			'requiredSkins' => $data['settings']['skins'],
			'category' => $data['settings']['category'],
			'scripts' => array_map( $prefixGadgetNs, $data['module']['scripts'] ),
			'styles' => array_map( $prefixGadgetNs, $data['module']['styles'] ),
			'dependencies' => $data['module']['dependencies'],
			'messages' => $data['module']['messages'],
			'position' => $data['module']['position'],
		);

		return new self( $info );

	}

	/**
	 * Get a placeholder object to use if a gadget doesn't exist
	 *
	 * @param string $id name
	 * @return Gadget
	 */
	public static function newEmptyGadget( $id ) {
		return new self( array( 'name' => $id ) );
	}

	/**
	 * Whether the provided gadget id is valid
	 *
	 * @param string $id
	 * @return bool
	 */
	public static function isValidGadgetID( $id ) {
		return strlen( $id ) > 0 && ResourceLoader::isValidModuleName( Gadget::getModuleName( $id ) );
	}


	/**
	 * @return String: Gadget name
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * @return String: Gadget description parsed into HTML
	 */
	public function getDescription() {
		return wfMessage( "gadget-{$this->getName()}" )->parse();
	}

	/**
	 * @return String: Wikitext of gadget description
	 */
	public function getRawDescription() {
		return wfMessage( "gadget-{$this->getName()}" )->plain();
	}

	/**
	 * @return String: Name of category (aka section) our gadget belongs to. Empty string if none.
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
	 * @param $user User: user to check against
	 * @return Boolean
	 */
	public function isEnabled( $user ) {
		return (bool)$user->getOption( "gadget-{$this->name}", $this->onByDefault );
	}

	/**
	 * Checks whether given user has permissions to use this gadget
	 *
	 * @param $user User: user to check against
	 * @return Boolean
	 */
	public function isAllowed( $user ) {
		return count( array_intersect( $this->requiredRights, $user->getRights() ) ) == count( $this->requiredRights )
			&& ( $this->requiredSkins === true || !count( $this->requiredSkins ) || in_array( $user->getOption( 'skin' ), $this->requiredSkins ) );
	}

	/**
	 * @return Boolean: Whether this gadget is on by default for everyone (but can be disabled in preferences)
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
	 * @return Boolean: Whether all of this gadget's JS components support ResourceLoader
	 */
	public function supportsResourceLoader() {
		return $this->resourceLoaded;
	}

	/**
	 * @return Boolean: Whether this gadget has resources that can be loaded via ResourceLoader
	 */
	public function hasModule() {
		return count( $this->styles )
			+ ( $this->supportsResourceLoader() ? count( $this->scripts ) : 0 )
				> 0;
	}

	/**
	 * @return String: Definition for this gadget from MediaWiki:gadgets-definition
	 */
	public function getDefinition() {
		return $this->definition;
	}

	/**
	 * @return Array: Array of pages with JS (including namespace)
	 */
	public function getScripts() {
		return $this->scripts;
	}

	/**
	 * @return Array: Array of pages with CSS (including namespace)
	 */
	public function getStyles() {
		return $this->styles;
	}

	/**
	 * @return Array: Array of all of this gadget's resources
	 */
	public function getScriptsAndStyles() {
		return array_merge( $this->scripts, $this->styles );
	}

	/**
	 * @return array
	 */
	public function getTargets() {
		return $this->targets;
	}

	/**
	 * Returns list of scripts that don't support ResourceLoader
	 * @return Array
	 */
	public function getLegacyScripts() {
		if ( $this->supportsResourceLoader() ) {
			return array();
		}
		return $this->scripts;
	}

	/**
	 * Returns names of resources this gadget depends on
	 * @return Array
	 */
	public function getDependencies() {
		return $this->dependencies;
	}

	/**
	 * @return array
	 */
	public function getMessages() {
		return $this->messages;
	}

	/**
	 * Returns array of permissions required by this gadget
	 * @return Array
	 */
	public function getRequiredRights() {
		return $this->requiredRights;
	}

	/**
	 * Returns array of skins where this gadget works
	 * @return Array
	 */
	public function getRequiredSkins() {
		return $this->requiredSkins;
	}

	/**
	 * Returns the position of this Gadget's ResourceLoader module
	 * @return String: 'bottom' or 'top'
	 */
	public function getPosition() {
		return $this->position;
	}
}

