const viSettingsObj = {
  vintillectDomain: 'vintillect.com',

  getJobStatus: function() {
    const jobStatusU = 'https://' + viSettingsObj.vintillectDomain + '/vintillect-importer/info.php?cmd=jobstatus&uploadId=';
    const uploadId = jQuery('#upload-id').val();
    jQuery('#config-response-msg').css('color', 'black').text('');

    if (!uploadId) {
      jQuery('#config-response-msg').css('color', 'red').text('Please enter an upload ID.');
      jQuery('#upload-id').focus();
      return;
    }
    if (uploadId.length !== 32) {
      jQuery('#config-response-msg').css('color', 'red').text('Please enter a valid ID.');
      jQuery('#upload-id').focus();
      return;
    }

    jQuery.getJSON(jobStatusU + uploadId, function(response) {
      if (response['error']) {
        console.error('info.php jobstatus error', uploadId, response);
        jQuery('#config-response-msg').css('color', 'red').text('Information for this upload ID is not available;\n' + response['error']);
        return;
      }
      else {
        const stageArr = ['new', 'downloadDataFiles', 'parsePosts', 'parseSavedItems', 'parsePostsFromApps', 'parsePostsInGroups', 'parseYourVideos', 'parsePhotoAlbums', 'parseNotes', 'parseStories', 'messageHtml', 'uploadProcessedFiles', 'complete'];
        jQuery('#config-response-msg').css('color', 'black').text(`Stage '${response['stage']}' ${response['status']} in ${response['level']}/${response['totalStages']} stages`);
      }
    })
    .fail(function(a,b,c) {
      console.warn('info not available for uploadId', a, b, c);
      jQuery('#config-response-msg').css('color', 'red').text('failed to contact server to get info');
    });
  
  }, // end getJobStatus()


  requestInfoFromVintillect: function() {
    const settingsU = 'https://' + viSettingsObj.vintillectDomain + '/vintillect-importer/info.php?';
    const uploadId = jQuery('#upload-id').val();
    jQuery('#config-response-msg').css('color', 'black').text('');

    if (!uploadId) {
      jQuery('#config-response-msg').css('color', 'red').text('Please enter an upload ID.');
      jQuery('#upload-id').focus();
      return;
    }
    if (uploadId.length !== 32) {
      jQuery('#config-response-msg').css('color', 'red').text('Please enter a valid ID.');
      jQuery('#upload-id').focus();
      return;
    }

    const urlParams = new URLSearchParams({ cmd:"getnew", uploadId:uploadId });

    const website = jQuery('#vi-wp-website').val();
    if (website) {
      urlParams.append('website', website);
    }

    jQuery.getJSON(settingsU + urlParams.toString(), function(response) {
      if (response['error']) {
        console.error('info.php getnew error', uploadId, response);
        jQuery('#config-response-msg').css('color', 'red').text('Information for this upload ID is not available;\n' + response['error']);
        return;
      }
      if (! ('uploadId' in response)) {
        console.error('info.php getnew error no uploadId response', uploadId, response);
        jQuery('#config-response-msg').css('color', 'red').text('Information for this upload ID is not available;\n' + response['error']);
        return;
      }

      let successMsg = '';
      if (response['uploadId'] && !response['s3Url']) {
        successMsg = 'Currently being processed. Getting link previews usually takes the longest time.';
        jQuery('#config-response-msg').css('color', 'orange');
        return;
      }

      viSettingsObj.sendAjaxConfig(response, successMsg);
    })
    .fail(function(a,b,c) {
      console.warn('info not available for uploadId', a, b, c);
      jQuery('#config-response-msg').css('color', 'red').text('failed to contact server to get info');
    });
  
  }, // end requestInfoFromVintillect()


  updateConfigFromS3: function() {
    const s3Url = window['vi_config']['config']['s3Url'] + 'processed/config.json';
    jQuery('#config-response-msg').css('color', 'black').text('');

    jQuery.getJSON(s3Url, function(response) {
      viSettingsObj.sendAjaxConfig(response, false);
    })
    .fail(function(a,b,c) {
      console.warn('info not available for uploadId', a, b, c);
      jQuery('#config-response-msg').css('color', 'red').text('failed to contact server to get info');
    });
  
  }, // end updateConfigFromS3()


  sendAjaxConfig: function(response, altSuccessMsg) {
    window['vi_config']['config'] = response;
    window['vi_config']['config']['json'] = JSON.stringify(response);

    var data = {
      'action': 'fb2wp_update_config', // AJAX action name registered in the PHP code
      'nonce': window['vi_config']['nonce'], // Nonce value passed from PHP to JavaScript    https://wordpress.stackexchange.com/questions/231797/what-is-nonce-and-how-to-use-it-with-ajax-in-wordpress
      'page':'fb2wp',
      'data': response
    };

    jQuery.post(window['vi_config']['url'], data, function(response) {
      const responseMessage = response['data']['message'];

      if (response['success']) {
        console.log('AJAX config request successful:', responseMessage);
        if (altSuccessMsg) {
          jQuery('#config-response-msg').css('color', 'black').text(altSuccessMsg);
          return;
        }

        jQuery('#config-response-msg').css('color', 'black').text(response['data']['message']);
        viSettingsObj.setCookie('vi-reload-message', response['data']['message'], 5);
        window.location.reload();
      } else {
        console.error('AJAX config request failed:', responseMessage);

        if (response['data']['message'] === 'Invalid nonce.') {
          jQuery('#config-response-msg').css('color', 'red').text('Session expired. Please refresh page.');
        }
        else {
          jQuery('#config-response-msg').css('color', 'red').text('Failed to update config in WordPress\n' + response['data']['message']);
        }
      }
    });
  },


  resetSettings: function() {
    if (!confirm('Are you sure you want to reset your upload?')) {
      return;
    }
    
    viSettingsObj.sendAjaxConfig({'doReset':true}, false);
  }, // end resetSettings()


  setCookie: function(cname, cvalue, seconds) {
    const d = new Date();
    d.setTime(d.getTime() + (seconds*1000));
    let expires = "expires="+ d.toUTCString();
    document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
  },
  getCookie: function(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    else return '';
  }

} // end viSettingsObj


jQuery(document).ready(function() {
  jQuery('#get-config-btn').on('click', viSettingsObj.requestInfoFromVintillect);
  jQuery('#get-status-btn').on('click', viSettingsObj.getJobStatus);
  jQuery('#update-config-btn').on('click', viSettingsObj.updateConfigFromS3);
  jQuery('#reset-upload-btn').on('click', viSettingsObj.resetSettings);

  const reloadMsg = viSettingsObj.getCookie('vi-reload-message');
  if (reloadMsg) {
    jQuery('#config-response-msg').text(reloadMsg);
  }
});
