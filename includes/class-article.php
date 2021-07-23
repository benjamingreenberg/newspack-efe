<?php
/**
 * Feed Article Class.
 *
 * @package    Newspack_EFE
 */

namespace Newspack_EFE;

use DateTime, DOMDocument, DomNode;

defined( 'ABSPATH' ) || exit;

/**
 * Creates an article from an article_element gotten from an EFE feed.
 */
abstract class Article {

	/**
	 * The body of the article in html.
	 *
	 * @var string
	 */
	protected $body = null;

	/**
	 * The description, synopsis, or abstract of the article.
	 *
	 * @var string
	 */
	protected $description = null;

	/**
	 * The categories of the article, as defined by EFE.
	 *
	 * @var array<string>
	 */
	protected $efe_categories = null;

	/**
	 * The keywords of the article, as defined by EFE.
	 *
	 * @var array<string>
	 */
	protected $efe_keywords = null;

	/**
	 * The details about the featured image.
	 *
	 * @var Image
	 */
	protected $featured_image = null;

	/**
	 * The guid of the article.
	 *
	 * The guid is a globally unique identifier set by EFE that can be used to
	 * identify whether a post already exists for the article.
	 *
	 * It is referred to as a duid in EFE's newsml feeds, and can be used to help
	 * navigate through the article's NewsItem node using XPath.
	 *
	 * @var string
	 */
	protected $guid = null;

	/**
	 * Indicates whether changes should be made/saved or files downloaded.
	 *
	 * @var boolean
	 */
	protected $is_dry_run = false;

	/**
	 * Indicates whether the article was successfully parsed/processed.
	 *
	 * @var boolean true if successfully parsed, false otherwise.
	 */
	protected $is_valid = true;

	/**
	 * The publication date of the article.
	 *
	 * @var DateTime
	 */
	protected $publish_date = null;

	/**
	 * The feed the article comes from.
	 *
	 * @var string
	 */
	protected $feed_source = null;

	/**
	 * The title of the article
	 *
	 * @var string
	 */
	protected $title = null;

	/**
	 * The date the article was last updated.
	 *
	 * @var DateTime
	 */
	protected $last_updated = null;

	/**
	 * The WordPress categories of the article.
	 *
	 * @var array<int>
	 */
	protected $wordpress_categories = null;

	/**
	 * XML object of the article.
	 *
	 * Has an <item> node that contains the article's elements/properties.
	 *
	 * @var DomDocument
	 */
	protected $xml_doc = null;

	/**
	 * Constructor.
	 *
	 * @param mixed  $article_element The element from the feed that contains the
	 *                                data about the article. The variable type is
	 *                                dependant on the format of the feed the
	 *                                element comes from.
	 * @param bool   $is_dry_run      Optional. If set to true, will not download images,
	 *                                change files, or create new ones. Default false.
	 * @param string $feed_source     Optional. The feed that the article comes from.
	 *                                Will be included as an argument in some filters
	 *                                and actions. Default empty string.
	 * @see NewsML_Article for info on instantiating an article from a NewsML
	 *      feed.
	 */
	public function __construct( $article_element, $is_dry_run = false, $feed_source = '' ) {
		$this->is_dry_run  = $is_dry_run;
		$this->feed_source = $feed_source;
		$this->build_article( $article_element );
	}

	/**
	 * Sets article properties using the article_element.
	 *
	 * @param mixed $article_element The element from the feed that contains the
	 *                               data about the article. The variable type is
	 *                               dependant on the format of the feed the
	 *                               element comes from.
	 * @return void
	 */
	abstract protected function build_article( $article_element );

	/**
	 * Sets xml_doc to a Document with the article as an RSS feed item element.
	 *
	 * @return void
	 */
	protected function build_rss_xml() {
		$this->xml_doc = new DOMDocument( '1.0', 'UTF-8' );
		$item          = $this->xml_doc->createElement( 'item' );
		$item_node     = $this->xml_doc->appendChild( $item );

		$title = $this->xml_doc->createElement( 'title', htmlspecialchars( $this->title ) );
		$item_node->appendChild( $title );

		$publish_rss_date = $this->publish_date->format( DateTime::RSS );
		$pub_date         = $this->xml_doc->createElement( 'pubDate', $publish_rss_date );
		$item_node->appendChild( $pub_date );

		$guid      = $this->xml_doc->createElement( 'guid', $this->guid );
		$guid_node = $item_node->appendChild( $guid );
		$guid_node->setAttribute( 'isPermaLink', 'false' );

		if ( $this->description ) {
			$description = $this->xml_doc->createElement( 'description', htmlspecialchars( $this->description ) );
			$item_node->appendChild( $description );
		}

		$content       = $this->xml_doc->createElementNS(
			'http://purl.org/rss/1.0/modules/content/',
			'content:encoded'
		);
		$content_node  = $item_node->appendChild( $content );
		$content_cdata = $this->xml_doc->createCDATASection( $this->body );
		$content_node->appendChild( $content_cdata );

		if ( $this->featured_image->is_valid() && $this->featured_image->get_local_url( $this->feed_source ) ) {
			$enclosure      = $this->xml_doc->createElement( 'enclosure' );
			$enclosure_node = $item_node->appendChild( $enclosure );
			$enclosure_node->setAttribute( 'url', $this->featured_image->get_local_url( $this->feed_source ) );
			$enclosure_node->setAttribute( 'length', $this->featured_image->get_filesize() );
			$enclosure_node->setAttribute( 'type', $this->featured_image->get_mime_type() );
		}
	}

	/**
	 * Sets xml_doc to a Document with the article as an Atom feed entry element.
	 *
	 * @return void
	 */
	protected function build_atom_xml() {
		$this->xml_doc = new DOMDocument( '1.0', 'UTF-8' );
		$entry         = $this->xml_doc->createElement( 'entry' );
		$entry_node    = $this->xml_doc->appendChild( $entry );

		$title = $this->xml_doc->createElement( 'title', htmlspecialchars( $this->title ) );
		$entry_node->appendChild( $title );

		$id = $this->xml_doc->createElement( 'id', $this->guid );
		$entry_node->appendChild( $id );

		$updated_atom_date = $this->last_updated->format( DateTime::ATOM );
		$updated           = $this->xml_doc->createElement( 'updated', $updated_atom_date );
		$entry_node->appendChild( $updated );

		$published_atom_date = $this->publish_date->format( DateTime::ATOM );
		$published           = $this->xml_doc->createElement( 'published', $published_atom_date );
		$entry_node->appendChild( $published );

		$guid      = $this->xml_doc->createElement( 'guid', $this->guid );
		$guid_node = $entry_node->appendChild( $guid );
		$guid_node->setAttribute( 'isPermaLink', 'false' );

		$content      = $this->xml_doc->createElement( 'content' );
		$content_node = $entry_node->appendChild( $content );
		$content_node->setAttribute( 'type', 'html' );
		$content_cdata = $this->xml_doc->createCDATASection( $this->body );
		$content_node->appendChild( $content_cdata );
	}

	/**
	 * Returns the article as an RSS feed item element.
	 *
	 * @return DomNode|false RSS item element of the article, or false if the
	 *                      article is not valid.
	 */
	public function get_rss_xml() {
		if ( ! $this->is_valid() ) {
			return false;
		}
		if ( ! $this->xml_doc || 'item' !== $this->xml_doc->firstChild->tagName ) {
			$this->build_rss_xml();
		}
		return $this->xml_doc->firstChild;
	}

	/**
	 * Returns the article as an Atom feed entry element.
	 *
	 * @return DomNode|null Atom entry element of the article, or null if the
	 *                      article is not valid.
	 */
	public function get_atom_xml() {
		if ( ! $this->is_valid() ) {
			return false;
		}

		if ( ! $this->xml_doc || 'entry' !== $this->xml_doc->firstChild->tagName ) {
			$this->build_atom_xml();
		}
		return $this->xml_doc->firstChild;
	}

	/**
	 * Indicates whether the article was successfully processed.
	 *
	 * @return boolean
	 */
	public function is_valid() {
		return $this->is_valid;
	}
}
