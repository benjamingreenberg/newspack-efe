<?php
/**
 * Inform Class
 *
 * @package    Newspack_EFE
 */

namespace Newspack_EFE;

defined( 'ABSPATH' ) || exit;

/**
 * Class to log messages and/or show them in the UI.
 */
class Inform {

	/**
	 * The option to save notices in.
	 *
	 * @var string
	 */
	const NOTICES_OPTION = 'newspack_efe_inform_notices';

	/**
	 * Stores notices.
	 *
	 * @var array
	 */
	private static $notices = array();

	/**
	 * Setup notices.
	 *
	 * @return void
	 */
	public static function init() {
		self::$notices = get_option( self::NOTICES_OPTION, array() );
		add_action( 'admin_notices', array( __CLASS__, 'show_notices' ) );
	}

	/**
	 * Adds a new notice.
	 *
	 * @param string|WP_Error $message String to be shown in the notice, or
	 *                                 WP_Error object.
	 * @param string          $key Used to ensure notices aren't duplicated.
	 * @param string          $type Optional. Used for styling of the notice.
	 *                              Should be one of the following: error,
	 *                              warning, success, info. Default: error.
	 * @return void
	 */
	public static function add_admin_notice( $message, $key, $type = 'error' ) {
		if ( is_wp_error( $message ) ) {
			$message = $message->get_error_message();
		}
		self::$notices[ $key ] = array(
			'message' => $message,
			'type'    => $type,
		);
		update_option( self::NOTICES_OPTION, self::$notices );
	}

	/**
	 * Renders all notices.
	 *
	 * @return void
	 */
	public static function show_notices() {
		if ( ! empty( self::$notices ) ) {
			foreach ( self::$notices as $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
					<p>
						<?php echo esc_html( $notice['message'] ); ?>
					</p>
				</div>
				<?php
			endforeach;
			?>

			<?php
		}
	}

	/**
	 * Sets notices to an empty array.
	 *
	 * @return void
	 */
	public static function clear_notices() {
		self::$notices = array();
		update_option( self::NOTICES_OPTION, self::$notices );
	}

	/**
	 * Adds a message to the debug log, if enabled.
	 *
	 * @param mixed $message The message to log.
	 * @return void
	 */
	public static function write_to_log( $message ) {
		if ( true === WP_DEBUG ) {
			/* phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log */
			/* phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_print_r */
			if ( is_array( $message ) || is_object( $message ) ) {
				error_log( print_r( $message, true ) );
			} else {
				error_log( $message );
			}
			/* phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log */
			/* phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_print_r */
		}
	}
}
