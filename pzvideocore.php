<?php
/**
 * Plugin Name:       Pzvideocore
 * Description:       PeakZebra VideoHelp core tables and includes for REST api. Activate this before installing/activating other
 *                    PZ plugins.
 * Requires at least: 6.1
 * Requires PHP:      7.0
 * Version:           1.0.4
 * Author:            Robert Richardson
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pzcore
 *
 * @package           pzcore
 *
 */

 /**
  * 1.0.4 -- 5/28/24 preserve prod and ver when incoming url was from magic key email
  * 1.0.3 -- 5/27/24 added pz_toc
  * 1.0.2 -- 5/21/24 Add is_published field to pz_product table
  * 1.0.1 -- 5/17/24 Preserve 'prod' and 'ver' parms if present in URL
  * 1.0.0 -- 5/15/24 Bumped version number
  * 0.1.5 -- added pz_toc table for part/section/subsection
  * 0.1.4 -- bumped version for multi-product
  */
  
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * This plugin contains several functions for the "videohelper" website blueprint. 
 * These are used in conjunction with the additional plugins pzdomaingrid, pzmagicform, and pzvideoform
 * 
 * This file includes:
 * 1. pz_auto_login -- logs current user in as guest, so they'll have continued access
 * 2. pz_login_redirect -- Redirect users with editor or administrator role to the curator dashboard page
 * 3. pz_check_access -- Check whether we can let a requesting entity view a particular page.
 * 
 * There are two places where we intervene in the process of delivering a specific page that's been requested.
 * These are 1) at login and 2) at template_redirect
 * 
 * At login, we merely check to see if the user is an admin or a vid-curator. If either, they are
 * redirected to the admin page.
 * 
 * At template_redirect, we process the various possible parameters to derive a target URL, and then we 
 * redirect to that URL. The relevant function is pz_check_access()
 */

// some constants
define( 'PZ_NONCE_DURATION', "+24 hours" ); 
define( 'PZ_CURATOR_DASHBOARD', "/curator-dashboard");
define( 'PZ_MAGIC_KEY_PAGE', "/magic");

// hook to create the two tables, one for access tokens, one for whitelisted domains
register_activation_hook(
	__FILE__,
	'pz_onActivate'
);

// register hooks for processing submission of the new video form
add_action('admin_post_do-video-form', 'do_video_form');
add_action('admin_post_nopriv_do-video-form', 'do_video_form');


/**
 * Extend time before automatic logout to 1 month
 */
function keep_me_logged_in_for_1_month( $expirein ) {
  return 2592000; // 1 month in seconds
  }
add_filter( 'auth_cookie_expiration', 'keep_me_logged_in_for_1_month' );

/** 
 * Automated guest user login - if someone presents a valid token, we log them in so 
 * we don't have to keep checking. 
 */
function pz_auto_login() {
  if (!is_user_logged_in()) {
      //determine WordPress user account to impersonate
      $user_login = 'guest';

     //get user's ID
      $user = get_user_by( 'login', $user_login );
      if( !$user ) {
        echo "Problem with user account " . $user_login;
        exit;
      }
      $user_id = $user->ID;

      
      //login
      $user_return = wp_set_current_user($user_id, $user_login);
      if( !is_object( $user_return )) {
        echo "Problem with setting current user.";
        exit;
      }
      wp_set_auth_cookie($user_id);
      $creds = array(
        'user_login'    => 'guest',
        'user_password' => 'XTK59anadjOaRGgT(E^EMpdD',
        'remember'      => true
      );
    
      $user = wp_signon( $creds, false );
    
      if ( is_wp_error( $user ) ) {
        echo $user->get_error_message();
        exit;
      }
  }
}

/** 
 * Redirect users with editor role to the curator dashboard page
 */
function pz_login_redirect( $url, $request, $user ) {
  if ( $user && is_object( $user ) && is_a( $user, 'WP_User' ) ) {
      if ( $user->has_cap( 'administrator' ) ) {
          $url = PZ_CURATOR_DASHBOARD;
      } else if( $user->has_cap( 'editor' )) {
        $url = PZ_CURATOR_DASHBOARD;
      }
      else {
          // $url = '/' ; 
      }
  }
  return $url;
}

add_filter( 'login_redirect', 'pz_login_redirect', 10, 3 );

/**
 * Check Access
 * Check whether we can let a requesting entity view a particular page.
 * Hook just before page loaded -- either we let them through (by returning)
 * --or else we redirect and they don't go to the page. 
 */
add_action( "template_redirect", "pz_check_access" );
function pz_check_access() {
  global $wp;
  
  //      if the user is logged in, then we don't need to worry about the token 
  //      parameter. We need to assemble the URL from qurl and anchor parameters.
  //      
  //      Since a request like this will have already determined the correct page
  //      slug for this product and version, we don't have to determine that. 


$current_url = home_url(add_query_arg(array(), $wp->request));

if( is_user_logged_in()) { 
  
  if( isset($_GET['qurl']) || isset($_GET['anchor'])) {
      
    if( isset($_GET['qurl']) && $_GET['qurl'] != '') {
      $redirect_url = "/" . sanitize_url($_GET['qurl']) . "/";
    } else $redirect_url = "/";
    if( isset($_GET['anchor'])) {
        $redirect_url = $redirect_url . trailingslashit($redirect_url) . "#" . $_GET['anchor'];
    }
    if( isset($_GET['prod'])) {  // preserve prod (product) parameter if it was used
      $redirect_url = $redirect_url . trailingslashit($redirect_url) . "?prod=" . $_GET['prod'];
    }
    if( isset($_GET['ver'])) {  // and preserve ver (version) parameter if used
      $redirect_url = $redirect_url . "&ver=" . $_GET['ver'];
    }

      wp_redirect($redirect_url);
      exit;
  }
  return;
}
      
  // }  else {  // logged in but without qurl and anchor parms 
  //     if( isset($_GET['prod'])) {  // preserve p (product) parameter if it was used
  //       $current_url = $current_url . trailingslashit($current_url) . "?prod=" . $_GET['prod'];
  //     }
  //     if( isset($_GET['ver'])) {  // and preserve v (version) parameter if used
  //       $current_url = $current_url . "&ver=" . $_GET['ver'];
  //     }
  // }
  // wp_redirect($current_url);
  // exit;



// otherwise, this isn't a logged-in user. 
// if there's an access token, check if valid and, if valid, log user in
if( isset($_GET['token'])) {
  
  if(pz_test_token( $_GET['token'] )) { 
    // log user in as guest
    pz_auto_login();
  } else {
    //    if not a valid token, redirect to request email to send magic key to, 
    //    preserving the URL and any anchors. 
    $redirect_url = PZ_MAGIC_KEY_PAGE . "/?q=" . $q . "&anchor=" . $anchor;
    if( isset($_GET['prod'])) {  // preserve prod (product) parameter if it was used
      $redirect_url = $redirect_url . "&prod=" . $_GET['prod'];
    }
    if( isset($_GET['ver'])) {  // and preserve ver (version) parameter if used
      $redirect_url = $redirect_url . "&ver=" . $_GET['ver'];
    }
    wp_redirect($redirect_url);
    exit;
  }

  
    // if we received the url as parameters, we need to reassembled that and redirect --
    // this would be the page the user was attempting to access before a magic key link
    // was required
    if( isset($_GET['qurl']) || isset($_GET['anchor'])) {
      
      if( isset($_GET['qurl']) && $_GET['qurl'] != '') {
        $redirect_url = "/" . $_GET['qurl'] . "/";
      } else {
        $redirect_url = "/";
        if( isset($_GET['anchor'])) {
          $redirect_url = $redirect_url . "#" . $_GET['anchor'];
        }
      }
      // add prod and ver to end of url
      if( isset($_GET['prod'])) {
        $redirect_url .= "/?prod=" . $_GET['prod'];
      }
      if( isset($_GET['ver'])) {
        $redirect_url .= "&ver=" . $_GET['ver'];
      }
        wp_redirect($redirect_url);
        exit;
      
    }
    
    return;
  
}

  // if we're not logged in and we don't have a valid token, then we need to offer a magic key email
  // to be nice, we'd like to store the full url they were _trying_ to get to and put it in the magic key link
  // we're using add_query_arg "off label" to get the current arg, without adding anything
  $q = add_query_arg( array(), $wp->request );
  if( isset($_GET['anchor'])) {
    $anchor = $_GET['anchor'];
  } else $anchor = '';
  

  
  // if we're headed to a 'magic' page (because we just redirected above, but this is a new page request now)
  if( $q == 'magic' ) return;
  if( $q == 'magic-sent') return;

  // any other value of $q or $anchor should be passed to the form on magic page, so we
  // can preserve it in the magic link we send to the user. That way, when they 
  // click in the email, they'll be taken to the page they were originally trying
  // to access.

  $redirect_url = PZ_MAGIC_KEY_PAGE . "/?q=" . $q . "&anchor=" . $anchor;
  wp_redirect($redirect_url);
  exit;
}

/**
 * VIMEO stuff
 * 
 */

// Call to Vimeo API for HTML to embed video viewer
function pz_get_embed( $video_url ) {
  $video_url = urlencode( $video_url );
  $request_url = "https://vimeo.com/api/oembed.json?url=" . $video_url; 
  $response = wp_remote_get( $request_url );
  $body = $response['body'];
 
  return $body;
}


function pz_get_thumbnail( $video_url ) {
  global $other_id;
  $p = strrpos( $video_url, '\/' );
  $video_id = substr( $video_url, $p+1 );
  $url = "https://api.vimeo.com/videos/" . $video_id . "/pictures";
  // $url = "https://api.vimeo.com/videos/878789429/pictures";
  $args = array(
    'headers' => array(
        'Authorization' => 'bearer ' . $other_id
    )
  );
  $response = wp_remote_get( $url, $args );
  $body = $response['body'];
  $start = strpos($body,'"width":640');
  $stop = strpos( $body, 'https', $start );
  $start = $stop;
  $stop = strpos( $body, 'r=pad', $start);
  $len = $stop - $start + 5;
  $thumb_url = substr($body, $start, $len );
  return $thumb_url;
}


/**
 * Create table where tokens will be stored before expiring
 */
function pz_onActivate() {
  global $wpdb;
  require_once( ABSPATH . 'wp-admin/includes/upgrade.php');

  $charset = $wpdb->get_charset_collate();
  $table_str = $wpdb->prefix . "pz_token";

  // create or update structure of pz_token table. 
  dbDelta("CREATE TABLE $table_str (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    token varchar(40) NOT NULL DEFAULT '',
    expiry varchar(32) NOT NULL DEFAULT '',
    PRIMARY KEY  (id)
  ) $charset;");

  $table_str = $wpdb->prefix . "pz_whitelist";

  // create or update structure of pz_whitelist table. 
  dbDelta("CREATE TABLE $table_str (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    domain varchar(255) NOT NULL DEFAULT '',
    PRIMARY KEY  (id)
  ) $charset;");

  $table_str = $wpdb->prefix . "pz_section";

  // create or update structure of pz_whitelist table. 
  dbDelta("CREATE TABLE $table_str (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    sort_num bigint(20) unsigned NOT NULL DEFAULT 0,
    product_name varchar(255) NOT NULL DEFAULT '',
    the_version varchar(40) NOT NULL DEFAULT '',
    part_name varchar(255) NOT NULL DEFAULT '', 
    section_name varchar(255) NOT NULL DEFAULT '',
    subsection_name varchar(255) NOT NULL DEFAULT '',
    video_name varchar(255) NOT NULL DEFAULT '',
    video_slug varchar(255) NOT NULL DEFAULT '',
    PRIMARY KEY  (id)
  ) $charset;");


$table_str = $wpdb->prefix . "pz_toc";

  // create or update structure of pz_toc (parts, section, subsection) table. 
  dbDelta("CREATE TABLE $table_str (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    product_name varchar(255) NOT NULL DEFAULT '',
    the_version varchar(40) NOT NULL DEFAULT '',
    part_name varchar(255) NOT NULL DEFAULT '', 
    section_name varchar(255) NOT NULL DEFAULT '',
    subsection_name varchar(255) NOT NULL DEFAULT '',
    PRIMARY KEY  (id)
  ) $charset;");

  $table_str = $wpdb->prefix . "pz_product";

  // create or update structure of pz_product table, where 
  // product names and associated versions are stored
  dbDelta("CREATE TABLE $table_str (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    product_name varchar(255) NOT NULL DEFAULT '',
    version_array varchar(2000) NOT NULL DEFAULT '',
    is_published varchar(2000) NOT NULL DEFAULT '',
    PRIMARY KEY  (id)
  ) $charset;");
}


/**
 * Access token stuff...
 */
  
/**
  * REST API for creating/retrieving pz specific nonce... 
**/

   add_action('rest_api_init', 'set_up_pzn_rest_route');
   function set_up_pzn_rest_route() {
     register_rest_route('pz/v1', 'pzn', array(
       'methods' => WP_REST_SERVER::READABLE,
       'callback' => 'do_pzn',
       'permission_callback' => '__return_true',
     ));
     register_rest_route('pz/v1', 'pzn_test', array(
       'methods' => WP_REST_SERVER::READABLE,
       'callback' => 'do_pzn_test',
       'permission_callback' => '__return_true',
     ));
    
   }

   // create a 'nonce' with a timestamp, add it to table, and return the random token portion of it
   function do_pzn($stuff) {
     global $wpdb;

     // calculate expiration date and time
     $d=strtotime("now");
     $e = strtotime( PZ_NONCE_DURATION, $d );
     $item['id'] = null;
     $item['expiry'] = $e;
     
     $results = wp_generate_password( 32, false, false );  // 32 random alphanumeric characters 
     $item['token'] = $results;
     
     // insert to nonce table
     if( $wpdb->insert( "{$wpdb->prefix}pz_token", $item ) <= 0 ) {  
      echo "Error:\n";
      var_dump( $wpdb );
      exit;
     }

     return $results;
   }

   // general function for testing 'nonce' token
   function pz_test_token($token) {
    global $wpdb;
    $now=strtotime("now");
    
    $item = [];
    $item['token'] = $_GET['token'];
    
    // get record from token table
    $results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}pz_token WHERE token = '{$item['token']}'", ARRAY_A );
    if( isset($results[0])) {
      $item = $results[0];
      if ($now >= $item['expiry']  ) {
        // delete the expired token
        $wpdb->delete( $wpdb->prefix . "pz_token", array('id' => $item['id']));
        return false;
      } else return true;

      } else {
        
        return false; // no matching token was found in table
      }
   }

   // wrapper for above function
   function do_pzn_test($stuff) {
    
    // get token from request URL and test for validity
     $result = pz_test_token($_GET['token']);
     if( $result ) return "valid";
     return "invalid";
   }

/**
 * Functions for handling video page (post) components and creating new post
 */

function pz_upload_image( $file_url, $post_id, $description ) {
  require_once(ABSPATH . 'wp-admin/includes/media.php');
  require_once(ABSPATH . 'wp-admin/includes/image.php');   

  // Gives us access to the download_url() and wp_handle_sideload() functions
  require_once( ABSPATH . 'wp-admin/includes/file.php' );

  $url = $file_url;
  $timeout_seconds = 5;

  // Download file to temp dir
  // it downloads with an automatic .tmp extension, so we change it to .png,
  // because that's what the file actually is
  $temp_file = download_url( $url, $timeout_seconds );
  if ( is_wp_error( $temp_file ) ) {
    $error_string = $result->get_error_message();
    echo '<div id="message" class="error"><p>' . $error_string . '</p></div>';
  } else {
  $png_file = str_replace( ".tmp", ".png", $temp_file );
  rename($temp_file, $png_file );
  }

  // we go through convoluted process to load the file to the media directory on the site
  if ( !is_wp_error( $temp_file ) ) {
    // Array based on $_FILE as seen in PHP file uploads
    $file = array(
        'name'     => basename($png_file), // ex: wp-header-logo.png
        'tmp_name' => $png_file,
        'error'    => 0,
        'size'     => filesize($png_file),
    );

    $overrides = array(
        // Tells WordPress to not look for the POST form
        // fields that would normally be present as
        // we downloaded the file from a remote server, so there
        // will be no form fields
        // Default is true
        'test_form' => false,

        // Setting this to false lets WordPress allow empty files, not recommended
        // Default is true
        'test_size' => true,
    ); // end if not error from creating temp file

    // all prepped -- let's pull the trigger and 
    // Move the temporary file into the uploads directory
    $results = wp_handle_sideload( $file, $overrides );
    
  }

    if ( !empty( $results['error'] ) ) {
        var_dump($results['error']);
        exit;
    } else {

        $filename  = $results['file']; // Full path to the file
        $local_url = $results['url'];  // URL to the file in the uploads dir
        $type      = $results['type']; // MIME type of the file

        // Perform any actions here based in the above results
    }

    $file_type = wp_check_filetype( $filename, null );

    $post_info = array(
      'guid'           => $local_url,
      'post_mime_type' => $file_type['type'],
      'post_title'     => $local_url,
      'post_content'   => '',
      'post_status'    => 'inherit',
  );
  
  // ah, the arcane world of media attachments... 
  $attach_id = wp_insert_attachment( $post_info, $filename, 0 );
  require_once( ABSPATH . 'wp-admin/includes/image.php' );
  $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
  wp_update_attachment_metadata( $attach_id,  $attach_data );
    

  $attachment_id = attachment_url_to_postid( $local_url );

  set_post_thumbnail( $post_id, $attachment_id );
}

// let's handle the form submission when the curator user has filled in the details
// for a new video post   


function do_video_form() {
  global $wpdb;


    // extract vimeo video id number from video url in form https://vimeo.com/878789429
  $video_id = parse_url($_POST['video_url'], PHP_URL_PATH);

  $json_body = pz_get_embed( $_POST['video_url']);
  $retval[] = json_decode($json_body, true);
  $embed_string = $retval[0]['html']; // this is just where they happen to put it.
  // make sure image is big enough -- change sizes to 600x400
  // even if this size doesn't match the aspect ration of the viewer, the viewer will
  // automatically make itself as big as it can within the dimensions while still
  // maintaining the aspect ration. 
  $start = strpos($embed_string, 'width=') - 1;
  $end = strpos($embed_string, 'frameborder');
  $head = substr($embed_string, 0, $start);
  $tail = substr($embed_string, $end );
  $embed_string = $head . ' width="600" height="400" ' . $tail; 
    
  // retrieve thumbnail from vimeo
  $thumbnail = pz_get_thumbnail( $video_id );
    
  // create the contents of the video page as if they'd been created in the wordpress editor
  $long_desc = '<!-- wp:group {"layout":{"type":"constrained"}} -->
  <div class="wp-block-group"><!-- wp:html --> ' . 
  $embed_string . 
  '    <!-- /wp:html --></div>
  <!-- /wp:group -->
  
  <!-- wp:paragraph -->
  <p>' . sanitize_text_field($_POST['long_desc']) . ' </p> 
  <!-- /wp:paragraph -->' ;
  if( isset($_POST['link_url1'])) {
    $long_desc = $long_desc . '<!-- wp:paragraph -->
    <p> You may also want to view <a href="' . $_POST['link_url1'] . '">' . sanitize_text_field($_POST['link_text1']) . ' </a></p> 
    <!-- /wp:paragraph -->' ;
    $long_desc = $long_desc . '<!-- wp:paragraph -->
    <p> <a href="' . $_POST['link_url2'] . '">' . sanitize_text_field($_POST['link_text2']) . ' </a></p> 
    <!-- /wp:paragraph -->' ;
  }
   
     
  // Gather post data for insertion
  $my_post = array(
    'post_title'    => sanitize_text_field($_POST['video_name']),
    'post_content'  => $long_desc,
    'post_status'   => 'publish',
     'post_category' => [1],
    'post_author'   => 1,
  );

 // Insert the post into the database.
  $post_id = wp_insert_post( $my_post );
  pz_upload_image( $thumbnail, $post_id, '');


  //parse out the invidual part, section, and subsection items
  $kittens = $_POST['cat'];
  $temp = explode(" -> ", $kittens );

  // Finally, add the information about this video post to the 
  // pzsection table so it can be selected in various places...

  // let's set up the data that will be the same in each record

  $item['id'] = null;
  $item['part_name'] = $temp[0];
  $item['section_name'] = $temp[1];
  $item['subsection_name'] = isset($temp[2]) ? $temp[2] : '';
  $item['video_name'] = $_POST['video_name'];
	$name = sanitize_title_with_dashes( $_POST['video_name']);
  $item['video_slug'] = $name;
  
  // and get the product and version arrays from the posted input
  $products = $_POST['product_array'];
  $version_list = $_POST['release_array'];

  // now we iterate through each product and through each version within each product
foreach( $products as $product ) {
  $item['product_name'] = $product;
  foreach( $version_list as $version ) {
    // if the product name is found in the string, it's a fit, else skip
    if( str_contains( $version, $product )) {
      $stripped_version = substr($version, strpos($version, "/") + 1); // grab release number after slash
      $item['the_version'] = $stripped_version;
      if( $wpdb->insert( "{$wpdb->prefix}pz_section", $item ) <= 0 ) {  
      echo "Error:\n";
      var_dump( $wpdb );
      exit;
     }
    }
  }
}
}

  

//   // // and now we just jump back to the dashboard
//   // wp_redirect( PZ_CURATOR_DASHBOARD );
//   // exit;
// }
  
// $other_id = '4d1d240914baf79a3c2a8bd72935c742';
$other_id = 'ef3660c0803d2ae9ef888af772521006';
   


function filter_allowed_block_types_when_post_provided( $allowed_block_types, $editor_context ) {
  if ( ! empty( $editor_context->post ) ) {
      return array( 'core/paragraph', 'core/heading', 'pz/pzvideoform' );
  }
  return $allowed_block_types;
}

// add_filter( 'allowed_block_types_all', 'filter_allowed_block_types_when_post_provided', 10, 2 );