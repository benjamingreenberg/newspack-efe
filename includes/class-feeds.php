<?php
/**
 * Feed Class
 *
 * @package    Newspack_EFE
 */

namespace Newspack_EFE;

use WP_Error, Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class for retrieving content from an EFE feed.
 */
class Feeds {

	/**
	 * Retrieves articles from all active EFE feeds.
	 *
	 * @param bool $is_dry_run Optional. If set to true, will not download images.
	 *                         change files, or create new ones. Default false.
	 * @return Articles|WP_Error Articles object, or WP_Error on
	 *                                             failure.
	 */
	public static function get_articles( $is_dry_run = false ) {
		$articles = new Articles( $is_dry_run );
		try {
			/**
			 * Filters the articles variable.
			 *
			 * @param Articles $articles from all feeds.
			 * @param bool $is_dry_run If set to true, should not download images,
			 *                         change files, or create new ones.
			 */
			$articles = apply_filters( 'newspack_efe_feed_articles', $articles, $is_dry_run );
			return $articles;
		} catch ( Exception $e ) {
			$message = sprintf(
				/* translators: %s: Exception message. */
				__( 'Unable to process feeds: %s', 'newspack-efe' ),
				$e->getMessage()
			);
			Inform::write_to_log( $message );
			return new WP_Error( 'unexpected', $message );
		}
	}
}
