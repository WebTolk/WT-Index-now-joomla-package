<?php
/**
 * @package       WT IndexNow package
 * @subpackage    WT IndexNow - Phoca Cart
 * @version       1.0.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2025 Sergey Tolkachyov
 * @license       GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Joomla\Plugin\Content\Wtindexnowphocacart\Extension;

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
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use function count;
use function defined;

// No direct access
defined('_JEXEC') or die;

final class Wtindexnowphocacart extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

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
            'onAjaxWtindexnowphocacart' => 'onAjaxWtindexnowphocacart',
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
        if (!in_array($event->getContext(),['com_phocacart.phocacartcategory','com_phocacart.phocacartitem'])) {
            return;
        }
        if(!$this->main_plugin_params) return;
        if($this->main_plugin_params->get('mode', 'now') === 'manual') return;
        $item = $event->getItem();
        $this->triggerIndexNowEvent($this->prepareUrls([$item->id], $event->getContext()));
    }

    /**
     * @param   array  $articles_links
     *
     * @return bool
     * @since 1.0.0
     */
    private function triggerIndexNowEvent(array $articles_links = []): bool
    {
        if (empty($articles_links)) {
            return false;
        }

        $event  = AbstractEvent::create(
            'onWtIndexNowSendUrls',
            [
                'subject' => $this,
                'urls'    => $articles_links,
            ]
        );
        $result = $this->getApplication()
            ->getDispatcher()
            ->dispatch($event->getName(), $event)->getArgument('result', false);

        return $result;
    }

    /**
     * Index now on article change state
     *
     * @param   AfterChangeStateEvent  $event
     *
     *
     * @since 1.0.0
     */
    public function onContentChangeState(AfterChangeStateEvent $event): void
    {
        if (!in_array($event->getContext(),['com_phocacart.phocacartcategory','com_phocacart.phocacartitem'])) {
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
        $view = $app->getInput()->get('view','');
        if (!($option === 'com_phocacart' && in_array($view, ['phocacartitems', 'phocacartitem', 'phocacartcategories', 'phocacartcategory']))) {
            return;
        }

        $toolbar = $app->getDocument()->getToolbar('toolbar');

        $lang = $app->getLanguage('site');
        $tag  = $lang->getTag();
        $app->getLanguage()
            ->load('plg_content_wtindexnowphocacart', JPATH_ADMINISTRATOR, $tag, true);

        $button = (new BasicButton('send-to-indexnow'))
            ->text(Text::_('PLG_WTINDEXNOWPHOCACART_BUTTON_LABEL'))
            ->icon('fa-solid fa-arrow-up-right-dots')
            ->onclick("window.wtindexnowphocacart()");
        $view = $app->getInput()->get('view');
        if ($view === 'phocacartitems' || $view === 'phocacartcategories') {
            $button->listCheck(true);
        }

        $toolbar->appendButton($button);

        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $app->getDocument()
            ->getWebAssetManager();
        $wa->registerAndUseScript(
            'wtindexnow.content.ajax.send',
            'plg_content_wtindexnowphocacart/ajaxsend.js'
        );
    }

    /**
     * Main ajax job. Send to IndexNow array of items here
     * from the button in the toolbar in the products or categories list
     * and product or category edit page
     *
     * @param   AjaxEvent  $event
     *
     * @since 1.0.0
     */
    public function onAjaxWtindexnowphocacart(AjaxEvent $event): void
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
            'PLG_WTINDEXNOWPHOCACART_ELEMENTS_SENT_SUCCESSFULLY',
            count($item_ids)
        ) : Text::sprintf('PLG_WTINDEXNOWPHOCACART_ELEMENTS_SENT_UNSUCCESSFULLY', count($item_ids));
        $event->setArgument('result', $message);
    }

    /**
     * Returns the URL of the article or category
     *
     * @param   array   $item_ids
     * @param   string  $context
     *
     * @return string[] array of URLs
     *
     * @since 1.0.0
     */
    private function prepareUrls(array $item_ids, string $context = 'com_phocacart.phocacartitem'): array
    {

        $item_ids = ArrayHelper::toInteger($item_ids);
        $app = $this->getApplication();
        $linkMode = $app->get('force_ssl', 0) >= 1 ? Route::TLS_FORCE : Route::TLS_IGNORE;
        $sent_urls = [];

        require_once(JPATH_ADMINISTRATOR . '/components/com_phocacart/libraries/bootstrap.php');
        require_once JPATH_SITE . '/administrator/components/com_phocacart/libraries/phocacart/route/route.php';

        foreach ($item_ids as $item_id) {
            switch ($context) {
                case 'com_phocacart.phocacartcategory':
                    $category 		= \PhocacartCategory::getCategoryById($item_id);
                    $url = \PhocacartRoute::getCategoryRoute(
                        catid:$category->id,
                        catidAlias: $category->alias
                    );
                    break;
                case 'com_phocacart.phocacartitem':
                default:
                    $product = \PhocacartProduct::getProduct($item_id);

                    $url = \PhocacartRoute::getItemRoute(
                        id: $product->id,
                        catid:$product->catid,
                        idAlias: $product->alias,
                        catidAlias: $product->catalias,
                        lang: [$product->language]
                        );
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