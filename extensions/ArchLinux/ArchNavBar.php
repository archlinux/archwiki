<?php

namespace MediaWiki\Extensions\ArchLinux;

global $wgArchNavBar, $wgArchHome, $wgArchNavBarSelected, $wgArchNavBarSelectedDefault;

?>
<div id="archnavbar" class="noprint"><!-- Arch Linux global navigation bar -->
    <div id="archnavbarlogo">
        <p><a id="logo" href="<?php if (isset($wgArchHome)) {
                echo $wgArchHome;
            } ?>"></a></p>
    </div>
    <div id="archnavbarmenu">
        <ul id="archnavbarlist">
            <?php
            if (isset($wgArchNavBar)) {
                foreach ($wgArchNavBar as $name => $url) {
                    if ((isset($wgArchNavBarSelected) && $this->data['title'] == $name && in_array($name, $wgArchNavBarSelected))
                        || (!(isset($wgArchNavBarSelected) && in_array($this->data['title'], $wgArchNavBarSelected)) && isset($wgArchNavBarSelectedDefault) && $name == $wgArchNavBarSelectedDefault)) {
                        $anbClass = ' class="anb-selected"';
                    } else {
                        $anbClass = '';
                    }
                    echo '<li id="anb-' . strtolower($name) . '"' . $anbClass . '><a href="' . $url . '">' . $name . '</a></li>';
                }
            }
            ?>
        </ul>
    </div>
</div><!-- #archnavbar -->
