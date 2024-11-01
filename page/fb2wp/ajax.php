<?php
namespace VintillectImporter\Fb2wp;
use VintillectImporter\Helpers\Vintillect_Helpers;
use VintillectImporter\Helpers\Vintillect_Ajax_Helpers;
use VintillectImporter\Helpers\Vintillect_CollageMaker;
use \DateTime;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define('VINTILLECT_IMPORTER_CONFIG_FIELD', 'vintillect_importer_fb2wp_config');


class Vintillect_Fb2wp_Ajax {
  private $ajaxHelper = null;
  private $configSettings = [];
  private $availableOptions = [];
  private $allowMedia = false;
  private $mediaUrlBase = '';
  private $adLinksInPostFooter = false;
  private $firstLink = null;
  private $tagsDict = [];
  private $helpers;


  public function init() {
    $this->helpers = new Vintillect_Helpers();
    $this->configSettings = $this->helpers->getFb2wpConfigSettings();
    $this->mediaUrlBase = isset($this->configSettings['s3Url']) ? $this->configSettings['s3Url'] : '';
    $this->ajaxHelper = new Vintillect_Ajax_Helpers('fb2wp', $this->mediaUrlBase, true, $this->configSettings);

    add_action('wp_ajax_fb2wp_post_ajax_action', array($this, 'postAjaxCallback'));
    add_action('wp_ajax_vintillect_media_ajax_action', array($this->ajaxHelper, 'mediaAjaxCallback'));
    add_action('wp_ajax_fb2wp_update_config', array($this, 'updateConfig'));

    $this->availableOptions = isset($this->configSettings['options']) ? explode(',', $this->configSettings['options']) : [];
    $this->allowMedia = in_array('albums', $this->availableOptions);
    $this->adLinksInPostFooter = in_array('ad_links', $this->availableOptions);
    $this->ajaxHelper->adLinksInPostFooter = $this->adLinksInPostFooter;
  }


  public function postAjaxCallback() {
    if (! $this->ajaxHelper->verifyAdmin()) return;

    $title = isset( $_POST['data']['title'] ) ? sanitize_text_field($_POST['data']['title']) : '';
    $date_gmt = isset( $_POST['data']['date_gmt'] ) ? sanitize_key($_POST['data']['date_gmt']) : '';
    $status = isset( $_POST['data']['status'] ) ? sanitize_key($_POST['data']['status']) : 'draft';
    $postsJson = isset( $_POST['data']['posts'] ) ? sanitize_textarea_field($_POST['data']['posts']) : '[]';
    $makeGallery = isset( $_POST['make-gallery'] );
    $postsJson = stripslashes($postsJson);
    $posts = json_decode($postsJson, true);
    $siteTzDate = get_date_from_gmt($date_gmt); // https://developer.wordpress.org/reference/functions/get_date_from_gmt/
    $dateTimeGmt = new DateTime($siteTzDate);
    $lastTimestamp = $dateTimeGmt->getTimestamp();
    $doAddTags = (isset( $_POST['data']['import_tags'] ) && $_POST['data']['import_tags'] === 'true');

    if (!$title || empty($posts) || !$date_gmt || !$status) {
      $response = array(
        'message' => 'Incomplete data.',
      );
      
      wp_send_json_error($response);
      return;
    }

    include_once(VINTILLECT_IMPORTER_PLUGIN_DIR . 'includes/collage.php');
    $collageMaker = new Vintillect_CollageMaker(800, 550); // 800 x 550 is WP's thumbnail size
    $collageMaker->setCanvasColors(255, 255, 255, 127);

    $errors = [];
    $postId = 0;
    
    if ($status !== 'upload-only') {
      // create the post first so images can be saved relating to it
      $postarr = ['post_date_gmt'=>$date_gmt, 'post_date'=>$siteTzDate, 'post_content'=>'', 'post_title'=>$title, 'post_status'=>$status, 'post_type'=>'post'];
      $postId = wp_insert_post($postarr, true); // https://developer.wordpress.org/reference/functions/wp_insert_post/
    }

    $galleryContent = '';
    if ($makeGallery) {
      $media = $this->extractMediaFromPosts($posts);
      $galleryContent = $this->ajaxHelper->attachGalleryToPostSimple($media, '', $siteTzDate, $postId);
    }

    $formattedPosts = $this->formatMultiplePosts($posts, $postId, $siteTzDate, $doAddTags, false);
    if ($galleryContent) { array_unshift($formattedPosts, $galleryContent); }
    $lineSeparator = $this->ajaxHelper->makeHorizontalLine();
    $formattedContent = implode($lineSeparator, $formattedPosts);

    if ($this->adLinksInPostFooter) {
      $formattedContent = $this->ajaxHelper->addVintillectAdLink($formattedContent);
    }

    $linkImageExternalThumbnail = $this->ajaxHelper->createExternalFeaturedImage($postId, $this->firstLink);

    if ($doAddTags) {
      wp_add_post_tags($postId, array_keys($this->tagsDict));
    }

    if ($status !== 'upload-only') {
      $postarr = ['ID'=>$postId, 'post_content'=>wp_kses_post($formattedContent), 'post_date_gmt'=>$date_gmt, 'post_date'=>$siteTzDate];
      $postId = wp_update_post($postarr, true); // https://developer.wordpress.org/reference/functions/wp_insert_post/
      // https://developer.wordpress.org/rest-api/reference/posts/#schema-date

      if (!$linkImageExternalThumbnail && !empty($this->ajaxHelper->imageAttachmentIds)) {
        $imagePaths = $this->ajaxHelper->getImagePathsFromAttachmentIds();
        list ($imgArr, $srcImgSizes) = $collageMaker->makeSourceImagesAndSizesFromPaths($imagePaths);

        if (! empty($imgArr)) {
          $collageMaker->copyImagesToCanvas($imgArr, $srcImgSizes);
          $this->ajaxHelper->saveCollage($collageMaker, $lastTimestamp, $postId);

          foreach ($imgArr as $img) {
            imagedestroy($img);
          }
        }
      }
    }

    if (!empty($errors)) {
      wp_send_json_error(['message'=>'errors saving post', 'errors'=>$errors]);
    }
    else {
      wp_send_json_success(['message' => 'Created new post.', 'id' => $postId, 'tags'=>$this->tagsDict]);
    }
  } // end postAjaxCallback()


  private function formatMultiplePosts($posts, $postId, $siteTzDate, $doAddTags) {
    if (!$posts || empty($posts)) { return []; }
    $hasGroupedPosts = (count($posts) > 1);
    $formattedPosts = [];

    foreach ($posts as $post) {
      $description = '';
      $mediaFormatted = [];
      $pTitle = $post['title'];

      if ($doAddTags) {
        foreach ($this->ajaxHelper->getHashtags($post['description']) as $tag) {
          $this->tagsDict[$tag] = 1; // creates unique tag keys
        }
      }

      if ($this->allowMedia && !empty($post['media']) && ($post['description'] === $post['media'][0]['description'])) { // do not duplicate the media description
        if ($post['description'] !== $post['title']) {
          $description = $pTitle;
          $formattedContent = $this->ajaxHelper->formatTextContent($description);
        }
      }
      else {
        $post['description'] = mb_convert_encoding($post['description'], 'ISO-8859-1', 'UTF-8');

        if ($hasGroupedPosts) {
          $description = "$pTitle\n\n";
        }

        if ($post['description'] && $post['description'] !== $post['title']) { $description .= $post['description']; }
        $formattedContent = $this->ajaxHelper->formatTextContent($description);
      }

      if ($this->allowMedia && !empty($post['media'])) {
        if (count($post['media']) > 3) {
          $mediaContent = $this->ajaxHelper->attachGalleryToPostSimple($post['media'], '', $siteTzDate, $postId);

          if ($mediaContent) {
            $mediaFormatted[] = $mediaContent;
          }
          else {
            $errors[] = 'Unable to post Gallery in post "' . $post['title'] . '"';
          }
        }
        else {
          foreach ($post['media'] as $mediaItem) {
            $mediaContent = $this->ajaxHelper->attachSingleMediaItemToPost($mediaItem, '', $siteTzDate, $postId);

            if ($mediaContent) {
              $mediaFormatted[] = $mediaContent;
            }
            else {
              $errors[] = 'Unable to post photo '.basename($mediaItem['uri']).' in post "' . $post['title'] . '"';
            }
          }
        }

        if ($formattedContent) { $formattedContent .= "\n\n"; }
        $formattedContent .= implode("\n\n", $mediaFormatted);
      } // end allowMedia && $post['media']

      $linksFormatted = [];

      foreach ($post['links'] as $linkItem) {
        $linksFormatted[] = $this->ajaxHelper->makeLinkContent($linkItem);

        if (!$this->firstLink && isset($linkItem['imgUrl'])) {
          $this->firstLink = $linkItem;
        }
      }
      if (! empty($linksFormatted)) {
        if ($formattedContent) { $formattedContent .= "\n\n"; }
        $formattedContent .= implode("\n\n", $linksFormatted);
      }

      $formattedPosts[] = $formattedContent;
      $formattedContent = '';
    } // end foreach ($posts as $post)

    return $formattedPosts;
  } // formatMultiplePosts()


  public function updateConfig() {
    if (! $this->ajaxHelper->verifyAdmin()) return;

    if (isset( $_POST['data']['doReset'] )) {
      delete_option(VINTILLECT_IMPORTER_CONFIG_FIELD);
      wp_send_json_success(['message' => 'reset complete', 'success'=>true]);
      return;
    }

    $uploadId = isset( $_POST['data']['uploadId'] ) ? sanitize_text_field($_POST['data']['uploadId']) : '';
    $s3Url = isset( $_POST['data']['s3Url'] ) ? sanitize_url($_POST['data']['s3Url']) : '';
    $options = isset( $_POST['data']['options'] ) ? sanitize_text_field($_POST['data']['options']) : '';
    $jsonAvailable = isset( $_POST['data']['jsonAvailable'] ) ? sanitize_text_field($_POST['data']['jsonAvailable']) : '';
    $dateUploaded = isset( $_POST['data']['dateUploaded'] ) ? sanitize_text_field($_POST['data']['dateUploaded']) : '';

    if (!$uploadId) {
      wp_send_json_error(['message'=>'Missing uploadId']);
      return;
    }

    $viConfig = ['uploadId'=>$uploadId];
    if ($s3Url) { $viConfig['s3Url'] = $s3Url; }
    if ($options) { $viConfig['options'] = $options; }
    if ($jsonAvailable) { $viConfig['jsonAvailable'] = $jsonAvailable; }
    if ($dateUploaded) { $viConfig['dateUploaded'] = $dateUploaded; }

    $viConfigJson = json_encode($viConfig, true);
    update_option(VINTILLECT_IMPORTER_CONFIG_FIELD, $viConfigJson);

    wp_send_json_success(['message' => 'updated', 'success'=>true]);
  }

  private function extractMediaFromPosts(&$posts) {
    $media = [];
    $mediaCount = 0;

    foreach ($posts as $post) {
      foreach ($post['media'] as $mediaItem) {
        if ($this->ajaxHelper->isImage($mediaItem['ext'])) {
          $mediaCount++;
        }
      }
    }

    if ($mediaCount < 2) {
      return '';
    }

    foreach ($posts as $pidx => $post) {
      if (count($media) > 8) { break; }

      foreach ($post['media'] as $midx => $mediaItem) {
        if (count($media) > 8) { break; }

        if ($this->ajaxHelper->isImage($mediaItem['ext'])) {
          $media[] = $mediaItem;
          unset($post['media'][$midx]);
        }
      }

      if (empty($post['media']) && empty($post['links']) && !$post['description']) {
        unset($posts[$pidx]);
      }
    }

    return $media;
  }

} // end Fb2wp_Ajax class
