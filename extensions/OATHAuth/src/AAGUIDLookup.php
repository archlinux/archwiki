<?php
/**
 * @license GPL-2.0-or-later
 */

namespace MediaWiki\Extension\OATHAuth;

/**
 * Lookup table for mapping AAGUIDs to human-readable device names.
 *
 * AAGUID (Authenticator Attestation Globally Unique Identifier) is a 128-bit
 * identifier that uniquely identifies a type of authenticator (e.g., a specific
 * password manager or hardware security key model).
 *
 * @see https://fidoalliance.org/specs/fido-v2.0-rd-20180702/fido-metadata-statement-v2.0-rd-20180702.html
 */
class AAGUIDLookup {
	/**
	 * Look up a device name by AAGUID.
	 *
	 * @param string $aaguid The AAGUID in UUID format (e.g., "ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4")
	 * @return string|null The device name, or null if not found
	 */
	public static function getDeviceName( string $aaguid ): ?string {
		$aaguids = json_decode( file_get_contents( __DIR__ . '/aaguids.json' ), true );
		return $aaguids[ strtolower( $aaguid ) ] ?? null;
	}

	/**
	 * Generate a friendly name for a passkey based on its AAGUID.
	 *
	 * @param string $aaguid The AAGUID in UUID format
	 * @return string The device name, or "Passkey" if AAGUID is unknown
	 */
	public static function generateFriendlyName( string $aaguid ): string {
		return self::getDeviceName( $aaguid ) ?? 'Passkey';
	}
}
