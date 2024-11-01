<?php
namespace VintillectImporter\X2wp;
use VintillectImporter\Helpers\Vintillect_Helpers;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class X2wp_Settings_View {
  private $pluginName = 'x2wp';
  private $settingsGroup = 'x2wp_settings';
  private $optionsGroup = 'x2wp_options';

  public function display_view($hasAllowedImportsOption, $configSettings) {
    print "<div id='tutorial-link'><a target='video-tutorial' href='https://vintillect.com/vintillect-importer/video.php?page=vintillect-x2wp&tab=settings'>Settings Tutorial</a><br /><a target='help-instructions' href='https://vintillect.com/vintillect-importer/x2wp/help.html#settings'>Help Instructions</a></div></div>\n";

    $hasConfig = (array_key_exists('uploadId', $configSettings) && !empty($configSettings['uploadId']));
    if (!$hasConfig) {
      ?>
        <p style="font-size:14px;">The Vintillect Importer cloud service parses your large social media data file so this plugin can present your data in a way that you can select posts and media to easily copy into your blog.</p>

        <table style="width:85%;">
          <tbody>
            <tr>
              <td style="width:43%; padding-right:1rem;">Large data files will fail to upload on most sites. On Vintillect, you can upload it securely and directly to the cloud from which this plugin can select and import text posts, images, and videos:</td>
              <td style="vertical-align:top;">If you have already uploaded your data file and received a confirmation email that it has been processed, enter the your secret Upload ID here.</td>
            </tr>
            <tr>
              <td>
                <form method="post" action="https://vintillect.com/vintillect-importer/upload.php" target="vintillectcomx2wp">
                  <input type="hidden" name="website" id="vi-wp-website" value="<?php echo esc_url(get_site_url()) ?>" />
                  <input type="hidden" name="socmedproj" value="x2wp" />
                  <button class="btn btn-success" type="submit"><span class="dashicons dashicons-upload" style="float:left;font-size:2rem;margin-right:1rem;margin-top: 0.5rem;"></span> Upload Twitter Data File<br />on Vintillect.com</button>
                </form>
              </td>
              <td style="vertical-align:top;">
                <div class="form-inline row">
                  <div class="col-6"><input id="upload-id" name="upload-id" class="form-control" placeholder="enter upload ID" /></div>
                  <div class="col-6"><button id="get-config-btn" class="btn btn-primary"><span class="dashicons dashicons-admin-generic"></span> Get my Settings</button></div>
                  <div id="config-response-msg" style="margin:0.4rem 0 0 2rem;"></div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      <?php
      //           <div class="col-3"><button id="get-status-btn" class="btn btn-info"><span class="dashicons dashicons-clock"></span> Get processing status</button></div>
    }
    else {
      if (! $hasAllowedImportsOption) {
        print "<p style='margin:1rem 0; font-size:1rem;'>Your upload is being processed. It will take several minutes and you will receive an email when it is done. After you receive the email, refresh this page and then you can view your posts.</p>\n";
      }

      $expirationMessage = '';
      if (array_key_exists('dateUploaded', $configSettings)) {
        $expirationDate = date('F j, Y', strtotime( $configSettings['dateUploaded'] . ' + 7 days'));
        $expirationMessage = "Expires on $expirationDate.";
      }

      ?>
      <p style="font-size:1rem; margin:1rem 0;">Your upload ID code: <b><?php echo esc_attr($configSettings['uploadId']); ?></b></p>

      <div class="form-inline row" style="margin:2rem 0;">
        <div class="col-2"><input id="upload-id" name="upload-id" class="form-control" placeholder="enter upload ID" value="<?php echo esc_attr($configSettings['uploadId']); ?>" /></div>
        <div class="col-2"><button id="get-config-btn" class="btn btn-primary"><span class="dashicons dashicons-admin-generic"></span> Get my Settings</button></div>
        <!-- <div class="col-3"><button id="get-status-btn" class="btn btn-info"><span class="dashicons dashicons-clock"></span> Get processing status</button></div> -->
        <div id="config-response-msg" style="margin:0.4rem 0 0 2rem;"></div>
      </div>

      <p>Your files will be available for 7 days after uploading. <?php echo esc_html($expirationMessage) ?> After that, they will be automatically removed to protect your privacy.</p><br />

      <div id="reset-upload-div" class="alert alert-light">
        If your upload has expired already, or you want to submit a new upload with fresher content, you can click this button and it will reset your data with FW2WP. Any current blog posts or media uploaded to your site will remain. Please note that if you want media to be added again after a new upload, you will need to pay again.<br />
        <button id="reset-upload-btn" class="btn btn-info">Reset My Upload</button>
      </div>
      <?php
    }

    $gigabyte = 1073741824; // 1,073,741,824 bytes in a gigabyte
    $spaceFree = round(disk_free_space(ABSPATH)/$gigabyte);
    $uploadSpaceAvailable = round(disk_total_space(ABSPATH)/$gigabyte);
    $spaceUsed = $uploadSpaceAvailable - $spaceFree;
    $percentUsed = ($uploadSpaceAvailable) ? round((100 * $spaceUsed / $uploadSpaceAvailable), 2) : '0';
    $progressColor = 'white';
    if ($percentUsed >= 70) { $progressColor = 'orange'; }
    if ($percentUsed >= 90) { $progressColor = 'red'; }

    // $duCmd = 'du -d 0 ' . ABSPATH; // what if this is a Windows Server?
    // $duOutput = shell_exec($duCmd);
    // $duOutputTokens = explode(' ', $duOutput);
    // $realSpaceUsed = round(intVal($duOutputTokens[0]) / (1024 * 1024), 3);
    // <div>Actual space used by this WordPress site: <u>$realSpaceUsed GB</u></div>

    $usedSpaceStr = "$percentUsed % space used ($spaceUsed GB) out of space available $uploadSpaceAvailable GB";

?>
    <div id="space-available" style="margin-top:4rem;">
      <h5>WordPress Media Library Space</h5>
      <div><?php echo esc_html($usedSpaceStr); ?></div>
      <div style="width:30%; background-color:<?php echo esc_attr($progressColor); ?>;"><progress value="<?php echo intval($percentUsed); ?>" max="100" style="width:100%;"></progress></div>
      <div style="color:goldenrod;">Be careful not to run out of space when uploading media.</div>
      <div>Be sure to check with your web host regarding how much actual space you have available.<br />The space available shown above <u>might not</u> reflect how much space you really have available on a <u>shared web host</u>.</div>
    </div>
    <?php


    $helpers = new Vintillect_Helpers();
    $foundPlugin = $helpers->getExternalImageUrlPlugin();

    if (! $foundPlugin) {
      $pluginsSearchUrl = get_admin_url() . 'plugin-install.php?s=fifu&tab=search&type=term';
      $thisPluginBaseUrl = plugin_dir_url( VINTILLECT_IMPORTER_PLUGIN_DIR ) . 'vintillect-importer/';

      ?>
      <br /><br />
      <div id="recommended-plugins" class="alert alert-warning">
        <p>It is recommended that you install and activate this plugin so that your blog roll isn't just a bland wall of text if it includes links to external sites. It will be used to include preview images for those links.</p>

        <div class="row">
            <div class="col-md-6">
              <a href="<?php echo esc_url($pluginsSearchUrl); ?>" target="_blank"><img src="<?php echo esc_url($thisPluginBaseUrl); ?>assets/fifu.png" border="0" alt="Featured Image from URL WordPress Plugin" /></a>
            </div>
        </div>
      </div>
      <?php
    }
    else {
      if ($foundPlugin === 'featured-image-from-url') {
        print '<br /><br><p>"Featured Image from URL WordPress Plugin" is installed.</p>';
        if (! function_exists('fifu_dev_set_image')) {
          print '<p class="alert alert-danger">However, the "Featured Image from URL WordPress Plugin" may not be activated.</p>';
        }
      }
    }

  } // end display_view()

}