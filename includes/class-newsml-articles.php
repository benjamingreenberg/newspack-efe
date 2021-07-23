<?php
/**
 * NewsML Articles Class
 *
 * @package    Newspack_EFE
 */

namespace Newspack_EFE;

use DateTimeImmutable, Exception, SimpleXMLElement, WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Builds Articles from the contents of an EFE NewsML Feed.
 */
class NewsML_Articles extends Articles {

	/**
	 * Constructor.
	 *
	 * @param Feed|null $feed       Optional. Feed content, or null. Default null.
	 * @param bool      $is_dry_run Optional. If set to true, will not download
	 *                              images, change files, or create new ones.
	 *                              Default false.
	 */
	public function __construct( $feed = null, $is_dry_run = false ) {
		$this->is_dry_run = $is_dry_run;
		$this->articles   = array();

		if ( ! $feed ) {
			$this->initialize_xml();
			return;
		}

		$feed_doc = $feed->to_doc();

		if ( is_wp_error( $feed_doc ) ) {
			$this->is_valid = false;
			Inform::write_to_log( $feed );
			return;
		}
		/* phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
		$datetime_created = new DateTimeImmutable( (string) $feed_doc->NewsEnvelope->DateAndTime );
		/* phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
		$this->initialize_xml( $datetime_created );
		$this->add_articles( $feed );
	}

	/**
	 * Adds articles from a NewsML feed.
	 *
	 * @param Feed $feed Feed content.
	 */
	public function add_articles( $feed ) {
		try {
			$feed_doc = $feed->to_doc();
			if ( is_wp_error( $feed_doc ) ) {
				$this->is_valid = false;
				Inform::write_to_log( $feed );
				return;
			}

			$channel = $this->xml_doc->getElementsByTagName( 'channel' )->item( 0 );
			/* phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
			foreach ( $feed_doc->NewsItem as $news_item ) {
				$article     = new NewsML_Article( $news_item, $this->is_dry_run );
				$article_rss = $article->get_rss_xml();

				if ( $article_rss ) {
					$this->articles[] = $article;
					$article_node     = $this->xml_doc->importNode( $article_rss, true );
					$channel->appendChild( $article_node );
				}
			}
			/* phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */

			if ( empty( $this->articles ) ) {
				$this->is_valid = false;
				$message        = __( 'EFE Importer was not able to successfully process any articles within the NewsML document.', 'newspack-efe' );
				Inform::write_to_log( $message );
			}
		} catch ( Exception $e ) {
			$this->is_valid = false;
			$message        = sprintf(
				/* translators: %s: Exception message. */
				__( 'Error processing NewsML feed: %s', 'newspack-efe' ),
				$e->getMessage()
			);
			Inform::write_to_log( $message );
		}
	}
}
