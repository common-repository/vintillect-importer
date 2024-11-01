<?php
namespace VintillectImporter\Helpers;

// utility functions used in Ajax classes
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class Vintillect_Ajax_Helpers {
  public $prefixCode = null;
  private $mediaUrlBase = '';
  public $doMbStringConvert = false;
  public $imageAttachmentIds = [];
  public $adLinksInPostFooter = false;
  private $configSettings = [];
  private $helpers;

  public function __construct($prefixCode, $mediaUrlBase, $doMbStringConvert, $configSettings) {
    $this->helpers = new Vintillect_Helpers();
    $this->prefixCode = $prefixCode;
    $this->mediaUrlBase = $mediaUrlBase;
    $this->doMbStringConvert = $doMbStringConvert;
    $this->configSettings = $configSettings;
  }


  // sideloading a file from URL:  https://wp-kama.com/function/media_handle_sideload    https://developer.wordpress.org/reference/functions/media_handle_sideload/    https://developer.wordpress.org/reference/hooks/wp_handle_upload/
  public function mediaAjaxCallback() {
    if (! $this->verifyAdmin()) return;

    $url = isset( $_POST['data']['url'] ) ? sanitize_url($_POST['data']['url']) : '';
    $title = isset( $_POST['data']['title'] ) ? sanitize_text_field($_POST['data']['title']) : '';
    $description = isset( $_POST['data']['description'] ) ? sanitize_textarea_field($_POST['data']['description']) : '';
    $date_gmt = isset( $_POST['data']['date_gmt'] ) ? sanitize_text_field($_POST['data']['date_gmt']) : '';
    $status = isset( $_POST['data']['status'] ) ? sanitize_key($_POST['data']['status']) : 'draft';
    $postId = isset( $_POST['data']['postid'] ) ? intval($_POST['data']['postid']) : 0;
    $siteTzDate = get_date_from_gmt($date_gmt); // https://developer.wordpress.org/reference/functions/get_date_from_gmt/
    $humanDt = $this->humanDateTime($siteTzDate);
    $makeNewBlogPost = isset( $_POST['data']['new_blog_post'] ) ? ($_POST['data']['new_blog_post'] === 'true') : false;

    if (!$date_gmt) {
      $response = array(
        'message' => 'Incomplete data.',
      );
      
      // Send the JSON error response
      wp_send_json_error($response);
      return;
    }

    $ext = substr($url, strrpos($url, '.') + 1);
    if (!$this->isValidMediaType($ext)) {
      wp_send_json_error(['message'=>'Invalid media type: '.$url]);
      return;
    }

    if ($title && (strpos($title, 'Timeline photos') === false)) {
      if ($description) { $description = "$title - $description"; }
      else { $description = $title; }
    }

    if ($makeNewBlogPost) {
      $title = ($title) ? "$title - $humanDt" : $humanDt;
      $postarr = ['post_date_gmt'=>$date_gmt, 'post_date'=>$siteTzDate, 'post_content'=>wp_kses_post($description), 'post_excerpt'=>sanitize_text_field($description), 'post_title'=>$title, 'post_status'=>$status, 'post_type'=>'post'];
      $postId = wp_insert_post($postarr, true); // https://developer.wordpress.org/reference/functions/wp_insert_post/   https://developer.wordpress.org/rest-api/reference/posts/#schema-date
    }
    
    $fileBaseName = basename($url);
    $attachmentId = $this->helpers->getAttachmentIdByFilename($fileBaseName);
    $existsAlready = false;

    if ($attachmentId) {
      $existsAlready = true;
      if (!$makeNewBlogPost) {
        wp_send_json_success(['message'=>'Media item already exists: '.$fileBaseName, 'id'=>$attachmentId]);
        return;
      }
    }

    $url = $this->mediaUrlBase . $url;

    if (!$existsAlready) {
      $attachmentId = $this->downloadAndSaveMediaUrl($url, $description, $siteTzDate, $postId);

      // if there is an error
      if( is_wp_error( $attachmentId ) ){
        wp_send_json_error(['message'=>'errors saving file: '.$url, 'errors'=>$attachmentId->get_error_messages()]);
        return;
      }
    }

    if ($makeNewBlogPost) {
      $ext = $this->getExt($url);

      if ($this->isImage($ext)) {
        $imageUrl = wp_get_attachment_image_url($attachmentId, 'full');
        $imageSrc = '<img src="'.$imageUrl.'" alt="'.$description.'" class="wp-image-'.$attachmentId.'"/>';
        $updatedContent = '<!-- wp:image {"id":'.$attachmentId.',"sizeSlug":"full","linkDestination":"attachment"} -->'."\n".'<figure class="wp-block-image size-full"><a href="'.$imageUrl.'">'.$imageSrc.'</a>' . (($description) ? '<figcaption class="wp-element-caption">'.$description.'</figcaption>' : '') . "</figure>\n<!-- /wp:image -->";
        $updatedPostId = wp_update_post(['ID'=>$postId, 'post_content'=>wp_kses_post($updatedContent)], true);
        set_post_thumbnail( $updatedPostId, $attachmentId );
      }
      elseif ($this->isVideo($ext)) {
        $videoUrl = wp_get_attachment_url($attachmentId);
        $updatedContent = "<!-- wp:video -->\n\n".'<figure class="wp-block-video"><video controls src="'. $videoUrl .'"></video>' . (($description) ? '<figcaption class="wp-element-caption">'.$description.'</figcaption>' : '') ."</figure>\n<!-- /wp:video -->";
        $updatedPostId = wp_update_post(['ID'=>$postId, 'post_content'=>wp_kses_post($updatedContent)], true);
      }
      elseif ($this->isAudio($ext)) {
        $videoUrl = wp_get_attachment_url($attachmentId);
        $updatedContent = "<!-- wp:audio -->\n\n".'<figure class="wp-block-audio"><audio controls src="'. $videoUrl .'"></audio>' . (($description) ? '<figcaption class="wp-element-caption">'.$description.'</figcaption>' : '') ."</figure>\n<!-- /wp:audio -->";
        $updatedPostId = wp_update_post(['ID'=>$postId, 'post_content'=>wp_kses_post($updatedContent)], true);
      }
    
      if (is_wp_error($updatedPostId)) {
        wp_send_json_error(['message'=>'errors saving post', 'errors'=>$updatedPostId->get_error_messages()]);
        return;
      }

      wp_send_json_success(['message'=>'Created new post from media attachment. '.$videoUrl]);
      return;
    }

    // Prepare the response
    $response = array(
      'message' => 'Created new media item. '.$fileBaseName,
      'id' => $attachmentId
    );
    
    // Send the JSON response
    wp_send_json_success($response);
  } // end x2wp_media_ajax_callback()


  function verifyAdmin() {
    // Verify the nonce
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash ( $_POST['nonce'] ) ) , 'vi_ajax_nonce' ) ) {
      // Prepare the error response
      $response = array(
        'message' => 'Invalid nonce.',
      );

      // Send the JSON error response
      wp_send_json_error($response);
      return false;
    }

    // Check if the current user is an administrator
    if (! current_user_can('administrator')) {
      // Prepare the error response
      $response = array(
        'message' => 'You are not authorized to perform this action.',
      );
      
      // Send the JSON error response
      wp_send_json_error($response);
      return false;
    }

    return true;
  }

  function attachGalleryToPostSimple($mediaItems, $description, $siteTzDate, $postId) {
    $galleryIds = [];
    $imagesContent = [];
    $videosContent = [];

    foreach ($mediaItems as $mediaItem) {
      $mediaUrl = $this->mediaUrlBase . $mediaItem['uri'];
      $fileBaseName = basename($mediaUrl);
      $attachmentId = $this->helpers->getAttachmentIdByFilename($fileBaseName);

      if ($this->doMbStringConvert) {
        $mediaItem['description'] = mb_convert_encoding($mediaItem['description'], 'ISO-8859-1', 'UTF-8');
      }

      if ($this->isImage($mediaItem['ext'])) {
        if ($attachmentId) {
          $galleryIds[] = $attachmentId;
          $this->imageAttachmentIds[] = $attachmentId;
        }
        else {
          $attachmentId = $this->downloadAndSaveMediaUrl($mediaUrl, $description, $siteTzDate, $postId);
          if (!is_wp_error($attachmentId)) {
            $galleryIds[] = $attachmentId;
            $this->imageAttachmentIds[] = $attachmentId;
          }
          else {
            continue;
          }
        }

        $thumbnailImageUrl = wp_get_attachment_image_url($attachmentId);
        $imageUrl = wp_get_attachment_image_url($attachmentId, 'full');
        $imageSrc = '<img src="'.$thumbnailImageUrl.'" alt="'.$mediaItem['description'].'" class="wp-image-'.$attachmentId.'"/>';
        $imagesContent[] = '<!-- wp:image {"id":'.$attachmentId.',"sizeSlug":"thumbnail","linkDestination":"attachment"} -->'."\n".'<figure class="wp-block-image size-thumbnail"><a href="'.$imageUrl.'">'.$imageSrc.'</a>' . (($mediaItem['description']) ? '<figcaption class="wp-element-caption">'.$mediaItem['description'].'</figcaption>' : '') . "</figure>\n<!-- /wp:image -->";
      }
      elseif ($this->isVideo($mediaItem['ext'])) {
        if (!$attachmentId) {
          $attachmentId = $this->downloadAndSaveMediaUrl($mediaUrl, $description, $siteTzDate, $postId);
        }

        $videoUrl = wp_get_attachment_url($attachmentId);
        $videosContent[] = "<!-- wp:video -->\n\n".'<figure class="wp-block-video"><video controls src="'. $videoUrl .'"></video>' . (($mediaItem['description']) ? '<figcaption class="wp-element-caption">'.$mediaItem['description'].'</figcaption>' : '') . '<a href="'. $videoUrl .'">View in Window</a></figure>'."\n\n<!-- /wp:video -->";
      }
      elseif ($this->isAudio($mediaItem['ext'])) {
        if (!$attachmentId) {
          $attachmentId = $this->downloadAndSaveMediaUrl($mediaUrl, $description, $siteTzDate, $postId);
        }

        $audioUrl = wp_get_attachment_url($attachmentId);
        $videosContent[] = "<!-- wp:audio -->\n\n".'<figure class="wp-block-audio"><audio controls src="'. $audioUrl .'"></audio>' . (($mediaItem['description']) ? '<figcaption class="wp-element-caption">'.$mediaItem['description'].'</figcaption>' : '') . '<a href="'. $audioUrl .'">View in Window</a></figure>'."\n\n<!-- /wp:audio -->";
      }
    }

    $galleryContent = '<!-- wp:gallery {"linkTo":"media","sizeSlug":"thumbnail"} --><figure class="wp-block-gallery has-nested-images columns-default is-cropped">'."\n";

    if (!empty($galleryIds)) {
      $galleryContent .= implode("\n\n", $imagesContent);
    }

    if (! empty($videosContent)) {
      if (!empty($imagesContent)) {
        $galleryContent .= "\n\n";
      }
      
      $galleryContent .= implode("\n\n", $videosContent);
    }

    if (!empty($galleryIds) || !empty($videosContent)) {
      $galleryContent .= "</figure>\n<!-- /wp:gallery -->\n\n";
    }
    else {
      return '';
    }

    return $galleryContent;
  }


  function attachSingleMediaItemToPost($mediaItem, $postDescription, $siteTzDate, $postId) {
    $mediaUrl = $this->mediaUrlBase . $mediaItem['uri'];
    $fileBaseName = basename($mediaUrl);
    $attachmentId = $this->helpers->getAttachmentIdByFilename($fileBaseName);
    $mediaContent = '';
    $description = isset($mediaItem['description']) ? $mediaItem['description'] : $postDescription;

    if ($this->doMbStringConvert) {
      $description = mb_convert_encoding($description, 'ISO-8859-1', 'UTF-8');
    }

    if ($this->isImage($mediaItem['ext'])) {
      if (!$attachmentId) {
        $attachmentId = $this->downloadAndSaveMediaUrl($mediaUrl, $description, $siteTzDate, $postId);

        if (is_wp_error($attachmentId)) {
          // error_log("errors saving attachment: " . json_encode($attachmentId->get_error_messages()) );
          wp_send_json_error(['message'=>'errors saving attachment', 'errors'=>$attachmentId->get_error_messages()]);
          return false;
        }

      }

      $this->imageAttachmentIds[] = $attachmentId;
      $mediaContent = $this->createFullImageHtml($attachmentId, $mediaItem);
    }
    elseif ($this->isVideo($mediaItem['ext'])) {
      if (!$attachmentId) {
        $attachmentId = $this->downloadAndSaveMediaUrl($mediaUrl, $description, $siteTzDate, $postId);
      }
      if (is_wp_error($attachmentId)) {
        wp_send_json_error(['message'=>'errors saving attachment', 'errors'=>$attachmentId->get_error_messages()]);
        return false;
      }
      if (is_wp_error($attachmentId)) {
        return false;
      }

      $mediaContent = $this->createVideoHtml($attachmentId, $mediaItem);
    }
    elseif ($this->isAudio($mediaItem['ext'])) {
      if (!$attachmentId) {
        $attachmentId = $this->downloadAndSaveMediaUrl($mediaUrl, $description, $siteTzDate, $postId);
      }
      if (is_wp_error($attachmentId)) {
        wp_send_json_error(['message'=>'errors saving attachment', 'errors'=>$attachmentId->get_error_messages()]);
        return false;
      }
      if (is_wp_error($attachmentId)) {
        return false;
      }

      $mediaContent = $this->createAudioHtml($attachmentId, $mediaItem);
    }

    return $mediaContent;
  }

  function makeLinkContent($linkItem) {
    if ($this->doMbStringConvert) {
      $linkItem['description'] = mb_convert_encoding($linkItem['description'], 'ISO-8859-1', 'UTF-8');
    }

    if (array_key_exists('imgUrl', $linkItem)) {
      $description = (array_key_exists('description', $linkItem)) ? "<br />\n".$linkItem['description'] : '';
      return '<!-- wp:image {"align":"center","sizeSlug":"medium","linkDestination":"custom","className":"is-resized"} -->'."\n".'<figure class="wp-block-image aligncenter size-medium is-resized"><a href="' .$linkItem['url']. '"><img src="' .$linkItem['imgUrl']. '" alt="' .$linkItem['name']. '"/></a><figcaption class="wp-element-caption"><a href="' .$linkItem['url']. '">' .$linkItem['name'] . "</a>$description</figcaption></figure>\n<!-- /wp:image -->";
    }
    else {
      $description = (array_key_exists('description', $linkItem)) ? "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\"><!-- wp:paragraph -->\n<p>".$linkItem['description']."</p>\n<!-- /wp:paragraph --></blockquote>\n<!-- /wp:quote -->" : '';
      return '<!-- wp:paragraph -->'."\n".'<p><a href="' .$linkItem['url']. '">' .$linkItem['name']. "</a></p>\n<!-- /wp:paragraph -->\n\n$description";
    }
  }

  // some themes do not include excerpts in their blog rolls, so this is unused for now
  function createExcerpt($post) {
    // for use in the $postArr['post_excerpt'] field
    $content = $post['description'];
    $firstWordLength = count(explode(' ', $content));

    if (! empty($post['links'])) {
      $link = $post['links'][0];
      $linkContent = (array_key_exists('imgUrl', $link)) ? '<a href="'.$link['url'].'"><img src="'.$link['imgUrl'].'" style="max-width:100%;"><br />'.$link['name'].'</a>' : '<a href="'.$link['url'].'">'.$link['name'].'</a>';
      $linkNameWordLength = count(explode(' ', $link['name']));

      if (array_key_exists('description', $link) && $link['description']) {
        if ($this->doMbStringConvert) {
          $link['description'] = mb_convert_encoding($link['description'], 'ISO-8859-1', 'UTF-8');
        }

        $linkDescriptionWordLength = count(explode(' ', $link['description']));

        if (($firstWordLength + $linkNameWordLength + $linkDescriptionWordLength) < 55) {
          $linkContent .= " - ".$link['description'];
        }
        else {
          $remainingWordsAllowed = 50 - ($firstWordLength + $linkNameWordLength);
          if ($remainingWordsAllowed > 0) {
            $descArr = array_slice(explode(' ', $link['description']), 0, $remainingWordsAllowed);
            $linkContent .= " - ". implode(' ', $descArr);
          }
        }
      }

      $content = ($content) ? "$content<br />\n$linkContent" : $linkContent;
    }
    elseif (! empty($post['media'])) {
      $mediaItem = $post['media'][0];
      $fileBaseName = basename($mediaItem['uri']);
      $attachmentId = $this->helpers->getAttachmentIdByFilename($fileBaseName);

      if ($this->isImage($mediaItem['ext'])) {
        $thumbnailImageUrl = wp_get_attachment_image_url($attachmentId);
        $mediaContent = '<img src="'.$thumbnailImageUrl.'" alt="'.$mediaItem['description'].'" />';
      }
      else {
        $videoUrl = wp_get_attachment_url($attachmentId);
        $mediaContent = '<video controls src="'. $videoUrl .'"></video>';
      }

      $content = ($content) ? "$content<br />\n$mediaContent" : $mediaContent;
    }

    return $content;
  }


  function getExt($url) {
    $url = substr($url, strrpos($url, '.') + 1);
    $queryStartIdx = strpos($url, '?');
    return  ($queryStartIdx === false) ? $url : substr($url, 0, $queryStartIdx);
  }
  function isImage($ext) {
    return in_array($ext, ['jpg', 'jpeg', 'jpe', 'gif', 'png', 'webp']);
  }
  function isVideo($ext) {
    return in_array($ext, ['mp4', 'mpg']);
  }
  function isAudio($ext) {
    return in_array($ext, ['aac', 'mp3']);
  }
  function isValidMediaType($ext) {
    $allowed_media_extensions = array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp', 'mp4', 'mpg', 'aac', 'mp3' );
    return in_array($ext, $allowed_media_extensions);
  }

  function downloadAndSaveMediaUrl($url, $description, $siteTzDate, $postId) {
    $attachmentId = null;

    // load the file
    $tmp = download_url( $url );
    if ( ! is_wp_error( $tmp ) ) {
      // correct the filename in the query lines.
      $shortFileName = basename( $url );
      if (strlen($shortFileName) >= 200) {
        $shortFileName = substr($shortFileName, 0, 180) . $this->getExt($shortFileName);
      }

      $fileArray = [ 'name' => $shortFileName, 'tmp_name' => $tmp ];
      $postData = ['post_date'=>$siteTzDate];

      // error_log("postData 2: " . json_encode($postData) );
      $attachmentId = media_handle_sideload($fileArray, $postId, $shortFileName, $postData);
      // error_log("errors saving attachment 2: " . json_encode($attachmentId->get_error_messages()) );
    }
    else {
      return $tmp;
    }

    @unlink( $tmp );
    return $attachmentId;
  }

  function createFullImageHtml($attachmentId, $mediaItem) {
    $imageUrl = wp_get_attachment_image_url($attachmentId, 'full');
    $imageSrc = '<img src="'.$imageUrl.'" alt="'.$mediaItem['description'].'" class="wp-image-'.$attachmentId.'"/>';
    return '<!-- wp:image {"id":'.$attachmentId.',"sizeSlug":"full","linkDestination":"attachment"} -->'."\n".'<figure class="wp-block-image size-full"><a href="'.$imageUrl.'">'.$imageSrc.'</a>' . (($mediaItem['description']) ? '<figcaption class="wp-element-caption">'.$mediaItem['description'].'</figcaption>' : '') . "</figure>\n<!-- /wp:image -->";
  }
  function createVideoHtml($attachmentId, $mediaItem) {
    $videoUrl = wp_get_attachment_url($attachmentId);
    return "<!-- wp:video -->\n\n".'<figure class="wp-block-video"><video controls src="'. $videoUrl .'"></video>' . (($mediaItem['description']) ? '<figcaption class="wp-element-caption">'.$mediaItem['description'].'</figcaption>' : '') . '<a href="'. $videoUrl .'">View in Window</a></figure>'."\n\n<!-- /wp:video -->";
  }
  function createAudioHtml($attachmentId, $mediaItem) {
    $audioUrl = wp_get_attachment_url($attachmentId);
    return "<!-- wp:audio -->\n\n".'<figure class="wp-block-audio"><audio controls src="'. $audioUrl .'"></audio>' . (($mediaItem['description']) ? '<figcaption class="wp-element-caption">'.$mediaItem['description'].'</figcaption>' : '') . '<a href="'. $audioUrl .'">View in Window</a></figure>'."\n\n<!-- /wp:audio -->";
  }

  function formatTextContent($content) {
    if (!$content) { return ''; }
    $content = str_replace("\n\n", "</p><!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>", $content);
    $content = "<!-- wp:paragraph -->\n<p>$content</p><!-- /wp:paragraph -->";
    return $content;
  }

  function makeHorizontalLine() {
    return "\n\n<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/><!-- /wp:separator -->\n\n";
  }

  function humanDateTime($dateTimeStr) {
    return date("F j, Y, g:i a", strtotime($dateTimeStr));
  }

  function isVintillectS3Url($url) {
    return strpos($url, $this->mediaUrlBase) == 0;
  }


  function createExternalFeaturedImage($postId, $firstLink) {
    if (empty($firstLink) || !$firstLink['imgUrl']) { return false; }
    $externalImageUrl = null;

    $externalImageUrl = $firstLink['imgUrl'];
    $ext = $this->getExt($externalImageUrl);
    // error_log("createExternalFeaturedImage externalImageUrl ($externalImageUrl) ext ($ext)");
    if (!$externalImageUrl || !$this->isImage($ext)) { return false; }

    $foundPlugin = $this->helpers->getExternalImageUrlPlugin();
    if (!$foundPlugin) { return false; }

    if ($foundPlugin === 'featured-image-from-url' && function_exists('fifu_dev_set_image')) {
      fifu_dev_set_image($postId, $externalImageUrl);
    }

    return true;
  }

  function getMimeTypeByExtension($ext) {
    $extToMime = ['jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'jpe'=>'image/jpeg', 'gif'=>'image/gif', 'png'=>'image/png', 'webp'=>'image/webp'];
    if (!isset($extToMime[$ext])) { return ''; }
    return $extToMime[$ext];
  }

  function getImagePathsFromAttachmentIds() {
    $imagePaths = [];
  
    foreach ($this->imageAttachmentIds as $attachmentId) {
      $imagePath = wp_get_original_image_path($attachmentId);
      if ($imagePath) {
        $imagePaths[] = $imagePath;
      }
    }
  
    return $imagePaths;
  }

  function saveCollage($collageMaker, $lastTimestamp, $parentPostId) {
    // save the image
    $uploadDir = wp_upload_dir(date('Y/m', $lastTimestamp), true);
    $filename = $lastTimestamp . '_collage.png';
    $saveFilePath = $uploadDir['path'] .'/' . $filename;
    $attachmentUrl = $uploadDir['url'] .'/' . $filename;
    $collageMaker->saveImg($saveFilePath);

    $collageAttachmentId = $this->helpers->getAttachmentIdByFilename($filename);

    if (! $collageAttachmentId) {
      // save into WordPress as attachment
      $wp_filetype = wp_check_filetype( $saveFilePath, null );
      $attachment = array(
        'guid'=> $attachmentUrl,
        'post_mime_type' => $wp_filetype['type'],
        'post_title' => sanitize_file_name( $filename ),
        'post_content' => '',
        'post_status' => 'inherit'
      );
      $collageAttachmentId = wp_insert_attachment( $attachment, $saveFilePath, $parentPostId );

      // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
      require_once( ABSPATH . 'wp-admin/includes/image.php' );
      // Generate the metadata for the attachment, and update the database record.
      $attach_data = wp_generate_attachment_metadata( $collageAttachmentId, $saveFilePath );
      wp_update_attachment_metadata( $collageAttachmentId, $attach_data );
    }

    set_post_thumbnail( $parentPostId, $collageAttachmentId );
    return $collageAttachmentId;
  }

  function getHashtags($string) {  
    preg_match_all('/#([^\s]+)/', $string, $matches);  
    if ($matches) {
      // error_log(json_encode($matches[1]));
      return $matches[1];
    }
    return [];
  }


  function addVintillectAdLink($formattedContent) {
    if (! $this->adLinksInPostFooter) { return $formattedContent; }

    $pickedKey = array_rand($this->ads);
    $pickedAd = $this->ads[ $pickedKey ];
    // error_log("addVintillectAdLink $pickedKey {$pickedAd['type']} {$pickedAd['text']}" );
    $lineSpacer = "\n\n<!-- wp:paragraph -->\n<p></p>\n<!-- /wp:paragraph -->\n\n<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n\n";
    $x2wpLink = '';

    if ($pickedAd['type'] === 'phrase') {
      $x2wpLink = $lineSpacer . "<!-- wp:paragraph {\"align\":\"center\"} -->\n<p class=\"has-text-align-center\">".
        "<a href=\"{$pickedAd['url']}\">{$pickedAd['text']}</a>".
        " - ad&#8203;vertisement</p>\n<!-- /wp:paragraph -->\n\n";
    }
    elseif ($pickedAd['type'] === 'image') {
      $imageUrlArr = $this->getAdImageUrl($pickedAd);

      if ($imageUrlArr) {
        $attachId = $imageUrlArr['attachId'];
        $imageUrl = $imageUrlArr['url'];
        $x2wpLink = $lineSpacer . '<!-- wp:image {"id":'. $attachId .',"align":"center","sizeSlug":"full","linkDestination":"custom"} -->'."\n".'<figure class="wp-block-image aligncenter size-full">'.
          "<a href=\"{$pickedAd['url']}\"><img src=\"$imageUrl\" alt=\"{$pickedAd['text']}\" class=\"wp-image-$attachId\"/></a><figcaption class=\"wp-element-caption\"><sup>ad&#8203;vertisement</sup></figcaption>" .
          "</figure>\n<!-- /wp:image -->\n";
      }
    }

    $formattedContent .= $x2wpLink;
    return $formattedContent;
  }

  function getAdImageUrl($ad) {
    $imageFileName = $ad['file'];
    $q = new \WP_Query(array('fields' => 'ids', 'name'=>$imageFileName, 'post_type' =>'attachment', 'post_status' => 'inherit', 'ignore_sticky_posts' => true, 'posts_per_page' => 1 ) );
    
    if ( $q->have_posts() ) { // if ad image exists already in the media library
      $attid = $q->posts[0];
      $imageUrl = wp_get_attachment_image_url($attid, 'full'); // or use $this->helpers->getAttachmentIdByFilename()
      return ['url'=>$imageUrl, 'attachId'=>$attid];
    }

    $assetDir = VINTILLECT_IMPORTER_PLUGIN_DIR . "assets/ads/";
    $filePath = $assetDir . $imageFileName;
    $fileUrl = get_block_asset_url( $filePath );
    $fileArr = array(
      'name'     => basename( $imageFileName ),
      'type'     => mime_content_type( $filePath ),
      'tmp_name' => download_url($fileUrl),
      'size'     => filesize( $filePath ),
    );

    $sideload = wp_handle_sideload($fileArr, ['action'=> sanitize_text_field($_POST['action'])]);
    if( ! empty( $sideload['error'] )) {
      // error_log("unable to save $filePath into media library: " . $sideload['error']);
      return false;
    }

    $attid = wp_insert_attachment(
      array(
        'guid'           => $sideload[ 'url' ],
        'post_mime_type' => $sideload[ 'type' ],
        'post_title'     => basename( $sideload[ 'file' ] ),
        'post_content'   => 'advertisement for X2WP Twitter to WordPress Importer',
        'post_status'    => 'inherit',
      ),
      $sideload[ 'file' ]
    );

    if( is_wp_error( $attid ) || ! $attid ) {
      return false;
    }

    // update metadata, regenerate image sizes
    require_once( ABSPATH . 'wp-admin/includes/image.php' );
    wp_update_attachment_metadata( $attid, wp_generate_attachment_metadata( $attid, $sideload[ 'file' ] ) );

    $imageUrl = $sideload['url'];
    return ['url'=>$imageUrl, 'attachId'=>$attid];
  }

  private $ads = [
    ['type'=>'phrase', 'text'=>'How to copy all of your Twitter posts into your blog', 'url'=>'https://vintillect.com/vintillect-importer/x2wp/help.html?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Grow your WordPress blog with Twitter', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-twitter-to-wordpress-1.php?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Copy your Twitter pics into your WordPress blog securely', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Copy your Twitter videos into your WordPress blog', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-twitter-to-wordpress-1.php?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Quickly import all of your Twitter content into your blog', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'I used the Vintillect Importer Plugin to copy these posts.', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Archive your tweets before bigot CEO sinks Twitter like MySpace!', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-twitter-to-wordpress-1.php?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'X2WP_Tweets_Deleted_Half_Banner.png', 'text'=>'All of your tweets left on Twitter will be deleted when it goes bankrupt and goes offline!', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'X2WP_Full_Banner.png', 'text'=>'Grow your WordPress content from your tweets using the Vintillect Importer', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'X2WP_Rectangle.png', 'text'=>'Copy your tweets before X (Twitter) disappears like MySpace using the Vintillect Importer', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-twitter-to-wordpress-1.php?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'X2WP_Yellow_Leaderboard.png', 'text'=>'Easily copy all of your tweets and media into your blog using the Vintillect Importer', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'X2WP_muskmurdockmessage.png', 'text'=>'Archive your Tweets before Musk sinks Twitter like Murdock did MySpace!', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-twitter-to-wordpress-1.php?cd=f44cfc3f30'],

    ['type'=>'phrase', 'text'=>'How to copy your Facebook posts into your blog securely', 'url'=>'https://vintillect.com/vintillect-importer/fb2wp/help.html?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Grow your WordPress blog with Facebook', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Copy your Facebook pics into your WordPress blog', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-facebook-to-wordpress-1.php?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Copy your Facebook videos into your WordPress blog', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Quickly import all of your Facebook content into your blog', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'FB2WP_Monetize_Facebook_Half_Banner.png', 'text'=>'Monetize your Facebook posts in WordPress using the Vintillect Importer', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'FB2WP_Full_Banner.png', 'text'=>'Grow your WordPress content from your Facebook posts using the Vintillect Importer', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-facebook-to-wordpress-1.php?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'FB2WP_Rectangle.png', 'text'=>'Copy your Facebook posts using the Vintillect Importer', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'FB2WP_Yellow_Leaderboard.png', 'text'=>'Easily copy all of your posts and media into your blog using the Vintillect Importer', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-facebook-to-wordpress-1.php?cd=f44cfc3f30'],

    ['type'=>'image', 'file'=>'Meme_Storage_Square.png', 'text'=>'Want a better way to organize and view your awesome meme collection?', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'Copy_Easily_Half_Banner.png', 'text'=>'Copy your favorite social media posts into your blog using the Vintillect Importer', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'Vintillect_Importer_Micro_Bar.png', 'text'=>'Copy your social media posts securely using Vintillect Importer', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'No_More_Twitter_Service_Square.png', 'text'=>'No more Twitter service! Copy your tweets before they disappear forever!', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-twitter-to-wordpress-1.php?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'Twitter_follow_MySpace_Half_Banner.png', 'text'=>'Twitter X will follow MySpace, archive your tweets before they disappear forever', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'Social_Media_Archive_Gray_Square.png', 'text'=>'Social media archive, secure copy into your blog, no more endless scrolling', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'image', 'file'=>'Social_Media_Copy_Purple_Square.png', 'text'=>'Copy your social media posts to your blog: Facebook, Twitter X, WordPress blog', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-facebook-to-wordpress-1.php?cd=f44cfc3f30'],

    ['type'=>'phrase', 'text'=>'Avoid Twitter&apos;s MySpace fate. Preserve your content with Vintillect Importer.', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-twitter-to-wordpress-1.php?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Avoid losing your Twitter history like MySpace users did. Use Vintillect Importer.', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Save your tweets from a potential MySpace fate. Use Vintillect Importer now.', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-twitter-to-wordpress-1.php?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Elon Musk&apos;s Twitter: the next MySpace? Protect your content with Vintillect Importer.', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Moved to Meta Threads? Archive your old tweets with Vintillect Importer!', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-twitter-to-wordpress-1.php?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Switching to Bluesky? Keep your Twitter history intact with Vintillect Importer.', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Enjoying Mastodon? Archive your Twitter content with Vintillect Importer.', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-twitter-to-wordpress-1.php?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Moving on from Twitter? Archive your tweets with Vintillect Importer.', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Embrace new platforms, keep your old tweets. Use Vintillect Importer.', 'url'=>'https://vintillect.com/vintillect-importer/lp/groc/copy-twitter-to-wordpress-1.php?cd=f44cfc3f30'],
    ['type'=>'phrase', 'text'=>'Meta Threads, Bluesky, or Mastodon? Preserve your Twitter content with Vintillect Importer.', 'url'=>'https://vintillect.com/vintillect-importer/?cd=f44cfc3f30'],
  ];

}
