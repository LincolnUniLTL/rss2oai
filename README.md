RSS2OAI
======
RSS2OAI is a PHP script that converts a Wordpress RSS feed to a minimal OAI feed. 

**NOTE:** This is no longer maintained. We last used it successfully on PHP 7.2. There are (probably a number of!) compatibility issues with PHP 8.x. We're happy for people to fork and update it.


Caveats
-------

A. The WordPress RSS feed only stores:

1. for each item, when it was published; and
2. for the feed overall, the last time any of its items was edited.

This means when your OAI feed is called with a from argument, you can configure it to return either:

1. only items first published since that from date ($useBuildDate=false); or
2. every item as long as at least one item has been edited since the from date, otherwise nothing ($useBuildDate=true).


B. Arguments other than the following will be *ignored*:
 * verb
 * metadataPrefix
 * from

 
C. Verbs other than the following will get an *error message*:
 * Identify
 * ListMetadataFormats
 * ListRecords


D. It may also work for other RSS feeds but this has not been tested.
 

Configuration
-------------
At the top of the script, configure:

* the URL of the RSS feed you want to use as a source. The code only supports one RSS feed to one OAI feed, so if you have multiple RSS sources you'll need multiple copies of the code.

* your admin email - this will be published in the OAI feed's Identity response

* if desired, one or more text statements to be included as dc.rights fields. If not desired, leave as an empty array

* how you want the feed to deal with $from dates

* whether the page itself is served over https


Use-case: indexing a WordPress site in Primo
--------------------------------------------

1. You quite likely want to include pages as well as posts: in this case you'll need the WordPress plugin "RSS Includes Pages" http://infolific.com/technology/software-worth-using/include-pages-in-wordpress-rss-feeds/

2. Place index.php on your own php-enabled server and configure it as above. If the feed is just a blog and past posts are rarely edited then $useBuildDate should be false. But if you want Primo to pick up all post/page edits then $useBuildDate must be true.

Now in Primo Back Office:

3. add a new source (Local Data > Data Sources > Add a New Data Source) using system=Other; format=DC; file-splitter=OAI splitter; record path=oai_dc:dc; character set=UTF-8

4. create a normalisation set (Local Data > Normalization Sets) - it's easiest to duplicate from an existing OAI-based norm set if you have one, otherwise just make sure you at least deal with: dc:type; dc:identifier; dc:date; dc:title; dc:creator; dc:subject; dc:description; dc:publisher; dc:rights.  Note that there'll be two copies of dc:description. The first is a 'blurb' which you may want to display on the details tab; the second is the full-text of the post/page which you may want to index for searching. We additionally included a links:thumbnail rule, and a ranking:booster1 rule.

5. create a line in the delivery mapping table "GetIT! Link 2 Configuration" (General > Mapping Tables) for your data source code with: Online Resource; not_restricted; display; linktorsrc. This suppresses the "GetIt" link that would otherwise appear.

6. Deploy & Utilities > Deploy All

7. create a new regular pipe (Publishing > Create New Pipe) with harvesting method=OAI; metadata format=oai_dc; server=the url of your index.php

8. run your pipe, wait a 12-24 hours for indexing, and test.

9. when you're happy with display / search, schedule your pipe to run daily (Publishing > Scheduler).

An example record in our Primo instance is at http://primo-direct-apac.hosted.exlibrisgroup.com/LIN:All_resources:LTL5160 - also try searching for "library hours", "careers", "databases"
