<?php

/**
 * cf https://github.com/web-auth/webauthn-lib/blob/5.2.3/src/Denormalizer/WebauthnSerializerFactory.php
 *
 * Changes:
 * * Re-namespaced and capitalisation of class name changed
 * * Updated to MW code style
 * * class_exists() check removed; composer makes sure classes exist
 * * Mapping of class => package removed, along with related constants
 * * PhpDocExtractor removed from ObjectNormalizer/PropertyInfoExtractor
 *
 * @license MIT
 */

declare( strict_types = 1 );

namespace MediaWiki\Extension\OATHAuth;

use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\UidNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\Denormalizer\AttestationObjectDenormalizer;
use Webauthn\Denormalizer\AttestationStatementDenormalizer;
use Webauthn\Denormalizer\AttestedCredentialDataNormalizer;
use Webauthn\Denormalizer\AuthenticationExtensionNormalizer;
use Webauthn\Denormalizer\AuthenticationExtensionsDenormalizer;
use Webauthn\Denormalizer\AuthenticatorAssertionResponseDenormalizer;
use Webauthn\Denormalizer\AuthenticatorAttestationResponseDenormalizer;
use Webauthn\Denormalizer\AuthenticatorDataDenormalizer;
use Webauthn\Denormalizer\AuthenticatorResponseDenormalizer;
use Webauthn\Denormalizer\CollectedClientDataDenormalizer;
use Webauthn\Denormalizer\ExtensionDescriptorDenormalizer;
use Webauthn\Denormalizer\PublicKeyCredentialDenormalizer;
use Webauthn\Denormalizer\PublicKeyCredentialDescriptorNormalizer;
use Webauthn\Denormalizer\PublicKeyCredentialOptionsDenormalizer;
use Webauthn\Denormalizer\PublicKeyCredentialSourceDenormalizer;
use Webauthn\Denormalizer\PublicKeyCredentialUserEntityDenormalizer;
use Webauthn\Denormalizer\TrustPathDenormalizer;
use Webauthn\Denormalizer\VerificationMethodANDCombinationsDenormalizer;

final readonly class WebAuthnSerializerFactory {
	public function __construct(
		private AttestationStatementSupportManager $attestationStatementSupportManager
	) {
	}

	public function create(): SerializerInterface {
		$denormalizers = [
			new ExtensionDescriptorDenormalizer(),
			new VerificationMethodANDCombinationsDenormalizer(),
			new AuthenticationExtensionNormalizer(),
			new PublicKeyCredentialDescriptorNormalizer(),
			new AttestedCredentialDataNormalizer(),
			new AttestationObjectDenormalizer(),
			new AttestationStatementDenormalizer( $this->attestationStatementSupportManager ),
			new AuthenticationExtensionsDenormalizer(),
			new AuthenticatorAssertionResponseDenormalizer(),
			new AuthenticatorAttestationResponseDenormalizer(),
			new AuthenticatorDataDenormalizer(),
			new AuthenticatorResponseDenormalizer(),
			new CollectedClientDataDenormalizer(),
			new PublicKeyCredentialDenormalizer(),
			new PublicKeyCredentialOptionsDenormalizer(),
			new PublicKeyCredentialSourceDenormalizer(),
			new PublicKeyCredentialUserEntityDenormalizer(),
			new TrustPathDenormalizer(),
			new UidNormalizer(),
			new ArrayDenormalizer(),
			new ObjectNormalizer(
				propertyTypeExtractor: new PropertyInfoExtractor( typeExtractors: [
					new ReflectionExtractor(),
				] )
			),
		];

		return new Serializer( $denormalizers, [ new JsonEncoder() ] );
	}
}
