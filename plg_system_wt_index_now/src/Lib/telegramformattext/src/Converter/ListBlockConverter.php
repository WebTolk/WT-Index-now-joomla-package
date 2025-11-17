<?php
/**
 * List block converter.
 *
 * @package       WT Index now joomla plagin
 */

namespace WPSocio\TelegramFormatText\Converter;

use WPSocio\TelegramFormatText\ElementInterface;

/**
 * Class ListBlockConverter
 */
class ListBlockConverter extends BaseConverter {

	/**
	 * {@inheritdoc}
	 */
	public function convert( ElementInterface $element ) {
		return "\n" . $element->getValue() . "\n";
	}

	/**
	 * {@inheritdoc}
	 */
	public function getSupportedTags() {
		return [ 'ol', 'ul' ];
	}
}
