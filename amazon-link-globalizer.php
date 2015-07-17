<?php   
    /* 
    Plugin Name: Amazon Affiliate Link Globalizer
    Plugin URI: http://www.affiliate-geo-target.com/amazon-wordpress-plugin.html
    Version: 1.2
    Description: Rewrites Amazon.com/Amzn.com links to the <a href="http://A-FWD.com">A-FWD</a> webservice. This webservice performs user IP Geolocation and forwards the visitor to 'their' country specific Amazon store. In contrast to similar plugins, this plugin does not use any Javascript and does not perform external HTTP requests by itself.
    Author: Attila Gyoerkoes, Markus Goetz (Woboq)
    Author URI: http://www.woboq.com/
    License: GNU General Public License v2 or later
    License URI: http://www.gnu.org/licenses/gpl-2.0.html
    */  

defined('ABSPATH') or die('Restriced Access');

if(!class_exists('WAALG'))
{
    class WAALG
    {       
        private static $afwd_tlds = array('com', 'ca', 'uk', 'de', 'fr', 'it', 'es', 'jp', 'cn', 'in', 'br', 'au', 'mx');
        
        /**
         * Regular expression
         * matches: <a (someattributes) href="(someurl)" (somemoreattributes)>
         *
         * @var string
         * @see content_filter
         */
         const link_pattern = '#<a\s*([^>]*)\s*href\s*=\s*"([^"]*)"\s*([^>]*)\s*>#';

        /**
         * Regular expression
         * matches URLs to amazon.com containing an asin
         * 
         * @var string
         * @see link_replacer
         */
        const amzn_asin_pattern  = '#(?:http:\/\/)?(?:www\.)?(?:(?:amazon\.com/(?:[\w-&%]+\/)?(?:o\/ASIN|dp|ASIN|gp\/product|exec\/obidos\/ASIN)\/)|(?:amzn\.com\/))([A-Z0-9]{10})(?:[^"]+)?#';

        /**
         * Regular expression
         * matches URLs to amazon.com containing keywords
         *
         * @var string
         * @see link_replacer
         */
        const amzn_keyw_pattern = '#(?:http:\/\/)?(?:www\.)?(?:amazon\.)(?:com\/)(?:(?:gp\/search\/)|(?:s\/))(?:[^"]*)(?:keywords=)([^"&]*)(?:[^"]*)?#';
        
        /**
         * Construct the plugin object
         */
        public function __construct() {
            add_action('admin_init', Array(&$this, 'admin_init'));
            add_action('admin_menu', Array(&$this, 'admin_menu'));
            add_filter('the_content', Array(&$this, 'content_filter'), 50);
        }
        
        /**
         * Activate the plugin
         */
        private function init_settings()
        {
            register_setting('waalg-group', 'waalg_enable_asin');
            register_setting('waalg-group', 'waalg_enable_keyw');

            register_setting('waalg-group', 'waalg_affilate_id');

            register_setting('waalg-group', 'waalg_ascsubtag');
            register_setting('waalg-group', 'waalg_fallback');
            register_setting('waalg-group', 'waalg_add_url'); 

            // Try getting options from old version
            $id_list = get_option('waalg_affilate_id');
            foreach (self::$afwd_tlds as $tld) {
                if (get_option('woboq_amazon_link_globalizer_affiliate_id_'.$tld)) {
                    if (is_array($id_list) && array_key_exists($tld, $id_list))
                        if ($id_list[$tld] == '')
                            $id_list[$tld] = get_option('woboq_amazon_link_globalizer_affiliate_id_'.$tld);
                    else 
                        $id_list[$tld] = get_option('woboq_amazon_link_globalizer_affiliate_id_'.$tld);                   
                }                    
            }
            update_option('waalg_affilate_id', $id_list);    
        }        
         
        /**
         * Activate the plugin
         */
        public static function activate() {
            self::init_settings();       
        }
        
        /**
         * Deactivate the plugin
         */     
        public static function deactivate()
        {
            unregister_setting('waalg-group', 'waalg_enable_asin');
            unregister_setting('waalg-group', 'waalg_enable_keyw');
            
            unregister_setting('waalg-group', 'waalg_affilate_id');

            unregister_setting('waalg-group', 'waalg_ascsubtag');
            unregister_setting('waalg-group', 'waalg_fallback');
            unregister_setting('waalg-group', 'waalg_add_url');            
        }
        
        public function admin_init()
        {
            $this->init_settings();         
        }
        
        public function admin_menu()
        {           
            add_options_page('Woboq Amazon Affiliate Link Globalizer Settings', 'Woboq Amazon Affiliate Link Globalizer', 'manage_options', 'waalg', array(&$this, 'settings_page_callback'));
        } 
        
        public function settings_page_callback()
        {
            if(!current_user_can('manage_options'))
            {
                wp_die(__('You do have sufficient permissions to access this page.'));
            }

            // render settings
            include(sprintf("%s/settings.php", dirname(__FILE__)));
        }
        
        /**
         * Get a list of supported top level domains.
         *
         * @return array array with tlds
         */
        public static function getTldList() {
            return self::$afwd_tlds;     
        }
        
        /**
         *
         *
         * @param content to be replaced ()
         * @return string new content, with replaced urls
         */        
        public function content_filter($content) {
            if (function_exists('is_main_query') && !is_main_query())
                return $content;
            
            return preg_replace_callback(self::link_pattern, 
                                         Array($this, 'link_replacer'), 
                                         $content); 
        }

        /**
         * Callback for preg_replace_callback. Tries to replace html anchors
         * containing amazon URLs with anchors containing URLs to the
         * a-fwd.com webservice
         * 
         * @param anchor with an URL pointing to amazon.com
         * @return string new achnor with an URL pointing to a-fwd.com
         * @see content_filter
         */
        private function link_replacer($match) {
            
            $attributes1    = $match[1];
            $url            = $match[2];
            $attributes2    = $match[3];
            $add_url_params = '';
            $found_matches = 0; // count matches
            
            // Try replacing asin links
            if (get_option('waalg_enabled_asin', 1) == 1) {
                $url = preg_replace_callback(self::amzn_asin_pattern, 
                                            Array($this, 'asin_url_replacer'), 
                                            $url,
                                            -1,
                                            $found_matches);
            }
            // Try replacing keyword links
            if ( ($found_matches <= 0) && 
                 (get_option('waalg_enabled_keyw', 1) == 1) ) {
                $url = preg_replace_callback(self::amzn_keyw_pattern, 
                                             Array($this, 'keyw_url_replacer'), 
                                             $url,
                                             -1,
                                             $found_matches);
            }
            // Build link only if replacements were made
            if ($found_matches > 0) {
                // We don't want search engines going there
                // Change 'rel' attribute to 'nofollow'
                $attributes1 = preg_replace('#rel\s*=\s*"[^"]+"#',
                                            'rel="nofollow"',
                                             $attributes1,
                                             -1,
                                             $found_matches);
                if ($found_matches <= 0) {
                    $attributes2 = preg_replace('#rel\s*=\s*"[^"]+"#',
                                                'rel="nofollow"',
                                                $attributes2,
                                                -1,
                                                $found_matches);
                }
                // No 'rel' attribute found, append one
                if ($found_matches <= 0) {
                    $attributes2 .= ' rel="nofollow"';
                }
                // Additional url parameters
                if (get_option('waalg_fallback', '-') != '-') {
                    $add_url_params .= '&fb='.get_option('waalg_fallback', 'com');
                }
                if (get_option('waalg_ascsubtag', '') != '') {
                    $add_url_params .= '&ascsubtag='.get_option('waalg_ascsubtag', '');
                }
                if (get_option('waalg_add_url', '') != '') {
                    $add_url_params .= get_option('waalg_add_url', '');
                }
                $add_url_params .= '&sc=w';
                
                // Build the actual link
                $new_link = '<a '.$attributes1.' href="'.$url.$add_url_params.'" '.$attributes2.'>';
                return $new_link;
            }
            
            return $match[0];
        }

        /**
         * Callback for preg_replace_callback. If an URL is
         * pointing to amazon.com and containing an asin it will be
         * replaced with a link to a-fwd.com
         *
         * @param preg_replace_callback will be supplying parameters
         * @return string new URL pointing to a-fwd.com
         * @see link_replacer
         */
        private function asin_url_replacer($match) {
            $asin = $match[1];
            $new_url = 'http://a-fwd.com/asin-com='.$asin;
            // Append tracking ids for every country specified
            $id_list = get_option('waalg_affilate_id');
            if (is_array($id_list)) {
                foreach (self::$afwd_tlds as $tld) {
                    if (array_key_exists($tld, $id_list) && ($id_list[$tld] != ''))
                        $new_url = $new_url.'&'.$tld.'='.urlencode($id_list[$tld]);
                }
            }
            return $new_url;
        }

        /**
         * Callback for preg_replace_callback. If an URL is
         * pointing to amazon.com and containing keywords it will be
         * replaced with a link to a-fwd.com
         *
         * @param preg_replace_callback will be supplying parameters
         * @return string new URL pointing to a-fwd.com
         * @see link_replacer
         */
        private function keyw_url_replacer($match) {
            $keywords = $match[1];
            $new_url = 'http://a-fwd.com/s='.$keywords;
            // Append tracking ids for every country specified
            $id_list = get_option('waalg_affilate_id');
            if (is_array($id_list)) {
                foreach (self::$afwd_tlds as $tld) {
                    if (array_key_exists($tld, $id_list) && ($id_list[$tld] != ''))
                        $new_url = $new_url.'&'.$tld.'='.urlencode($id_list[$tld]);
                }
            }
            return $new_url;
        }
        
    }// END class WAALG
}


if(class_exists('WAALG')) {
    // (de)activation hooks
    register_activation_hook(__FILE__, array('WAALG', 'activate'));
    register_deactivation_hook(__FILE__, array('WAALG', 'deactivate'));
    
    // instantiate plugin and link to settings page
    $waalg = new WAALG(); 
    if(isset($waalg)) {
        // link on the plugins page
        function plugin_settings_link($links) { 
            $settings_link = '<a href="options-general.php?page=waalg">Settings</a>'; 
            array_unshift($links, $settings_link); 
            return $links; 
        }
        $plugin = plugin_basename(__FILE__); 
        add_filter("plugin_action_links_$plugin", 'plugin_settings_link');
    }
}
