<?php
namespace VintillectImporter\X2wp;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class X2wp_ChatMedia_View {

  public function display_view($hasAllowedImportsOption, $configSettings) {
    if (isset($_GET['cname'])) {
      ?>
      <div id="chatmedia-view">
        <div id="date-filters">
          <div class="row">
            <div class="col-md-2">
              <label for="group-select">Merge Rows By</label>
              <select id="group-select">
                <option value="timestamp">No Merge</option>
                <option value="title">Gallery Title</option>
                <option value="date-iso">Daily</option>
                <option value="week-of-year">Weekly</option>
                <option value="month-of-year">Monthly</option>
              </select>
            </div>
            <div class="col-md-2">
              <label for="date-formats">Title Date Formats</label>
              <select id="date-formats">
                <option value="ddd MMM D, YYYY hh:mm A">Sun Jul 23, 2023 2:45 PM</option>
                <option value="ddd D MMM, YYYY hh:mm A">Sun 23 Jul, 2023 2:45 PM</option>
                <option value="MM/DD/YYYY hh:mm A">07/23/2023 2:45 PM</option>
                <option value="MM/DD/YYYY HH:mm">07/23/2023 14:45</option>
                <option value="M/D/YYYY hh:mm A">7/23/2023 2:45 PM</option>
                <option value="D/M/YYYY hh:mm A">23/7/2023 2:45 PM</option>
                <option value="DD/MM/YYYY hh:mm A">23/07/2023 2:45 PM</option>
                <option value="DD/MM/YYYY HH:mm">23/07/2023 14:45</option>
                <option value="YYYY-MM-DD HH:mm:ss">2023-07-23 14:45:00</option>
              </select>
            </div>
            <div class="col-md-2">
            </div>
            <div class="col-md-2">
              <label for="status-select">Default Post Status</label>
              <select id="status-select">
                <option value="draft">Draft</option>
                <option value="publish">Publish</option>
              </select>
            </div>
            <div class="col-md-4">
              <button type="button" id="post-all-mass-upload-btn" class="btn btn-sm btn-outline-danger">Import All Checked Rows</button>
              <select id="which-pages">
                <option value="page">this page only</option>
                <option value="all">all pages</option>
              </select>
              <div id="mass-upload-progress-wrapper">
                <div id="mass-upload-progress-text"></div>
                <progress id="mass-upload-progress-bar" value="0" max="100"></progress>
              </div>
            </div>
          </div><!-- end .row -->
        </div><!-- end #date-filters -->

        <div id="chatmedia-table-wrapper"></div>
      </div><!-- end #chatmedia-view -->

      <div id="loading-spinner" class="text-center"><div class="spinner-border text-primary" role="status"></div></div>

      <!-- Modal -->
      <div class="modal fade" id="media-modal" tabindex="-1" role="dialog" aria-labelledby="media-modal-title" aria-hidden="true">
        <div class="modal-dialog" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="media-modal-title">Picture / Video / Audio</h5>
              <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
            <div class="modal-body">
              ...
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <!-- <button type="button" class="btn btn-primary">Save changes</button> -->
            </div>
          </div>
        </div>
      </div>
      <?php
    }
    else {
      ?>
      <div id="chatmedia-view">
        <div id="chatmedia-table-wrapper"></div>
      </div>
      <?php
    }
  }

}
