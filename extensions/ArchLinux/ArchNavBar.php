<?php

namespace MediaWiki\Extensions\ArchLinux;

/**
 * @var string $title
 * @var array $archNavBar
 * @var string $archHome
 * @var array $archNavBarSelected
 * @var string $archNavBarSelectedDefault
 */
?>
<div id="archnavbar" class="noprint mw-no-invert">
    <div id="archnavbarlogo">
        <p><a id="logo" href="<?= $archHome ?>"></a></p>
    </div>
    <div id="archnavbarmenu">
        <ul id="archnavbarlist">
            <?php
            foreach ($archNavBar as $name => $url) {
                if (($title == $name && in_array($name, $archNavBarSelected))
                    || (!(in_array($title, $archNavBarSelected)) && $name == $archNavBarSelectedDefault)) {
                    $anbClass = ' class="anb-selected"';
                } else {
                    $anbClass = '';
                }
                ?>
            <li id="anb-<?= strtolower($name) ?>"<?= $anbClass ?>><a href="<?= $url ?>"><?= $name ?></a></li><?php
            }
            ?>
        </ul>
    </div>
</div>
