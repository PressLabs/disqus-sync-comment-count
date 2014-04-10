disqus-sync-comment-count
=========================

The plugin handles synchronization of Disqus comment count for delivering to frontend via AJAX at page load.
The plugin is called from the Disqus WordPress Comment System plugin, each time comments are synchronized to the WP database.
At synchronization, the plugin runs a database comment count for each post ID for which new comments have been received.
The plugin keeps records of all post IDs and corresponding comment count in a JSON file, which is later queried for the comment counts, as necessary.

Configuration:
- in WordPress Disqus Comment System plugin, in ./disqus.php, place the following hook in the body of the function 'dsq_sync_comments' at the point where the processing of the new comments is complete:
        do_action( 'dsq_after_sync_comments', $comments );
- install and activate the disqus-sync-comment-count plugin. At initialization, the plugin performs an initial comment count on the post IDs corresponding to all published posts, thus initializing the JSON file.