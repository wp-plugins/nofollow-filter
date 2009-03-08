<?php
/*
Plugin Name: NoFollow Filter
Plugin URI: http://www.agarassino.com/wordpress-plugin-nofollow-filter/
Description: Nofollow Filter is a Wordpress plugin that automatically adds “nofollow” attribute to certain links on your posts.
Author: Agustin Garassino
Version: 0.1
Author URI: http://www.agarassino.com.com/
Installation Instructions: PLEASE read the readme.txt that came with this plugin for usage instructions.
*/

### Install function ###
function nofollow_filter_install() {

    global $wpdb;

    $nff_table = $wpdb->prefix . 'nofollow_filter';

    if($wpdb->get_var("show tables like '$nff_table'") != $nff_table) {
        $sql = "
    CREATE TABLE " . $nff_table . " (
  id int(11) NOT NULL auto_increment,
  url varchar(255) NOT NULL,
  name varchar(255) NOT NULL,
active tinyint(1) NOT NULL,
  PRIMARY KEY  (id)
);";
        require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
        dbDelta($sql);
    }

}
register_activation_hook(__FILE__, 'nofollow_filter_install');

### Admin ###
//register the hook
add_action('admin_menu', 'nofollow_filter_menu');

//adding the option
function nofollow_filter_menu() {
    add_options_page('No follow Filter options', 'Nofollow Filter', 8, __FILE__, 'nofollow_filter_options');
}


//the page itself
function nofollow_filter_options() {
    global $wpdb;
    $nff_table = $wpdb->prefix . 'nofollow_filter';

    //if user wants to add a site
    if(isset($_POST['submiturl'])) {
        if($_POST['sitestatus'] == 'on') {
            $_POST['sitestatus'] == 1;
        }
        $args = array(
        'sitename' => FILTER_SANITIZE_STRING,
        'siteurl' => FILTER_VALIDATE_URL,
        'sitestatus' => FILTER_VALIDATE_INT);
        $siteData = filter_input_array(INPUT_POST, $args);

        $errors = array();

        if(empty($siteData['sitename'])) {
            $errors['sitename'] = 'Site name cannot be empty';
        }
        if(empty($siteData['siteurl'])) {
            $errors['siteurl'] = 'Site url cannot be empty';
        }
        if(count($errors) == 0) {

            $sql = "INSERT INTO $nff_table (name, url, active) VALUES ('$siteData[sitename]', '$siteData[siteurl]', '$siteData[sitestatus]')";
            $result = $wpdb->query($sql);
            if($result) {
                $nffmsg = 'Site added ;)';
            }
        }
    }
    //if user wants to delete a site
    if(isset($_POST['delselected'])) {
        foreach($_POST['del'] as $key => $val) {
            if(is_numeric($key) AND $val == 1) {

                $sql = "DELETE FROM $nff_table WHERE id = '$key'";
                $result = $wpdb->query($sql);
                if($result) {
                    $nffmsg = 'Sites has been deleted';

                }
            }
        }
    }

    //if user wants to edit a site
    if(isset($_POST['update'])) {
        foreach($_POST['sites'] as $key => $val) {
            if(is_numeric($key) AND !empty($val['url'])) {
                if(empty($val[active])) {
                    $val[active] = 0;
                }
                $sql = "UPDATE $nff_table
                        SET url = '$val[url]',
                        active = '$val[active]'
                        WHERE id = '$key'";
                $result = $wpdb->query($sql);
                if($result) {
                    $nffmsg = 'Sites has been updated';

                }
            }
        }
    }
    ?>
<div class="wrap">
    <h2>NoFollow Filter Options</h2>

        <?php if($nffmsg) { ?>

    <div id="message1" class="updated fade">
        <p>
            <strong><?php echo $nffmsg; ?></strong>
        </p>
    </div>

            <?php }
        if(count($errors) > 0) { ?>
    <div id="message1" class="updated fade">
        <?php foreach($errors as $error) { ?>
        <p><strong><?php echo $error; ?></strong> </p>
        <?php } ?>
    </div>
    <?php } ?>

    <h4>Add a new site</h4>
    <form name="addsite" method="post" action="<?php echo $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']; ?>" >
        <label for="sitename">Name:</label>
        <input type="text" name="sitename" id="sitename" value="" />
        <label for="siteurl">Url:</label>
        <input type="text" name="siteurl" id="siteurl" value="" />
        <input type="checkbox" name="sitestatus" value="1" /><span>Active?</span>
        <input type="submit" name="submiturl" value="Add Site" />
    </form>

    <h3>Sites</h3>

        <?php
        $sitesList = $wpdb->get_results("SELECT * FROM $nff_table");

        if(count($sitesList) > 0) { ?>
    <form name="sitelist" method="post" action="" >
        <table class="widefat post fixed">
            <thead>
                <tr>
                    <th class="manage-column column-cb check-column"></th>
                    <th class="manage-column column-cb" scope="col">Id</th>
                    <th>Name</th>
                    <th>Url</th>
                    <th>Active?</th>
                </tr>
            </thead>
            <?php foreach($sitesList as $site) { ?>
            <tr>
                <td>
                    <input type="checkbox" name="del[<?php echo $site->id;?>]" value='1' />

                </td>
                <td><?php echo $site->id; ?> </td>
                <td><?php echo $site->name; ?> </td>
                <td>
                    <input type="text" name="sites[<?php echo $site->id; ?>][url]" value="<?php echo $site->url; ?>" />
                </td>
                <td>
                    <input type="checkbox" name="sites[<?php echo $site->id;?>][active]" value="1" <?php if($site->active == 1) echo "checked='checked'"; ?> />
                </td>

            </tr>
            <?php } ?>
        </table>
        <input style="float: left;" type='submit' name="delselected" value="Delete selected" />
        <input style="float: right;" type='submit' name="update" value="Update" />

    </form>
    <?php } else { ?>

    <p>No sites</p>
    <?php } ?>
</div>
<?php   
}

### The filter ###
function nofollow_filter($content) {

global $wpdb;
$nff_table = $wpdb->prefix . 'nofollow_filter';

//we get all the actives sites url
$nffSql=  "SELECT * FROM $nff_table WHERE active = '1'";

$nffSites = $wpdb->get_results($nffSql);

if(count($nffSites) > 0) {

    $doc = new DOMDocument();
    $modContent = mb_convert_encoding($content, 'HTML-ENTITIES', "UTF-8");
    @$doc->loadHTML($modContent);
    $allLinks = $doc->getElementsByTagName('a');
    foreach($nffSites as $nffSite) {

        foreach($allLinks as $alink) {
            $pos = stripos($alink->getAttribute('href'), $nffSite->url);
            if($pos !== false) {

                $alink->setAttribute('rel', 'nofollow');
            }
        }
        $doc->saveHtml();
    }
    $remove_html = preg_replace('/^<!DOCTYPE.+?>/', '', str_replace( array('<html>', '</html>', '<body>', '</body>'), array('', '', '', ''), $doc->saveHTML()));
    return $remove_html;
} else {
 return $content;
}
}
add_filter('the_content', 'nofollow_filter');
?>