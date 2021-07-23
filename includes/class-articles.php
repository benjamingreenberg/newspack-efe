<?php
/**
 * Feed Articles Class
 *
 * @package    Newspack_EFE
 */

namespace Newspack_EFE;

use WP_Error, DOMDocument, Exception, SimpleXMLElement, DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Builds Articles from the contents of an EFE Feed.
 */
class Articles {

	/**
	 * Articles in the feed.
	 *
	 * @var array<Article>
	 */
	protected $articles = null;

	/**
	 * Indicates whether changes should be made/saved or files downloaded.
	 *
	 * @var boolean
	 */
	protected $is_dry_run = false;

	/**
	 * Indicates whether the articles were successfully parsed/processed.
	 *
	 * @var boolean true if successfully parsed, false otherwise.
	 */
	protected $is_valid = true;

	/**
	 * XML Document of the processed feed in RSS format.
	 *
	 * Should be compatible with many 3rd party WordPress feed reader plugins.
	 *
	 * @var DomDocument
	 */
	protected $xml_doc = null;

	/**
	 * Constructor.
	 *
	 * @param bool      $is_dry_run Optional. If set to true, will not download
	 *                                images, change files, or create new ones.
	 *                                Default false.
	 * @param Feed|null $feed       Optional. Feed content, or null. Default null.
	 */
	public function __construct( $is_dry_run = false, $feed = null ) {
		$this->is_dry_run = $is_dry_run;
		$this->articles   = array();

		if ( ! $feed ) {
			$this->initialize_xml();
		}
	}

	/**
	 * Sets the articles collection.
	 *
	 * @param array<Article> $articles The articles for the collection.
	 * @return void
	 */
	public function set_articles( $articles ) {
		$this->articles = $articles;
	}

	/**
	 * Adds an article to the collection.
	 *
	 * @param Article $article the article to add.
	 * @return void
	 */
	public function add_article( $article ) {
		$this->articles[] = $article;
		$this->append_to_xml_doc( $article );
	}

	/**
	 * Appends an article to the RSS XML Document.
	 *
	 * @param Article $article the article to append.
	 * @return void
	 */
	protected function append_to_xml_doc( $article ) {
		$article_rss = $article->get_rss_xml();
		if ( $article_rss ) {
			$this->articles[] = $article;
			$article_node     = $this->xml_doc->importNode( $article_rss, true );
			$channel          = $this->xml_doc->getElementsByTagName( 'channel' )->item( 0 );
			$channel->appendChild( $article_node );
		}
	}

	/**
	 * Sets the xml_doc property to a new Document using the RSS feed format.
	 *
	 * The document will have a "channel" element with the title, description, and
	 * language elements in it. No articles/items are added.
	 *
	 * @param DateTimeImmutable|null $publication_date Optional. The publication date of the feed.
	 * @return void
	 */
	protected function initialize_xml( $publication_date = null ) {
		$this->xml_doc                     = new DOMDocument( '1.0', 'UTF-8' );
		$this->xml_doc->preserveWhiteSpace = false;
		$this->xml_doc->formatOutput       = true;
		$rss                               = $this->xml_doc->createElement( 'rss' );
		$rss_node                          = $this->xml_doc->appendChild( $rss );
		$rss_node->setAttribute( 'version', '2.0' );

		$channel      = $this->xml_doc->createElement( 'channel' );
		$channel_node = $rss_node->appendChild( $channel );
		$channel_node->appendChild( $this->xml_doc->createElement( 'title', 'Newspack EFE Importer Articles' ) );
		$channel_node->appendChild( $this->xml_doc->createElement( 'description', 'Document created by Newspack EFE Importer Plugin' ) );
		$channel_node->appendChild( $this->xml_doc->createElement( 'language', 'ES' ) );

		if ( ! $publication_date ) {
			$publication_date = current_datetime();
		}

		$rss_date = $publication_date->format( DateTimeImmutable::RSS );
		$pub_date = $this->xml_doc->createElement( 'pubDate', $rss_date );
		$channel_node->appendChild( $pub_date );
	}

	/**
	 * Returns the articles as a Document formatted as an RSS feed.
	 *
	 * @return DomDocument The rss document.
	 */
	public function get_rss_xml() {
		return $this->xml_doc;
	}

	/**
	 * Returns the articles as a string formatted as an RSS feed.
	 *
	 * @return string|false The XML or false if an error occurs.
	 */
	public function save_xml() {
		if ( ! $this->xml_doc || ! $this->is_valid() ) {
			return false;
		}

		return $this->xml_doc->saveXML();
	}

	/**
	 * Indicates whether there are any articles.
	 *
	 * @return bool True if there are articles, false otherwise.
	 */
	public function has_articles() {
		return ( ! empty( $this->articles ) );
	}

	/**
	 * Indicates whether the feed was able to be processed and has articles.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid() {
		return ( $this->is_valid && $this->has_articles() );
	}
}
