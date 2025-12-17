<?php
/**
 * @package       WT IndexNow package
 * @subpackage    WT IndexNow - JoomShopping
 * @version       1.0.0
 * @Author        Sergey Sergevnin, Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2025 Sergey Sergevnin, Sergey Tolkachyov
 * @license       GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Joomla\Plugin\Jshoppingadmin\Wtindexnowjshopping\Extension;

use Exception;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\Button\BasicButton;
use Joomla\Component\Jshopping\Site\Helper\Helper;
use Joomla\Component\Jshopping\Site\Lib\JSFactory;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

use function count;
use function defined;
// No direct access
defined('_JEXEC') or die;
final class Wtindexnowjshopping extends CMSPlugin implements SubscriberInterface
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
            'onAfterSaveProduct'              => 'onAfterSaveProduct',
            'onAfterSaveCategory'             => 'onAfterSaveCategory',
            'onAfterPublishCategory'          => 'onAfterPublishCategory',
            'onAfterPublishProduct'           => 'onAfterPublishProduct',
            'onBeforeDisplayListProductsView' => 'onBeforeDisplayListProductsView',
            'onBeforeDisplayListCategoryView' => 'onBeforeDisplayListCategoryView',
            'onBeforeDisplayEditProductView'  => 'onBeforeDisplayEditProductView',
            'onBeforeEditCategories'          => 'onBeforeEditCategories',
            'onAjaxWtindexnowjshopping'       => 'onAjaxWtindexnowjshopping',
        ];
    }

    /**
     * Send to IndexNow after save product
     *
     *
     * @param   Event  $event
     *
     * @return void
     * @since 1.0.0
     */
    public function onAfterSaveProduct(Event $event): void
    {
        $product = $event->getArgument(0);
        $this->triggerIndexNowEvent($this->prepareUrls([$product->product_id], 'com_jshopping.product'));
    }

    /**
     * Send to IndexNow after save category
     *
     *
     * @param   Event  $event
     *
     * @return void
     * @since 1.0.0
     */
    public function onAfterSaveCategory(Event $event): void
    {
        $category = $event->getArgument(0);
        $this->triggerIndexNowEvent($this->prepareUrls([$category->category_id], 'com_jshopping.category'));
    }


    /**
     * Add a button to Joomshopping Toolbar for sending to IndexNow via ajax for products
     *
     * @param Event $event
     *
     *
     * @since 1.0.0
     */
    function onBeforeDisplayListProductsView($event) {

        if(!$this->params->get('show_button', true)) return;
        $this->addToolbarButton(listCheck: true);
    }

    /**
     * Add a button to Joomshopping Toolbar for sending to IndexNow via ajax for categories
     *
     * @param Event $event
     *
     *
     * @since 1.0.0
     */
    function onBeforeDisplayListCategoryView($event){
        if(!$this->params->get('show_button', true)) return;
        $this->addToolbarButton(listCheck: true);
    }

    /**
     * Add a IndexNow button to Joomshopping Toolbar to product edit page
     *
     * @param Event $event
     *
     *
     * @since 1.0.0
     */
    function onBeforeDisplayEditProductView($event){
        if(!$this->params->get('show_button', true)) return;
        $this->addToolbarButton(listCheck: false);
    }

    /**
     * Add a IndexNow button to Joomshopping Toolbar to category edit page
     *
     * @param Event $event
     *
     *
     * @since 1.0.0
     */
    function onBeforeEditCategories($event){
        if(!$this->params->get('show_button', true)) return;
        $this->addToolbarButton(listCheck: false);
    }

    /**
     * Send to main plugin array of urls triggering event onWtIndexNowSendUrls
     *
     * @param   array  $items_links
     *
     * @return bool
     *
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
        $result = $this->getApplication()
            ->getDispatcher()
            ->dispatch($event->getName(), $event)->getArgument('result', false);

        return $result;
    }

    /**
     * Send to IndexNow JoomShopping categories on after change state
     *
     * @param   \Joomla\Event\Event $event
     *
     *
     * @since 1.0.0
     */
    public function onAfterPublishCategory($event): void
    {
        if(!$this->main_plugin_params) return;
        if($this->main_plugin_params->get('mode', 'now') === 'manual') return;
        [$ids, $state] = $event->getArguments();
        $this->triggerIndexNowEvent($this->prepareUrls($ids, 'com_jshopping.category'));
    }

    /**
     * Send to IndexNow JoomShopping products on after change state
     *
     * @param   \Joomla\Event\Event $event
     *
     *
     * @since 1.0.0
     */
    public function onAfterPublishProduct($event): void
    {
        if(!$this->main_plugin_params) return;
        if($this->main_plugin_params->get('mode', 'now') === 'manual') return;
        [$ids, $state] = $event->getArguments();
        $this->triggerIndexNowEvent($this->prepareUrls($ids, 'com_jshopping.product'));
    }



    /**
     * Main ajax job. Send to IndexNow array of $items_ids here
     * from the button in the toolbar in the products list, category list
     * and product and category edit page
     *
     * @param   AjaxEvent  $event
     *
     * @since 1.0.0
     */
    public function onAjaxWtindexnowjshopping(AjaxEvent $event): void
    {
        if (!Session::checkToken('GET')) {
            return;
        }

        if (!$this->getApplication()->isClient('administrator')) {
            return;
        }

        $data        = $this->getApplication()->getInput()->json->getArray();
        $items_ids = $data['items_ids'];
        $context = $data['context'];

        if (!count($items_ids)) {
            $event->setArgument('result', false);
            return;
        }
        $result  = $this->triggerIndexNowEvent($this->prepareUrls($items_ids, $context));
        $message = $result ? Text::sprintf(
            'PLG_WTINDEXNOWJSHOPPING_ITEMS_SENT_SUCCESSFULLY',
            count($items_ids)
        ) : Text::sprintf('PLG_WTINDEXNOWJSHOPPING_ITEMS_SENT_UNSUCCESSFULLY', count($items_ids));
        $event->setArgument('result', $message);
    }

    /**
     * Returns the URL of the JoomShopping products or categories
     *
     * @param   array   $item_ids
     * @param   string  $context com_jshopping.product or com_jshopping.category
     *
     * @return array
     *
     * @since 1.0.0
     */
    private function prepareUrls(array $item_ids, string $context): array
    {
        require_once JPATH_SITE . '/components/com_jshopping/bootstrap.php';
        $linkMode = $this->getApplication()->get('force_ssl', 0) >= 1 ? Route::TLS_FORCE : Route::TLS_IGNORE;
        $sent_urls = [];

        foreach ($item_ids as $item_id) {
            switch ($context) {
                case 'com_jshopping.product':
                    $property_publish = 'product_publish';
                    $item = JSFactory::getTable('product');
                    $item->load($item_id);

                    break;

                case 'com_jshopping.category':
                default:
                    $property_publish = 'category_publish';
                    $item = JSFactory::getTable('category');
                    $item->load($item_id);

                    break;
            }

            // Don't send unpublished products or categories
            if (!$this->params->get('send_unpublished', 0) && $item->$property_publish < 1) {
                continue;
            }
            switch ($context) {
                case 'com_jshopping.product':
                    $category_id = $item->getCategory();
                    $url = 'index.php?option=com_jshopping&controller=product&task=view&category_id='.$category_id.'&product_id=' . $item_id;
                    $defaultItemid = Helper::getDefaultItemid($url);
                    $url .= '&Itemid=' . $defaultItemid;

                    break;

                case 'com_jshopping.category':
                default:
                    $url = 'index.php?option=com_jshopping&controller=category&task=view&category_id=' . $item_id;
                    $defaultItemid = Helper::getDefaultItemid($url);
                    $url .= '&Itemid=' . $defaultItemid;

                    break;
            }

            $sent_urls[] = Route::link(
                client:'site',
                url: $url,
                xhtml: true,
                tls: $linkMode,
                absolute: true
            );
        }

        return $sent_urls;
    }

    /**
     * Add IndexNow button to the JoomShopping toolbar
     *
     * @param bool $listCheck true for list check param for button
     *
     *
     * @since 1.0.0
     */
    private function addToolbarButton($listCheck = false): void
    {
        $app = $this->getApplication();
        $toolbar = $app->getDocument()->getToolbar('toolbar');

        $lang = $app->getLanguage('site');
        $tag  = $lang->getTag();
        $app->getLanguage()
            ->load('plg_jshoppingadmin_wtindexnowjshopping', JPATH_ADMINISTRATOR, $tag, true);

        $button = (new BasicButton('send-to-indexnow'))
            ->text(Text::_('PLG_WTINDEXNOWJSHOPPING_BUTTON_LABEL'))
            ->icon('fa-solid fa-arrow-up-right-dots')
            ->onclick('window.wtindexnowjshopping()');
        if ($listCheck) {
            $button->listCheck(true);
        }
        $toolbar->appendButton($button);

        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $app->getDocument()->getWebAssetManager();
        $wa->registerAndUseScript(
            'wtindexnow.jshoppingadmin.ajax.send',
            'plg_jshoppingadmin_wtindexnowjshopping/ajaxsend.js'
        );
    }
}