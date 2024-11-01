// replacement for datatables.js
// requires jQuery
// provides classes for use with Bootstrap CSS styling
// fixes the problem of rowGroup being cut off by page

class RowBasketDataTable {
  #tableWrapperId; #tableBodyId; #entriesPerPageDropdownId; #searchTextId; #showingEntryCountsId; #paginationId; #data = []; #firstRow;
  #columnHeaders = []; // array of [{title:'html', sortField:'optional-fieldname'}]
  #columnConfig = []; // array[ { render(records, index){} } ] requires render() in each column
  #selectedBasketEntrySize = 25;
  #currentPageIdx = 0;
  #totalPages = 0; #totalGroups = 0;
  #searchableFields = []; // array
  #dateField = null; #minDate = null; #maxDate = null;
  #searchQuery = ''; #groupingField = ''; #orderByField = '';
  #groupingFunction = null;
  #preFilterFunction = null;
  #currentPageGroupKeys = []; // array [groupKey]
  #metaGroupRecords = []; // associative groupKey:['checked':false]
  #baskets = [];
  #basketSortedKeys = [];
  #dateRangePicker;
  #orderByDirection = 'ASC';
  #dateFormat = 'D, M d y';
  #downTriangleIcon = '&#x25bc;';
  #upTriangleIcon = '&#x25b2;';


  constructor(settings, options) {
    // do validations and set constants and variables before returning self object; return nothing when validation fails
    if (!('tableWrapperId' in settings)) { console.error('Missing required setting: tableWrapperId'); return; }
    if (!('data' in settings)) { console.error('Missing required setting: data'); return; }
    if (!('groupingField' in settings)) { console.error('Missing required setting: groupingField'); return; }
    if (!('columnHeaders' in settings)) { console.error('Missing required setting: columnHeaders'); return; }
    if (!('columnConfig' in settings)) { console.error('Missing required setting: columnConfig'); return; }

    if (!settings['data'] || settings['data'].length === 0) { console.error('No records in data; must be array of objects (simple records)'); return; }
    if (!settings['columnHeaders'] || settings['columnHeaders'].length === 0) { console.error('No column headers defined'); return; }
    if (!settings['columnConfig'] || settings['columnConfig'].length === 0) { console.error('No column configs defined'); return; }


    const tableWrapperId = this.#tableWrapperId = settings['tableWrapperId'];
    this.#tableBodyId = tableWrapperId+'-tablebody';
    this.#entriesPerPageDropdownId = tableWrapperId+'-entriesperpage';
    this.#searchTextId = tableWrapperId+'-search';
    this.#showingEntryCountsId = tableWrapperId+'-showing-counts';
    this.#paginationId = tableWrapperId+'-pagination';
    this.#data = settings['data'] || [];
    const firstRow = this.#data[0];
    this.#columnHeaders = settings['columnHeaders'] || []; // array of [{title:'html', sortField:'optional-fieldname'}]
    this.#columnConfig = settings['columnConfig'] || []; // array[ { render(records, index){} } ] requires render() in each column
    this.#selectedBasketEntrySize = 25;
    this.#currentPageIdx = 0;
    this.#totalPages = 1;
    this.#totalGroups = this.#data.length || 0;
    this.#searchableFields = []; // array
    this.#dateField = null;
    this.#minDate = null;
    this.#maxDate = null;
    this.#searchQuery = '';
    this.#groupingField = settings['groupingField'];
    this.#orderByField = '';
    this.#currentPageGroupKeys = []; // array [groupKey]
    this.#metaGroupRecords = []; // associative groupKey:['checked':false]

    if (options) {
      if ('selectedBasketEntrySize' in options) { this.#selectedBasketEntrySize = options['selectedBasketEntrySize']; }
      if ('searchableFields' in options && options['searchableFields']) {
        this.#searchableFields = options['searchableFields'] || [];

        for (const field of this.#searchableFields) {
          if (!(field in firstRow)) { console.error('Searchable field (in searchableFields) not found in data row:', field); return; }
        }
      }

      if ('dateField' in options) {
        this.#dateField = options['dateField'];
        if (!(this.#dateField in firstRow)) { console.error('Option dateField not found in data:', this.#dateField); return; }
      }
      if ('orderByField' in options) {
        this.#orderByField = options['orderByField'];
        if (!(this.#orderByField in firstRow)) { console.error('Option orderByField not found in data:', this.#orderByField); return; }
      }

      if ('defaultOrderDirection' in options) { this.#orderByDirection = options['defaultOrderDirection']; }
      if ('searchQuery' in options) { this.#searchQuery = options['searchQuery']; }

    } // if options

    const self = this;

    jQuery(document).ready(function() { // render initial HTML
      let columnHeaderRowHtml = `<th><input type="checkbox" id="${self.#tableWrapperId}-toggle-check-all" /></th>`;
    
      for (const columnHeader of self.#columnHeaders) {
        const extraClass = ('extra' in columnHeader) ? columnHeader['extra'] : '';
        const sortBtn = ('sortField' in columnHeader) ? `<button class="btn btn-primary-outline rowbaskettable-header-sort-btn" value="${columnHeader['sortField']}">${self.#upTriangleIcon}</button>` : '';
        columnHeaderRowHtml += `<th ${extraClass}>${columnHeader['title']} ${sortBtn}</th>\n`;
      }
    
      let entriesPerPageHtml = `\n<label for="${self.#entriesPerPageDropdownId}">Show</label> <select id="${self.#entriesPerPageDropdownId}">`;
      for (const entrySize of [10, 25, 50, 100]) {
        const selected = (entrySize === self.#selectedBasketEntrySize) ? ' selected' : '';
        entriesPerPageHtml += `<option value="${entrySize}"${selected}>${entrySize}</option>`;
      }
      entriesPerPageHtml += `</select> <label for="${self.#entriesPerPageDropdownId}">entries</label>\n`;

      let dateRangeHtml = '';
      if (self.#dateField) {
        const minDateHtml = `<input type="text" name="min-date" placeholder="Minimum Date" />`;
        const maxDateHtml = `<input type="text" name="max-date" placeholder="Maximum Date" />`;
        dateRangeHtml = `<div class="col-md-4" id="${self.#tableWrapperId}-daterange">${minDateHtml} to ${maxDateHtml}</div>`;
      }

      const searchHtml = `<label for="${self.#searchTextId}">Search:</label> <input type="search" id="${self.#searchTextId}" placeholder="filter query" />`;
      const gotoBottomBtn = `<button class="btn btn-outline-info goto-bottom-btn">Go to Bottom &#8681;</button>`;
      const beforeTableHtml = `<div class="row"><div class="col-md-2">${entriesPerPageHtml}</div>${dateRangeHtml}<div class="col-md-3">${searchHtml}</div><div class="col-md-2">${gotoBottomBtn}</div></div>`;
      const rowsHtml = self.#renderRows();
      const tableHtml = `<div class="row"><table class="table table-striped table-hover table-bordered">\n<thead><tr>${columnHeaderRowHtml}</tr></thead>\n<tbody id="${self.#tableWrapperId}-tablebody">${rowsHtml}</tbody></table></div>`;
      const entryCountsHtml = self.#renderShowingEntryCounts();
      const paginationHtml = self.#renderPagination();
      const gotoTopBtn = `<button class="btn btn-outline-info goto-top-btn">Go to Top &#8679;</button>`;
      const afterTableHtml = `<div class="row"><div class="col-md-4" id="${self.#showingEntryCountsId}">${entryCountsHtml}</div><div class="col-md-4" id="${self.#paginationId}">${paginationHtml}</div><div class="col-md-2">${gotoTopBtn}</div></div>`;
      jQuery('#'+self.#tableWrapperId).html(beforeTableHtml + tableHtml + afterTableHtml);
      jQuery('#'+'loading-spinner').hide(); // separated because of sed replacement of #

      // set element events
      jQuery(document).on('click', '#'+self.#tableWrapperId+' .page-link', (e) => self.#clickedPaginationBtn(e));
      jQuery(document).on('click', '.rowbaskettable-header-sort-btn', (e) => self.#clickedSortBtn(e));
      jQuery(document).on('click', '.goto-bottom-btn', (e) => self.#gotoBottomBtn(e));
      jQuery(document).on('click', '.goto-top-btn', (e) => self.#gotoTopBtn(e));
      jQuery(document).on('change', '#'+self.#tableWrapperId+'-toggle-check-all', (e) => self.#toggleAllCheckboxes(e) );
      jQuery(document).on('change', '.'+self.#tableWrapperId+'-check', (e) => self.#toggleOneCheckbox(e) );
      jQuery(document).on('change', '#'+self.#entriesPerPageDropdownId, (e) => self.#changeEntriesPerPage(e) );
      jQuery(document).on('blur', '#'+self.#searchTextId, (e) => self.#updateSearch() );
      jQuery(document).on('keypress', '#'+self.#searchTextId, (e) => {
        if (e.which === 13) { self.#updateSearch(); } // if keypress is Enter key
      });

      if (self.#dateField) {
        const dateRangePickerElem = jQuery('#'+self.#tableWrapperId+'-daterange')[0];
        self.#dateRangePicker = new DateRangePicker(dateRangePickerElem, {'autohide':true, 'format':self.#dateFormat, 'allowOneSidedRange':true});

        jQuery('#'+self.#tableWrapperId+'-daterange input').on('changeDate', function() {
          const dates = self.#dateRangePicker.getDates();
          self.#minDate = dates[0];
          self.#maxDate = dates[1];
          self.#updateSearch();
        });
      }

    }); // end jQuery(document).ready()
  } // end constructor


  setBasketGroupingField(fieldName) {
    this.#groupingField = fieldName;
  }
  setBasketGroupingFunction(fn) {
    this.#groupingFunction = fn;
  }
  setPreFilterFunction(fn) {
    this.#preFilterFunction = fn;
  }
  reRender() {
    jQuery('#'+'loading-spinner').show(); // separated because of sed replacement of #
    let rowHtml = this.#renderRows();
    jQuery('#'+this.#tableBodyId).html(rowHtml);
    jQuery('#'+this.#showingEntryCountsId).html( this.#renderShowingEntryCounts() );
    jQuery('#'+this.#paginationId).html( this.#renderPagination() );
    jQuery('#'+'loading-spinner').hide(); // separated because of sed replacement of #
  }
  setDateFormat(newFormat) {
    this.#dateFormat = newFormat;
    // this.#dateRangePicker.setOptions({'format':newFormat});
  }

  getAllBaskets() {
    return this.#baskets;
  }
  getCurrentPageBaskets() {
    const retArr = [];

    for (const k of this.#currentPageGroupKeys) {
      retArr[k] = this.#baskets[k];
    }

    return retArr;
  }
  getCheckedBaskets() {
    const allIsChecked = jQuery('#'+this.#tableWrapperId+'-toggle-check-all').is(':checked');
    return {'all':allIsChecked, 'groups':this.#metaGroupRecords};
  }


  #renderRows() {
    // data, currentPageIdx, totalRecords, selectedBasketEntrySize, groupingField, searchQuery, searchableFields, minDate, maxDate, dateField, orderByField, orderByDirection
    let preFilteredData = structuredClone(this.#data); // the thread grouping function may mess with the internals of the objects, so it's best to make a clone
    if (this.#preFilterFunction) { preFilteredData = this.#preFilterFunction(preFilteredData); }
    const filtered = this.#filterRows(preFilteredData);
    if (!filtered.length) { return ''; }
    let ordered = filtered;

    if (this.#orderByField) {
      if ((typeof filtered[0][this.#orderByField]) === 'number') {
        ordered = (this.#orderByDirection === 'ASC') ? filtered.sort((a,b) => { return a[this.#orderByField] - b[this.#orderByField]; }) : filtered.sort((a,b) => { return b[this.#orderByField] - a[this.#orderByField]; })
      }
      else {
        ordered = (this.#orderByDirection === 'ASC') ? filtered.sort((a,b) => { return a[this.#orderByField].localeCompare(b[this.#orderByField]); }) : filtered.sort((a,b) => { return b[this.#orderByField].localeCompare(a[this.#orderByField]); })
      }
    }

    this.#baskets = []; // associative groupKey:record
    if (this.#groupingFunction) { this.#baskets = this.#groupingFunction(ordered); }
    else {
      for (const record of ordered) {
        const groupingFieldStr = (this.#orderByField === 'timestamp') ? record[this.#groupingField] : '_' + record[this.#groupingField];
        if (! (groupingFieldStr in this.#baskets)) { this.#baskets[ groupingFieldStr ] = []; }
        this.#baskets[ groupingFieldStr ].push(record);
      }
    }

    this.#basketSortedKeys = Object.keys(this.#baskets);
    if (this.#basketSortedKeys.length) {
      if (this.#orderByDirection === 'DESC' && !isNaN(this.#basketSortedKeys[0])) {
        // if (this.#orderByDirection === 'DESC' && this.#orderByField === 'timestamp')
        this.#basketSortedKeys.reverse();
      }
      else if (this.#groupingFunction) {
        this.#basketSortedKeys = (this.#orderByDirection === 'ASC') ? this.#basketSortedKeys.sort((a,b) => { return a.localeCompare(b); }) : this.#basketSortedKeys.sort((a,b) => { return b.localeCompare(a); })
      }
    }

    this.#totalGroups = this.#basketSortedKeys.length;
    this.#totalPages = Math.ceil(this.#totalGroups / this.#selectedBasketEntrySize);
    const startIdx = this.#selectedBasketEntrySize * this.#currentPageIdx;
    const endIdx = ((startIdx + this.#selectedBasketEntrySize) < this.#totalGroups) ? (startIdx + this.#selectedBasketEntrySize) : this.#totalGroups;
    this.#currentPageGroupKeys = this.#basketSortedKeys.slice(startIdx, endIdx);
    const rowHtmlArr = [];
    const checkedAll = jQuery('#'+this.#tableWrapperId+'-toggle-check-all').is(':checked');

    for (let i=0; i<this.#currentPageGroupKeys.length; i++) {
      const groupKey = this.#currentPageGroupKeys[i];
      const checkFieldIsChecked = (groupKey in this.#metaGroupRecords) ? this.#metaGroupRecords[groupKey].checked : checkedAll;
      const checkFieldChecked = checkFieldIsChecked ? ' checked' : '';
      const checkField = `<input type="checkbox" class="${this.#tableWrapperId}-check"${checkFieldChecked} value="${groupKey}" />`;
      const cellsHtml = [];

      for (const columnDef of this.#columnConfig) {
        const cell = columnDef['render'](groupKey, i, this.#baskets[groupKey]);
        cellsHtml.push(`<td>${cell}</td>`);
      }
      const cellsHtmlJoined = cellsHtml.join('');

      rowHtmlArr.push(`<tr><td>${checkField}</td>${cellsHtmlJoined}</tr>`);
    }

    return rowHtmlArr.join('\n');
  } // end renderRows()


  #renderShowingEntryCounts() {
    const startIdx = this.#selectedBasketEntrySize * this.#currentPageIdx;
    const startRow = startIdx + 1;
    const endRow = ((startIdx + this.#selectedBasketEntrySize) < this.#totalGroups) ? (startIdx + this.#selectedBasketEntrySize) : this.#totalGroups;
    const renderHtml = `Showing ${startRow} to ${endRow} of ${this.#totalGroups} entries`;
    return renderHtml;
  } // end renderShowingEntryCounts()


  #renderPagination() {
    if (this.#totalPages === 1) {
      return `<nav aria-label="table pagination"><ul class="pagination"><li class="page-item active disabled"><button class="page-link" value="1">1</button></li></ul></nav>`;
    }

    const pageLinksHtml = [`<nav aria-label="table pagination"><ul class="pagination">`];

    if (this.#currentPageIdx > 0) {
      // previous button
      pageLinksHtml.push(`<li class="page-item"><button class="page-link" value="previous">Previous</button></li>`);

      if (this.#currentPageIdx > 1) {
        // first button
        pageLinksHtml.push(`<li class="page-item"><button class="page-link" value="0">1</button></li>`);

        if (this.#currentPageIdx > 2) {
          // before between ellipsis
          pageLinksHtml.push(`<li class="page-item disabled"><button class="page-link">...</button></li>`);
        }
      }

      // previous count button
      pageLinksHtml.push(`<li class="page-item"><button class="page-link" value="${this.#currentPageIdx-1}">${this.#currentPageIdx}</button></li>`);
    }

    // current page button
    pageLinksHtml.push(`<li class="page-item active"><button class="page-link" value="current">${this.#currentPageIdx+1}</button></li>`);

    if (this.#totalPages > 2 && this.#currentPageIdx < this.#totalPages-2) {
      // next count button
      pageLinksHtml.push(`<li class="page-item"><button class="page-link" value="${this.#currentPageIdx+1}">${this.#currentPageIdx+2}</button></li>`);

      if (this.#totalPages > 3) {
        // after between button
        pageLinksHtml.push(`<li class="page-item disabled"><button class="page-link">...</button></li>`);
      }
    }

    if (this.#currentPageIdx < this.#totalPages-1) {
      // last button
      pageLinksHtml.push(`<li class="page-item"><button class="page-link" value="${this.#totalPages-1}">${this.#totalPages}</button></li>`);
      // next button
      pageLinksHtml.push(`<li class="page-item"><button class="page-link" value="next">Next</button></li>`);
    }

    const pageListCombined = pageLinksHtml.join('\n');
    const renderHtml = `<nav aria-label="table pagination"><ul class="pagination">${pageListCombined}</ul></nav>`;
    return renderHtml;
  } // end renderPagination()


  #filterRows(preFilteredData) {
    if (!(this.#searchableFields.length && this.#searchQuery) && !(this.#minDate || this.#maxDate)) { return preFilteredData; }
    const filtered = [];
    const minDateGmt = (this.#minDate) ? viCommonObj.formatGmtDateTime(this.#minDate) : '';

    let maxDateGmt;
    if (this.#maxDate) {
      const nextDayMaxDate = new Date(this.#maxDate.getTime() + (86400-10) * 1000);
      maxDateGmt = viCommonObj.formatGmtDateTime(nextDayMaxDate);
    }

    for (const record of preFilteredData) {
      if (minDateGmt && record['date_gmt'] < minDateGmt) { continue; }
      if (maxDateGmt && record['date_gmt'] > maxDateGmt) { continue; }

      const combinedFieldValues = this.#searchableFields.map((searchField) => record[searchField]).join(' ').toLocaleLowerCase();
      if (combinedFieldValues.indexOf(this.#searchQuery.toLocaleLowerCase()) !== -1) {
        filtered.push(record);
      }
    }

    return filtered;
  } // end filterRows()


  #clickedPaginationBtn(e) {
    const searchVal = e.currentTarget.value;

    if (searchVal === 'current') {
      return;
    }
    if (searchVal === 'previous') {
      this.#currentPageIdx -= 1;
    }
    else if (searchVal === 'next') {
      this.#currentPageIdx += 1;
    }
    else {
      this.#currentPageIdx = parseInt(searchVal, 10);
    }

    this.reRender();
  }

  #clickedSortBtn(e) {
    const sortField = e.currentTarget.value;

    if (this.#orderByField === sortField) {
      this.#orderByDirection = (this.#orderByDirection === 'ASC') ? 'DESC' : 'ASC';
    }
    else {
      this.#orderByField = sortField;
      this.#orderByDirection = 'ASC';
    }

    const arrow = (this.#orderByDirection === 'ASC') ? this.#upTriangleIcon : this.#downTriangleIcon;
    jQuery(e.currentTarget).html(arrow);
    this.reRender();
  }

  #gotoBottomBtn(e) {
    window.scrollTo(0, document.body.scrollHeight);
  }
  #gotoTopBtn(e) {
    window.scrollTo(0, 0);
  }

  #toggleAllCheckboxes(e) {
    const checked = e.currentTarget.checked;
    this.#metaGroupRecords = [];

    for (const groupKey of this.#currentPageGroupKeys) {
      this.#metaGroupRecords[groupKey] = {'checked':checked};
    }

    jQuery('.'+this.#tableWrapperId+'-check').prop('checked', checked);
  }

  #toggleOneCheckbox(e) {
    const groupKey = e.currentTarget.value;
    const checked = e.currentTarget.checked;
    this.#metaGroupRecords[groupKey] = {'checked':checked};
  }

  #changeEntriesPerPage(e) {
    this.#selectedBasketEntrySize = parseInt( e.currentTarget.value, 10 );
    this.reRender();
  }

  #updateSearch() {
    let doUpdate = false;

    if (this.#dateField) {
      const newDates = this.#dateRangePicker.getDates();
      const newMinDate = newDates[0];
      const newMaxDate = newDates[1];

      if (newMinDate !== this.#minDate) { this.#minDate = newMinDate; doUpdate = true; }
      if (newMaxDate !== this.#maxDate) { this.#maxDate = newMaxDate; doUpdate = true; }
    }

    const newSearchQuery = '' + jQuery('#'+this.#searchTextId).val();
    if (newSearchQuery !== this.#searchQuery) { this.#searchQuery = newSearchQuery; doUpdate = true; }

    if (doUpdate) {
      this.reRender();
    }
  }

}
