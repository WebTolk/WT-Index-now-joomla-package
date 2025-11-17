<?php
/**
 * Default converter.
 *
 * @package       WT Index now joomla plagin
 */

namespace WPSocio\TelegramFormatText\Converter;

/**
 * Class DefaultConverter
 */
class DefaultConverter extends BaseConverter {

	const DEFAULT_CONVERTER = '_default';

	/**
	 * {@inheritdoc}
	 */
	public function getSupportedTags() {
		return [ self::DEFAULT_CONVERTER ];
	}
}
