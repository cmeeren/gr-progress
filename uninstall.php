<?php
if (defined("WP_UNINSTALL_PLUGIN")) {
    delete_option("gr_progress_cvdm_shelves");
    delete_option("gr_progress_cvdm_coverURLs");
}
?>