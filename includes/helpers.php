<?php
namespace VintillectImporter\Helpers;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Vintillect_Helpers {
  function getFb2wpConfigSettings() {
    $json = get_option('vintillect_importer_fb2wp_config');
    if (!$json) { return []; }

    $configSettings = json_decode($json, true);
    $configSettings['vintillect_importer_fb2wp_config'] = $json;
    return $configSettings;
  }

  function getX2wpConfigSettings() {
    $json = get_option('vintillect_importer_x2wp_config');
    if (!$json) { return []; }

    $configSettings = json_decode($json, true);
    $configSettings['vintillect_importer_x2wp_config'] = $json;
    return $configSettings;
  }

  function getExternalImageUrlPlugin() {
    $plugins = get_plugins();

    foreach ($plugins as $pluginKey => $plugin) {
      if ($plugin['TextDomain'] === 'featured-image-from-url') {
        return 'featured-image-from-url';
      }
    }
  }

  function getAttachmentIdByFilename($filename) {
    global $wpdb;
    // $wpdb->show_errors();
    $lastPeriodIdx = strrpos($filename, '.');
    $fileScaledName = substr($filename, 0, $lastPeriodIdx) . '-scaled' . substr($filename, $lastPeriodIdx);

    $attachmentId = $wpdb->get_var(
      $wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND (meta_value like %s OR meta_value like %s) LIMIT 1", '%'.$filename, '%'.$fileScaledName)
    );
    return $attachmentId;
  }
}
