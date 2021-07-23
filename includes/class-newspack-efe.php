<?php
/**
 * Newspack EFE setup
 *
 * @package Newspack
 */

namespace Newspack_EFE;

use  WP_Error, DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Main Newspack_EFE Class.
 */
class Newspack_EFE {

	/**
	 * The single instance of the class.
	 *
	 * @var Newspack
	 */
	protected static $_instance = null; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The hook for the cron job.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'newspack_efe_refresh';

	/**
	 * Option key for the last datetime articles were successfully fetched.
	 *
	 * @var DateTimeImmutable
	 */
	const LAST_SUCCESSFUL_RUN_OPTION = 'newspack_efe_last_successful_run';

	/**
	 * Main Newspack EFE Instance.
	 * Ensures only one instance of Newspack EFE is loaded or can be loaded.
	 *
	 * @return Newspack_EFE - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Newspack Constructor.
	 */
	public function __construct() {
		$this->define_constants();
		$this->includes();

		register_deactivation_hook( __FILE__, array( __CLASS__, 'handle_deactivation' ) );

		// Used by cron job. @see set_refresh_schedule().
		add_action( self::CRON_HOOK, array( __CLASS__, 'refresh_efe_data' ) );

		Inform::init();
	}

	/**
	 * Define plugin-wide Newspack_EFE Constants.
	 */
	private function define_constants() {
		define( 'NEWSPACK_EFE_ABSPATH', dirname( NEWSPACK_EFE_PLUGIN_FILE ) . '/' );
	}

	/**
	 * Include required core files used in admin.
	 * e.g. include_once NEWSPACK_EFE_ABSPATH . 'includes/foo.php';
	 */
	private function includes() {
		include_once NEWSPACK_EFE_ABSPATH . 'includes/class-article.php';
		include_once NEWSPACK_EFE_ABSPATH . 'includes/class-articles.php';
		include_once NEWSPACK_EFE_ABSPATH . 'includes/class-feed-api.php';
		include_once NEWSPACK_EFE_ABSPATH . 'includes/class-feed.php';
		include_once NEWSPACK_EFE_ABSPATH . 'includes/class-feeds.php';
		include_once NEWSPACK_EFE_ABSPATH . 'includes/class-image.php';
		include_once NEWSPACK_EFE_ABSPATH . 'includes/class-inform.php';
		include_once NEWSPACK_EFE_ABSPATH . 'includes/class-iptc-news-codes.php';
		include_once NEWSPACK_EFE_ABSPATH . 'includes/class-newsml-article.php';
		include_once NEWSPACK_EFE_ABSPATH . 'includes/class-newsml-articles.php';
		include_once NEWSPACK_EFE_ABSPATH . 'includes/class-newsml-photos.php';
		include_once NEWSPACK_EFE_ABSPATH . 'includes/class-newsml-texts.php';
		include_once NEWSPACK_EFE_ABSPATH . 'includes/class-settings.php';
	}

	/**
	 * Clear the cron job on deactivation.
	 *
	 * Also makes sure admin form will show the cron job is disabled, in case a
	 * user ever activates the plugin again.
	 *
	 * @return void
	 */
	public static function handle_deactivation() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		update_option( Settings::IS_SCHEDULED_OPTION, false );
	}

	/**
	 * Enable or disable the cron job.
	 *
	 * @param boolean $is_enabled True if the cron job should be enabled, false
	 *                            otherwise.
	 * @return bool|WP_Error True if the cron job was enabled, False if it was
	 *                       disabled, or WP_Error on failure.
	 */
	public static function set_refresh_schedule( $is_enabled = true ) {
		if ( ! $is_enabled ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
		} elseif ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			if ( ! self::can_be_scheduled() ) {
				$message = __( 'Unable to schedule importing from EFE due to missing configuration options.', 'newspack-efe' );
				return new WP_Error( 'missing_options', $message );
			}
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}

		update_option( Settings::IS_SCHEDULED_OPTION, $is_enabled );

		return $is_enabled;
	}

	/**
	 * Indicates whether auto-fetching can be enabled.
	 *
	 * @return boolean True if auto-fetching can be enabled, false otherwise.
	 */
	public static function can_be_scheduled() {

		$has_active_feeds = false;
		/**
		 * Filters the has active feeds variable.
		 *
		 * @param boolean $has_feeds True if at least one feed is active, false
		 *                           otherwise.
		 */
		$has_active_feeds = apply_filters( 'newspack_efe_has_active_feeds', $has_active_feeds );

		return $has_active_feeds;
	}

	/**
	 * Rebuild the RSS output file with latest articles from EFE feed.
	 *
	 * @param bool $is_dry_run Optional. If set to true, will not download images.
	 *                         change files, or create new ones. Default false.
	 * @return Articles|WP_Error Articles object, or WP_Error on
	 *                                             failure.
	 */
	public static function refresh_efe_data( $is_dry_run = false ) {
		Inform::clear_notices();
		$articles = Feeds::get_articles( $is_dry_run );

		if ( is_wp_error( $articles ) ) {
			self::process_refresh_failure( $articles );
			return $articles;
		}

		if ( ! $articles || ! $articles->is_valid() ) {
			$message = __( 'No valid articles were able to be generated from the EFE feed. You may need to review the debug log to determine the cause.', 'newspack-efe' );
			Inform::write_to_log( $message );
			self::process_refresh_failure( $message );
			return new WP_Error( 'unknown', $message );
		}

		if ( ! $is_dry_run ) {
			$save_result = self::save_to_rss_file( $articles );
			if ( is_wp_error( $save_result ) ) {
				Inform::write_to_log( $save_result );
				self::process_refresh_failure( $save_result );
				return $save_result;
			}
		}

		$now = new DateTimeImmutable();
		update_option( NEWSPACK_EFE::LAST_SUCCESSFUL_RUN_OPTION, $now );

		return $articles;
	}

	/**
	 * Adds an admin notice if the last successful refresh was over 3 hours ago.
	 *
	 * @param WP_Error $error Error that occurred for the current refresh attempt.
	 * @return void
	 */
	protected static function process_refresh_failure( $error ) {
		$three_hours_ago = new DateTimeImmutable( '-179 minutes' );
		$last_update     = get_option( NEWSPACK_EFE::LAST_SUCCESSFUL_RUN_OPTION );
		if ( ! $last_update || $last_update > $three_hours_ago ) {
			Inform::add_admin_notice( $error, 'refresh-error', 'error' );
		}
	}

	/**
	 * Saves articles to the filesystem as a RSS feed.
	 *
	 * @param Articles $articles The articles to save.
	 * @return bool|WP_Error True if the file was saved, WP_Error on failure.
	 */
	protected static function save_to_rss_file( $articles ) {
		$upload_dir  = wp_get_upload_dir();
		$output_file = get_option( Settings::OUTPUT_FILE_OPTION, Settings::DEFAULT_OUTPUT_FILE );
		$rss_file    = $upload_dir['basedir'] . '/' . $output_file;
		global $wp_filesystem;

		if ( ! function_exists( '\\WP_Filesystem' ) ) {
			include_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! \WP_Filesystem() ) {
			$message = __( 'Unable to initialize and connect to WP_Filesystem', 'newspack-efe' );
			return new WP_Error( 'save_file', $message );
		}

		$result = self::save_file( $rss_file, $articles->save_xml() );

		if ( ! $result ) {
			/* translators: %s: Location of RSS file. */
			$message = sprintf( __( 'Error attempting to save rss file with EFE articles to %s', 'newspack-efe' ), $rss_file );
			return new WP_Error( 'save_file', $message );
		}

		return $result;
	}

	/**
	 * Writes a string to a file.
	 *
	 * @param string $filepath Path to the file where to write the data.
	 * @param string $contents The data to write.
	 * @return bool True on success, false on failure.
	 */
	public static function save_file( $filepath, $contents ) {
		global $wp_filesystem;

		if ( ! function_exists( '\\WP_Filesystem' ) ) {
			include_once ABSPATH . 'wp-admin/includes/file.php';
		}

		if ( ! \WP_Filesystem() ) {
			return false;
		}

		$result = $wp_filesystem->put_contents( $filepath, $contents );

		return $result;
	}

	/**
	 * Downloads a remote file and saves it to the filesystem.
	 *
	 * @param string $url                   URL to retrieve.
	 * @param string $save_to_filepath      Path to the file where to save the file.
	 * @param string $feed_source           The feed that the url was found in.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public static function download_feed_file( $url, $save_to_filepath, $feed_source ) {
		$save_result = false;

		/**
		 * Fires when a file referenced in an EFE feed will be downloaded.
		 *
		 * @param string        $url              URL to retrieve.
		 * @param string        $save_to_filepath Path to save the file to.
		 * @param string        $source           The feed that the url was found in.
		 * @param bool|WP_Error $result           True if the file was successfully saved,
		 *                                        false if no download attempt has been made,
		 *                                        or WP_Error on failure (passed by reference).
		 */
		do_action_ref_array( 'newspack_efe_download_feed_file', array( $url, $save_to_filepath, $feed_source, &$save_result ) );

		if ( $save_result ) {
			return $save_result;
		}

		$file_data = self::remote_get( $url );

		if ( is_wp_error( $file_data ) ) {
			return $file_data;
		}

		$save_result = self::save_file( $save_to_filepath, $file_data );

		if ( ! $save_result ) {
			$message = __( 'Failed to save a file to the local filesystem.', 'newspack-efe' );
			return new WP_Error( 'download_file', $message );
		}

		return $save_result;
	}

	/**
	 * Returns the response body from an HTTP get request.
	 *
	 * @see WP_Http::request() For default arguments information.
	 *
	 * @param string $url  URL to retrieve.
	 * @param array  $args Optional. Request arguments. Default empty array.
	 * @return string|WP_Error The response body or WP_Error on failure.
	 */
	public static function remote_get( $url, $args = array() ) {
		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$message = sprintf(
				/* translators: 1: Error code, 2: Error message. */
				__( 'Error downloading file: %1$s %2$s.', 'newspack-efe' ),
				$response->get_error_code(),
				$response->get_error_message()
			);

			return new WP_Error( 'download_file', $message );
		}

		if ( ! isset( $response['response'], $response['response']['code'] ) ) {
			$message = __( 'Error downloading file: the response from the server was not as expected.', 'newspack-efe' );
			return new WP_Error( 'download_file', $message );
		}

		if ( 200 !== $response['response']['code'] ) {
			$message = sprintf(
				/* translators: 1: Response code, 2: Response message. */
				__( 'Error downloading file: %1$s %2$s.', 'newspack-efe' ),
				$response['response']['code'],
				$response['response']['message']
			);

			return new WP_Error( 'download_file', $message );
		}

		return $response['body'];
	}
}

Newspack_EFE::instance();
