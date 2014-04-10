<?php
/*
Plugin Name: Disqus Sync Comment Count
Plugin URI: http://presslabs.com/
Description: The plugin tracks the number of Disqus comments per post and enables loading the comment number via AJAX calls at page init.
Author: PressLabs
Version: 1.0
Author URI: http://pesslabs.com/
*/


function sync_comment_count( $comments ) {
    global $wpdb;

    // user MUST be logged out during this process
    wp_set_current_user(0);

    // Establish the path to the comment count JSON file.
    $filename = "disqus-comment-count.json";
    $filepath = dirname( __FILE__ ) . "/$filename";

    $comment_count = file_get_contents( $filepath );
    if ( strlen( $comment_count ) == 0 ) {
        $comment_count = array();
    } else {
        $comment_count = json_decode( $comment_count, true );
    }

    // Get the thread IDs for the selection of post IDs from database.
    $thread_ids = array();
    foreach ( $comments as $comment ) {
        if ( ! isset( $thread_ids[ $comment->thread->id ] ) ) {
            $thread_ids[ $comment->thread->id ] = 0;
        }
    }

    if ( count( $thread_ids ) != 0 ) {
        $thread_ids = "'" . implode( "', '", array_keys( $thread_ids ) ) . "'";

        $results = $wpdb->get_results( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = 'dsq_thread_id' AND meta_value IN ({$thread_ids})" );
        foreach ( $results as $result ) {
            $post_comments_count = wp_count_comments( $result->post_id );
            $comment_count[ $result->post_id ] = $post_comments_count->approved;
        }
        unset( $thread_ids );
        unset( $result );

        file_put_contents( $filepath, json_encode( $comment_count ) );
    }
}
add_action( 'dsq_after_sync_comments', 'sync_comment_count', 1, 1 );


function get_synched_dsq_comment_count() {
    $POST = file_get_contents( 'php://input' );
    if ( $POST !== '' ) {
        // Establish the path to the comment count JSON file.
        $filename = "disqus-comment-count.json";
        $filepath = dirname( __FILE__ ) . "/$filename";
        $comment_count = file_get_contents( $filepath );
        $comment_count = json_decode( $comment_count, true );
        $request_post_ids = json_decode( $POST, true );
        $response_data = array();
        foreach ( $request_post_ids['post_ids'] as $index => $request_post_id ) {
            $response_data[ "$request_post_id" ] = $comment_count[ "$request_post_id" ];
        }
        echo json_encode( $response_data );
    }
    exit( 0 );
}
add_action( 'wp_ajax_dsq_comment_count', 'get_synched_dsq_comment_count' );
add_action( 'wp_ajax_nopriv_dsq_comment_count', 'get_synched_dsq_comment_count' );


function the_dsq_synched_comment_count( $post_id ) {
    // Define the post ID tag to look for.
    define( 'THE_POST_ID_TAG', '%POST_ID%' );
    // Establish the path to the comment count JSON file.
    $filename = "disqus-comment-count-template.html";
    $filepath = dirname( __FILE__ ) . "/$filename";
    $comment_count_html_template = file_get_contents( $filepath );
    // Return the HTML with tags replaced by corresponding values.
    echo str_replace( THE_POST_ID_TAG, $post_id, $comment_count_html_template );
}



function the_dsq_comment_count_style_and_script() {
    // Establish the path to the comment count JSON file.
    $filename = "disqus-comment-count-style.css";
    $filepath = dirname( __FILE__ ) . "/$filename";
    $comment_count_template_style = file_get_contents( $filepath );
    echo '<style type="text/css">';
    echo $comment_count_template_style;
    echo '</style>';
?>
    <script type="text/javascript">
        function get_comment_count() {
            var comment_count_divs = document.getElementsByClassName( "dsq_comment_count_box" );
            if ( comment_count_divs.length > 0 ) {
                var post_ids = new Array();
                for ( var index = 0; index < comment_count_divs.length; index ++ ) {
                    var comment_count_div = comment_count_divs.item( index );
                    var comment_count_div_id = comment_count_div.getAttribute( 'id' );
                    // Extract the post ID from the div ID and push it to the array of post IDs.
                    post_ids.push( comment_count_div_id.substr( comment_count_div_id.lastIndexOf( '_' ) + 1 ) );
                }
                if ( post_ids.length != 0 ) {
                    var xmlhttp = new XMLHttpRequest();
                    xmlhttp.open( "POST", "<?php echo admin_url( 'admin-ajax.php' ); ?>?action=dsq_comment_count" );
                    xmlhttp.setRequestHeader( "Content-Type", "application/json;charset=UTF-8" );
                    xmlhttp.send( JSON.stringify( { "post_ids" : post_ids } ) );
                    xmlhttp.onreadystatechange = function() {
                        if ( ( xmlhttp.readyState == 4 ) && ( xmlhttp.status == 200 ) ) {
                            var posts_comment_counts = JSON.parse( xmlhttp.responseText );
                            for ( var post_id in posts_comment_counts ) {
                                var comment_count_div_nodes = document.getElementById( "dsq_comment_count_" + post_id ).childNodes;
                                for ( var node_index = 0; node_index < comment_count_div_nodes.length; node_index ++ ) {
                                    // Check if the element has the 'dsq_comment_count_value' class (it might have other classes, as well)
                                    if ( comment_count_div_nodes[ node_index ].nodeName.toLowerCase() == "span" ) {
                                        var className = " " + comment_count_div_nodes[ node_index ].getAttribute( "class" ) + " ";
                                        if ( className.indexOf( " dsq_comment_count_value " ) > -1 ) {
                                            if ( posts_comment_counts[ post_id ] == 0 ) {
                                                comment_count_div_nodes[ node_index ].innerHTML = "";
                                            } else if ( posts_comment_counts[ post_id ] == 1 ) {
                                                comment_count_div_nodes[ node_index ].innerHTML = "1 comment";
                                            } else  {
                                                comment_count_div_nodes[ node_index ].innerHTML = posts_comment_counts[ post_id ] + " comments";
                                            }
                                        }
                                    }
                                }
                            }
                        }        
                    }
                }
            }
        }
        window.onload() = function() {
            get_comment_count();
        }
    </script>
<?php
}


function the_dsq_comment_count_script_call() {
?>
    <script type="text/javascript">
        get_comment_count();
    </script>
<?php
}


function init_json_file() {
    global $wpdb;

    // Establish the path to the comment count JSON file.
    $filename = "disqus-comment-count.json";
    $filepath = dirname( __FILE__ ) . "/$filename";

    $comment_count = array();
    $results = $wpdb->get_results( "SELECT ID FROM $wpdb->posts WHERE post_type='post' AND post_status='publish'" );
    foreach ( $results as $result ) {
        $post_comments_count = wp_count_comments( $result->ID );
        $comment_count[ $result->ID ] = $post_comments_count->approved;
    }
    unset( $thread_ids );
    unset( $result );

    file_put_contents( $filepath, json_encode( $comment_count ) );
}
register_activation_hook( __FILE__, 'init_json_file' );