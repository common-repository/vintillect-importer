<?php
namespace VintillectImporter\X2wp;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class X2wp_Posts_View {

  public function display_view($hasAllowedImportsOption, $configSettings) {
    ?>
      <div id="posts-view">
        <div id="date-filters">
          <div class="row">
            <div class="col-md-2">
              <label for="group-select">Merge Rows By</label>
              <select id="group-select">
                <option value="timestamp">No Merge</option>
                <option value="date-iso">Daily</option>
                <option value="week-of-year">Weekly</option>
                <option value="month-of-year">Monthly</option>
                <option value="tag">Tag</option>
                <option value="thread">Thread</option>
                <option value="user-mentioned">User Mentioned</option>
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
              <label><input type="checkbox" id="import-tags" /> Import Tags</label>
            </div>
            <!-- <div class="col-md-1">
              <label for="twitter-filter">Filter</label>
              <select id="twitter-filter">
                <option value="">None</option>
                <option value="tag">Tag</option>
                <option value="screen_name">Screen Name</option>
              </select>
            </div>
            <div class="col-md-2">
              <div id="twitter-filter-value-wrapper">
                <label for="twitter-filter-value">on</label>
                <select id="twitter-filter-value">
                  <option value="">Select</option>
                </select>
              </div>
            </div> -->
            <div class="col-md-1">
              <label for="status-select">Post Status</label>
              <select id="status-select">
                <option value="draft">Draft</option>
                <option value="publish">Publish</option>
              </select>
            </div>
            <div class="col-md-3">
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

        <div id="posts-table-wrapper"></div>

        <div id="loading-spinner" class="text-center"><div class="spinner-border text-primary" role="status"></div></div>
      </div><!-- end #posts-view -->
    <?php
  }

}
