const viChatLogsObj = {
  chatLogs: [],

  load: function(f, viJsonUrl) {
    const self = f;

    jQuery.getJSON(viJsonUrl, function(data) {
      self.chatLogs = data;

      for (const datum of data) {
        if (!datum['title']) {
          datum['title'] = '[No longer on Twitter]';
        }
        else {
          datum['title'] = viCommonObj.interpretUnicode(datum['title']);
        }
      }

      jQuery(document).ready(function() {
        self.loadDataTable();
      }); // end jQuery ready
    }) // end jQuery.getJSON({})
    .fail(function() {
      const errorWarning = `<div class="alert alert-danger purchase-warning" role="alert">The data for this tab is not available. You might not have such content.</div>`;
      jQuery('#chatlogs-view').html(errorWarning);
    });
  }, // end load()
  

  loadDataTable: function() {
    const self = this;

    const dtSettings = {
      'tableWrapperId':'chatlogs-table-wrapper',
      'data':self.chatLogs,
      'groupingField':'name',
      'columnHeaders':[{'title':'Title', 'sortField':'name'}, {'title':'Message Count', 'sortField':'messageCount'}],
      'columnConfig':[{'render':self.titleCell }, {'render':self.messageCountCell }],
    };
    const dtOptions = {
      'selectedBasketEntrySize':25,
      'searchableFields':['title'],
      'orderByField':'title'
    };
    self.rowBasketTable = new RowBasketDataTable(dtSettings, dtOptions);

  }, // end loadDataTable()

  titleCell: function(groupKey, i, posts) {
    return posts[0]['title'];
  },
  messageCountCell: function(groupKey, i, posts) {
    return posts[0]['messageCount'];
  }

}; // end viChatLogsObj

viChatLogsObj.load(viChatLogsObj, window['vi_posts_var']['dataUrl']);
