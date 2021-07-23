<?php
/**
 * NewsML Texts Class
 *
 * @package    Newspack_EFE
 */

namespace Newspack_EFE;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Contains methods to interact with NewsML NewsComponents for texts.
 *
 * TEXT COLLECTION
 *   "Text Collection" is EFE's term for a NewsComponent that contains other
 *   NewsComponents with text content. Technically NewsComponents with text
 *   content have a ContentItem element, under which is a MediaType Element with
 *   a "FormatName" attribute equal to "Text":
 *    NewsItem
 *      NewsComponent (Main)
 *        NewsComponent (Text Collection)
 *          NewsComponent (Text)
 *            ContentItem
 *              MediaType[FormatName="Text"]
 *
 *   The Text Collection NewsComponent has a Duid that is the Main
 *   NewsComponent's Duid, followed by the string ".texts"
 *
 *   Although it's possible to have multiple NewsComponents within the Text
 *   Collection, it appears that EFE's multimedia feed only has one, which
 *   contains the article content (headline, publication datetime, body)
 *
 *   The Text NewsComponent has an attribute named "Euid" (Element ID) with a
 *   value based on the NewsItem's Duid. It also has a Duid attribute that
 *   consists of the Text Collection's Duid, followed by its own Euid.
 *
 *   To get to the Text NewsComponent, use Xpath to find the first NewsComponent
 *  with a Duid that begins with "<MAIN NEWS COMPONENT DUID>.texts", and has an
 *   Euid attribute that begins with the the NewsItem's Guid/Duid.
 *
 *   Once you have the Text NewsComponent, the different parts of the article
 *   content can be obtained from:
 *   Headline:      Text NewsComponent->NewsLines->HeadLine
 *   Publish Date:  Text NewsComponent->DescriptiveMetadata->DateLineDate
 *   Article Body:  Text NewsComponent->ContentItem->DataContent->nitf->body->{'body.content'}
 *     Each paragraph of the article is within a <p></p> element within the
 *     body.content element.
 */
class NewsML_Texts {

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
	 * Indicates whether the article was successfully parsed/processed.
	 *
	 * @var boolean true if successfully parsed, false otherwise.
	 */
	protected $is_valid = true;

	/**
	 * The publication date of the article.
	 *
	 * @var DateTimeImmutable
	 */
	protected $publish_date = null;

	/**
	 * The title of the article
	 *
	 * @var string
	 */
	protected $title = null;

	/**
	 * Constructor.
	 *
	 * @param SimpleXmlElement $news_item NewsItem containing the Text Collection.
	 */
	public function __construct( $news_item ) {
		$text_component = self::get_first_text_component( $news_item );
		if ( ! $text_component ) {
			$this->is_valid = false;
			$news_item_duid = (string) $news_item['Duid'];
			$message        = sprintf(
				/* translators: %s: News Item DUID. */
				__( 'Unable to process EFE article with Duid %s. An error occurred processing its Text Collection section.', 'newspack-efe' ),
				$news_item_duid
			);
			Inform::write_to_log( $message );
			return;
		}
		/* phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
		$this->title        = (string) $text_component->NewsLines->HeadLine;
		$this->publish_date = new DateTimeImmutable( (string) $text_component->DescriptiveMetadata->DateLineDate );
		$this->description  = (string) $text_component->ContentItem->DataContent->nitf->body->{'body.head'}->abstract->p;
		$body_content       = $text_component->ContentItem->DataContent->nitf->body->{'body.content'};
		$this->body         = '';
		/* phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
		foreach ( $body_content->children() as $content ) {
			$this->body .= $content->asXML();
		}
	}

	/**
	 * Returns the largest file for the first image for a News Component.
	 *
	 * @param SimpleXMLElement $news_item to get the text component from.
	 * @return SimpleXMLElement|false Element with the text component, or false on
	 *                                failure.
	 * @see get_image_properties for more information about the returned
	 *                                    array
	 */
	public static function get_first_text_component( $news_item ) {
		/* phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
		$collection_duid = $news_item->NewsComponent['Duid'] . '.texts';
		$text_components = $news_item->NewsComponent->xpath(
			'.//NewsComponent[starts-with(@Duid,"' . $collection_duid . '")  and @Euid]'
		);
		/* phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */

		if ( ! $text_components ) {
			return false;
		}
		return $text_components[0];
	}


	/**
	 * Indicates whether the article was successfully processed.
	 *
	 * @return boolean
	 */
	public function is_valid() {
		return $this->is_valid;
	}


	/**
	 * Get the body of the article in html.
	 *
	 * @return  string
	 */
	public function get_body() {
		return $this->body;
	}

	/**
	 * Get the description, synopsis, or abstract of the article.
	 *
	 * @return  string
	 */
	public function get_description() {
		return $this->description;
	}

	/**
	 * Get the publication date of the article.
	 *
	 * @return  DateTimeImmutable
	 */
	public function get_publish_date() {
		return $this->publish_date;
	}

	/**
	 * Get the title of the article
	 *
	 * @return  string
	 */
	public function get_title() {
		return $this->title;
	}
}
