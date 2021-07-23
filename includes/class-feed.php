<?php
/**
 * Feed Class
 *
 * @package    Newspack_EFE
 */

namespace Newspack_EFE;

use WP_Error, Exception, SimpleXMLElement;

defined( 'ABSPATH' ) || exit;

/**
 * Class for interacting with an EFE feed.
 */
class Feed {

	/**
	 * Unprocessed data returned by a Feed.
	 *
	 * @var string
	 */
	protected $raw_feed = null;

	/**
	 * An XML document of the feed.
	 *
	 * @var SimpleXMLElement
	 */
	protected $doc = null;

	/**
	 * Constructor.
	 *
	 * @param string $feed Unprocessed data returned by a Feed.
	 */
	public function __construct( $feed ) {
		$this->raw_feed = $feed;
	}

	/**
	 * Returns an XML Document of the feed
	 *
	 * @return SimpleXMLElement|WP_Error The document, or WP_Error on failure.
	 */
	public function to_doc() {
		if ( ! $this->doc ) {
			return $this->set_doc();
		}

		return $this->doc;
	}

	/**
	 * Sets the doc property of the feed.
	 *
	 * @return SimpleXMLElement|WP_Error The document, or WP_Error on failure.
	 */
	protected function set_doc() {
		try {
			$this->doc = new SimpleXMLElement( $this->raw_feed );
			return $this->doc;

		} catch ( Exception $e ) {
			$message = sprintf(
				/* translators: %s: Exception message. */
				__( 'Unable to process NewsML feed: %s', 'newspack-efe' ),
				$e->getMessage()
			);
			return new WP_Error( 'unexpected', $message );
		}
	}
}
