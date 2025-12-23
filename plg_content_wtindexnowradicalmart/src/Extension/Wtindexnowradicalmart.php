<?php
/**
 * @package       WT IndexNow package
 * @subpackage    WT IndexNow - RadicalMart (com_radicalmart)
 * @version       1.0.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2025 Sergey Tolkachyov
 * @license       GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Joomla\Plugin\Content\Wtindexnowradicalmart\Extension;

use Joomla\CMS\Component\ComponentHelper;
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
use Joomla\Component\RadicalMart\Site\Helper\RouteHelper;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

use function count;
use function defined;

// No direct access
defined('_JEXEC') or die;

final class Wtindexnowradicalmart extends CMSPlugin implements SubscriberInterface
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
            'onAjaxWtindexnowradicalmart' => 'onAjaxWtindexnowradicalmart',
        ];
    }

    /**
     * @param   AfterSaveEvent  $event
     *
     * @return void
     *
     * @since 1.0.0
     */
    public function onContentAfterSave(AfterSaveEvent $event): void
    {
        if (!in_array($event->getContext(),['com_radicalmart.product','com_radicalmart.category'])) {
            return;
        }
        if(!$this->main_plugin_params) return;
        if($this->main_plugin_params->get('mode', 'now') === 'manual') return;
        $item = $event->getItem();
        $this->triggerIndexNowEvent($this->prepareUrls([$item->id], $event->getContext()));
    }

    /**
     * @param   array  $items_links
     *
     * @return bool
     * @since 1.0.0
     */
    private function triggerIndexNowEvent(array $items_links = []): bool
    {
        if (empty($items_links)) {
            return false;
        }

        $event  = AbstractEvent::create(
            'onWtIndexNowSendUrls',
            [
                'subject' => $this,
                'urls'    => $items_links,
            ]
        );

        return $this->getApplication()
                    ->getDispatcher()
                    ->dispatch($event->getName(), $event)
                    ->getArgument('result', false);
    }

    /**
     * Index now on product or category change state
     *
     * @param   AfterChangeStateEvent  $event
     *
     *
     * @since 1.0.0
     */
    public function onContentChangeState(AfterChangeStateEvent $event): void
    {
        if (!in_array($event->getContext(),['com_radicalmart.product','com_radicalmart.category'])) {
            return;
        }
        if(!$this->main_plugin_params) return;
        if($this->main_plugin_params->get('mode', 'now') === 'manual') return;
        $ids = $event->getPks();
        $this->triggerIndexNowEvent($this->prepareUrls($ids, $event->getContext()));
    }

    /**
     * Add a button to Joomla Toolbar for sending to IndexNow via ajax
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
        $view = $app->getInput()->get('view');
        if (!($option === 'com_radicalmart' && in_array($view,['products', 'product', 'categories', 'category']))) {
            return;
        }

        $toolbar = $app->getDocument()->getToolbar('toolbar');

        $lang = $app->getLanguage();
        $tag  = $lang->getTag();
        $app->getLanguage()
            ->load('plg_content_wtindexnowradicalmart', JPATH_ADMINISTRATOR, $tag, true);

        $button = (new BasicButton('send-to-indexnow'))
            ->text(Text::_('PLG_WTINDEXNOWRADICALMART_BUTTON_LABEL'))
            ->icon('fa-solid fa-arrow-up-right-dots')
            ->onclick("window.wtindexnowradicalmart()");

        if (in_array($view,['products', 'categories'])) {
            $button->listCheck(true);
        }

        $toolbar->appendButton($button);

        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $app->getDocument()
            ->getWebAssetManager();
        $wa->registerAndUseScript(
            'wtindexnow.radicalmart.ajax.send',
            'plg_content_wtindexnowradicalmart/ajaxsend.js'
        );
    }

    /**
     * Main ajax job. Send to IndexNow array of $item_ids here
     * from the button in the toolbar in the products or category list
     * and product or category edit page
     *
     * @param   AjaxEvent  $event
     *
     * @since 1.0.0
     */
    public function onAjaxWtindexnowradicalmart(AjaxEvent $event): void
    {
        if (!Session::checkToken('GET')) return;
        if (!$this->getApplication()->isClient('administrator')) return;

        $data        = $this->getApplication()->getInput()->json->getArray();
        $item_ids = $data['item_ids'];
        $context = $data['context'];

        if (!count($item_ids)) {
            $event->setArgument('result', false);

            return;
        }

        $result  = $this->triggerIndexNowEvent($this->prepareUrls($item_ids, $context));
        $message = $result ? Text::sprintf(
            'PLG_WTINDEXNOWRADICALMART_ELEMENTS_SENT_SUCCESSFULLY',
            count($item_ids)
        ) : Text::sprintf('PLG_WTINDEXNOWRADICALMART_ELEMENTS_SENT_UNSUCCESSFULLY', count($item_ids));
        $event->setArgument('result', $message);
    }

    /**
     * Returns the URL of the product or category
     *
     * @param   array   $item_ids
     * @param   string  $context  `com_radicalmart.product` or `com_radicalmart.category`
     *
     * @return string[] array of URLs
     *
     * @since 1.0.0
     */
    private function prepareUrls(array $item_ids, string $context = 'com_radicalmart.product'): array
    {

        $app = $this->getApplication();
        $linkMode = $app->get('force_ssl', 0) >= 1 ? Route::TLS_FORCE : Route::TLS_IGNORE;
        $sent_urls = [];

        foreach ($item_ids as $item_id) {

            switch ($context) {
                case 'com_radicalmart.category':
                    $model = $app
                        ->bootComponent('com_radicalmart')
                        ->getMVCFactory()
                        ->createModel('Category', 'Administrator', ['ignore_request' => true]);
                    break;
                case 'com_radicalmart.product':
                default:
                    $model = $app
                        ->bootComponent('com_radicalmart')
                        ->getMVCFactory()
                        ->createModel('Product', 'Administrator', ['ignore_request' => true]);
                    break;
            }
            $model->setState('params', ComponentHelper::getParams('com_radicalmart'));
            $item = $model->getItem($item_id);

            // Don't send unpublished products or categories
            if (!(int)$this->params->get('send_unpublished', 0) == 1 && $item->state < 1) {
                continue;
            }

            switch ($context) {
                case 'com_radicalmart.category':
                    $url = RouteHelper::getCategoryViewRoute($item->id, $item->language);
                    break;
                case 'com_radicalmart.product':
                default:
                    $url = RouteHelper::getProductRoute($item->id, $item->category, $item->language);
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