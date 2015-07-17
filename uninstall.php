<?php
defined('ABSPATH') or die('Restriced Access');

if (!defined('WP_UNINSTALL_PLUGIN')) 
    exit();

delete_option('waalg_enable_asin');
delete_option('waalg_enable_keyw');

delete_option('waalg_affilate_id');

delete_option('waalg_ascsubtag');
delete_option('waalg_fallback');
delete_option('waalg_add_url');

?>
