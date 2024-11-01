const viPostsObj = {
  mediaUrlBase: '',
  wpParsedPosts: [],
  allowMediaUpload: false,
  rowBasketTable: null,
  postedGmts: {},
  isTweetCircle: false,
  twitterFilter: '',
  twitterFilterValue: '',

  load: function(f, viJsonUrl) {
    const self = f;
    self.mediaUrlBase = window['vi_posts_var']['config']['s3Url'];
    self.isTweetCircle = (viJsonUrl.lastIndexOf('twitter-circle-tweet.json') !== -1);

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
      if (window['vi_posts_var']['availableOptions'].includes('media')) {
        self.allowMediaUpload = true;
      }

      jQuery('#date-formats').on('change', function() {
        self.dateFormat = jQuery(this).val();

        for (const post of self.wpParsedPosts) {
          post['title'] = moment.unix( post['timestamp'] ).format(self.dateFormat);
        }

        self.rowBasketTable.reRender();
      });

      jQuery('#twitter-filter').on('change', function() {
        self.twitterFilter = jQuery(this).val();

        if (! self.twitterFilter) {
          self.twitterFilterValue = '';
          jQuery('#twitter-filter-value').val('');
          jQuery('#twitter-filter-value-wrapper').hide();
          self.rowBasketTable.setPreFilterFunction(null);
          self.rowBasketTable.reRender();
          return;
        }

        const filterValues = ['<option value="">Select</option>'];

        if (self.twitterFilter === 'tag') {
          const tagsDict = {};

          for (const post of self.wpParsedPosts) {
            if (!('tags' in post)) { continue; }
            for (const tag of post['tags']) {
              if (tag in tagsDict) { continue; }
              tagsDict[tag] = true;
              filterValues.push(`<option value="${tag}">${tag}</option>`);
            }
          }

          self.rowBasketTable.setPreFilterFunction(self.tagFilter);
        }

        else if (self.twitterFilter === 'screen_name') {
          const screenNameDict = {};

          for (const post of self.wpParsedPosts) {
            if (!('userMentions' in post)) { continue; }
            for (const userMention of post['userMentions']) {
              const screenName = userMention['screen_name'];
              if (screenName in screenNameDict) { continue; }
              screenNameDict[screenName] = true;
              filterValues.push(`<option value="${screenName}">${screenName}</option>`);
            }
          }

          self.rowBasketTable.setPreFilterFunction(self.userFilter);
        }

        jQuery('#twitter-filter-value').html(filterValues.join('\n'));
        jQuery('#twitter-filter-value-wrapper').show();
      });

      jQuery('#twitter-filter').on('change', function() {
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

      if (viPostsObj.isTweetCircle) {
        mediaItem['uri'] = 'data/twitter_circle_tweet_media/' + mediaItem['filename'];
      }
      else {
        mediaItem['uri'] = 'data/tweets_media/' + mediaItem['filename'];
      }
      if (! ('ext' in mediaItem)) {
        mediaItem['ext'] = mediaItem['uri'].substring( mediaItem['uri'].lastIndexOf('.')+1 );
      }
    }

    for (const link of post['links']) {
      post['postText'] = post['postText'].replace(link['url'], link['expanded_url']);
      link['url'] = link['expanded_url'];
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
      'year': momentDate.format('YYYY'),
      'id': post['id']
    };

    item['content'] = (item['content']) ? viCommonObj.interpretUnicode(item['content']) : '';

    for (const linkItem of item['links']) {
      linkItem['description'] = (linkItem['description']) ? viCommonObj.interpretUnicode(linkItem['description']) : '';
    }

    if (post['tags'].length > 0) { item['tags'] = post['tags']; }
    if ('reply' in post) { item['reply'] = post['reply']; }
    if ('userMentions' in post) { item['userMentions'] = post['userMentions']; }

    return item;
  },


  sendAjaxRequest: function(uploadArr, elem, postType) {
    const posts = [];
    let dateGmtsToPost = [];
    const lastTitle = viPostsObj.addPostToPostRecursive(uploadArr, posts, dateGmtsToPost);

    var data = {
      'action': 'x2wp_post_ajax_action', // AJAX action name registered in the PHP code
      'nonce': window['vi_posts_var']['nonce'], // Nonce value passed from PHP to JavaScript    https://wordpress.stackexchange.com/questions/231797/what-is-nonce-and-how-to-use-it-with-ajax-in-wordpress
      // Additional data to be sent along with the AJAX request if needed
      'page':'x2wp',
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

      if ('subTweets' in post) {
        postTransformed['subTweets'] = [];
        viPostsObj.addPostToPostRecursive(post['subTweets'], postTransformed['subTweets'], dateGmtsToPost);
      }
      if ('reply' in post && post['reply']) {
        postTransformed['repliedToTweetUrl'] = 'https://twitter.com/' + post['reply']['screenName'] + '/status/' + post['reply']['tweetId'];
      }
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

      // group by threads
      if (groupField === 'thread') {
        self.rowBasketTable.setBasketGroupingFunction( function(posts){
          const replyIdDict = {};
          const idDict = {};
          const preBaskets = {};
          const baskets = {};

          for (const post of posts) {
            idDict[post['id']] = post;
    
            if ('reply' in post && post['reply']) {
              const origTweetId = post['reply']['tweetId'];
              if (! (origTweetId in replyIdDict)) {
                replyIdDict[ origTweetId ] = [];
              }
              replyIdDict[ origTweetId ].push( post );
            }
          }
        
          for (const id in replyIdDict) {
            if (id in idDict) {
              idDict[id]['subTweets'] = replyIdDict[id];
    
              if ('reply' in idDict[id]) { continue; }
              preBaskets[id] = [ idDict[id] ];
            }
            else {
              if (! (id in preBaskets)) { preBaskets[id] = []; }
              preBaskets[id].push( ...replyIdDict[id] );
            }
          }

          for (const id in preBaskets) {
            const bGroup = preBaskets[id];
            if (! ('subTweets' in bGroup[0] || bGroup.length > 1)) { continue; }
            baskets[id] = bGroup;
            // let firstPost = bGroup[0];
            // if ('reply' in firstPost) {
            //   firstPost['content'] = `<!-- wp:paragraph -->\n<p><a href="https://twitter.com/${firstPost['reply']['screenName']}/status/${firstPost['reply']['tweetId']}" target="twitter">https://twitter.com/${firstPost['reply']['screenName']}/status/${firstPost['reply']['tweetId']}</a></p><!-- /wp:paragraph -->\n\n` + firstPost['content'];
            // }
          }
    
          return baskets;
        });
      }
      else if (groupField === 'user-mentioned') { // group by users mentioned in tweet
        self.rowBasketTable.setBasketGroupingFunction( function(posts){
          const baskets = {};

          for (const post of posts) {
            if (!('userMentions' in post)) { continue; }
            for (const userMention of post['userMentions']) {
              const screenName = userMention['screen_name']
              if (! (screenName in baskets)) { baskets[screenName] = []; }
              baskets[screenName].push(post);
            }
          }

          return baskets;
        });
      }
      else if (groupField === 'tag') { // group by tags
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

      // console.log(checkedRowGroups);
      self.uploadPageGroups( Object.values(checkedRowGroups) );
    }); // end #post-all-mass-upload-btn click

    /*
    // the problem with this is that it doesn't trigger the toggleAllCheckboxes() or toggleOneCheckbox() in rowbasket_datatable; doesn't trigger the onchange event.
    jQuery(document).on('click', '#posts-table-wrapper table td:first-child, #posts-table-wrapper table th:first-child', function() {
      const checkbox = jQuery(this).children('input');
      checkbox.prop('checked', ! checkbox.is(':checked') );
    });
    */

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
      if (groupField === 'tag' || groupField === 'user-mentioned') {
        title = groupKey;
      }
      else if (groupField === 'thread' && !timestamp) {
        timestamp = post['timestamp'];
        title = post['title'];
        break;
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
      const tags = ('tags' in post && post['tags'].length) ? '#' + post['tags'].join(', #') : '';
      let contentFormatted = `<p><b>${post['title']} <span class="is-posted" data-gmt="${post['date_gmt']}" ${showPosted}>Posted</span></b></p>\n` + post['content'] + `<p>${tags}</p>`;

      if ('subTweets' in post) {
        contentFormatted += '<div class="sub-tweets">\n' + viPostsObj.groupPostContent(groupKey, idx, post['subTweets']) + '\n</div>';
      }
      contentGrouped.push(contentFormatted);
    }

    const contentGroupedHtml = contentGrouped.join('<hr />\n');
    return contentGroupedHtml;
  },

  tagFilter: function(data) {
    const filterValue = jQuery('#twitter-filter-value').val();
    const filtered = [];

    if (!filterValue) { return data; }

    for (const post of data) {
      if ('tags' in post && post['tags'].includes(filterValue)) {
        filtered.push(post);
      }
    }

    return filtered;
  },

  userFilter: function(data) {
    const filterValue = jQuery('#twitter-filter-value').val();
    const filtered = [];

    if (!filterValue) { return data; }

    for (const post of data) {
      if (!('userMentions' in post)) { continue; }
      for (const userMention of post['userMentions']) {
        if (userMention['screen_name'] === filterValue) {
          filtered.push(post);
          break;
        }
      }
    }

    return filtered;
  },

  countMedia: function(posts) {
    return posts.reduce((acc, post) => acc + post['media'].length, 0);
  }

}; // end viPostsObj

viCommonObj.load(viCommonObj, window['vi_posts_var']['dataUrl']);
viPostsObj.load(viPostsObj, window['vi_posts_var']['dataUrl']);
