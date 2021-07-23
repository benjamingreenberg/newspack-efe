<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package    Newspack_EFE
 */

namespace Newspack_EFE;

use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Defines plugin options and creates an admin settings page to configure them.
 */
class Settings {

	/**
	 * Default filepath to save the xml file, relative to the uploads directory.
	 *
	 * @var string
	 */
	const DEFAULT_OUTPUT_FILE = 'efe_articles.xml';

	/**
	 * Option key for indicating whether the cron job is enabled.
	 *
	 * @var string
	 */
	const IS_SCHEDULED_OPTION = 'newspack_efe_is_scheduled';

	/**
	 * Option key for the filepath to save the xml file to.
	 *
	 * @var string
	 */
	const OUTPUT_FILE_OPTION = 'newspack_efe_output_file';


	/**
	 * Setup the admin ui.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_plugin_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'page_init' ) );
	}

	/**
	 * Adds the settings to the WordPress menu system, under "Settings".
	 *
	 * @return void
	 */
	public static function add_plugin_page() {
		add_submenu_page(
			'options-general.php',
			'Newspack EFE Integration',
			'EFE Importer',
			'manage_options',
			'newspack-efe',
			array( __CLASS__, 'create_admin_page' )
		);
	}

	/**
	 * Register and add settings.
	 *
	 * @return void
	 */
	public static function page_init() {
		register_setting(
			'newspack_efe_settings',
			self::IS_SCHEDULED_OPTION,
			array(
				'default'           => null,
				'sanitize_callback' => array( __CLASS__, 'validate_is_scheduled_change' ),
			)
		);
		register_setting(
			'newspack_efe_settings',
			self::OUTPUT_FILE_OPTION,
			array(
				'default'           => self::DEFAULT_OUTPUT_FILE,
				'sanitize_callback' => array( __CLASS__, 'validate_output_file_change' ),
			)
		);

		add_settings_section(
			'newspack_efe_settings_section',
			'',
			array( __CLASS__, 'settings_section_header' ),
			'newspack_efe'
		);

		add_settings_field(
			self::IS_SCHEDULED_OPTION,
			__( 'Auto-fetch every hour?', 'newspack_efe' ),
			array( __CLASS__, 'is_scheduled_field' ),
			'newspack_efe',
			'newspack_efe_settings_section',
			array(
				'description' => __( '(configure at least one feed below before enabling)', 'newspack_efe' ),
			)
		);

		add_settings_field(
			self::OUTPUT_FILE_OPTION,
			__( 'RSS file to save articles to', 'newspack_efe' ),
			array( __CLASS__, 'output_file_field' ),
			'newspack_efe',
			'newspack_efe_settings_section'
		);

		add_settings_section(
			'newspack_efe_settings_feeds_section',
			'',
			array( __CLASS__, 'feeds_section_header' ),
			'newspack_efe'
		);

		/**
		 * Fires when the Newspack EFE admin page's feed section has been added.
		 */
		do_action( 'newspack_efe_admin_page_feeds_init' );
	}

	/*
	 * Here begin callbacks for settings sections and settings fields.
	 */

	/**
	 * Render the admin settings page.
	 *
	 * @return void
	 */
	public static function create_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'newspack_efe' ) );
		}

		?>
		<div class="wrap newspack_efe-admin">
			<h1><?php esc_html_e( 'Newspack EFE Integration Settings', 'newspack_efe' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'newspack_efe_settings' );
				do_settings_sections( 'newspack_efe' );
				submit_button();
				?>
			</form>
			<form action="admin.php?page=newspack-efe" method="post">
				<?php
				submit_button( __( 'Fetch', 'newspack_efe' ), 'secondary', 'fetch', false );
				?>
				&nbsp;&nbsp;&nbsp;
				<?php
				submit_button( __( 'Test', 'newspack_efe' ), 'secondary', 'test', false );
				// phpcs:disable WordPress.Security.NonceVerification
				if ( ! empty( $_POST['fetch'] ) ) {
					self::fetch_and_show_result();
				} elseif ( ! empty( $_POST['test'] ) ) {
					self::fetch_and_show_result( true );
				}
				// phpcs:enable WordPress.Security.NonceVerification
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Undocumented function
	 *
	 * @param boolean $is_dry_run Optional. If set to true, will not download images,
	 *                            change files, or create new ones. Default false.
	 * @return void
	 */
	public static function fetch_and_show_result( $is_dry_run = false ) {
		try {
			$efe_articles = Newspack_EFE::refresh_efe_data( $is_dry_run );
			?>
			<h2>
				<?php echo esc_html( __( 'Result', 'newspack_efe' ) ); ?>:
			</h2>
			<?php if ( is_wp_error( $efe_articles ) ) : ?>
				<p>
					<b><?php echo esc_html( __( 'ERROR', 'newspack-efe' ) ); ?></b>
					<?php echo esc_html( $efe_articles->get_error_message() ); ?>
				</p>
				<?php
				return;
			endif;
			?>
			<b>
				<?php if ( $is_dry_run ) : ?>
					<?php echo esc_html( __( 'TEST ONLY: Articles were successfully fetched and processed, but no files were changed or added to the filesystem.', 'newspack_efe' ) ); ?>
				<?php else : ?>
					<?php echo esc_html( __( 'Articles were successfully fetched, processed, and saved in the RSS format', 'newspack_efe' ) ); ?>
				<?php endif; ?>
			</b>
			<textarea style="width:100%; height:500px; border:none;">
				<?php echo esc_html( $efe_articles->save_xml() ); ?>
			</textarea>
			<?php
		} catch ( Exception $e ) {
			?>
			<p>
				<b>
					<?php echo esc_html( __( 'Unexpected error occurred', 'newspack_efe' ) ); ?>:
				<b>
			</p>
			<p>
				<?php echo esc_html( $e->getMessage() ); ?>
			</p>
			<?php
		}
	}

	/**
	 * Render the General section header.
	 *
	 * The header has no additional explanatory text.
	 *
	 * @param array $args the callback args.
	 * @return false
	 */
	public static function settings_section_header( $args ) {
		return false;
	}

	/**
	 * Render the "is scheduled" checkbox field.
	 *
	 * @param array $args the callback args.
	 * @return void
	 */
	public static function is_scheduled_field( $args ) {
		$value = get_option( self::IS_SCHEDULED_OPTION );
		echo '<input name="' . esc_attr( self::IS_SCHEDULED_OPTION ) . '" id="' . esc_attr( self::IS_SCHEDULED_OPTION ) . '" type="checkbox" value="1" ' . checked( 1, $value, false ) . ' />';
		echo esc_html( $args['description'] );
	}

	/**
	 * Render the Output File field.
	 *
	 * @param array $args the callback args.
	 * @return void
	 */
	public static function output_file_field( $args ) {
		$value = get_option(
			self::OUTPUT_FILE_OPTION,
			self::DEFAULT_OUTPUT_FILE
		);
		echo '<input name="' . esc_attr( self::OUTPUT_FILE_OPTION ) . '" id="' . esc_attr( self::OUTPUT_FILE_OPTION ) . '" type="string" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * Render the Feeds section header.
	 *
	 * @param array $args the callback args.
	 * @return void
	 */
	public static function feeds_section_header( $args ) {
		echo '<h1>Feeds</h1>';
	}

	/**
	 * Validates the is_scheduled option when it is changed.
	 *
	 * If scheduling is being enabled, then there needs to be at least one active
	 * feed.
	 *
	 * @param string|null $input The string "1" if scheduling is being enabled, or
	 *                           null if it is being disabled.
	 * @return string|null The value of $input, or null if validation failed.
	 */
	public static function validate_is_scheduled_change( $input ) {
		if ( $input ) {
			// phpcs:disable WordPress.Security.NonceVerification
			// Nonce verification for $_POST will be handled by the Settings API
			// before any user input is saved.

			$active_feeds = array();
			/**
			 * Filters the array of all currently active feeds.
			 *
			 * @param array $active_feeds Associative array of active feeds with the
			 *                            Class_Name as the key and a human readable
			 *                            name or description as the value.
			 *                            'Feed_API' => 'EFE API Feed'
			 * @param array $post the $_POST variable from a form submission, or an
			 *                    empty array. The intention behind including a copy
			 *                    the $_POST variable is to allow plugins to check if
			 *                    values have been submitted that could change the
			 *                    active status of their feed. It should not be used
			 *                    to save or display user input.
			 */
			$active_feeds = apply_filters( 'newspack_efe_active_feeds', $active_feeds, $_POST );
			// phpcs:enable WordPress.Security.NonceVerification
			if ( empty( $active_feeds ) ) {
				$input   = null;
				$message = __( 'Unable to enable auto-fetching of articles because no feeds are enabled.' );
				add_settings_error(
					self::IS_SCHEDULED_OPTION,
					self::IS_SCHEDULED_OPTION,
					$message
				);
			}
		}
		return $input;
	}

	/**
	 * Validates the output file option when it is changed.
	 *
	 * @param string|null $input The value the output file is being changed to.
	 * @return string The value of $input, or the default option value if input
	 *                is null.
	 */
	public static function validate_output_file_change( $input ) {
		if ( ! $input ) {
			$input = self::DEFAULT_OUTPUT_FILE;
		}
		return sanitize_file_name( $input );
	}

}

if ( is_admin() ) {
	Settings::init();
}
