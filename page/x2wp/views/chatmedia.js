const viChatMediaObj = {
  mediaUrlBase: '',
  wpParsedMedia: [],
  allowMediaUpload: false,
  rowBasketTable: null,

  load: function(f, viJsonUrl) {
    const self = f;
    self.mediaUrlBase = window['vi_posts_var']['config']['s3Url'];

    jQuery.getJSON(viJsonUrl, function(data) {
      for (let i=0; i<data.length; i++) {
        const media = self.parseMedia(data[i], i);
        self.wpParsedMedia.push(media);
      }

      jQuery(document).ready(function() {
        self.loadDataTable();
      });
    })
    .fail(function() {
      const errorWarning = `<div class="alert alert-danger purchase-warning" role="alert">The data for this tab is not available. You might not have such content.</div>`;
      jQuery('#chatmedia-view').html(errorWarning);
    });

    jQuery(document).ready(function() {
      jQuery(document).on('click', '.view-larger-img', self.viewInModal);
      jQuery(document).on('click', '.upload-file-btn', self.sendMediaItemAjaxRequest);
      jQuery(document).on('click', '.view-larger-video-btn', self.viewInModal);
      jQuery(document).on('click', '.retry-post-errors', self.uploadPageGroupsWithErrors);
      
      if (window['vi_posts_var']['availableOptions'].includes('chatmedia')) {
        self.allowMediaUpload = true;
      }
      else {
        jQuery('#status-select option[value="upload-only"]').remove();
      }

      jQuery('#date-formats').on('change', function() {
        self.dateFormat = jQuery(this).val();

        for (const post of self.wpParsedMedia) {
          post['description'] = post['title'];
        }

        self.rowBasketTable.reRender();
      });
    });
  }, // end load()


  parseMedia: function(media, idx) {
    const momentDate = moment(media['creation_timestamp']);
    const defaultDateFormat = 'ddd MMM D, YYYY hh:mm A'; // matches first option value in select#date-formats
    const userTime = momentDate.format(defaultDateFormat);

    const item = {
      'date_gmt': media['creation_timestamp'].replace("T"," ").substring(0, 19) + 'Z',
      'title': userTime, // media.userTime.substring(0, media.userTime.length - 10),
      'description': '',
      'content': '',
      'status': 'draft',
      'timestamp': Math.round(momentDate.format("X") / 1000),
      'ext': media['ext'],
      'uri': media['uri'],
      'size': Math.round(media['size'] / 1024),
      'date-iso': momentDate.format('YYYY-MM-DD'),
      'week-of-year': momentDate.format('YYYY-ww'),
      'month-of-year': momentDate.format('YYYY-MM'),
      'year': momentDate.format('YYYY'),
      'rowIdx': idx
    };
    return item;
  },


  // JavaScript code to send an AJAX request to the WordPress plugin
  sendAjaxRequest: function(uploadArr, elem, postType) {
    if (! viChatMediaObj.allowMediaUpload) {
      alert("You haven't purchased the Direct Message Photos and Videos option yet.")
      return;
    }

    const posts = [];
    let lastTitle = '';
    let lastDateGMT = '';

    for (const post of uploadArr) {
      const media = {'uri':post['uri'], 'date_gmt':post['date_gmt'], 'description':post['description'], 'ext':post['ext']};
      const postTransformed = {
        'title':       post['title'],
        'description': post['description'],
        'links':       [],
        'media':       (viChatMediaObj.allowMediaUpload) ? [media] : []
      };
      posts.push(postTransformed);

      lastTitle = post['title'];
      lastDateGMT = post['date_gmt'];
    }

    var data = {
      'action': 'x2wp_post_ajax_action', // AJAX action name registered in the PHP code
      'nonce': window['vi_posts_var']['nonce'], // Nonce value passed from PHP to JavaScript    https://wordpress.stackexchange.com/questions/231797/what-is-nonce-and-how-to-use-it-with-ajax-in-wordpress
      // Additional data to be sent along with the AJAX request if needed
      'page':'x2wp',
      'data': {
        'posts': JSON.stringify( posts ),
        'title': lastTitle,
        'date_gmt': lastDateGMT,
        'status':   jQuery('#status-select').val()
      }
    };

    if (postType == 'gallery') {
      data['make-gallery'] = true;
    }
    
    return jQuery.post(window['vi_posts_var']['url'], data, function(response) {
      const responseMessage = response['data']['message'];

      if (response['success']) {
        console.log('AJAX request successful:', responseMessage);
        viCommonObj.showSuccessNotification(responseMessage, elem);
      } else {
        console.error('AJAX request failed:', responseMessage);
        viCommonObj.showErrorNotification(responseMessage, elem);
      }
    });
  }, // end sendAjaxRequest()


  uploadGroupsInProcess: [],
  uploadGroupsWithErrors: [],
  
  loadDataTable: function() {
    const self = this;

    const dtSettings = {
      'tableWrapperId':'chatmedia-table-wrapper',
      'data':self.wpParsedMedia,
      'groupingField':'timestamp',
      'columnHeaders':[{'title':'Create Post'}, {'title':'Title (Date)', 'sortField':'timestamp', 'extra':'id="albums-table-wrapper-title-th"'}, {'title':'Content'}],
      'columnConfig':[{'render':self.createPostBtn }, {'render':self.createFirstTitle }, {'render':(groupKey, i, posts) => self.groupPostContent(groupKey, i, posts) }],
    };
    const dtOptions = {
      'selectedBasketEntrySize':25,
      'searchableFields':['content'],
      'dateField':'timestamp',
      'orderByField':'timestamp'
    };
    self.rowBasketTable = new RowBasketDataTable(dtSettings, dtOptions);

    jQuery('#chatmedia-view').on('click', '.grouped-media-btn', function () {
      const groupKey = jQuery(this).data('group-key').toString();
      const baskets = self.rowBasketTable.getAllBaskets();
      const uploadArr = baskets[groupKey];
      self.sendAjaxRequest( uploadArr, this, 'post' );
    });

    jQuery('#chatmedia-view').on('click', '.upload-gallery-btn', function () {
      const groupKey = jQuery(this).data('group-key').toString();
      const baskets = self.rowBasketTable.getAllBaskets();
      const uploadArr = baskets[groupKey];
      self.sendAjaxRequest( uploadArr, this, 'gallery' );
    });

    jQuery('#group-select').on('change', function() {
      self.rowBasketTable.setBasketGroupingField(jQuery(this).val());
      self.rowBasketTable.reRender();
    });

    jQuery('#post-all-mass-upload-btn').on('click', function() {
      const checkedBaskets = self.rowBasketTable.getCheckedBaskets();
      const allChecked = checkedBaskets['all'];
      if (!allChecked && ! Object.values(checkedBaskets['groups']).some(x => x.checked)) { alert('No Mass Upload checkboxes are checked yet.'); return; }
      if (! confirm('Are you sure you want to upload all media that are checked for Mass Upload?')) { return; }
      console.warn('Start mass upload!');
      jQuery('#mass-upload-progress-text').text('Uploaded 0%');
      jQuery('#mass-upload-progress-bar').val(0);
      jQuery('#mass-upload-progress-wrapper').show();

      const whichPages = jQuery('#which-pages').val();
      const baskets = (whichPages === 'page') ? self.rowBasketTable.getCurrentPageBaskets() : self.rowBasketTable.getAllBaskets();
      let checkedRowGroups = {};

      for (const groupKey in baskets) {
        let isChecked = allChecked;
        if (groupKey in checkedBaskets['groups']) {
          isChecked = checkedBaskets['groups'][groupKey].checked;
        }

        if (isChecked) {
          checkedRowGroups[groupKey] = baskets[groupKey];
        }
      }

      self.uploadPageGroups( Object.values(checkedRowGroups) );
    }); // end #post-all-mass-upload-btn click

  }, // end loadDataTable()


  uploadPageGroups: function(uploadGroups) {
    const totalGroups = uploadGroups.length;
    const maxUploadsAtOnce = 3;
    let currentlyInProcess = 0;
    let uploadedCount = 0;
    const sendErrors = [];
    console.log('uploadGroups', uploadGroups);

    let uploadInterval = setInterval(function() {
      if (currentlyInProcess > maxUploadsAtOnce) { return; }
      if (uploadGroups.length === 0) {
        if (sendErrors.length) {
          jQuery('#send-errors').html( sendErrors.join('<br />\n') + '<br />\n<button class="btn btn-info retry-post-errors">Retry these Posts</button>' );
        }
        clearInterval(uploadInterval);
        return;
      }

      currentlyInProcess++;
      const uploadArr = uploadGroups.shift();

      viChatMediaObj.sendAjaxRequest(uploadArr, jQuery('#post-all-mass-upload-btn'), 'post')
      .done(function() {
        uploadedCount++;
        currentlyInProcess--;
        const percentUploaded = Math.ceil(uploadedCount * 10 / totalGroups) * 10; // ##.# precision
        console.log('upload progress', uploadedCount, totalGroups, percentUploaded);
        const continueText = (percentUploaded < 100) ? ', running ...' : '';
        jQuery('#mass-upload-progress-text').text(`Uploaded ${percentUploaded}%${continueText}`);
        jQuery('#mass-upload-progress-bar').val(percentUploaded);
      })
      .fail(function() {
        currentlyInProcess--;
        console.error('failed to post', uploadArr);
        const sendError = 'failed to post ' + uploadArr[uploadArr.length-1].title;
        sendErrors.push(sendError);
        viChatMediaObj.uploadGroupsWithErrors.push(uploadArr);

        if (jQuery('#send-errors').length) {
          jQuery('#send-errors').html( sendErrors.join('<br />\n') );
        }
        else {
          const errorWarning = `<div class="alert alert-danger purchase-warning" id="send-errors" role="alert">${sendError}</div>`;
          jQuery('#date-filters').before(errorWarning);
        }
      });
    }, 300);

  }, // uploadPageGroups()

  uploadPageGroupsWithErrors: function() {
    const uploadClones = structuredClone(viChatMediaObj.uploadGroupsWithErrors);
    viChatMediaObj.uploadGroupsWithErrors.length = 0;
    viChatMediaObj.uploadPageGroups(uploadClones);
  },

  createPostBtn: function(groupKey, idx, posts) {
    const galleryBtn = (viChatMediaObj.allowMediaUpload && (viChatMediaObj.countImages(posts) > 1)) ? `<br /><br />\n<button class="btn btn-success btn-sm upload-gallery-btn" data-group-key="${groupKey}">Create Gallery Post</button>` : '';
    return `<button class="btn btn-success btn-sm grouped-media-btn" data-group-key="${groupKey}">Create Grouped Post</button>` + galleryBtn;
  },
  createFirstTitle: function(groupKey, idx, posts) {
    let title = '';
    let timestamp = '';

    for (const post of posts) {
      if (timestamp < post['timestamp']) {
        timestamp = post['timestamp'];
        title = post['title'];
      }
    }

    return title;
  },
  groupPostContent: function(groupKey, idx, posts) {
    const contentGrouped = [];
    const self = this;

    for (const mediaItem of posts) {
      const isImage = ('jpg|jpeg|png|webp|gif'.indexOf(mediaItem['ext']) !== -1);
      const isVideo = ('mp4|mpg'.indexOf(mediaItem['ext']) !== -1);
      const isAudio = ('aac|mp3'.indexOf(mediaItem['ext']) !== -1);
      if (!isImage && !isVideo && !isAudio) {
        console.warn('not an image nor video nor audio', mediaItem);
        viCommonObj.showErrorNotification('not an image nor video nor audio: ' + mediaItem['uri'], null);
        return;
      }

      let mediaHtmlTag = '';
      let mediaHtmlWrapped = '';
      const rowIdx = mediaItem['rowIdx'];

      if (isImage) {
        mediaHtmlTag = `<!-- wp:image {"align":"center","sizeSlug":"medium","linkDestination":"custom","className":"is-resized"} -->\n<figure class="wp-block-image aligncenter size-medium is-resized"><img src="${self.mediaUrlBase}${mediaItem['uri']}" alt="${mediaItem['title']}" type="button" id="gallery-img-${rowIdx}" class="view-larger-img" /><figcaption class="wp-element-caption"><strong>${mediaItem['title']}</strong> - ${mediaItem['description']}</a></figcaption>\n\n`;
        mediaHtmlWrapped = `<div class="gallery-item" data-rowidx="${rowIdx}">${mediaHtmlTag}\n<button class="btn btn-success btn-sm upload-file-btn">Upload Individual File</button></figure>\n<!-- /wp:image --></div>`;
      }
      else if (isVideo) { // is video
        mediaHtmlTag = `<!-- wp:video -->\n\n<figure class="wp-block-video"><video controls src="${self.mediaUrlBase}${mediaItem['uri']}" id="gallery-video-${rowIdx}"></video><figcaption class="wp-element-caption"><strong>${mediaItem['title']}</strong> - ${mediaItem['description']}</figcaption>\n\n`;
        mediaHtmlWrapped = `<div class="gallery-item" data-rowidx="${rowIdx}">${mediaHtmlTag}\n<button class="btn btn-outline-info btn-sm view-larger-video-btn">View in Larger Window</button><br>\n<button class="btn btn-success btn-sm upload-file-btn">Upload Individual File</button></figure>\n<!-- /wp:video --></div>`;
      }
      else if (isAudio) {
        mediaHtmlTag = `<!-- wp:audio -->\n\n<figure class="wp-block-audio"><audio controls src="${self.mediaUrlBase}${mediaItem['uri']}" id="gallery-audio-${rowIdx}"></audio>\n\n`;
        mediaHtmlWrapped = `<div class="gallery-item" data-rowidx="${rowIdx}">${mediaHtmlTag}\n<button class="btn btn-success btn-sm upload-file-btn">Upload Individual File</button></figure>\n<!-- /wp:audio --></div>`;
      }

      contentGrouped.push(mediaHtmlWrapped);
    }

    const contentGroupedHtml = contentGrouped.join('\n\n');
    return contentGroupedHtml;
  },



  sendMediaItemAjaxRequest: function() {
    if (! viChatMediaObj.allowMediaUpload) {
      alert("You haven't purchased the Direct Message Photos and Videos option yet.")
      return;
    }

    const galleryItem = jQuery(this).closest('.gallery-item');
    const rowIdx = parseInt(jQuery(galleryItem).data('rowidx'), 10);
    const mediaItem = viChatMediaObj.wpParsedMedia.find((x) => x['rowIdx'] === rowIdx);
    const elem = this;

    var post = {
      'url': mediaItem['uri'],
      'title': mediaItem['title'],
      'description': mediaItem['description'],
      'date_gmt': viCommonObj.formatGmtDateTime( viCommonObj.changeTimeStampToDate(mediaItem['timestamp']) ),
    };
    var data = {
      'action': 'vintillect_media_ajax_action', // AJAX action name registered in the PHP code
      'nonce': window['vi_posts_var']['nonce'], // Nonce value passed from PHP to JavaScript    https://wordpress.stackexchange.com/questions/231797/what-is-nonce-and-how-to-use-it-with-ajax-in-wordpress
      // Additional data to be sent along with the AJAX request if needed
      'page':'x2wp',
      'data': post
    };
    
    jQuery.post(window['vi_posts_var']['url'], data, function(response) {
      const responseMessage = response['data']['message'];

      if (response['success']) {
        console.log('AJAX request successful:', responseMessage);
        viCommonObj.showSuccessNotification(responseMessage, elem);
      } else {
        console.error('AJAX request failed:', responseMessage);
        viCommonObj.showErrorNotification(responseMessage, elem);
      }
    });
  }, // end sendMediaItemAjaxRequest()

  viewInModal: function() {
    const galleryItem = jQuery(this).closest('.gallery-item');
    const rowIdx = parseInt(jQuery(galleryItem).data('rowidx'), 10);
    const mediaItem = viChatMediaObj.wpParsedMedia.find((x) => x['rowIdx'] === rowIdx);
    let naturalWidth = 250;

    if (viCommonObj.isImage(mediaItem['ext'])) {
      naturalWidth = jQuery(`#gallery-img-${rowIdx}`)[0].naturalWidth;
    }
    else {
      naturalWidth = jQuery(`#gallery-video-${rowIdx}`)[0].videoWidth;
    }

    const isImage = ('jpg|jpeg|png|webp|gif'.indexOf(mediaItem['ext']) !== -1);
    const isVideo = ('mp4|mpg|mpeg'.indexOf(mediaItem['ext']) !== -1);
    const isAudio = ('aac|mp3'.indexOf(mediaItem['ext']) !== -1);
    let mediaHtml = ``;
    
    if (isImage) {
      mediaHtml = `<img src="${viChatMediaObj.mediaUrlBase}${mediaItem['uri']}" alt="${mediaItem['title']}"/><br />${mediaItem['description']}`;
    }
    else if (isVideo) {
      mediaHtml = `<video controls src="${viChatMediaObj.mediaUrlBase}${mediaItem['uri']}"></video><br />${mediaItem['description']}`;
    }
    else if (isAudio) {
      mediaHtml = `<audio controls><source src="${viChatMediaObj.mediaUrlBase}${mediaItem['uri']}" type="audio/${mediaItem['ext']}"></audio><br />${mediaItem['description']}`;
    }
    else {
      console.warn('unknown media type', mediaItem);
      return;
    }
    
    jQuery('#media-modal .modal-body').html(mediaHtml);
    jQuery('#media-modal').modal('show');
  },

  countImages: function(posts) {
    return posts.reduce((acc, post) => acc + (viCommonObj.isImage(post['ext']) ? 1 : 0), 0);
  }

}; // end viChatMediaObj

viChatMediaObj.load(viChatMediaObj, window['vi_posts_var']['dataUrl']);
