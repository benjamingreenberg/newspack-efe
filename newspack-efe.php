<?php
/**
 * Plugin Name: Newspack EFE Integration
 * Description: Regularly pulls content from EFE service.
 * Version: 1.0.0
 * Author: Automattic
 * Author URI: https://newspack.blog/
 * License: GPL2
 */

defined( 'ABSPATH' ) || exit;

/**
 * Run the show.
 */
class Newspack_EFE {

	/**
	 * @var string The hook for the cron job.
	 */
	const CRON_HOOK = 'newspack_efe_refresh';

	/**
	 * @var string The option to save the token in.
	 */
	const TOKEN_OPTION = 'newspack_efe_token';

	/*
	 * @var string The filename of the RSS feed to generate.
	 */
	const EFE_FILE = 'efe.xml';

	/**
	 * Set up the cron job.
	 */
	public static function init() {
		register_deactivation_hook( __FILE__, [ __CLASS__, 'handle_deactivation' ] );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}

		add_action( self::CRON_HOOK, [ __CLASS__, 'refresh_efe_data' ] );
	}

	/**
	 * Clear the cron job on deactivation.
	 */
	public static function handle_deactivation() {
    	wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Refresh the RSS feed.
	 */
	public static function refresh_efe_data() {
		/**
		 * IMPORTANT! These constants need to be defined in wp-config.php.
		 */
		if ( ! defined( 'NEWSPACK_EFE_CLIENT_ID' ) || ! defined( 'NEWSPACK_EFE_CLIENT_SECRET' ) || ! defined( 'NEWSPACK_EFE_CLIENT_PRODUCT' ) ) {
			return;
		}

		self::refresh_token();
		self::refresh_data();
	}

	/**
	 * Get a new access token. Each access token is only valid for 24 hours.
	 */
	public static function refresh_token() {
		add_action( 'http_api_curl', [ __CLASS__, 'cipher_workaround' ] );
		$response = wp_remote_get(
			sprintf( 'https://apinews.efeservicios.com/account/token?clientId=%s&clientSecret=%s', urlencode( NEWSPACK_EFE_CLIENT_ID ), NEWSPACK_EFE_CLIENT_SECRET ),
			[
				'headers' => [
					'accept' => 'application/json',
				],
			]
		);
		remove_action( 'http_api_curl', [ __CLASS__, 'cipher_workaround' ] );

		if ( ! is_wp_error( $response ) && isset( $response['response'], $response['response']['code'] ) && 200 === $response['response']['code'] ) {
			$token = trim( $response['body'], '"' );
			update_option( self::TOKEN_OPTION, $token );
		}
	}

	/**
	 * Get the latest RSS data and output to a file.
	 */
	public static function refresh_data() {
		$token = get_option( self::TOKEN_OPTION, '' );
		if ( ! $token ) {
			return;
		}

		add_action( 'http_api_curl', [ __CLASS__, 'cipher_workaround' ] );
		$response = wp_remote_get(
			sprintf( 'https://apinews.efeservicios.com/content/items_ByProductId?product_id=%d&format=rss', NEWSPACK_EFE_CLIENT_PRODUCT ),
			[
				'headers' => [
					'accept' => 'text/plain; version=1.0',
					'Authorization' => 'Bearer ' . $token,
				],
			]
		);
		remove_action( 'http_api_curl', [ __CLASS__, 'cipher_workaround' ] );

		if ( ! is_wp_error( $response ) && isset( $response['response'], $response['response']['code'] ) && 200 === $response['response']['code'] ) {
			$rss = $response['body'];
			$upload_dir = wp_get_upload_dir();
			$rss_file = $upload_dir['basedir'] . '/' . self::EFE_FILE;
			file_put_contents( $rss_file, $rss );
		}
	}

	/**
	 * EFE uses out-of-date server config which isn't super compatible with Atomic's.
	 * 
	 * @see https://pressableonatomic.wordpress.com/2020/05/01/dh-key-too-small-error-on-checkitofftravel-com/#comment-600
	 */
	public static function cipher_workaround( &$ch ) {
        curl_setopt( $ch, CURLOPT_SSL_CIPHER_LIST, 'ECDHE-RSA-AES256-GCM-SHA384' );
	}
}
Newspack_EFE::init();