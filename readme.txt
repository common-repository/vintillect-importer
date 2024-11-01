=== Vintillect Importer ===
Contributors: vintillect
Tags: facebook, twitter, tweet, social
Requires at least: 6.0
Tested up to: 6.5.3
Stable tag: 2.0.8
Requires PHP: 7.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Creates blog posts from Facebook and Twitter (X) posts. Fast import of text, images, and videos. Includes filtering, sorting, and grouping.

== Description ==
With this plugin, you can upload your personal Facebook or Twitter data file to be processed, and then import all of your posts into your blog. You may also download your pictures and videos too. Since you likely have hundreds of posts, this plugin provides filtering, sorting, and grouping of posts.

This plugin provides very useful previews that make your blog roll a bit more like Twitter. This makes it so that your blog roll isn't just a wall of text. Instead, it will be decorated with preview images for each post. Your links to other websites can have preview images. If you have multiple images in a post, a collage preview image will be created for you.

After installation and activation, go to Vintillect Importer.

[Contact me for any questions, comments, or suggestions.](https://vintillect.com/contact.php)

**Service Domains and Third Party External Services**
This plugin will retrieve extra data from [vintillect.com](https://vintillect.com/vintillect-importer/) after you enter your upload ID. It can let you know the status of the processing and import your configuration file for this plugin to work correctly.  [Vintillect's Privacy Policy](https://vintillect.com/privacy.html) and [Terms of Service](https://vintillect.com/tos.html)
Your uploaded zip files will be uncompressed onto an [Amazon S3 cloud storage domain](https://x2wp-processed.s3.amazonaws.com) in which special URLs will grant only your plugin access to the files such as images and videos. Additionally, Vintillect uploaded processed data files which are read into the plugin tables for easy viewing and filtering.
[AWS Service Terms](https://aws.amazon.com/service-terms/) and [AWS Privacy Notice](https://aws.amazon.com/privacy/)

**Privacy**
Your privacy will be respected. When you upload your data file, it goes straight to AWS S3 cloud storage and decompressed there. A few data files are automatically processed on cloud servers that transform them into formats that can be used by this plugin and those files are moved back into cloud storage, and those are only accessible by a special URL known to this plugin.
[Vintillect's Privacy Policy](https://vintillect.com/privacy.html) and [Terms of Service](https://vintillect.com/tos.html)

After you upload your data file, you will be provided an upload ID that allows only this plugin on your WordPress site access to import your data. After 7 days, it will be deleted to avoid the possibility of hackers gaining access to it. We only keep your name, email, upload ID, and logs to check if there was an error when formatting the data that is needed for importing into your blog. Your email will not be used for email lists or given to anyone else.

We ask that you also respect the privacy of others as well. Use discretion when deciding to upload media that was shared to you in Twitter. Get their permission before importing if you think they may not want those images or videos published in your blog. [Your blog could get suspended if you post privately shared media without getting consent.](https://wordpress.com/support/user-guidelines/)

**Optional Purchases**

Importing your posts, notes, groups, and saved items are free. Media (photo and video albums, messenger chat media) are available to import with a purchase. This helps us to pay for cloud storage and processing costs.

== Important ==
Pay attention to the Settings tab where it shows the space used vs space available. WordPress has a maximum limit of 1 GB space for free users. When you are importing many large media files, space can get used up very quickly. [Click here](https://wordpress.com/support/space-upgrade/) if you are hosted on WordPress.com and you think you may need more storage space.

Use a personal computer or laptop for this process. The personal data file can be very large and a mobile device may not be best for downloading and uploading it.

== Screenshots ==
1. Control over how so many posts are imported
2. Filter by date range and search query
3. Add images and videos from multiple posts grouped into single posts
4. Merge posts by time period (daily, weekly, monthly)

== Installation ==
**First: Download your personal data file**
[Facebook Data File Download Instructions](https://vintillect.com/vintillect-importer/fb2wp/#data-download)
[Twitter Data File Download Instructions](https://vintillect.com/vintillect-importer/x2wp/#data-download)

The file will likely be very large, maybe even 3 gigabytes, so be sure to download it onto a harddrive with enough space.

**Second: Getting Started with This Plugin**
1. Install the plugin via the *Plugins -> Add Plugins* page and activate.
2. Go to the Settings page through the left sidebar Vintillect Importer -> X2WP (or FB2WP).
3. On the form, enter your name and email. Upload your Facebook or Twitter data file.
4. Wait for an email that confirms it is ready for review. It takes a while for the cloud server to process the data file. It will be worth your patience.
5. Refresh the Settings page.
6. Click any of the other tabs at the top to review your posts and media.

== Changelog ==
= 2.0.8 =
* Normal font weight for date titles.
* New banner images.

= 2.0.6 =
* Fix photo upload and image assets bugs.
* Separate buttons to create gallery posts and regular vertical media posts.

= 2.0.4 =
* Compatible with vintillect.com landing page forms.
* New logo.

= 2.0.3 =
* Major refactoring for updating config instead of multiple field options.
* WordPress plugin compliance fixes.
* Moved upload form to Vintillect site.
* Merge AWS domains for processed data.
* Restrict media URL base to guard against XSS.

= 1.1.0 =
* [New Feature] Discounted media import for back-link ads.
* Merged FB2WP and X2WP importers into one plugin: Vintillect Importer.
