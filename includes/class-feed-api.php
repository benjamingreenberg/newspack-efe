<?php
/**
 * EFE API Feed Class
 *
 * @package    Newspack_EFE
 */

namespace Newspack_EFE;

use WP_Error, DateTimeImmutable, CurlHandle, SimpleXMLElement, Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Interacts with the EFE API
 */
class Feed_API {

	/**
	 * The URL for EFE's Web API.
	 *
	 * @var string
	 */
	const API_URL = 'https://apinews.efeservicios.com';

	/**
	 * Option key for the API client id.
	 *
	 * @var string
	 */
	const CLIENT_ID_OPTION = 'newspack_efe_feed_efe_api_client_id';

	/**
	 * Option key for the API client secret.
	 *
	 * @var string
	 */
	const CLIENT_SECRET_OPTION = 'newspack_efe_feed_efe_api_client_secret';

	/**
	 * Option key for indicating if fetching from the API is enabled.
	 *
	 * @var string
	 */
	const IS_ENABLED_OPTION = 'newspack_efe_feed_efe_api_is_enabled';

	/**
	 * Option key for the product id.
	 *
	 * @var string
	 */
	const PRODUCT_ID_OPTION = 'newspack_efe_feed_efe_api_product_id';

	/**
	 * Option key for the API token.
	 *
	 * @var string
	 */
	const TOKEN_OPTION = 'newspack_efe_feed_efe_api_token';

	/**
	 * Associative array of the formats the EFE API can return articles in.
	 *
	 * The array contains the human readable string, keyed by the value the API
	 * uses to refer to that format.
	 *
	 * @var array
	 */
	const FEED_FORMATS = array(
		'json'   => 'JSON',
		'newsml' => 'NewsML',
		'rss'    => 'RSS',
	);

	/**
	 * The key the EFE API uses to refer to the JSON format.
	 *
	 * Use as the value for the "format" parameter when requesting articles from
	 * the API, so that it will return the articles in the JSON format.
	 *
	 * @var string
	 */
	const JSON_FORMAT = 'json';

	/**
	 * The key the EFE API uses to refer to the NewsML format.
	 *
	 * Use as the value for the "format" parameter when requesting articles from
	 * the API, so that it will return the articles in the NewsML format.
	 *
	 * @var string
	 */
	const NEWSML_FORMAT = 'newsml';

	/**
	 * The key the EFE API uses to refer to the RSS format.
	 *
	 * Use as the value for the "format" parameter when requesting articles from
	 * the API, so that it will return the articles in the RSS format.
	 *
	 * @var string
	 */
	const RSS_FORMAT = 'rss';

	/**
	 * Set up hooks.
	 */
	public static function init() {
		add_action( 'newspack_efe_admin_page_feeds_init', [ __CLASS__, 'page_init' ] );
		add_action( 'newspack_efe_download_feed_file', [ __CLASS__, 'download_api_file' ], 10, 4 );
		add_filter( 'newspack_efe_active_feeds', [ __CLASS__, 'active_feeds' ], 10, 2 );
		add_filter( 'newspack_efe_feed_articles', [ __CLASS__, 'add_articles' ], 10, 2 );
	}

	/**
	 * Register and add settings
	 */
	public static function page_init() {
		register_setting(
			'newspack_efe_settings',
			self::IS_ENABLED_OPTION,
			array(
				'default'           => null,
				'sanitize_callback' => array( __CLASS__, 'validate_is_enabled_change' ),
			)
		);
		register_setting(
			'newspack_efe_settings',
			self::CLIENT_ID_OPTION
		);
		register_setting(
			'newspack_efe_settings',
			self::CLIENT_SECRET_OPTION
		);
		register_setting(
			'newspack_efe_settings',
			self::PRODUCT_ID_OPTION
		);

		add_settings_section(
			'newspack_efe_feed_efe_api_settings_section',
			__( 'EFE API', 'newspack_efe' ),
			array( __CLASS__, 'settings_section_header' ),
			'newspack_efe'
		);

		add_settings_field(
			self::IS_ENABLED_OPTION,
			__( 'Fetch from API', 'newspack_efe' ),
			array( __CLASS__, 'is_enabled_field' ),
			'newspack_efe',
			'newspack_efe_feed_efe_api_settings_section'
		);
		add_settings_field(
			self::CLIENT_ID_OPTION,
			__( 'API Client ID', 'newspack_efe' ),
			array( __CLASS__, 'client_id_field' ),
			'newspack_efe',
			'newspack_efe_feed_efe_api_settings_section'
		);
		add_settings_field(
			self::CLIENT_SECRET_OPTION,
			__( 'API Client Secret', 'newspack_efe' ),
			array( __CLASS__, 'client_secret_field' ),
			'newspack_efe',
			'newspack_efe_feed_efe_api_settings_section'
		);
		add_settings_field(
			self::PRODUCT_ID_OPTION,
			__( 'Product ID', 'newspack_efe' ),
			array( __CLASS__, 'product_id_field' ),
			'newspack_efe',
			'newspack_efe_feed_efe_api_settings_section'
		);
	}

	/**
	 * Validates the is_enabled option when it is changed.
	 *
	 * If is_enabled is changed to true, all other API fields must have a value.
	 *
	 * @param string|null $input The string "1" if scheduling is being enabled, or
	 *                           null if it is being disabled.
	 * @return string|null The value of $input, or null if validation failed.
	 */
	public static function validate_is_enabled_change( $input ) {
		if ( $input ) {
			// phpcs:disable WordPress.Security.NonceVerification
			// Disabling NonceVerification during code sniffing because verification
			// will be handled by the Settings API. This function only checks if a
			// value has been submitted, and does not save or use any user input.

			// Determine if enabled from a form submit, or code.
			if ( empty( $_POST ) && ! self::is_configured() ) {
				$input   = null;
				$message = __( 'Unable to enable fetching of articles from EFE API due to missing configuration options.' );
				Inform::write_to_log( $message );
			} elseif ( ! empty( $_POST ) ) {
				if ( empty( $_POST[ self::CLIENT_ID_OPTION ] ) ) {
					$input = null;
					add_settings_error(
						self::CLIENT_ID_OPTION,
						self::CLIENT_ID_OPTION,
						__( 'An EFE API Client ID is required in order to enable fetching from the EFE API.' )
					);
				}

				if ( empty( $_POST[ self::CLIENT_SECRET_OPTION ] ) ) {
					$input = null;
					add_settings_error(
						self::CLIENT_SECRET_OPTION,
						self::CLIENT_SECRET_OPTION,
						__( 'An EFE API Client Secret is required in order to enable fetching from the EFE API.' )
					);
				}

				if ( empty( $_POST[ self::PRODUCT_ID_OPTION ] ) ) {
					$input = null;
					add_settings_error(
						self::PRODUCT_ID_OPTION,
						self::PRODUCT_ID_OPTION,
						__( 'An EFE Product ID is required in order to enable fetching from the EFE API.' )
					);
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification
		}
		return $input;
	}

	/**
	 * Render the EFE API settings header
	 *
	 * The header has no additional explanatory text.
	 *
	 * @param array $args the callback args.
	 * @return null
	 */
	public static function settings_section_header( $args ) {
		return null;
	}

	/**
	 * Render the API Client ID input field.
	 *
	 * @param array $args the callback args.
	 * @return void
	 */
	public static function client_id_field( $args ) {
		$value = get_option( self::CLIENT_ID_OPTION );
		echo '<input name="' . esc_attr( self::CLIENT_ID_OPTION ) . '" id="' . esc_attr( self::CLIENT_ID_OPTION ) . '" type="string" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * Render the API Client Secret input field
	 *
	 * @param array $args the callback args.
	 * @return void
	 */
	public static function client_secret_field( $args ) {
		$value = get_option( self::CLIENT_SECRET_OPTION );
		echo '<input name="' . esc_attr( self::CLIENT_SECRET_OPTION ) . '" id="' . esc_attr( self::CLIENT_SECRET_OPTION ) . '" type="string" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * Render the EFE Product ID input field.
	 *
	 * @param array $args the callback args.
	 * @return void
	 */
	public static function product_id_field( $args ) {
		$value = get_option( self::PRODUCT_ID_OPTION );
		echo '<input name="' . esc_attr( self::PRODUCT_ID_OPTION ) . '" id="' . esc_attr( self::PRODUCT_ID_OPTION ) . '" type="string" value="' . esc_attr( $value ) . '" />';
	}

	/**
	 * Render the "enabled" checkbox field.
	 *
	 * @param array $args the callback args.
	 * @return void
	 */
	public static function is_enabled_field( $args ) {
		$value = get_option( self::IS_ENABLED_OPTION );
		echo '<input name="' . esc_attr( self::IS_ENABLED_OPTION ) . '" id="' . esc_attr( self::IS_ENABLED_OPTION ) . '" type="checkbox" value="1" ' . checked( 1, $value, false ) . ' />';
	}

	/**
	 * Retrieves articles from the API and adds them to the collection.
	 *
	 * @param Articles $articles   The collection to add the articles to.
	 * @param boolean  $is_dry_run Optional. If true, will not download images, change files, or create new ones.
	 *                             Default false.
	 * @return Articles The collection with any new articles added to it.
	 */
	public static function add_articles( $articles, $is_dry_run = false ) {
		$feed_data = self::get_feed_data();
		if ( is_wp_error( $feed_data ) ) {
			Inform::write_to_log( $feed_data );
			return $articles;
		}

		try {
			$feed     = new Feed( $feed_data );
			$feed_doc = $feed->to_doc();

			if ( is_wp_error( $feed_doc ) ) {
				Inform::write_to_log( $feed_doc );
				return $articles;
			}

			$feed_type = (string) $feed_doc->getName();

			if ( 'NewsML' !== $feed_type ) {
				$message = sprintf(
					/* translators: %s: Feed type. */
					__( 'Unable to process NewsML feed: invalid feed type: %s', 'newspack-efe' ),
					$feed_type
				);

				Inform::write_to_log( $message );
				return $articles;
			}

			/* phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
			foreach ( $feed_doc->NewsItem as $news_item ) {
				$article = new NewsML_Article( $news_item, $is_dry_run, __CLASS__ );
				if ( $article->is_valid() ) {
					$articles->add_article( $article );
				}
			}
			/* phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */

			return $articles;
		} catch ( Exception $e ) {
			$message = sprintf(
				/* translators: %s: Exception message. */
				__( 'Error processing EFE API feed: %s', 'newspack-efe' ),
				$e->getMessage()
			);
			Inform::write_to_log( $message );

			return $articles;
		}
	}

	/**
	 * Retrieves data from the EFE API Feed.
	 *
	 * @return string|WP_Error The response body, or WP_Error on failure.
	 */
	public static function get_feed_data() {

		if ( ! self::is_configured() ) {
			$message = __( 'Unable to get articles from the EFE API due to missing configuration options.', 'newspack-efe' );
			return new WP_Error( 'missing_options', $message );
		}

		$token = self::get_token_value();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args = array(
			'headers' => array(
				'accept'        => 'text/plain; version=1.0',
				'Authorization' => 'Bearer ' . $token,
			),
		);

		$product_id  = get_option( self::PRODUCT_ID_OPTION );
		$feed_format = self::NEWSML_FORMAT;
		$url         = sprintf(
			'%s/content/items_ByProductId?product_id=%d&format=%s',
			self::API_URL,
			rawurlencode( $product_id ),
			rawurlencode( $feed_format )
		);

		$response = self::get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! $response['body'] ) {
			$message = __( 'No data was returned from the EFE API', 'newspack-efe' );
			return new WP_Error( 'no_articles', $message );
		}

		return $response['body'];
	}

	/**
	 * Indicates whether all the configuration options to use the API are set.
	 *
	 * @param array $settings Optional associative array of settings to get
	 *                        configuration values from. If no array is given the
	 *                        stored configuration values will be used.
	 *                        Default empty array.
	 * @return bool True if configured, false otherwise.
	 */
	public static function is_configured( $settings = array() ) {
		$client_id     = false;
		$client_secret = false;
		$product_id    = false;

		if ( ! empty( $settings ) ) {
			$client_id     = $settings[ self::CLIENT_ID_OPTION ];
			$client_secret = $settings[ self::CLIENT_SECRET_OPTION ];
			$product_id    = $settings[ self::PRODUCT_ID_OPTION ];
		} else {
			$client_id     = get_option( self::CLIENT_ID_OPTION );
			$client_secret = get_option( self::CLIENT_SECRET_OPTION );
			$product_id    = get_option( self::PRODUCT_ID_OPTION );
		}

		$is_configured = ( $client_id && $client_secret && $product_id );
		return $is_configured;
	}

	/**
	 * Returns the stored API access token, or a new one if it has expired.
	 *
	 * @return string|WP_Error The token or WP_Error on failure.
	 */
	protected static function get_token_value() {
		$token = get_option( self::TOKEN_OPTION, '' );
		$now   = new DateTimeImmutable();
		if ( ! $token || ! isset( $token['expiration_date'], $token['value'] ) || $token['expiration_date'] < $now ) {
			$token = self::refresh_token();
		}

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		return $token['value'];
	}

	/**
	 * Retrieve and store a new access token from the EFE API server.
	 *
	 * The token is an array with the following structure:
	 *   'value' => string of the token itself
	 *   'expiration_date' => DateTimeImmutable when the token expires
	 *
	 * The token is required to retrieve articles from the EFE API. It is valid
	 * for 24 hours, but the stored token's expiration date is set to 23 hours
	 * from when it was retrieved to be sure that it doesn't expire mid-session.
	 *
	 * @return array|WP_Error The stored access token, or WP_Error on failure.
	 */
	protected static function refresh_token() {

		if ( ! self::is_configured() ) {
			$message = __( 'Unable to get articles from EFE due to missing configuration options.', 'newspack-efe' );
			return new WP_Error( 'missing_options', $message );
		}

		$client_id     = get_option( self::CLIENT_ID_OPTION );
		$client_secret = get_option( self::CLIENT_SECRET_OPTION );

		$url = sprintf(
			'%s/account/token?clientId=%s&clientSecret=%s',
			self::API_URL,
			rawurlencode( $client_id ),
			rawurlencode( $client_secret )
		);

		$args = array(
			'headers' => array(
				'accept' => 'application/json',
			),
		);

		$response = self::get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$token = array(
			'value'           => trim( $response['body'], '"' ),
			'expiration_date' => new DateTimeImmutable( '+23 hours' ),
		);

		update_option( self::TOKEN_OPTION, $token );
		return $token;
	}

	/**
	 * Performs an HTTP request using the GET method and returns its response.
	 *
	 * @param string $url  URL to retrieve.
	 * @param array  $args Optional. Request arguments. Default empty array.
	 * @return array|WP_Error The response or WP_Error on failure.
	 */
	public static function get( $url, $args = array() ) {
		add_action( 'http_api_curl', array( __CLASS__, 'cipher_workaround' ) );
		$response = wp_remote_get( $url, $args );
		remove_action( 'http_api_curl', array( __CLASS__, 'cipher_workaround' ) );

		if ( is_wp_error( $response ) ||
		! isset( $response['response'], $response['response']['code'] ) ||
		200 !== $response['response']['code'] ) {
			return self::process_response_error( $response );
		}

		return $response;
	}

	/**
	 * Sets the cipher list for remote connections to work with EFE's API server.
	 *
	 * EFE uses an out-of-date server config which isn't super compatible with
	 * Atomic's.
	 *
	 * @param CurlHandle|resource $handle for curl_setopt().
	 * @return void
	 *
	 * @see https://pressableonatomic.wordpress.com/2020/05/01/dh-key-too-small-error-on-checkitofftravel-com/#comment-600
	 */
	public static function cipher_workaround( &$handle ) {
		curl_setopt( $handle, CURLOPT_SSL_CIPHER_LIST, 'ECDHE-RSA-AES256-GCM-SHA384' );
	}

	/**
	 * Sets the API token to an empty string.
	 *
	 * @return void
	 */
	public static function reset_auth() {
		update_option( self::TOKEN_OPTION, '' );
	}

	/**
	 * Processes an error or unexpected response from the API.
	 *
	 * @param array|WP_Error $response returned from wp_remote_get().
	 * @return WP_Error object with a message explaining the error.
	 */
	protected static function process_response_error( $response ) {
		$message    = '';
		$error_code = 'api_get_error';
		if ( is_wp_error( $response ) ) {
			$message = sprintf(
				/* translators: 1: Error message, 2: Error code. */
				__( 'Error retrieving articles from EFE: %1$s %2$s. This may be a temporary problem. Contact support if this message does not go away within 24 hours.', 'newspack-efe' ),
				$response->get_error_message(),
				$response->get_error_code()
			);
		} else {
			if ( ! empty( $response['response']['code'] ) && 200 !== $response['response']['code'] ) {
				if ( 400 === $response['response']['code'] ) {
					// The EFE API returns a status of 400 if the Client ID or Secret is
					// invalid when requesting a token.
					$error_code = 'api_auth_error';
					$message    = __( 'Authentication error received when getting articles from EFE. Please verify that the settings for connecting to the EFE API are correct.', 'newspack-efe' );
				} elseif ( 401 === $response['response']['code'] ) {
					// The EFE API returns 401 if the token is expired or invalid when
					// requesting articles.
					self::reset_auth();
					$message = __( 'Error retrieving articles from EFE: Invalid or expired authentication token. This is probably a temporary problem. Contact support if this message does not go away within 24 hours, or comes and goes several times a day', 'newspack-efe' );
				} else {
					$message = sprintf(
						/* translators: %d: Error code. */
						__( "Error retrieving articles from EFE: EFE's server responded with status code %d. This may be a temporary problem. Contact support if this message does not go away within 24 hours.", 'newspack-efe' ),
						$response['response']['code']
					);
				}
			} else {
				$message = __( "Error retrieving articles from EFE: the response from EFE's server was not as expected. This may be a temporary problem. Contact support if this message does not go away within 24 hours.", 'newspack-efe' );
			}
		}

		return new WP_Error( $error_code, $message );
	}

	/**
	 * Adds an item to the active feeds array if the EFE API Feed is active.
	 *
	 * Called when the newspack_efe_active_feeds filter hook is fired.
	 *
	 * @param array $active_feeds Associative array of active feeds with the
	 *                            Class_Name as the key and a human readable
	 *                            name or description as the value.
	 *                            e.g.: { 'Feed_API' => 'EFE API Feed' }.
	 * @param array $post         The $_POST variable from a submitted post, or an
	 *                            empty array if not called during a form submit.
	 * @return array The original $active_feeds array with an additional entry
	 *               for the EFE API Feed if it is active.
	 */
	public static function active_feeds( $active_feeds, $post ) {
		$is_enabled = false;

		if ( $post ) {
			$is_enabled = $post[ self::IS_ENABLED_OPTION ];
		} else {
			$is_enabled = get_option( self::IS_ENABLED_OPTION );
		}

		if ( $is_enabled && self::is_configured( $post ) ) {
			$active_feeds[ __CLASS__ ] = 'EFE API Feed';
		}

		return $active_feeds;
	}

	/**
	 * Handles downloading and saving files for articles from an API feed.
	 *
	 * Called when the newspack_efe_download_feed_file action hook is fired.
	 *
	 * @param string        $url              URL to retrieve.
	 * @param string        $save_to_filepath Path to save the file to.
	 * @param string        $feed_source      The feed that the url comes from.
	 * @param bool|WP_Error $save_result      True if the file was successfully saved, false if action
	 *                                        has not been handled, or WP_Error on failure (passed by reference).
	 * @return void
	 */
	public static function download_api_file( $url, $save_to_filepath, $feed_source, &$save_result ) {
		if ( ! $save_result && __CLASS__ === $feed_source ) {
			add_action( 'http_api_curl', array( __CLASS__, 'cipher_workaround' ) );
			$file_data = Newspack_EFE::remote_get( $url );
			remove_action( 'http_api_curl', array( __CLASS__, 'cipher_workaround' ) );

			if ( is_wp_error( $file_data ) ) {
				$save_result = $file_data;
				return;
			}

			$save_result = Newspack_EFE::save_file( $save_to_filepath, $file_data );

			if ( ! $save_result ) {
				$message     = __( 'Failed to save a file to the local filesystem.', 'newspack-efe' );
				$save_result = new WP_Error( 'download_file', $message );
			}
		}
	}
}

if ( is_admin() ) {
	Feed_API::init();
}
