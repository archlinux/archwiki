<?php

declare(strict_types=1);

namespace Base32;

/**
 * Base32 encoder and decoder.
 *
 * RFC 4648 compliant
 *
 * @see     http://www.ietf.org/rfc/rfc4648.txt
 * Some groundwork based on this class
 * https://github.com/NTICompass/PHP-Base32
 *
 * @author  Christian Riesen <chris.riesen@gmail.com>
 *
 * @see     http://christianriesen.com
 *
 * @license MIT License see LICENSE file
 */
class Base32
{
    /**
     * Alphabet for encoding and decoding base32.
     *
     * @var array
     */
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567=';

    private const BASE32_PATTERN = '/[^A-Z2-7]/';

    /**
     * Encodes into base32.
     *
     * @param string $string Clear text string
     *
     * @return string Base32 encoded string
     */
    public static function encode(string $string): string
    {
        // Empty string results in empty string
        if ('' === $string) {
            return '';
        }

        // Convert string to binary
        $binaryString = '';

        foreach (\str_split($string) as $s) {
            // Return each character as an 8-bit binary string
            $binaryString .= \sprintf('%08b', \ord($s));
        }

        // Break into 5-bit chunks, then break that into an array
        $binaryArray = self::chunk($binaryString, 5);

        // Pad array to be divisible by 8
        while (0 !== \count($binaryArray) % 8) {
            $binaryArray[] = null;
        }

        $base32String = '';

        // Encode in base32
        foreach ($binaryArray as $bin) {
            $char = 32;

            if (null !== $bin) {
                // Pad the binary strings
                $bin = \str_pad($bin, 5, '0', STR_PAD_RIGHT);
                $char = \bindec($bin);
            }

            // Base32 character
            $base32String .= self::ALPHABET[$char];
        }

        return $base32String;
    }

    /**
     * Decodes base32.
     *
     * @param string $base32String Base32 encoded string
     *
     * @return string Clear text string
     */
    public static function decode(string $base32String): string
    {
        // Only work in upper cases
        $base32String = \strtoupper($base32String);

        // Remove anything that is not base32 alphabet
        $base32String = \preg_replace(self::BASE32_PATTERN, '', $base32String);

        // Empty string results in empty string
        if ('' === $base32String || null === $base32String) {
            return '';
        }

        $base32Array = \str_split($base32String);

        $string = '';

        foreach ($base32Array as $str) {
            $char = \strpos(self::ALPHABET, $str);

            // Ignore the padding character
            if (32 !== $char) {
                $string .= \sprintf('%05b', $char);
            }
        }

        while (0 !== \strlen($string) % 8) {
            $string = \substr($string, 0, -1);
        }

        $binaryArray = self::chunk($string, 8);

        $realString = '';

        foreach ($binaryArray as $bin) {
            // Pad each value to 8 bits
            $bin = \str_pad($bin, 8, '0', STR_PAD_RIGHT);
            // Convert binary strings to ASCII
            $realString .= \chr((int) \bindec($bin));
        }

        return $realString;
    }

    /**
     * Creates an array from a binary string into a given chunk size.
     *
     * @param string $binaryString String to chunk
     * @param int    $bits         Number of bits per chunk
     *
     * @return array<string>
     */
    private static function chunk(string $binaryString, int $bits): array
    {
        $binaryString = \chunk_split($binaryString, $bits, ' ');

        if (' ' === \substr($binaryString, \strlen($binaryString) - 1)) {
            $binaryString = \substr($binaryString, 0, -1);
        }

        return \explode(' ', $binaryString);
    }
}
