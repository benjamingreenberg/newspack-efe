<?php
/**
 * IPTC News Codes Class
 *
 * @package    Newspack_EFE
 */

namespace Newspack_EFE;

defined( 'ABSPATH' ) || exit;

/**
 * Contains methods to interact with IPTC News Codes.
 *
 * @see https://iptc.org/standards/newscodes/groups/ for more info about the
 *      IPTC NewsCodes standard.
 */
class IPTC_News_Codes {

	/**
	 * Gets the IPTC subject codes for a NewsML News Item.
	 *
	 * Subject codes are within the DescriptiveMetadata element of the Main News
	 * Component. They will be the FormalName attribute of either a
	 * "SubjectMatter" element or "SubjectDetail" element:
	 *    NewsItem
	 *      NewsComponent (Main)
	 *        DescriptiveMetadata
	 *         SubjectCode
	 *            <SubjectMatter FormalName="#######" Schema="IptcSubjectCodes">
	 *             or
	 *            <SubjectDetail FormalName="#######" Schema="IptcSubjectCodes">
	 *
	 * @param [type] $news_item The NewsML News Item containing the Subject codes.
	 * @return array<string>|false the subject codes or false if none are found.
	 */
	public static function get_news_item_subject_codes( $news_item ) {
		$subject_codes = array();

		/* phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
		$subjects = $news_item->NewsComponent->xpath( './/DescriptiveMetadata/SubjectCode/*[@FormalName and @Scheme="IptcSubjectCodes"]' );
		/* phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase */
		if ( ! $subjects ) {
			$news_item_duid = (string) $news_item['Duid'];
			$message        = sprintf(
				/* translators: %s: The News Item Duid. */
				__( 'No IPTC subject codes found for the EFE article with Duid %s. Investigate further if this starts to occur regularly.', 'newspack-efe' ),
				$news_item_duid
			);
			Inform::write_to_log( $message );
			return false;
		}

		foreach ( $subjects as $subject ) {
			$subject_codes[] = (string) $subject['FormalName'];
		}
		return $subject_codes;
	}
}
