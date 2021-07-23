<?php
/**
 * Plugin Name: Newspack EFE Integration
 * Description: Regularly pulls content from EFE service.
 * Version: 1.0.1
 * Author: Automattic
 * Author URI: https://newspack.blog/
 * Text Domain: newspack-efe
 * License: GPL2
 *
 * @package    Newspack_EFE
 */

defined( 'ABSPATH' ) || exit;

// Define NEWSPACK_EFE_PLUGIN_FILE.
if ( ! defined( 'NEWSPACK_EFE_PLUGIN_FILE' ) ) {
	define( 'NEWSPACK_EFE_PLUGIN_FILE', __FILE__ );
}

// Include the main Newspack EFE class.
if ( ! class_exists( 'Newspack_EFE' ) ) {
	include_once dirname( __FILE__ ) . '/includes/class-newspack-efe.php';
}
