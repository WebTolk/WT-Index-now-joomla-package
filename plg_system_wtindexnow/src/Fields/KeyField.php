<?php
/**
 * @package       WT IndexNow package
 * @version    1.0.0
 * @Author     Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2022 - September 2025 Sergey Tolkachyov. All rights reserved.
 * @license    GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since      1.3.0
 */


namespace Joomla\Plugin\System\Wtindexnow\Fields;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\UserHelper;
use Joomla\Filesystem\File;

\defined('_JEXEC') or die;

class KeyField extends FormField
{

    protected $type = 'Key';

    /**
     * Method to get the field input markup for a spacer.
     * The spacer does not have accept input.
     *
     * @return  string  The field input markup.
     *
     * @since   1.3.0
     */
    protected function getInput()
    {
        $filename = $this->value;

        $new_value = '';
        if (empty($this->value)) {
            $new_value = UserHelper::genRandomPassword(64);
            $filename = $new_value;
        }

        $field_input = [];
        $field_input[] = '<div class="input-group">';
        $field_input[] = '<input type="text" class="form-control" name="' . $this->__get('name') . '" id="' . $this->__get('id') . '" value="' . (!empty($this->value) ? $this->value : $new_value). '">';

        if (empty($this->value)) {
            $field_input[] = '<div class="invalid-feedback d-block">';
            $field_input[] = '<p>' . Text::_('PLG_WTINDEXNOW_FIELD_KEY_KEY_IS_EMPTY') . '</p>';
            $field_input[] = '<p>' . '&#10060' . Text::_('PLG_WTINDEXNOW_FIELD_KEY_FILE_IS_EMPTY') . '</p>';
            $field_input[] = '</div>';
            $this->value = $new_value;
        } else {
            $field_input[] = '<div class="valid-feedback d-block">';
            $field_input[] = '<p>' . Text::_('PLG_WTINDEXNOW_FIELD_KEY_KEY_IS_CREATED') . '</p>';
            $field_input[] = '<p>' . '&#9989' . ' ' . Text::_('PLG_WTINDEXNOW_FIELD_KEY_FILE_IS_CREATED') . '</p>';
            $field_input[] = '</div>';

            if (!file_exists(JPATH_SITE . '/' . $filename . '.txt')):
                File::write(file: JPATH_SITE . '/' . $filename . '.txt', buffer: $filename);
            endif;
        }
        $field_input[] = '</div>';

        return implode('', $field_input);
    }

    /**
     * @return  string  The field label markup.
     *
     * @since   1.3.0
     */
    protected function getTitle()
    {
        return $this->getLabel();
    }

    /**
     * @return  string  The field label markup.
     *
     * @since   1.3.0
     */
    protected function getLabel()
    {
        return Text::_(($this->element['label'] ? (string)$this->element['label'] : (string)$this->element['name']));
    }
}