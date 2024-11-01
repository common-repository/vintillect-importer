<?php
namespace VintillectImporter\X2wp;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class X2wp_ChatLogs_View {

  public function display_view($hasAllowedImportsOption, $configSettings) {
    ?>
      <div id="chatlogs-view">
        <?php
          if ( (strpos($configSettings['options'], 'chatlogs') !== false) && (strpos($configSettings['jsonAvailable'], 'chatlog.zip') !== false) ) {
            $zipUrl = $configSettings['s3Url'] . 'processed/chatlog.zip';
            ?>
            <div>You can <a href="<?php echo esc_url($zipUrl); ?>">download your chat logs file</a>.</div>
            <?php
          }
          else {
            $uploadId = $configSettings['uploadId'];
            ?>
              <div class="alert alert-warning purchase-warning" id="purchase-warning" role="alert">Chat history available for purchase.<br/>Check the <a href="<?php echo esc_url('https://vintillect.com/vintillect-importer/x2wp/cart.php?uploadid=' . $uploadId); ?>" target="_blank">the Direct Message History option</a>.</div>
            <?php
          }
        ?>

        <div id="chatlogs-table-wrapper"></div>
      </div>
    <?php
  }

}
