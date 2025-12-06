<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\TemplateData\Config;

use Iterator;
use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityConfiguration\Schema\SchemaBuilder;
use MediaWiki\Extension\CommunityConfiguration\Validation\IValidator;
use MediaWiki\Extension\CommunityConfiguration\Validation\ValidationStatus;
use MediaWiki\Extension\CommunityConfiguration\Validation\ValidatorFactory;
use MediaWiki\Title\Title;

/**
 * @license GPL-2.0-or-later
 */
class FeaturedTemplatesSchemaValidator implements IValidator {

	public function __construct(
		private readonly IValidator $jsonSchemaValidator,
		private readonly Config $config,
	) {
	}

	public static function factory( ValidatorFactory $validatorFactory, Config $mainConfig, string $jsonSchema ): self {
		$jsonSchemaValidator = $validatorFactory->newValidator(
			'TemplateData-FeaturedTemplates',
			'jsonschema',
			[ $jsonSchema ],
		);
		return new self( $jsonSchemaValidator, $mainConfig );
	}

	/**
	 * @param mixed $config
	 * @param string|null $version
	 * @return ValidationStatus
	 */
	private function validate( $config, ?string $version = null ): ValidationStatus {
		$resp = new ValidationStatus();
		$configArray = json_decode( json_encode( $config ), true );

		// Fatal: More than one property in FeaturedTemplates
		if ( count( $configArray['FeaturedTemplates'] ) > 1 ) {
			$resp->addFatal(
				'FeaturedTemplates',
				'',
				'More than one property in FeaturedTemplates',
			);
		}
		$featuredTemplates = $configArray['FeaturedTemplates'][0] ?? [];

		// Fatal: Duplicates in the `titles` array
		$titles = $featuredTemplates['titles'] ?? [];
		$duplicates = array_diff_assoc( $titles, array_unique( $titles ) );
		if ( count( $duplicates ) > 0 ) {
			$resp->addFatal(
				'FeaturedTemplates',
				'titles',
				'Duplicate titles in FeaturedTemplates',
			);
		}

		// Fatal: Titles not in the TemplateData namespace
		foreach ( $titles as $title ) {
			$titleObject = Title::newFromText( $title );
			// Check if the title is valid
			if ( !$titleObject || !$titleObject->isKnown() ) {
				$resp->addFatal(
					'FeaturedTemplates',
					'titles',
					'Invalid title in FeaturedTemplates',
				);
				continue;
			}
			$templateDataNamespaces = $this->config->get( 'TemplateDataEditorNamespaces' );
			if ( !$titleObject->inNamespaces( $templateDataNamespaces ) ) {
				$resp->addFatal(
					'FeaturedTemplates',
					'titles',
					'Title not in TemplateData namespace',
				);
			}
		}
		// Validate the config against the JSON schema
		// This will add any validation errors to the response
		$resp->merge( $this->jsonSchemaValidator->validateStrictly( $config, $version ) );

		return $resp;
	}

	/** @inheritDoc */
	public function validateStrictly( $config, ?string $version = null ): ValidationStatus {
		$status = $this->jsonSchemaValidator->validateStrictly( $config, $version );
		if ( !$status->isOK() ) {
			return $status;
		}
		return $this->validate( $config, $version );
	}

	/** @inheritDoc */
	public function validatePermissively( $config, ?string $version = null ): ValidationStatus {
		return $this->jsonSchemaValidator->validatePermissively( $config, $version );
	}

	/** @inheritDoc */
	public function areSchemasSupported(): bool {
		return $this->jsonSchemaValidator->areSchemasSupported();
	}

	/** @inheritDoc */
	public function getSchemaBuilder(): SchemaBuilder {
		return $this->jsonSchemaValidator->getSchemaBuilder();
	}

	/** @inheritDoc */
	public function getSchemaIterator(): Iterator {
		return $this->jsonSchemaValidator->getSchemaIterator();
	}

	/** @inheritDoc */
	public function getSchemaVersion(): ?string {
		return $this->jsonSchemaValidator->getSchemaVersion();
	}

}
