<?php
/**
 * NewsML Photos Class
 *
 * @package    Newspack_EFE
 */

namespace Newspack_EFE;

defined( 'ABSPATH' ) || exit;

/**
 * Contains methods to interact with NewsML NewsComponents for photos.
 *
 * PHOTO COLLECTION
 *   "Photo Collection" is EFE's term for a NewsComponent that contains one or
 *   more NewsComponents, each containing data related to one distinct photo
 *   ("Photo Component"). Each Photo Component contains NewsComponents with
 *   details about the content that makes up the Photo ("Photo Content
 *   Component"). Each Photo Content Component has a NewsComponent with data
 *   related to the image file, like its dimensions, filesize, filename, and the
 *   url to download it ("Photo File Component"). Each Photo Content Component
 *   has a NewsComponent with text data that can be used as a caption or "alt"
 *   attribute for an <img> tag ("Photo Text Component). Each Photo File
 *   Component has one or more ContentItems, each of which has data related to a
 *   different size of the same photo:
 *    NewsItem
 *     NewsComponent (Main Component)
 *        NewsComponent[Duid="<Main Component Duid>.photos"] (Photo Collection)
 *          NewsComponent[Duid="<Photo Collection Duid>.foo" EUid="foo"] (Photo Component for photo 1)
 *            NewsComponent[Duid="<Photo Component Duid>.file"] (Photo File Component)
 *              ContentItem (image size 1 of photo)
 *               MediaType[FormatName="Photo"]
 *              ContentItem (image size 2 of photo)
 *                MediaType[FormatName="Photo"]
 *            NewsComponent (Photo Text Component)
 *              ContentItem
 *                MediaType[FormatName="Text"]
 *          NewsComponent[Duid="<Photo Collection Duid>.bar" EUid="bar"] (Photo Component for photo 2 - optional)
 *            ...
 */
class NewsML_Photos {

	/**
	 * Returns the largest file for the first image for a News Component.
	 *
	 * @param SimpleXMLElement $news_item to get the image for.
	 * @return array|false Array with the properties for the image, or false on
	 *                     failure.
	 * @see get_image_properties for more information about the returned
	 *                                    array
	 */
	public static function get_first_image( $news_item ) {
		/* phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
		$news_item_duid   = (string) $news_item['Duid'];
		$collection_duid  = $news_item->NewsComponent['Duid'] . '.photos';
		$photo_components = $news_item->NewsComponent->xpath(
			'.//NewsComponent[starts-with(@Duid,"' . $collection_duid . '") and @Euid]'
		);
		/* phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
		if ( ! $photo_components ) {
			$message = sprintf(
				/* translators: %s: News Item DUID. */
				__( 'The EFE article with Duid %s does not have a Photo Collection. Investigate further if this starts to occur regularly.', 'newspack-efe' ),
				$news_item_duid
			);
			Inform::write_to_log( $message );
			return false;
		}

		$photo_component = $photo_components[0];
		$photo_file_duid = $photo_component['Duid'] . '.file';

		$photo_file_components = $photo_component->xpath(
			'.//NewsComponent[@Duid="' . $photo_file_duid . '"]'
		);

		if ( ! $photo_file_components ) {
			$message = sprintf(
				/* translators: %s: News Item DUID. */
				__( 'The EFE article with Duid %s does not have a File Component in its Photo Component. Investigate further if this starts to occur regularly.', 'newspack-efe' ),
				$news_item_duid
			);
			Inform::write_to_log( $message );
			return false;
		}

		$image_content_item = self::get_largest_image_content_item( $photo_file_components[0] );
		if ( ! $image_content_item ) {
			$message = sprintf(
				/* translators: %s: News Item DUID. */
				__( 'Error trying to determine the largest image for EFE article with Duid %s. Investigate further if this starts to occur regularly.', 'newspack-efe' ),
				$news_item_duid
			);
			Inform::write_to_log( $message );
			return false;
		}

		return self::get_image_properties( $image_content_item, $photo_component );
	}

	/**
	 * Returns the ContentItem with the largest image in a Photo File Component.
	 *
	 * @param SimpleXMLElement $photo_file_component Photo File Component Element.
	 * @return SimpleXMLElement of the ContentItem.
	 */
	public static function get_largest_image_content_item( $photo_file_component ) {
		$largest_filesize = 0;
		$largest_image    = false;
		/* phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
		foreach ( $photo_file_component->ContentItem as $image_file ) {
			$filesize = (int) $image_file->Characteristics->SizeInBytes;
			if ( $filesize > $largest_filesize ) {
				$largest_filesize = $filesize;
				$largest_image    = $image_file;
			}
		}
			/* phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
		return $largest_image;
	}

	/**
	 * Returns properties for an image from its ContentItem and PhotoComponent.
	 *
	 * @param SimpleXMLElement $image_content_item a Photo File Component.
	 * @param SimpleXMLElement $photo_component the PhotoComponent for the file.
	 * @return array<mixed> with properties of the image:
	 *                        filesize (int) the file size in bytes
	 *                        url (string) the remote address for the image
	 *                        mime_type (string) the mime type for downloading
	 *                        width (int) the image width in pixels
	 *                        height (int) the image height in pixels
	 *                        filename (string) the filename with extension
	 *                        caption (string) if one exists in the PhotoComponent
	 */
	public static function get_image_properties( $image_content_item, $photo_component ) {
			/* phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
		$image = array(
			'filesize'  => (int) $image_content_item->Characteristics->SizeInBytes,
			'url'       => (string) $image_content_item['Href'],
			'mime_type' => (string) $image_content_item->MimeType['FormalName'],
		);
		foreach ( $image_content_item->Characteristics->children() as $characteristic ) {
				/* phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
			switch ( (string) $characteristic['FormalName'] ) {
				case 'Width':
					$image['width'] = (int) $characteristic['Value'];
					break;
				case 'Height':
					$image['height'] = (int) $characteristic['Value'];
					break;
				case 'EFE_Filename':
					$image['filename'] = (string) $characteristic['Value'];
					break;
			}
		}
		$caption = self::get_photo_caption( $photo_component );
		if ( $caption ) {
			$image['caption'] = $caption;
		}
		return $image;
	}

	/**
	 * Get the caption of a Photo Component.
	 *
	 * @param SimpleXMLElement $photo_component the Photo Component.
	 * @return string|false the caption, or false if none is found.
	 */
	public static function get_photo_caption( $photo_component ) {
		$caption_component_duid = $photo_component['Duid'] . '.text';
		$caption_components     = $photo_component->xpath(
			'.//NewsComponent[@Duid="' . $caption_component_duid . '"]'
		);

		if ( ! $caption_components ) {
			return false;
		}
		$caption_component = $caption_components[0];
		$caption           = (string) $caption_component->ContentItem->DataContent->nitf->body->{'body.content'}->p;
		return $caption;
	}
}
