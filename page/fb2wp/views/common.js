const viCommonObj = {
  mediaUrlBase: '',
  uploadId: '',

  load: function(f, dataUrl) {
    if (dataUrl) {
      viCommonObj['mediaUrlBase'] = window['vi_posts_var']['config']['s3Url'];

      // parse the uploadId from the dataUrl
      let awsComIdx = dataUrl.indexOf('amazonaws.com/');
      if (awsComIdx > -1) {
        awsComIdx = awsComIdx + 14;
        viCommonObj.uploadId = dataUrl.substring(awsComIdx, dataUrl.indexOf('/', awsComIdx+1));
      }
    }
  }, // end load()

  changeTimeStampToDate(timestamp) {
    return new Date(timestamp * 1000);
  },
  formatGmtDateTime: function(date) {
    return date.toISOString().replace("T"," ").substring(0, 19) + 'Z';
  },

  formatPostContent: function(post) {
    let content = post['postText'] || post['text'] || '';
    content = viCommonObj.interpretUnicode(content);
    const links = post['links'] || [];
    const media = post['media'];
    content = content.replace(/\n\n/g, '</p><!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>');
    content = `<!-- wp:paragraph -->\n<p>${content}</p><!-- /wp:paragraph -->`;

    if (links.length > 0) {
      for (let i=0; i<links.length; i++) {
        const link = links[i];
        let linkContent;
        let linkDescription = (link['description']) ? `\n<blockquote class="wp-block-quote"><p>${link['description']}</p></blockquote>` : '';

        if (link['imgUrl']) {
          linkContent = `<!-- wp:image {"align":"center","sizeSlug":"medium","linkDestination":"custom","className":"is-resized"} -->\n<figure class="wp-block-image aligncenter size-medium is-resized"><a href="${link['url']}"><img src="${link['imgUrl']}" alt="${link['name']}"/></a><figcaption class="wp-element-caption"><a href="${link['url']}"><strong>${link['name']}</strong></a>${linkDescription}</figcaption></figure>\n<!-- /wp:image -->`;
        }
        else {
          linkContent = `<a href="${link['url']}"><strong>${link['name']}</strong></a>${linkDescription}`;
        }

        if (i===0) {
          content = linkContent + '\n\n' + content;
        }
        else {
          content = content + `\n\n` + linkContent;
        }
      }
    }

    if (media.length > 0) {
      for (let i=0; i<media.length; i++) {
        const mediaItem = media[i];
        let mediaHtml = `<!-- wp:image {"align":"center","sizeSlug":"medium","linkDestination":"custom","className":"is-resized"} -->\n<figure class="wp-block-image aligncenter size-medium is-resized"><img src="${viCommonObj['mediaUrlBase']}${mediaItem['uri']}" alt="${mediaItem['title']}" type="button" class="media-view-in-modal" /><figcaption class="wp-element-caption"><strong>${mediaItem['title']}</strong> - ${mediaItem['description']}</a></figcaption></figure>\n<!-- /wp:image -->`;
        
        if (viCommonObj.isVideo(mediaItem['ext'])) {
          mediaHtml = `<!-- wp:video -->\n<figure class="wp-block-video"><video controls src="${viCommonObj['mediaUrlBase']}${mediaItem['uri']}"></video><figcaption class="wp-element-caption"><strong>${mediaItem['title']}</strong> - ${mediaItem['description']}</figcaption></figure>\n<!-- /wp:video -->`; // \n<button class="btn btn-outline-info btn-sm media-view-in-modal">View in Larger Window</button>
        }
        else if (viCommonObj.isAudio(mediaItem['ext'])) {
          mediaHtml = `<!-- wp:audio -->\n<figure class="wp-block-audio"><audio controls src="${viCommonObj['mediaUrlBase']}${mediaItem['uri']}"></audio><figcaption class="wp-element-caption"><strong>${mediaItem['title']}</strong> - ${mediaItem['description']}</figcaption></figure>\n<!-- /wp:audio -->`; // \n<button class="btn btn-outline-info btn-sm media-view-in-modal">View in Larger Window</button>
        }
        if (i===0 && viCommonObj.isImage(mediaItem['ext'])) {
          content = mediaHtml + '\n\n' + content;
        }
        else {
          content = content + `\n\n` + mediaHtml;
        }
      }

      if (! window['vi_posts_var']['availableOptions'].includes('albums')) {
        const dateGmtId = 'popover-' + post['id'];
        content = `\n<div style="position:relative;"><button popovertarget="${dateGmtId}" popovertargetaction="show" class="alert alert-warning float-end">Cannot post</button>\n<div id="${dateGmtId}" popover class="alert alert-warning"><p>Media cannot be imported without<br/><a href="https://vintillect.com/vintillect-importer/fb2wp/cart.php?uploadid=${viCommonObj.uploadId}" target="_blank">the Photo &amp; Video Albums option</a>.</p></div></div>` + content;
      }
    }

    return content;
  }, // end formatPostContent()


  isImage: function(ext) {
    return ('jpg|jpeg|png|webp|gif'.indexOf(ext) !== -1);
  },
  isVideo: function(ext) {
    return ('mp4|mpg'.indexOf(ext) !== -1);
  },
  isAudio: function(ext) {
    return ('aac|mp3'.indexOf(ext) !== -1);
  },

  showSuccessNotification: function(msg, elem) {
    jQuery('#success-popup-notification').html(msg).show();
    setTimeout(function() {
      jQuery('#success-popup-notification').fadeOut('slow');
    }, 3000);
  },
  showErrorNotification: function(msg, elem) {
    if (msg === 'Invalid nonce.') {
      msg = 'Session expired. Please refresh page.';
    }

    jQuery('#error-popup-notification').html(msg).show();
    setTimeout(function() {
      jQuery('#error-popup-notification').fadeOut('slow');
    }, 3000);
  },
  interpretUnicode: function(binary) {
    const bytes = Uint8Array.from({ length: binary.length }, (_, index) =>
      binary.charCodeAt(index)
    );
    const decoder = new TextDecoder('utf-8');
    return decoder.decode(bytes);
  }

}; // end viCommonObj

if ('vi_posts_var' in window && 'dataUrl' in window['vi_posts_var']) {
  viCommonObj.load(viCommonObj, window['vi_posts_var']['dataUrl']);
}