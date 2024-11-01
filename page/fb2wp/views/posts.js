const viPostsObj = {
  mediaUrlBase: '',
  wpParsedPosts: [],
  allowMediaUpload: false,
  rowBasketTable: null,
  postedGmts: {},
  isGroup: false,

  load: function(f, viJsonUrl) {
    const self = f;
    self.mediaUrlBase = window['vi_posts_var']['config']['s3Url'];
    self.isGroup = (viJsonUrl.lastIndexOf('group_posts.json') !== -1);

    jQuery.getJSON(viJsonUrl, function(data) {
      for (const val of data) {
        if (!val['postText'] && !val['text'] && !val['links'].length && !val['media'].length) { continue; } // skip if there is no content

        self.wpParsedPosts.push(self.parsePost(val));
      }

      jQuery(document).ready(function() {
        self.loadDataTable();
      }); // end jQuery ready
    }) // end jQuery.getJSON({})
    .fail(function() {
      const errorWarning = `<div class="alert alert-danger page-error" role="alert">The data for this tab is not available. You might not have such content.</div>`;
      jQuery('#posts-view').html(errorWarning);
    });

    jQuery(document).ready(function() {
      if (window['vi_posts_var']['availableOptions'].includes('albums')) {
        self.allowMediaUpload = true;
      }

      jQuery('#date-formats').on('change', function() {
        self.dateFormat = jQuery(this).val();

        for (const post of self.wpParsedPosts) {
          post['title'] = moment.unix( post['timestamp'] ).format(self.dateFormat);
        }

        self.rowBasketTable.reRender();
      });

      jQuery(document).on('click', '.retry-post-errors', self.uploadPageGroupsWithErrors);
      jQuery(document).on('click', '.cancel-post-errors', self.cancelUploadPageGroupsWithErrors);
    });
  }, // end load()

  parsePost: function(post) {
    const momentDate = moment.unix(post['timestamp']);
    const defaultDateFormat = 'ddd MMM D, YYYY hh:mm A'; // matches first option value in select#date-formats
    if (! ('links' in post)) { post['links'] = []; }

    for (const mediaItem of post['media']) {
      mediaItem['description'] = (mediaItem['description']) ? viCommonObj.interpretUnicode(mediaItem['description']) : '';

      if (! ('ext' in mediaItem)) {
        mediaItem['ext'] = mediaItem['uri'].substring( mediaItem['uri'].lastIndexOf('.')+1 );
      }
    }

    let discussionGroup = null;
    if (viPostsObj.isGroup) {
      const groupTitle = post['postTitle'];
      let toGroupStartIdx = groupTitle.indexOf('to the group:');
      let postedInStartIdx = groupTitle.lastIndexOf('posted in');

      if (toGroupStartIdx > -1) {
        toGroupStartIdx += 14;
        discussionGroup = groupTitle.substring(toGroupStartIdx, groupTitle.length-1);
      }
      else if (postedInStartIdx > -1) {
        postedInStartIdx += 10;
        discussionGroup = groupTitle.substring(postedInStartIdx, groupTitle.length-1);
      }
    }

    const item = {
      'date_gmt': viCommonObj.formatGmtDateTime( viCommonObj.changeTimeStampToDate(post['timestamp']) ),
      'title':momentDate.format(defaultDateFormat),
      'description': post['postText'] || post['text'] || '',
      'content': viCommonObj.formatPostContent(post),
      'status': 'draft',
      'timestamp': post['timestamp'],
      'links': post['links'],
      'media': post['media'],
      'date-iso': momentDate.format('YYYY-MM-DD'),
      'week-of-year': momentDate.format('YYYY-ww'),
      'month-of-year': momentDate.format('YYYY-MM'),
      'year': momentDate.format('YYYY')
    };

    item['content'] = (item['content']) ? viCommonObj.interpretUnicode(item['content']) : '';

    for (const linkItem of item['links']) {
      linkItem['description'] = (linkItem['description']) ? viCommonObj.interpretUnicode(linkItem['description']) : '';
    }

    if ('tags' in post && post['tags'].length > 0) { item['tags'] = post['tags']; }
    if (viPostsObj.isGroup) { item['discussion-group'] = discussionGroup; }

    return item;
  },


  // JavaScript code to send an AJAX request to the WordPress plugin
  sendAjaxRequest: function(uploadArr, elem, postType) {
    const posts = [];
    let dateGmtsToPost = [];
    const lastTitle = viPostsObj.addPostToPostRecursive(uploadArr, posts, dateGmtsToPost);

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
          viPostsObj.postedGmts[dateGmt] = true;
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
      const postTransformed = {
        'title':       post['title'],
        'description': post['description'],
        'links':       post['links'],
        'media':       (viPostsObj.allowMediaUpload) ? post['media'] : []
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
    const self = viPostsObj;

    const dtSettings = {
      'tableWrapperId':'posts-table-wrapper',
      'data':self.wpParsedPosts,
      'groupingField':'timestamp',
      'columnHeaders':[{'title':'Create Post'}, {'title':'Title (Date)', 'sortField':'timestamp', 'extra':'id="posts-table-wrapper-title-th"'}, {'title':'Content'}],
      'columnConfig':[{'render':self.createPostBtn }, {'render':self.createFirstTitle }, {'render':self.groupPostContent }],
    };

    if (self.isGroup) {
      dtSettings['columnHeaders'].splice(2, 0, {'title':'Group', 'sortField':'discussion-group'});
      dtSettings['columnConfig'].splice(2, 0, {'render':self.createDiscussionGroupTitle});
    }

    const dtOptions = {
      'selectedBasketEntrySize':25,
      'searchableFields':['content'],
      'dateField':'timestamp',
      'orderByField':'timestamp'
    };
    self.rowBasketTable = new RowBasketDataTable(dtSettings, dtOptions);


    jQuery('#posts-view').on('click', '.create-post-btn', function () {
      const groupKey = jQuery(this).data('group-key').toString();
      const baskets = self.rowBasketTable.getAllBaskets();
      const uploadArr = baskets[groupKey];
      self.sendAjaxRequest( uploadArr, this, self, 'post' );
    });

    jQuery('#posts-view').on('click', '.upload-gallery-btn', function () {
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
      if (! confirm('Are you sure you want to upload all posts that are checked for Mass Upload?')) { return; }

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

      viPostsObj.sendAjaxRequest(uploadArr, jQuery('#post-all-mass-upload-btn'), 'post')
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
    const uploadClones = structuredClone(viPostsObj.uploadGroupsWithErrors);
    viPostsObj.uploadGroupsWithErrors.length = 0;
    viPostsObj.uploadPageGroups(uploadClones);
  },
  cancelUploadPageGroupsWithErrors: function() {
    jQuery('.cancel-post-errors').hide();
    viPostsObj.uploadGroupsWithErrors.length = 0;
  },

  createPostBtn: function(groupKey, idx, posts) {
    const galleryBtn = (viPostsObj.allowMediaUpload && (viPostsObj.countMedia(posts) > 1)) ? `<br /><br />\n<button class="btn btn-success btn-sm upload-gallery-btn" data-group-key="${groupKey}">Create Gallery Post</button>` : '';
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

    for (const post of posts) {
      const showPosted = (post['date_gmt'] in viPostsObj.postedGmts) ? 'style="display:inline;"' : 'style="display:none;"';
      const tags = ('tags' in post && post['tags'].length) ? 'Tags: #' + post['tags'].join(', #') : '';
      let contentFormatted = `<p><b>${post['title']} <span class="is-posted" data-gmt="${post['date_gmt']}" ${showPosted}>Posted</span></b></p>\n` + post['content'] + `<p>${tags}</p>`;

      contentGrouped.push(contentFormatted);
    }

    const contentGroupedHtml = contentGrouped.join('<hr />\n');
    return contentGroupedHtml;
  },
  createDiscussionGroupTitle: function(groupKey, idx, posts) {
    return posts[0]['discussion-group'];
  },

  countMedia: function(posts) {
    return posts.reduce((acc, post) => acc + post['media'].length, 0);
  }

}; // end viPostsObj

viCommonObj.load(viCommonObj, window['vi_posts_var']['dataUrl']);
viPostsObj.load(viPostsObj, window['vi_posts_var']['dataUrl']);
