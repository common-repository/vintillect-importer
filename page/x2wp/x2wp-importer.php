<?php
namespace VintillectImporter\X2wp;
use VintillectImporter\Helpers\Vintillect_Helpers;

if ( ! defined( 'ABSPATH' ) || ! defined( 'WPINC' )) {
    exit; // Exit if accessed directly
}

define('VINTILLECT_IMPORTER_CONFIG_FIELD', 'vintillect_importer_x2wp_config');


class Vintillect_X2wp_Admin_Form {
    private $currentTab = 'settings';
    private $pluginName = 'X2WP';
    private $pluginPage = 'vintillect-x2wp';
    private $configSettings = [];
    private $viewTabs = ['settings'=>'Settings'];
    private $hasAllowedImportsOption = false;
    private $helpers;
    public const PLUGIN_DIR = VINTILLECT_IMPORTER_PLUGIN_DIR . 'page/x2wp/';
    private $tabLabels = ['settings'=>'Settings', 'tweets'=>'Tweets', 'circle-tweets'=>'Circle Tweets', 'likes'=>'Likes', 'chatlogs'=>'Chat Logs', 'chatmedia'=>'Chat Media'];

    public function __construct()
    {
        if (VINTILLECT_IMPORTER_TAB) {
            $this->currentTab = VINTILLECT_IMPORTER_TAB;
        }

        $this->helpers = new Vintillect_Helpers();
    }


    function setup_options() {
        $this->configSettings = $this->helpers->getX2wpConfigSettings();
        $this->hasAllowedImportsOption = (array_key_exists('options', $this->configSettings) && !empty($this->configSettings['options']));
        $this->viewTabs = $this->hasAllowedImportsOption  ? $this->tabLabels :  ['settings'=>'Settings'];
    }

    function display_page_contents() {
        $this->display_nav_tabs();

        if ( $this->currentTab === 'settings') {
            include_once(self::PLUGIN_DIR . "views/settings.php");
            $tab = new X2wp_Settings_View();
            $tab->display_view($this->hasAllowedImportsOption, $this->configSettings);
        }
        else {
            $this->display_other_tabs();
        }
    }


    public function enqueue_scripts() {
        wp_register_style('bootstrapcss', plugins_url('assets/bootstrap.5.3.2.min.css', VINTILLECT_IMPORTER_PLUGIN_FILE));
        wp_enqueue_style('bootstrapcss');

        if ($this->currentTab !== 'settings') {
            wp_enqueue_script('datepicker', plugins_url('assets/datepicker-full.min.js', VINTILLECT_IMPORTER_PLUGIN_FILE), array('jquery'));
            wp_register_style('datepicker', plugins_url('assets/datepicker-bs5.min.css', VINTILLECT_IMPORTER_PLUGIN_FILE));
            wp_enqueue_style('datepicker');

            wp_enqueue_script('rowgroup_datatable', plugins_url('views/rowbasket_datatable.js', __FILE__), array('jquery'));
            wp_enqueue_script('vicommon', plugins_url('views/common.js', __FILE__), array('jquery'));
        }

        wp_register_style('x2wp', plugins_url('x2wp.css', __FILE__));
        wp_enqueue_style('x2wp');

        $s3UrlBase = '';
        $hasS3UrlBase = false;
        $dataJsonUrl = '';
        $availableOptions = [];
        $viConfig = $this->configSettings;

        if ($this->hasAllowedImportsOption) {
            $availableOptions = explode(',', $this->configSettings['options']);
            $s3UrlBase = $this->configSettings['s3Url'] . 'processed/';
            $hasS3UrlBase = true;
        }

        $tabJsonMapping = ['settings'=>'config.json', 'tweets'=>'tweets.json', 'circle-tweets'=>'twitter-circle-tweet.json', 'likes'=>'like.json', 'chatlogs'=>'chatlog_preview.json', 'chatmedia'=>'chatmedia.json'];
        $dataJsonUrl = ($hasS3UrlBase) ? $s3UrlBase . $tabJsonMapping[$this->currentTab] : '';
        $chatMediaCname = '';

        if ($this->currentTab === 'chatmedia' && isset($_GET['cname'])) {
            $chatMediaCname = sanitize_key($_GET['cname']); // preg_replace('/[^A-Za-z0-9_\-]/', '', $_GET['cname']);
            $dataJsonUrl = $s3UrlBase . "messages_media/$chatMediaCname.json";
        }

        switch ($this->currentTab) {
            case 'tweets':
            case 'circle-tweets':
            case 'likes':
                $viewStyleFile = 'posts.css';
                $viewScriptFile = 'posts.js';
                break;
            case 'chatlogs':
                $viewScriptFile = 'chatlogs.js';
                break;
            case 'chatmedia':
                $viewScriptFile = 'chatmediafiles.js';

                if ($chatMediaCname) {
                    $viewScriptFile = 'chatmedia.js';
                    wp_enqueue_script('bootstrapjs', plugins_url('assets/bootstrap.5.3.2.min.js', VINTILLECT_IMPORTER_PLUGIN_FILE), array('jquery'));
                }
                break;
            case 'settings':
            default:
                $viewScriptFile = 'settings.js';
                break;
        }

        wp_enqueue_script('viview', plugins_url('views/' . $viewScriptFile, __FILE__), array('jquery', 'wp-api', 'moment'));

        if ($this->currentTab === 'settings') {
            wp_localize_script('viview', 'vi_config', array(
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vi_ajax_nonce'),
                'config' => $viConfig
            ));
        }
        else {
            wp_localize_script('viview', 'vi_posts_var', array(
                'url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vi_ajax_nonce'),
                'config' => $viConfig,
                'dataUrl' => $dataJsonUrl,
                'availableOptions' => $availableOptions
            ));
        }
    } // end enqueue_scripts()


    function display_nav_tabs() {
        $baseUrl = '/wp-admin/admin.php?page='.$this->pluginPage;
        $uploadId = isset($this->configSettings['uploadId']) ? $this->configSettings['uploadId'] : null;
        $availableOptions = isset($this->configSettings['options']) ? explode(',', $this->configSettings['options']) : [];
        $cartUrl = 'https://vintillect.com/vintillect-importer/x2wp/cart.php?uploadid=' . $uploadId;
        ?>
        <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()) ?></h1>

        <?php
        if (in_array($this->currentTab, ['tweets', 'circle-tweets', 'likes']) && !in_array('media', $availableOptions)) {
            ?>
            <div class="alert alert-warning purchase-warning" id="purchase-warning" role="alert">Media cannot be imported without<br/><a href="<?php echo esc_url($cartUrl); ?>" target="_blank">the Photos &amp; Videos in Tweets option</a>.</div>
            <?php
        }
        elseif ($this->currentTab === 'chatmedia' && !in_array('chatmedia', $availableOptions)) {
            ?>
            <div class="alert alert-warning purchase-warning" id="purchase-warning" role="alert">Media cannot be imported without<br/><a href="<?php echo esc_url($cartUrl); ?>" target="_blank">the Direct Message Photos &amp; Videos option</a>.</div>
            <?php
        }
        elseif ($this->currentTab === 'chatlogs' && !in_array('chatlogs', $availableOptions)) {
            ?>
            <div class="alert alert-warning purchase-warning" id="purchase-warning" role="alert">Chat history cannot be downloaded without<br/><a href="<?php echo esc_url($cartUrl); ?>" target="_blank">the Direct Message History option</a>.</div>
            <?php
        }
        ?>

        <nav class="nav-tab-wrapper wp-clearfix" aria-label="Secondary menu">
        <?php
        foreach ($this->viewTabs as $tabKey => $tabName) {
            print '<a href="' . esc_url($baseUrl . '&tab=' . $tabKey) . '"';

            if ($this->currentTab === $tabKey) {
                print ' class="nav-tab nav-tab-active" aria-current="page">';
            }
            else {
                print ' class="nav-tab">';
            }

            print esc_html($tabName) . "</a>\n";
        }
        ?>
        </nav>

        <div id="error-popup-notification" class="alert alert-danger" role="alert"></div>
        <div id="vi-media-viewer-dlg" title="View Image">
            <div id="vi-media-viewer-inner"></div>
        </div>
        <?php
    } // display_nav_tabs()


    function display_other_tabs() {
        $tabLinkLabel = isset($this->tabLabels[ $this->currentTab ]) ? $this->tabLabels[ $this->currentTab ] : 'Settings';
        $videoUrl = 'https://vintillect.com/vintillect-importer/video.php?' . sanitize_url($_SERVER['QUERY_STRING']);
        $videoUrl = str_replace('video.php?http://', 'video.php?', $videoUrl);
        $helpUrl = 'https://vintillect.com/vintillect-importer/x2wp/help.html#' . $this->currentTab;
        print '<div id="tutorial-link"><a target="video-tutorial" href="' . esc_url($videoUrl) . '">' . esc_html($tabLinkLabel) . ' Tutorial</a><br /><a target="help-instructions" href="' . esc_url($helpUrl) . '">Help Instructions</a></div>'."\n";

        $viewClassFile = 'settings.php';
        $viewClassName = 'X2wp_Settings_View';

        switch ($this->currentTab) {
            case 'tweets':
            case 'circle-tweets':
            case 'likes':
                $viewClassFile = 'posts.php';
                $viewClassName = 'X2wp_Posts_View';
                break;
            case 'chatlogs':
                $viewClassFile = 'chatlogs.php';
                $viewClassName = 'X2wp_ChatLogs_View';
                break;
            case 'chatmedia':
                $viewClassFile = 'chatmedia.php';
                $viewClassName = 'X2wp_ChatMedia_View';
                break;
            case 'settings':
            default:
                $viewClassFile = 'settings.php';
                $viewClassName = 'X2wp_Settings_View';
                break;
        }

        include_once(self::PLUGIN_DIR . "views/$viewClassFile");
        $namespaceClassName = "VintillectImporter\\X2wp\\$viewClassName";
        $tab = new $namespaceClassName();
        $tab->display_view($this->hasAllowedImportsOption, $this->configSettings);
        ?>

            <div id="success-popup-notification" class="alert alert-info" role="alert"></div>
        </div>
        <?php
    } // display_other_tabs()

} // end class X2wp_Admin_Form
