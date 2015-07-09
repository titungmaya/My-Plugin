<?php
/*
 Plugin Name: Facebook to WP Post
 Description: Fetch posts from your facebook page 
 Version: 0.1
 Author: Surya Maya Lama
 */
 
if ( ! defined( 'ABSPATH' ) ) {
	exit; // disable direct access
}

define('FACEBOOK_TO_WP_POST_DIR', dirname(__FILE__));
define( 'FACEBOOK_TO_WP_POST_URL',  plugin_dir_url(__FILE__) );
require_once( FACEBOOK_TO_WP_POST_DIR . '/includes/class-facebook-to-wp-post-admin.php' );
?>