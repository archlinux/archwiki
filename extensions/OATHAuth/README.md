Main documentation can be found at https://www.mediawiki.org/wiki/Extension:OATHAuth

To generate a value for $wgOATHSecretKey, you can run `hexdump -vn32 -e'8/8 "%08X" "\n"' /dev/urandom`.
