const viChatMediaObj = {
  chatMediaListing: [],

  load: function(f, viJsonUrl) {
    const self = f;

    jQuery.getJSON(viJsonUrl, function(data) {
      self.chatMediaListing = data;

      jQuery(document).ready(function() {
        self.loadDataTable();
      }); // end jQuery ready
    }) // end jQuery.getJSON({})
    .fail(function() {
      const errorWarning = `<div class="alert alert-danger purchase-warning" role="alert">The data for this tab is not available. You might not have such content.</div>`;
      jQuery('#chatmedia-view').html(errorWarning);
    });
  }, // end load()
  

  loadDataTable: function() {
    const self = this;

    const dtSettings = {
      'tableWrapperId':'chatmedia-table-wrapper',
      'data':self.chatMediaListing,
      'groupingField':'filename',
      'columnHeaders':[{'title':'Title', 'sortField':'filename'}, {'title':'Type'}, {'title':'File Count', 'sortField':'count'}],
      'columnConfig':[{'render':self.titleCell }, {'render':self.messageType }, {'render':self.messageCountCell }],
    };
    const dtOptions = {
      'selectedBasketEntrySize':25,
      'searchableFields':['abbreviation'],
      'orderByField':'filename'
    };
    self.rowBasketTable = new RowBasketDataTable(dtSettings, dtOptions);

  }, // end loadDataTable()

  titleCell: function(groupKey, i, posts) {
    const filename = posts[0]['filename'];
    return '<a href="admin.php?page=vintillect-importer&tab=chatmedia&cname='+ filename.replace('.json', '') +'">'+ posts[0]['abbreviation'] +'</a>';
  },
  messageType: function(groupKey, i, posts) {
    return posts[0]['type'];
  },
  messageCountCell: function(groupKey, i, posts) {
    return posts[0]['count'];
  }

}; // end viChatMediaObj

viChatMediaObj.load(viChatMediaObj, window['vi_posts_var']['dataUrl']);
