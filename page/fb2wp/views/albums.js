const fb2wpAlbumsObj = {
  mediaUrlBase: '',
  wpParsedMedia: [],
  allowMediaUpload: false,
  rowBasketTable: null,
  postedGmts: {},

  load: function(f, viJsonUrl) {
    const self = fb2wpAlbumsObj;
    self.mediaUrlBase = window['vi_posts_var']['config']['s3Url'];
 
    jQuery.getJSON(viJsonUrl, function(data) {
      for (let i=0; i<data.length; i++) {
        const media = self.parseMedia(data[i], i);
        self.wpParsedMedia.push(media);
      }

      jQuery(document).ready(function() {
        self.loadDataTable();
      }); // end jQuery ready
    }) // end jQuery.getJSON({})
    .fail(function() {
      const errorWarning = `<div class="alert alert-danger page-error" role="alert">The data for this tab is not available. You might not have such content.</div>`;
      jQuery('#albums-view').html(errorWarning);
    });

    jQuery(document).ready(function() {
      jQuery(document).on('click', '.view-larger-img', self.viewInModal);
      jQuery(document).on('click', '.upload-file-btn', self.sendMediaItemAjaxRequest);
      jQuery(document).on('click', '.view-larger-video-btn', self.viewInModal);
      
      if (window['vi_posts_var']['availableOptions'].includes('albums')) {
        self.allowMediaUpload = true;
      }
      else {
        jQuery('#status-select option[value="upload-only"]').remove();
      }

      jQuery('#date-formats').on('change', function() {
        self.dateFormat = jQuery(this).val();

        for (const post of self.wpParsedMedia) {
          post['title'] = moment.unix( post['timestamp'] ).format(self.dateFormat);
        }

        self.rowBasketTable.reRender();
      });

      jQuery(document).on('click', '.retry-post-errors', self.uploadPageGroupsWithErrors);
      jQuery(document).on('click', '.cancel-post-errors', self.cancelUploadPageGroupsWithErrors);
    });
  }, // end load()

  parseMedia: function(media, idx) {
    const momentDate = moment.unix(media['creation_timestamp']);
    const defaultDateFormat = 'ddd MMM D, YYYY hh:mm A'; // matches first option value in select#date-formats

    const item = {
      'date_gmt': viCommonObj.formatGmtDateTime( viCommonObj.changeTimeStampToDate(media['creation_timestamp']) ),
      'title':momentDate.format(defaultDateFormat),
      'description': media['description'],
      'content': media['title'] + ' ' + media['description'],
      'status': 'draft',
      'timestamp': media['creation_timestamp'],
      'ext': media['ext'],
      'uri': media['uri'],
      'date-iso': momentDate.format('YYYY-MM-DD'),
      'week-of-year': momentDate.format('YYYY-ww'),
      'month-of-year': momentDate.format('YYYY-MM'),
      'year': momentDate.format('YYYY'),
      'rowIdx': idx
    };

    if ('tags' in media && media['tags'].length > 0) { item['tags'] = media['tags']; }

    return item;
  },


  sendAjaxRequest: function(uploadArr, elem, postType) {
    if (! fb2wpAlbumsObj.allowMediaUpload) {
      alert("You haven't purchased the Photo and Video Albums option yet.")
      return;
    }

    const posts = [];
    let dateGmtsToPost = [];
    const lastTitle = fb2wpAlbumsObj.addPostToPostRecursive(uploadArr, posts, dateGmtsToPost);

    var data = {
      'action': 'fb2wp_post_ajax_action', // AJAX action name registered in the PHP code
      'nonce': window['vi_posts_var']['nonce'], // Nonce value passed from PHP to JavaScript    https://wordpress.stackexchange.com/questions/231797/what-is-nonce-and-how-to-use-it-with-ajax-in-wordpress
      // Additional data to be sent along with the AJAX request if needed
      'page':'fb2wp',
      'data': {
        'posts': JSON.stringify( posts ),
        'title': lastTitle,
        'date_gmt': dateGmtsToPost[ dateGmtsToPost.length-1 ],
        'status':   jQuery('#status-select').val(),
        'import_tags': jQuery('#import-tags').is(":checked")
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

        for (const dateGmt of dateGmtsToPost) { // mark posts as "Posted"
          fb2wpAlbumsObj.postedGmts[dateGmt] = true;
          jQuery('.is-posted[data-gmt="'+dateGmt+'"]').show();
        }
      } else {
        console.error('AJAX request failed:', responseMessage);
        viCommonObj.showErrorNotification(responseMessage, elem);
      }
    });
  }, // end sendAjaxRequest()

  addPostToPostRecursive: function(inPosts, outPosts, dateGmtsToPost) {
    let lastTitle = '';

    for (const post of inPosts) {
      const media = {'uri':post['uri'], 'date_gmt':post['date_gmt'], 'description':post['description'], 'ext':post['ext']};
      const postTransformed = {
        'title':       post['title'],
        'description': post['description'],
        'links':       [],
        'media':       (fb2wpAlbumsObj.allowMediaUpload) ? [media] : []
      };

      outPosts.push(postTransformed);

      lastTitle = post['title'];
      dateGmtsToPost.push( post['date_gmt'] );
    }

    return lastTitle;
  },


  uploadGroupsInProcess: [],
  uploadGroupsWithErrors: [],
  
  loadDataTable: function() {
    const self = fb2wpAlbumsObj;

    const dtSettings = {
      'tableWrapperId':'albums-table-wrapper',
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


    jQuery('#albums-view').on('click', '.create-post-btn', function () {
      const groupKey = jQuery(this).data('group-key').toString();
      const baskets = self.rowBasketTable.getAllBaskets();
      const uploadArr = baskets[groupKey];
      self.sendAjaxRequest( uploadArr, this, 'post' );
    });

    jQuery('#albums-view').on('click', '.upload-gallery-btn', function () {
      const groupKey = jQuery(this).data('group-key').toString();
      const baskets = self.rowBasketTable.getAllBaskets();
      const uploadArr = baskets[groupKey];
      self.sendAjaxRequest( uploadArr, this, 'gallery' );
    });

    jQuery('#group-select').on('change', function() {
      const groupField = jQuery(this).val();
      self.rowBasketTable.setBasketGroupingField(groupField);
      self.rowBasketTable.setBasketGroupingFunction(null);

      if (groupField === 'tag') { // group by tags
        self.rowBasketTable.setBasketGroupingFunction( function(posts){
          const baskets = {};

          for (const post of posts) {
            if (!('tags' in post)) { continue; }
            for (const tag of post['tags']) {
              if (! (tag in baskets)) { baskets[tag] = []; }
              baskets[tag].push(post);
            }
          }

          return baskets;
        });
      }

      self.rowBasketTable.reRender();
    }); // end #group-select change

    jQuery('#post-all-mass-upload-btn').on('click', function() {
      const checkedBaskets = self.rowBasketTable.getCheckedBaskets();
      const allChecked = checkedBaskets['all'];
      if (!allChecked && ! Object.values(checkedBaskets['groups']).some(x => x.checked)) { alert('No Mass Upload checkboxes are checked yet.'); return; }
      if (! confirm('Are you sure you want to upload all media that are checked for Mass Upload?')) { return; }

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
    console.warn('Start mass upload!');
    jQuery('#mass-upload-progress-text').text('Uploaded 0%');
    jQuery('#mass-upload-progress-bar').val(0);
    jQuery('#mass-upload-progress-wrapper').show();
    jQuery('#send-errors').hide();

    const totalUploads = uploadGroups.length;
    const maxUploadsAtOnce = 3;
    let currentlyInProcess = 0;
    let uploadedCount = 0;
    let errorCount = 0;
    const sendErrors = [];
    const self = this;
    console.log('uploadGroups', uploadGroups);

    let uploadInterval = setInterval(function() {
      if (currentlyInProcess > maxUploadsAtOnce) { return; }
      if (uploadGroups.length === 0) {
        if (sendErrors.length) {
          jQuery('#send-errors').html( sendErrors.join('<br />\n') + '<br />\n<button class="btn btn-info cancel-post-errors">Cancel</button>\n<button class="btn btn-info retry-post-errors">Retry these Posts</button>' ).show();
        }
        clearInterval(uploadInterval);
        return;
      }

      currentlyInProcess++;
      const uploadArr = uploadGroups.shift(); // this should have removed element from uploadGroupsWithErrors
      console.log(`uploadGroups len=${uploadGroups.length}; uploadGroupsWithErrors len=${self.uploadGroupsWithErrors.length}`);

      const updateUploadStatus = function() {
        currentlyInProcess--;
        const stillUploading = (uploadedCount + errorCount) < totalUploads;
        const continueText = stillUploading ? ', running ...' : ', done!';
        const errorText = (errorCount > 0) ? `, ${errorCount} errors` : '';
        const percentUploaded = Math.ceil(uploadedCount * 10 / totalUploads) * 10; // ##.# precision
        console.log('upload progress', uploadedCount, totalUploads, percentUploaded);
        jQuery('#mass-upload-progress-text').text(`Uploaded ${percentUploaded}%${continueText}${errorText}`);
        jQuery('#mass-upload-progress-bar').val(percentUploaded);
      };

      const postFailure = function(msg) {
        errorCount++;
        console.error('failed to post', msg, uploadArr);
        const sendError = 'failed to post ' + uploadArr[uploadArr.length-1].title;
        sendErrors.push(sendError);
        self.uploadGroupsWithErrors.push(uploadArr);
        updateUploadStatus();

        if (jQuery('#send-errors').length) {
          jQuery('#send-errors').html( sendErrors.join('<br />\n') );
        }
        else {
          const errorWarning = `<div class="alert alert-danger purchase-warning" id="send-errors" role="alert"><a href="#" class="close" data-dismiss="alert" aria-label="close">&times;</a>${sendError}</div>`;
          jQuery('#date-filters').before(errorWarning);
        }
      };

      fb2wpAlbumsObj.sendAjaxRequest(uploadArr, jQuery('#post-all-mass-upload-btn'), 'post')
      .done(function(response) {
        if (response['success']) {
          console.log(response['data']['message']);
        }
        else {
          postFailure(response['data']['message']);
        }

        uploadedCount++;
        updateUploadStatus();
      })
      .fail((jqXHR, msg) => postFailure(msg));
    }, 300);

  }, // end uploadPageGroups()

  uploadPageGroupsWithErrors: function() {
    const uploadClones = structuredClone(fb2wpAlbumsObj.uploadGroupsWithErrors);
    fb2wpAlbumsObj.uploadGroupsWithErrors.length = 0;
    fb2wpAlbumsObj.uploadPageGroups(uploadClones);
  },
  cancelUploadPageGroupsWithErrors: function() {
    jQuery('.cancel-post-errors').hide();
    fb2wpAlbumsObj.uploadGroupsWithErrors.length = 0;
  },

  createPostBtn: function(groupKey, idx, posts) {
    const galleryBtn = (fb2wpAlbumsObj.allowMediaUpload && (fb2wpAlbumsObj.countImages(posts) > 1)) ? `<br /><br />\n<button class="btn btn-success btn-sm upload-gallery-btn" data-group-key="${groupKey}">Create Gallery Post</button>` : '';
    return `<button class="btn btn-success btn-sm create-post-btn" data-group-key="${groupKey}">Create Post</button>` + galleryBtn;
  },
  createFirstTitle: function(groupKey, idx, posts) {
    let title = '';
    let timestamp = 0;
    const groupField = jQuery('#group-select').val();

    for (const post of posts) {
      if (groupField === 'tag') {
        title = groupKey;
      }
      else if (timestamp < post['timestamp']) {
        timestamp = post['timestamp'];
        title = post['title'];
      }
    }

    return title;
  },
  groupPostContent: function(groupKey, idx, posts) {
    const contentGrouped = [];

    for (const mediaItem of posts) {
      const showPosted = (mediaItem['date_gmt'] in fb2wpAlbumsObj.postedGmts) ? 'style="display:inline;"' : 'style="display:none;"';
      const tags = ('tags' in mediaItem && mediaItem['tags'].length) ? '#' + mediaItem['tags'].join(', #') : '';
      const isImage = ('jpg|jpeg|png|webp|gif'.indexOf(mediaItem.ext) !== -1);
      const isVideo = ('mp4|mpg'.indexOf(mediaItem.ext) !== -1);
      const isAudio = ('aac|mp3'.indexOf(mediaItem.ext) !== -1);
      
      if (!isImage && !isVideo) {
        console.warn('not an image nor video', mediaItem);
        viCommonObj.showErrorNotification('not an image nor video: ' + mediaItem.uri, null)
        return;
      }

      let mediaHtmlTag = '';
      let mediaHtmlWrapped = '';
      const rowIdx = mediaItem['rowIdx'];

      if (isImage) {
        mediaHtmlTag = `<!-- wp:image {"align":"center","sizeSlug":"medium","linkDestination":"custom","className":"is-resized"} -->\n<figure class="wp-block-image aligncenter size-medium is-resized"><img src="${fb2wpAlbumsObj.mediaUrlBase}${mediaItem.uri}" alt="${mediaItem.title}" type="button" id="gallery-img-${rowIdx}" class="view-larger-img" /><figcaption class="wp-element-caption"><strong>${mediaItem.title}</strong> - ${mediaItem.description}</a></figcaption>\n\n`;
        mediaHtmlWrapped = `<div class="gallery-item" data-rowidx="${rowIdx}">${mediaHtmlTag}\n<button class="btn btn-success btn-sm upload-file-btn">Upload Individual File</button></figure>\n<!-- /wp:image --></div>`;
      }
      else if (isVideo) { // is video
        mediaHtmlTag = `<!-- wp:video -->\n\n<figure class="wp-block-video"><video controls src="${fb2wpAlbumsObj.mediaUrlBase}${mediaItem.uri}" id="gallery-video-${rowIdx}"></video><figcaption class="wp-element-caption"><strong>${mediaItem.title}</strong> - ${mediaItem.description}</figcaption>\n\n`;
        mediaHtmlWrapped = `<div class="gallery-item" data-rowidx="${rowIdx}">${mediaHtmlTag}\n<button class="btn btn-outline-info btn-sm view-larger-video-btn">View in Larger Window</button><br>\n<button class="btn btn-success btn-sm upload-file-btn">Upload Individual File</button></figure>\n<!-- /wp:video --></div>`;
      }
      else if (isAudio) {
        mediaHtmlTag = `<!-- wp:audio -->\n\n<figure class="wp-block-audio"><audio controls src="${fb2wpAlbumsObj.mediaUrlBase}${mediaItem.uri}" id="gallery-audio-${rowIdx}"></audio>\n\n`;
        mediaHtmlWrapped = `<div class="gallery-item" data-rowidx="${rowIdx}">${mediaHtmlTag}\n<button class="btn btn-outline-info btn-sm view-larger-audio-btn">View in Larger Window</button><br>\n<button class="btn btn-success btn-sm upload-file-btn">Upload Individual File</button></figure>\n<!-- /wp:audio --></div>`;
      }

      mediaHtmlWrapped = mediaHtmlWrapped  + ` <div class="is-posted" data-gmt="${mediaItem['date_gmt']}" ${showPosted}>Posted</div>\n<p>${tags}</p>`;
      contentGrouped.push(mediaHtmlWrapped);
    }

    const contentGroupedHtml = contentGrouped.join('<hr />\n');
    return contentGroupedHtml;
  },



  sendMediaItemAjaxRequest: function() {
    if (! fb2wpAlbumsObj.allowMediaUpload) {
      alert("You haven't purchased the Photo and Video Albums option yet.")
      return;
    }

    const galleryItem = jQuery(this).closest('.gallery-item');
    const rowIdx = parseInt(jQuery(galleryItem).data('rowidx'), 10);
    const mediaItem = fb2wpAlbumsObj.wpParsedMedia[ rowIdx ];
    const elem = this;

    var post = {
      'url': mediaItem.uri,
      'title': mediaItem.title,
      'description': mediaItem.description,
      'date_gmt': viCommonObj.formatGmtDateTime( viCommonObj.changeTimeStampToDate(mediaItem.timestamp) ),
    };
    var data = {
      'action': 'vintillect_media_ajax_action', // AJAX action name registered in the PHP code
      'nonce': window['vi_posts_var']['nonce'], // Nonce value passed from PHP to JavaScript    https://wordpress.stackexchange.com/questions/231797/what-is-nonce-and-how-to-use-it-with-ajax-in-wordpress
      // Additional data to be sent along with the AJAX request if needed
      'page':'fb2wp',
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
    const mediaItem = fb2wpAlbumsObj.wpParsedMedia[rowIdx];
    let naturalWidth = 250;

    if (viCommonObj.isImage(mediaItem.ext)) {
      naturalWidth = jQuery(`#gallery-img-${rowIdx}`)[0].naturalWidth;
    }
    else {
      naturalWidth = jQuery(`#gallery-video-${rowIdx}`)[0].videoWidth;
    }

    const isImage = ('jpg|jpeg|png|webp|gif'.indexOf(mediaItem.ext) !== -1);
    const isVideo = ('mp4|mpg|mpeg'.indexOf(mediaItem.ext) !== -1);
    // const isAudio = ('aac|mp3'.indexOf(mediaItem.ext) !== -1);
    let mediaHtml = ``;
    
    if (isImage) {
      mediaHtml = `<img src="${fb2wpAlbumsObj.mediaUrlBase}${mediaItem.uri}" alt="${mediaItem.title}"/><br />${mediaItem.description}`;
    }
    else if (isVideo) {
      mediaHtml = `<video controls src="${fb2wpAlbumsObj.mediaUrlBase}${mediaItem.uri}"></video><br />${mediaItem.description}`;
    }
    // else if (isAudio) {
    //   mediaHtml = `<audio controls><source src="${fb2wpAlbumsObj.mediaUrlBase}${mediaItem.uri}" type="audio/${mediaItem.ext}"></audio><br />${mediaItem.description}`;
    // }
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

}; // end fb2wpAlbumsObj

fb2wpAlbumsObj.load(fb2wpAlbumsObj, window['vi_posts_var']['dataUrl']);
