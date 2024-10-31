=== Post-Ticker ===
Contributors: bcbccouk
Donate link: http://www.bcbc.co.uk/wpbcbc/?page_id=649
Tags: post list, ticker, scrolling, rss, rss2, news feed, comments feed
Requires at least: 2.0
Tested up to: 2.7
Stable tag: 1.3.2

Inserts a scrolling text banner (or ticker) with Entries or Comments RSS feeds, or a more selective list of posts

== Description ==
Displays a scrolling list of post titles and excerpts with links to post.

Version 1.1.0 of this plugin allows usage of the Wordpress Entries and Comments RSS2 feeds for the ticker post list. For a more flexible post list configurable options include selecting the type of posts to display:
1) Most Popular (via Wordpress.com Stats Plugin)
2) Most Commented
3) Most Recent
4) User Specified,
and filters for specific categories and total numbers of posts to display. Excerpts are automatically created for those posts which do not already have one.

Version 1.3.0 now allows for changing the ticker speed.

== Installation ==
This section describes how to install the plugin and get it working.

1. Upload `post-ticker` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Configure options via the Settings>Post-Ticker Menu
1. Place `<?php insert_ticker(); ?>` in your templates where you want the ticker to appear

== Frequently Asked Questions ==
= Is the style of the ticker customizable? =
Not currently through the Admin page but the plugin source is easily modified to do this; just add inline styles to the `ticker_content()` function.
= Can I add more than one ticker with different content to my blog =
The plugin is set up to be fully managed through the Admin interface and with a simple insertion tag, so no. Advanced users may wish to edit the plugin source (specifically the `insert_ticker()` and `ticker_contents()` functions) to allow multiple, different tickers. 

== Screenshots ==

1. The plugin active on authors website.
