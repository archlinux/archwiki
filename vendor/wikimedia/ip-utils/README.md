[![Packagist](https://img.shields.io/packagist/v/wikimedia/ip-utils.svg?style=flat)](https://packagist.org/packages/wikimedia/ip-utils)

IPUtils
=======

Parse, match, and analyze IP addresses and CIDR ranges. This library supports both IPv4 and IPv6.

Additional documentation about the library can be found on
[mediawiki.org](https://www.mediawiki.org/wiki/IPUtils).


Usage
-----

<pre lang="php">
use Wikimedia\IPUtils;

IPUtils::isIPAddress( '::1' );
IPUtils::isIPv4( '124.24.52.13' );
</pre>

`IPSet` can be up to 100x faster than calling `IPUtils::isInRange()`
over multiple CIDR specs.

<pre lang="php">
use Wikimedia\IPSet;

// This will calculate an optimized data structure for the set
$ipset = new IPSet( [
    '208.80.154.0/26',
    '2620:0:861:1::/64',
    '10.64.0.0/22',
] );

// Run fast checks against the same re-usable IPSet object
if ( $ipset->match( $ip ) ) {
    // ...
}
</pre>


Performance
-----

In rough benchmarking, IPSet takes about 80% more time than `in_array()` checks
on a short (a couple hundred at most) array of addresses.  It's fast either way
at those levels, though, and IPSet would scale better than in_array if the
array were much larger.

For mixed-family CIDR sets, however, `IPSet::match()` gives well over 100x speedup
compared to iterating `IPUtils::isInRange()` over an array of CIDR specs.

The basic implementation is two separate binary trees (IPv4 and IPv6) as nested
PHP arrays with keys named 0 and 1.  The values false and true are terminal
match-fail and match-success; otherwise, the value is a deeper node in the tree.

A simple depth-compression scheme is also implemented: whole-byte tree
compression at whole-byte boundaries only, where no branching occurs during
that whole byte of depth.  A compressed node has keys 'comp' (the byte to
compare) and 'next' (the next node to recurse into if 'comp' matched successfully).

For example, given these inputs:

<pre>
25.0.0.0/9
25.192.0.0/10
</pre>

The v4 tree would look like:

<pre lang="php">
root4 => [
    'comp' => 25,
    'next' => [
        0 => true,
        1 => [
            0 => false,
            1 => true,
        ],
    ],
];
</pre>

(multi-byte compression nodes were attempted as well, but were
a net loss in my test scenarios due to additional match complexity)


Running tests
-------------

    composer install --prefer-dist
    composer test


History
-------

The IPUtils class started life in 2006 as part of [MediaWiki 1.7](https://www.mediawiki.org/wiki/MediaWiki_1.7) ([r15572](https://www.mediawiki.org/wiki/Special:Code/MediaWiki/15572)). It was
split out of the MediaWiki codebase and published as an independent library
during the [MediaWiki 1.34](https://www.mediawiki.org/wiki/MediaWiki_1.34) development cycle.

The IPSet class was created by Brandon Black in 2014 as faster alternative to `IPUtils::isInRange()` (MediaWiki 1.24, [change 131758](https://gerrit.wikimedia.org/r/131758)). It was moved to a library during the MediaWiki 1.26 development cycle ([change 221179](https://gerrit.wikimedia.org/r/221179), [change 218384](https://gerrit.wikimedia.org/r/218384)).
