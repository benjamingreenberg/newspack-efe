<?php
/**
 * NewsML Article Class
 *
 * @package    Newspack_EFE
 */

namespace Newspack_EFE;

use DateTimeImmutable, Exception, SimpleXMLElement;

defined( 'ABSPATH' ) || exit;

/**
 * Article built from a NewsML NewsItem element from an EFE "multimedia" feed.
 *
 * See DocBlock for $provider_id for more info about the formats EFE uses in
 * their NewsML feeds.
 *
 * Explanation of a NewsML NewsItem from an EFE multimedia feed:
 *
 * Duid attribute: NewsItem[Duid="X"], NewsItem->NewsComponent[Duid="Y"], etc
 *   The Duid ("Document Unique ID") attribute is used throughout the document
 *   and with different types of elements. Its value uniquely identifies an
 *   element within the document.
 *
 *   You can use a NewsItem's Duid to identify whether a post has already been
 *   created for an article. If it has, use the PublicIdentifier to determine
 *   whether the post should be updated.
 *
 *   The Duid for a NewsItem should be the same value as the guid element for
 *   the same article in EFE's rss feed. This is why it is assigned to the
 *   guid property of an article object.
 *
 * ProviderId element: NewsItem->Identification->NewsIdentifier->ProviderId
 *   The ProviderId indicates the type of content the NewsItem is for, and
 *   consequently the schema used for structuring the data within the NewsItem.
 *
 *   @see $provider_id property for more info.
 *
 * @see NewsML_Texts Class for info about text content in the
 *      NewsML feed, which contains the article's title, body, publication date,
 *      and description (abstract).
 *
 * @see NewsML_Photos Class for info about images/photos in the
 *      NewsML feed.
 */
class NewsML_Article extends Article {

	/**
	 * The NewsItem element of the article from a NewsML feed.
	 *
	 * @var SimpleXMLElement
	 */
	protected $article_element = null;

	/**
	 * IPTC subject codes of the article.
	 *
	 * @var array<string>
	 * @see IPTC_News_Codes::get_news_item_subject_codes() for more
	 *      info about subject codes for a NewsItem.
	 */
	protected $iptc_subject_codes = null;

	/**
	 * The NewsItem node of the article.
	 *
	 * @var SimpleXmlElement
	 */
	protected $news_item = null;

	/**
	 * The ProviderId of the NewsML feed.
	 *
	 * Location in NewsML: NewsItem->Identification->NewsIdentifier->ProviderId
	 *
	 * The ProviderId indicates the type of content the NewsItem is for, and
	 * consequently the schema used for structuring the data within the NewsItem.
	 * All NewsItems have the same schema up to the first few elements within the
	 * Main NewsComponent regardless of what kind of content it represents.
	 * The difference occurs after the NewsLines, AdministrativeMetadata, and
	 * DescriptiveMetadata elements of the Main NewsComponent.
	 * NewsItem
	 *  Comment
	 *  Identification
	 *  NewsManagement
	 *  NewsComponent (Main NewsComponent)
	 *    NewsLines
	 *    AdministrativeMetadata
	 *    DescriptiveMetadata
	 *    **** WHAT ELEMENT(S) ARE HERE CHANGE DEPENDING ON PROVIDER ID ****
	 *
	 * These are the current ProviderIds as of 2021-05-25:
	 *  audio.efeservicios.com:         Audio content consisting of audio and text (captions).
	 *  diarioenlinea.efeservicios.com  EFE's "Online Diary" content consisting of audio and text (captions).
	 *  foto.efeservicios.com:          Photo content consisting of images and text (captions).
	 *  infografia.efeservicios.com:    Infographics consisting of images and text (descriptions/explanations).
	 *  multimedia.efeservicios.com:    NewsItems consisting of multiple types of content, like text (article body), photos with captions, video with captions, etc.
	 *  reportaje.efeservicios.com:     Articles consisting of text (article body) and multiple photos (note: photos do not contain captions).
	 *  texto.efeservicios.com:         Text content, like a news article, but with no image or other type of supporting content.
	 *  video.efeservicios.com:         Video content consisting of an image and text (captions).
	 *
	 * @var string
	 */
	protected $provider_id = null;

	/**
	 * The public identifier of the article.
	 *
	 * If a post of the article already exists, the public identifier is used to
	 * determine which version is newer. Has the following form:
	 * urn:newsml:<ProviderId>:<DateId>:<Guid>:<RevisionId>
	 *
	 * @var string
	 */
	protected $public_identifier = null;

	/* phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found */
	/**
	 * Constructor.
	 *
	 * @param SimpleXmlElement $article_element The NewsItem element of the article from a NewsML feed.
	 * @param bool             $is_dry_run Optional. If set to true, will not download images,
	 *                                     change files, or create new ones. Default false.
	 * @param string           $source     Optional. The feed that the article comes from.
	 *                                     Will be included as an argument in some filters
	 *                                     and actions. Default "feed".
	 */
	public function __construct( $article_element, $is_dry_run = false, $source = 'feed' ) {
		parent::__construct( $article_element, $is_dry_run, $source );
	}
	/* phpcs:enable Generic.CodeAnalysis.UselessOverridingMethod.Found */

	/**
	 * Sets article properties using the article_element.
	 *
	 * @param SimpleXmlElement $article_element The NewsItem element of the article from a NewsML feed.
	 * @return void
	 */
	protected function build_article( $article_element ) {
		try {
			/* phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
			$this->provider_id = (string) $article_element->Identification->NewsIdentifier->ProviderId;
			if ( 'multimedia.efeservicios.com' !== $this->provider_id ) {
				$this->is_valid = false;
				$message        = sprintf(
					/* translators: %s: Provider ID. */
					__( 'Unable to process EFE article. The NewsItem contains an unsupported ProviderId, %s. "multimedia.efeservicios.com" is the only ProviderId supported by this plugin.', 'newspack-efe' ),
					$this->provider_id
				);
				Inform::write_to_log( $message );
				return;
			}
			$this->guid      = (string) $article_element['Duid'];
			$text_collection = new NewsML_Texts( $article_element );
			if ( ! $text_collection->is_valid() ) {
				$this->is_valid = false;
				return;
			}
			$this->title              = $text_collection->get_title();
			$this->publish_date       = $text_collection->get_publish_date();
			$this->description        = $text_collection->get_description();
			$this->body               = $text_collection->get_body();
			$this->public_identifier  = (string) $article_element->Identification->NewsIdentifier->PublicIdentifier;
			$this->last_updated       = new DateTimeImmutable( (string) $article_element->NewsManagement->ThisRevisionCreated );
			$this->iptc_subject_codes = IPTC_News_Codes::get_news_item_subject_codes( $article_element );
			/* phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */

			$first_image = NewsML_Photos::get_first_image( $article_element );
			if ( $first_image ) {
				$this->featured_image = new Image( $first_image );
			}
		} catch ( Exception $e ) {
			$this->is_valid = false;
			Inform::write_to_log( $e );
			return;
		}
	}

	/**
	 * Sets xml_doc to a Document with the article as an Atom feed entry element.
	 *
	 * @return void
	 */
	protected function build_atom_xml() {
		parent::build_atom_xml();

		$entry_node = $this->xml_doc->firstChild;
		$id         = $this->xml_doc->createElement( 'id', $this->public_identifier );
		$entry_node->appendChild( $id );
	}
}
