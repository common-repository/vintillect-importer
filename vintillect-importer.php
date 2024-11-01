<?php
/*
Plugin Name: Vintillect Importer
Plugin URI: http://vintillect.com/vintillect-importer
Description: Import social media posts from the most popular social apps. Go to Settings -> Vintillect Importer.
Version: 2.0.8
Author: Robert Seaborn
Author URI: http://vintillect.com
License: GPLv2 or later
*/
namespace VintillectImporter;
use VintillectImporter\X2wp\Vintillect_X2wp_Ajax;
use VintillectImporter\Fb2wp\Vintillect_Fb2wp_Ajax;
use VintillectImporter\X2wp\Vintillect_X2wp_Admin_Form;
use VintillectImporter\Fb2wp\Vintillect_Fb2wp_Admin_Form;


if ( ! defined( 'ABSPATH' ) || ! defined( 'WPINC' )) {
    exit; // Exit if accessed directly
}

define('VINTILLECT_IMPORTER_PLUGIN_FILE', __FILE__);
define('VINTILLECT_IMPORTER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VINTILLECT_IMPORTER_PAGE', isset($_GET['page']) ? sanitize_key($_GET['page']) : '');
define('VINTILLECT_IMPORTER_TAB', isset($_GET['tab']) ? sanitize_key($_GET['tab']) : '');
define('VINTILLECT_IMPORTER_REQUESTED', ( stripos(VINTILLECT_IMPORTER_PAGE, 'vintillect') !== FALSE ) || (stripos($_SERVER['REQUEST_URI'], 'options.php') && $_POST['option_page'] === 'vintillect-importer') );
define('VINTILLECT_IMPORTER_WEBSITE_URL', 'https://vintillect.com/vintillect-importer/');
define('VINTILLECT_IMPORTER_AWS_BASE_URL', 'https://x2wp-processed.s3.amazonaws.com/');

if (VINTILLECT_IMPORTER_REQUESTED) {
    include VINTILLECT_IMPORTER_PLUGIN_DIR . 'includes/helpers.php';
}

if (wp_doing_ajax() && isset($_POST['page'])) {
    if ($_POST['page'] === 'x2wp') {
        include VINTILLECT_IMPORTER_PLUGIN_DIR . 'includes/helpers.php';
        include VINTILLECT_IMPORTER_PLUGIN_DIR . 'includes/ajax-helpers.php';
        include VINTILLECT_IMPORTER_PLUGIN_DIR . 'page/x2wp/ajax.php';
        $vintillectX2wpAjaxHandler = new Vintillect_X2wp_Ajax();
        $vintillectX2wpAjaxHandler->init();
    }
    elseif ($_POST['page'] === 'fb2wp') {
        include VINTILLECT_IMPORTER_PLUGIN_DIR . 'includes/helpers.php';
        include VINTILLECT_IMPORTER_PLUGIN_DIR . 'includes/ajax-helpers.php';
        include VINTILLECT_IMPORTER_PLUGIN_DIR . 'page/fb2wp/ajax.php';
        $vintillectFb2wpAjaxHandler = new Vintillect_Fb2wp_Ajax();
        $vintillectFb2wpAjaxHandler->init();
    }
}
else {
    $vintillectPage = null;
    if (VINTILLECT_IMPORTER_REQUESTED) {
        if ((VINTILLECT_IMPORTER_PAGE === 'vintillect-x2wp') || (isset($_POST['_wp_http_referer']) && stripos($_POST['_wp_http_referer'], 'vintillect-x2wp'))) {
            include VINTILLECT_IMPORTER_PLUGIN_DIR . 'page/x2wp/x2wp-importer.php';
            $vintillectPage = new Vintillect_X2wp_Admin_Form();
        }
        else {
            include VINTILLECT_IMPORTER_PLUGIN_DIR . 'page/fb2wp/fb2wp-importer.php';
            $vintillectPage = new Vintillect_Fb2wp_Admin_Form();
        }
    }

    $vintillectAdminForm = new Vintillect_Form_Controller($vintillectPage);
}

class Vintillect_Form_Controller {
    private $page = null;

    public function __construct($page)
    {
        if (VINTILLECT_IMPORTER_REQUESTED) {
            $this->page = $page;
        }

        add_action('admin_menu', array($this, 'vintillect_admin_menu') );

        if (VINTILLECT_IMPORTER_REQUESTED) {
            add_action('admin_enqueue_scripts', array($this->page, 'enqueue_scripts'));
        }
    }


    function vintillect_admin_menu() {
        $menu_slug = 'vintillect-importer';

        if (VINTILLECT_IMPORTER_REQUESTED) {
            $this->page->setup_options();
            add_menu_page('Vintillect Importer (Facebook)', 'Vintillect Importer', 'manage_options', $menu_slug, array($this->page, 'display_page_contents'), 'dashicons-groups' );
        }
        else {
            add_menu_page('Vintillect Importer (Facebook)', 'Vintillect Importer', 'manage_options', $menu_slug, array($this, 'empty'), 'dashicons-groups');
        }

        add_submenu_page($menu_slug, 'Vintillect Importer (Facebook)', 'Import FB', 'manage_options', $menu_slug );

        if (VINTILLECT_IMPORTER_REQUESTED && VINTILLECT_IMPORTER_PAGE === 'vintillect-x2wp') {
            add_submenu_page($menu_slug, 'Vintillect Importer (Twitter)', 'Import X', 'manage_options', 'vintillect-x2wp', array($this->page, 'display_page_contents') );
        }
        else {
            add_submenu_page($menu_slug, 'Vintillect Importer (Twitter)', 'Import X', 'manage_options', 'vintillect-x2wp', array($this, 'empty') );
        }
    }

    function empty() {
    }
} // end class Vintillect_Form_Controller
