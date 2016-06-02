<?php defined('ABSPATH') or die('Restriced Access'); ?>

<div class="wrap">
    <h2>Amazon Settings</h2>
    <form method="post" action="options.php"> 
        <?php settings_fields('waalg-group');?>
        <?php do_settings_sections('waalg-group');?>
        <table class="form-table">  
            <tr valign="top">
                <th scope="row">
                    <label for="waalg_enable_asin">
                        Replace Amazon links to a product (containing an ASIN)
                    </label>
                </th>
                <td>
                  <input name="waalg_enable_asin"
                         type="checkbox"
                         id="waalg_enable_asin"
                         value="1"
                         <?php checked(get_option('waalg_enable_asin', 1), 1);?> />
                  <br/><br/>Example: https://www.amazon.com/gp/product/B018FK66TU/
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="waalg_enable_keyw">
                        Replace Amazon links containing search keywords
                    </label>
                </th>
                <td>
                  <input name="waalg_enable_keyw"
                         type="checkbox"
                         id="waalg_enable_keyw"
                         value="1"
                         <?php checked(get_option('waalg_enable_keyw', 1), 1);?> />
		                  <br/><br/>Example: https://www.amazon.com/s/ref=nb_sb_noss_2?url=search-alias%3Dmovies-tv&amp;field-keywords=star+wars
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="waalg_affilate_id">
                        Associates ID<br/>(optional)
                    </label>
                </th>
                <td>
                    <table >  
                        <?php generate_asin_input();?>
                    </table>
                </td>
            </tr> 
            <tr valign="top">
                <th scope="row">
                    <label for="waalg_fallback">
                      Fallback URL<br/>(optional)
                    </label>
                </th>
                <td>
                  <select name="waalg_fallback">
                      <?php generate_fallback_options();?>
                  </select> 
                </td>
            </tr>                
            <tr valign="top">
                <th scope="row">
                    <label for="waalg_ascsubtag">
                      Associate Sub-Tag<br/>(optional)
                    </label>
                </th>
                <td>
                    <input name="waalg_ascsubtag"
                           type="text"
                           id="waalg_ascsubtag"
                           value="<?php echo get_option('waalg_ascsubtag', '');?>" />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    <label for="waalg_add_url">
                      Additional URL parameters<br/>(optional)
                    </label>
                </th>
                <td>
                    <input name="waalg_add_url"
                           type="text"
                           id="waalg_add_url"
                           value="<?php echo get_option('waalg_add_url', '');?>" />
                </td>
            </tr>
        </table>
        <?php submit_button(); ?>
    </form>
</div>

<?php
include_once 'amazon-link-globalizer.php';

function generate_fallback_options()
{
    $selected_tld = get_option('waalg_fallback', '-');
    if ($selected_tld == '-')
        echo '                      <option value="-" selected="selected">None</option>'."\r\n"; 
    else
        echo '                      <option value="-">None</option>'."\r\n";  

    foreach (WAALG::getTldList() as $tld)
    {   
        if ($tld == $selected_tld)
            echo '                      <option value="'.$tld.'" selected="selected">Amazon.'.strtoupper($tld).'</option>'."\r\n"; 
        else
            echo '                      <option value="'.$tld.'">Amazon.'.strtoupper($tld).'</option>'."\r\n";               
    }
}

function generate_asin_input()
{
    $id_list = get_option('waalg_affilate_id');
    
    foreach (WAALG::getTldList() as $tld) 
    {
        $id = '';
        if (is_array($id_list) && array_key_exists($tld, $id_list))
            $id = $id_list[$tld];
        
        echo '            <tr valign="top">'."\r\n";
        echo '                <th scope="row" style="padding: 0px;">'."\r\n";
        echo '                    <label for="waalg_affilate_id['.$tld.']">Amazon.'.$tld.'</label>'."\r\n";
        echo '                </th>'."\r\n";
        echo '                <td style="padding: 0px;">'."\r\n";
        echo '                    <input name="waalg_affilate_id['.$tld.']"'."\r\n";
        echo '                           type="text" '."\r\n";
        echo '                           id="waalg_affilate_id['.$tld.']"'."\r\n";
        echo '                           value="'.$id.'" />'."\r\n";
        echo '                </td>'."\r\n";
        echo '            </tr>'."\r\n";
    }
}
?>
