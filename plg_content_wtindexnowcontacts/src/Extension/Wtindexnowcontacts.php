<?php
/**
 * @package       WT IndexNow package
 * @subpackage    WT IndexNow - Contacts (com_contact)
 * @version       1.0.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2025 Sergey Tolkachyov
 * @license       GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Joomla\Plugin\Content\Wtindexnowcontacts\Extension;

use Exception;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Event\Model\AfterChangeStateEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\Button\BasicButton;
use Joomla\Component\Contact\Site\Helper\RouteHelper;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use function count;
use function defined;

defined('_JEXEC') or die;

final class Wtindexnowcontacts extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    /**
     * Main index now plugin params.
     * @var ?Registry $main_plugin_params
     * @since 1.0.0
     */
    protected Registry|null $main_plugin_params = null;
    public function __construct($subject, array $config = []) {
        parent::__construct($subject, $config);

        if (PluginHelper::isEnabled('system', 'wtindexnow')) {
            $main_index_now_plugin    = PluginHelper::getPlugin('system', 'wtindexnow');
            $this->main_plugin_params = new Registry($main_index_now_plugin->params);
        }
    }

    /**
     *
     * @throws Exception
     * @return array
     *
     * @since 4.1.0
     *
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onContentAfterSave'      => 'onContentAfterSave',
            'onContentChangeState'    => 'onContentChangeState',
            'onAfterDispatch'         => 'onAfterDispatch',
            'onAjaxWtindexnowcontacts' => 'onAjaxWtindexnowcontacts',
        ];
    }

    /**
     * @param   AfterSaveEvent  $event
     *
     * @return void
     * @since 1.0.0
     */
    public function onContentAfterSave(AfterSaveEvent $event): void
    {
        $option = $this->getApplication()->getInput()->get('option');
        $extension = $this->getApplication()->getInput()->get('extension','');
        if (!($option === 'com_contact' || ($option === 'com_categories' && $extension === 'com_contact'))) {
            return;
        }
        if(!$this->main_plugin_params) return;
        if($this->main_plugin_params->get('mode', 'now') === 'manual') return;
        $item = $event->getItem();
        $this->triggerIndexNowEvent($this->prepareUrls([$item->id], $event->getContext()));
    }

    /**
     * @param   array  $contacts_links
     *
     * @return bool
     * @since 1.0.0
     */
    private function triggerIndexNowEvent(array $contacts_links = []): bool
    {
        if (empty($contacts_links)) {
            return false;
        }

        $event  = AbstractEvent::create(
            'onWtIndexNowSendUrls',
            [
                'subject' => $this,
                'urls'    => $contacts_links,
            ]
        );
        $result = $this->getApplication()
            ->getDispatcher()
            ->dispatch($event->getName(), $event)->getArgument('result', false);

        return $result;
    }

    /**
     * Index now on contact change state
     *
     * @param   AfterChangeStateEvent  $event
     *
     *
     * @since 1.0.0
     */
    public function onContentChangeState(AfterChangeStateEvent $event): void
    {
        $option = $this->getApplication()->getInput()->get('option');
        $extension = $this->getApplication()->getInput()->get('extension','');
        if (!($option === 'com_contact' || ($option === 'com_categories' && $extension === 'com_contact'))) {
            return;
        }
        if(!$this->main_plugin_params) return;
        if($this->main_plugin_params->get('mode', 'now') === 'manual') return;
        $ids = $event->getPks();
        $this->triggerIndexNowEvent($this->prepareUrls($ids, $event->getContext()));
    }

    /**
     * Add a button to Joomla Toolbar for sending to Telegram via ajax
     *
     * @since 1.0.0
     */
    public function onAfterDispatch(): void
    {
        if (!$this->params->get('show_button', true)) {
            return;
        }
        $app = $this->getApplication();
        if (!$app->isClient('administrator')) {
            return;
        }

        $option = $app->getInput()->get('option');
        $extension = $app->getInput()->get('extension','');
        if (!($option === 'com_contact' || ($option === 'com_categories' && $extension === 'com_contact'))) {
            return;
        }

        $toolbar = $app->getDocument()->getToolbar('toolbar');

        $lang = $app->getLanguage('site');
        $tag  = $lang->getTag();
        $app->getLanguage()
            ->load('plg_content_wtindexnowcontact', JPATH_ADMINISTRATOR, $tag, true);

        $button = (new BasicButton('send-to-indexnow'))
            ->text(Text::_('PLG_WTINDEXNOWCONTACTS_BUTTON_LABEL'))
            ->icon('fa-solid fa-arrow-up-right-dots')
            ->onclick("window.wtindexnowcontacts()");
        $view = $app->getInput()->get('view');
        if ($view === 'contacts' || $view === 'categories') {
            $button->listCheck(true);
        }

        $toolbar->appendButton($button);

        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $app->getDocument()
            ->getWebAssetManager();
        $wa->registerAndUseScript(
            'wtindexnow.contact.ajax.send',
            'plg_content_wtindexnowcontacts/ajaxsend.js'
        );
    }

    /**
     * Main ajax job. Send to IndexNow array of $article_ids here
     * from the button in the toolbar in the contacts list
     * and contact edit page
     *
     * @param   AjaxEvent  $event
     *
     * @since 1.0.0
     */
    public function onAjaxWtindexnowcontacts(AjaxEvent $event): void
    {
        if (!Session::checkToken('GET')) {
            return;
        }

        if (!$this->getApplication()->isClient('administrator')) {
            return;
        }

        $data        = $this->getApplication()->getInput()->json->getArray();
        $item_ids = $data['item_ids'];
        $context = $data['context'];

        if (!count($item_ids)) {
            $event->setArgument('result', false);

            return;
        }
        $result  = $this->triggerIndexNowEvent($this->prepareUrls($item_ids, $context));
        $message = $result ? Text::sprintf(
            'PLG_WTINDEXNOWCONTACTS_CONTACTS_SENT_SUCCESSFULLY',
            count($item_ids)
        ) : Text::sprintf('PLG_WTINDEXNOWCONTACTS_CONTACTS_SENT_UNSUCCESSFULLY', count($item_ids));
        $event->setArgument('result', $message);
    }

    /**
     * Returns the URL of the contact or category
     *
     * @param   array   $item_ids
     * @param   string  $context
     *
     * @return string[] array of URLs
     *
     * @since 1.0.0
     */
    private function prepareUrls(array $item_ids, string $context = 'com_contact.contact'): array
    {
        $app = $this->getApplication();
        $linkMode = $app->get('force_ssl', 0) >= 1 ? Route::TLS_FORCE : Route::TLS_IGNORE;
        $sent_urls = [];
        foreach ($item_ids as $item_id) {

            switch ($context) {
                case 'com_categories.category':
                    $item = $app->bootComponent('com_contact')
                        ->getCategory()->get($item_id);
                    break;
                case 'com_contact.contact':
                default:
                    $model = $app
                        ->bootComponent('com_contact')
                        ->getMVCFactory()
                        ->createModel('Contact', 'Administrator', ['ignore_request' => true]);
                    // Trick due to bug in core populateState() method
                    // @see https://github.com/joomla/joomla-cms/issues/46311
                    $model->getState('category.id');
                    $model->setState('params', (new Registry()));
                    $item = $model->getItem($item_id);
                    break;
            }
            // Don't send unpublished contacts or categories
            if (!(int)$this->params->get('send_unpublished', 0) == 1 && $item->published < 1) {
                continue;
            }

            switch ($context) {
                case 'com_categories.category':
                    $url = RouteHelper::getCategoryRoute($item->id, $item->language);
                    break;
                case 'com_contact.contact':
                default:

                    $url = RouteHelper::getContactRoute($item->id, $item->catid, $item->language);
                    break;
            }

            $sent_urls[] = Route::link(
                client: 'site',
                url: $url,
                xhtml: true,
                tls: $linkMode,
                absolute: true
            );
        }

        return $sent_urls;
    }
}