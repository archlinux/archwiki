<?php

namespace MediaWiki\Extension\VisualEditor\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\UserIdentity;

/**
 * Allows VisualEditor and other extensions to determine if VisualEditor is available for a given page.
 *
 * For example, the ConfirmEdit extension uses this service to know if a user could open the VisualEditor
 * interface on the page returned for a given request.
 *
 * Only specified methods are stable to call outside VisualEditor.
 */
class VisualEditorAvailabilityLookup {

	public const CONSTRUCTOR_OPTIONS = [
		'VisualEditorAvailableNamespaces',
		'VisualEditorAvailableContentModels',
		'VisualEditorEnableBetaFeature',
	];

	/**
	 * @internal For use by ServiceWiring.php only or when locating the service
	 */
	public const SERVICE_NAME = 'VisualEditor.VisualEditorAvailabilityLookup';

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly NamespaceInfo $namespaceInfo,
		private readonly ExtensionRegistry $extensionRegistry,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
	}

	/**
	 * Returns whether VisualEditor is available for a given page, request and user.
	 * If this returns false, then the current page view will not load VisualEditor in any circumstance.
	 *
	 * @stable to call
	 */
	public function isAvailable( Title $title, WebRequest $request, UserIdentity $userIdentity ): bool {
		// Only allow use on pages with supported content models
		if ( !$this->isAllowedContentType( $title->getContentModel() ) ) {
			return false;
		}

		// If forced by the URL parameter, skip the namespace check (T221892) and preference check
		if ( $request->getVal( 'veaction' ) === 'edit' ) {
			return true;
		}

		return $this->isAllowedNamespace( $title->getNamespace() ) && $this->isEnabledForUser( $userIdentity );
	}

	/**
	 * Returns whether VisualEditor is available to use in the given namespace
	 *
	 * @internal Only for use in VisualEditor. You should probably use {@link self::isAvailable} instead
	 */
	public function isAllowedNamespace( int $namespaceId ): bool {
		return in_array( $namespaceId, $this->getAvailableNamespaceIds(), true );
	}

	/**
	 * Get a list of the namespace IDs where VisualEditor is available to use.
	 *
	 * @internal Only for use in VisualEditor. You should probably use {@link self::isAvailable} instead
	 * @return int[] The order of the namespace IDs is not stable, so sorting should be applied as necessary
	 */
	public function getAvailableNamespaceIds(): array {
		$configuredNamespaces = array_replace(
			$this->extensionRegistry->getAttribute( 'VisualEditorAvailableNamespaces' ),
			$this->options->get( 'VisualEditorAvailableNamespaces' )
		);

		// Get a list of namespace IDs where VisualEditor is enabled
		$normalized = [];
		foreach ( $configuredNamespaces as $namespaceName => $enabled ) {
			$id = $this->namespaceInfo->getCanonicalIndex( strtolower( $namespaceName ) ) ?? $namespaceName;
			$normalized[$id] = $enabled && $this->namespaceInfo->exists( $id );
		}

		return array_keys( array_filter( $normalized ) );
	}

	/**
	 * Check if the configured allowed content models include the specified content model
	 *
	 * @internal Only for use in VisualEditor. You should probably use {@link self::isAvailable} instead
	 * @param string $contentModel Content model ID
	 * @return bool
	 */
	public function isAllowedContentType( string $contentModel ): bool {
		$availableContentModels = array_merge(
			$this->extensionRegistry->getAttribute( 'VisualEditorAvailableContentModels' ),
			$this->options->get( 'VisualEditorAvailableContentModels' )
		);

		return (bool)( $availableContentModels[$contentModel] ?? false );
	}

	/**
	 * Returns whether the given UserIdentity has VisualEditor disabled/enabled via preferences
	 *
	 * @internal Only for use in VisualEditor. You should probably use {@link self::isAvailable} instead
	 */
	public function isEnabledForUser( UserIdentity $userIdentity ): bool {
		// If the user opted out when it was in beta, then continue to opt them out
		if ( $this->userOptionsLookup->getOption( $userIdentity, 'visualeditor-autodisable' ) ) {
			return false;
		}

		$isBeta = $this->options->get( 'VisualEditorEnableBetaFeature' );
		return $isBeta ? $this->userOptionsLookup->getOption( $userIdentity, 'visualeditor-enable' ) :
			!$this->userOptionsLookup->getOption( $userIdentity, 'visualeditor-betatempdisable' );
	}
}
