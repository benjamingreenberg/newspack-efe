<?php
/**
 * Feed Image Class
 *
 * @package    Newspack_EFE
 */

namespace Newspack_EFE;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * Contains methods to interact with an image referenced in an EFE feed.
 */
class Image {

	/**
	 * The caption for the image.
	 *
	 * @var string
	 */
	protected $caption = null;

	/**
	 * The url to download the image from.
	 *
	 * @var string
	 */
	protected $download_url = null;

	/**
	 * The filename with extension.
	 *
	 * @var string
	 */
	protected $filename = null;

	/**
	 * The size of the image file in bytes.
	 *
	 * @var int
	 */
	protected $filesize = 0;

	/**
	 * The height of the image in pixels.
	 *
	 * @var int
	 */
	protected $height = 0;

	/**
	 * Indicates whether the parsing of the image info in the feed was successful.
	 *
	 * @var boolean true if successfully parsed, false otherwise.
	 */
	protected $is_valid = true;

	/**
	 * The url of the image on the local filesystem.
	 *
	 * Can be used to set as the featured image of an article.
	 *
	 * @var string
	 */
	protected $local_url = null;

	/**
	 * The mime type for downloading the image.
	 *
	 * @var string
	 */
	protected $mime_type = null;

	/**
	 * The article publish date, used to determine where to save the image.
	 *
	 * @var DateTimeImmutable
	 */
	protected $publish_date = null;

	/**
	 * The width of the image in pixels.
	 *
	 * @var int
	 */
	protected $width = 0;

	/**
	 * Constructor.
	 *
	 * @param array<mixed>      $image        The properties of the image on EFE's server.
	 * @param DateTimeImmutable $publish_date Optional. The date the image was published. Used to determine
	 *                                        the folder the image should be saved in. Default null.
	 */
	public function __construct( $image, $publish_date = null ) {
		if ( ! $publish_date ) {
			$publish_date = new DateTimeImmutable();
		}
		$this->publish_date = $publish_date;
		$this->caption      = $image['caption'];
		$this->download_url = $image['url'];
		$this->filename     = $image['filename'];
		$this->filesize     = $image['filesize'];
		$this->height       = $image['height'];
		$this->mime_type    = $image['mime_type'];
		$this->width        = $image['width'];
	}

	/**
	 * Downloads the image and sets the local_url.
	 *
	 * Uses the publish date to determine the subdirectory to save
	 * the image. It will not download an image if one already exists with the
	 * same filename in the directory. Instead, it will set the featured image
	 * to the url of the existing file.
	 *
	 * @param string $feed_source Optional. The feed that the data for the image
	 *                            comes from. Default empty.
	 *
	 * @return void
	 */
	public function download_image( $feed_source = '' ) {
		if ( ! $this->is_valid() ) {
			return;
		}

		$uploads_time_path = $this->publish_date->format( 'Y/m' );
		$upload_dir        = wp_upload_dir( $uploads_time_path );
		$filepath          = $upload_dir['path'] . '/' . $this->filename;
		$local_url         = $upload_dir['url'] . '/' . $this->filename;
		if ( file_exists( $filepath ) ) {
			$this->local_url = $local_url;
			return;
		}

		$result = Newspack_EFE::download_feed_file( $this->download_url, $filepath, $feed_source );

		if ( is_wp_error( $result ) ) {
			$this->is_valid = false;
			Inform::write_to_log( $result );
			return;
		}

		$this->local_url = $local_url;
	}

	/**
	 * Indicates whether the image is valid with properties needed to download it.
	 *
	 * Will return false if the properties exist, but there was a failure when
	 * attempting to download the image.
	 *
	 * @return  boolean true if valid, false otherwise.
	 */
	public function is_valid() {
		return ( $this->is_valid && $this->download_url && $this->filename );
	}

	/**
	 * Get the caption for the image.
	 *
	 * @return  string
	 */
	public function get_caption() {
		return $this->caption;
	}

	/**
	 * The URL of the image on the local filesystem.
	 *
	 * Can be used to set as the featured image of an article.
	 *
	 * If the local_url has not been set, and the information from the feed was
	 * successfully parsed, the image will be download and the local url of the
	 * downloaded image will be returned.
	 *
	 * @param string $feed_source The feed that the data for the image comes from.
	 *                            This is included as a variable in actions so
	 *                            feeds can handle downloading their own images.
	 *                            Default empty string.
	 * @return  string
	 */
	public function get_local_url( $feed_source = '' ) {
		if ( ! $this->local_url && $this->is_valid() ) {
			$this->download_image( $feed_source );
		}

		return $this->local_url;
	}

	/**
	 * Get the filename with extension.
	 *
	 * @return  string
	 */
	public function get_filename() {
		return $this->filename;
	}

	/**
	 * Get the size of the image file in bytes.
	 *
	 * @return  int
	 */
	public function get_filesize() {
		return $this->filesize;
	}

	/**
	 * Get the height of the image in pixels.
	 *
	 * @return  int
	 */
	public function get_height() {
		return $this->height;
	}

	/**
	 * Get the url to download the image from.
	 *
	 * @return  string
	 */
	public function get_download_url() {
		return $this->download_url;
	}

	/**
	 * Get the mime type for downloading the image.
	 *
	 * @return  string
	 */
	public function get_mime_type() {
		return $this->mime_type;
	}

	/**
	 * Get the width of the image in pixels.
	 *
	 * @return  int
	 */
	public function get_width() {
		return $this->width;
	}

	/**
	 * Get the article publish date, used to determine where to save the image.
	 *
	 * @return  DateTimeImmutable
	 */
	public function get_publish_date() {
		return $this->publish_date;
	}
}
