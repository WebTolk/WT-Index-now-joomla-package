<?php
/**
 * @package       WT IndexNow package
 * @version       1.0.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2024 Sergey Tolkachyov
 * @license       GNU/GPL 3
 * @since         1.0.0
 */

namespace Joomla\Plugin\Content\Wtindexnowcontent\Extension;

// No direct access
\defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Event\Model\AfterChangeStateEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\Registry\Registry;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\Button\BasicButton;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Event\SubscriberInterface;

use function count;


final class Wtindexnowcontent extends CMSPlugin implements SubscriberInterface
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
            'onAjaxWtindexnowcontent' => 'onAjaxWtindexnowcontent',
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
        if($event->getContext() !== 'com_content.article') return; // only for articles (content)
        if(!$this->main_plugin_params) return;
        if($this->main_plugin_params->get('mode', 'now') === 'manual') return;
        $article = $event->getItem();
        $this->triggerIndexNowEvent($this->prepareUrls([$article->id]));
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
        if($event->getContext() !== 'com_content.article') return; // only for articles (content)
        if(!$this->main_plugin_params) return;
        if($this->main_plugin_params->get('mode', 'now') === 'manual') return;
        $ids = $event->getPks();
        $this->triggerIndexNowEvent($this->prepareUrls($ids));
    }

    /**
     * Add a button to Joomla Toolbar for sending to Telegram via ajax
     *
     * @since 1.0.0
     */
    public function onAfterDispatch(): void
    {
        if(!$this->params->get('show_button', true)) return;
        if (!$this->getApplication()->isClient('administrator')) return;
        if ($this->getApplication()->getInput()->get('option') !== 'com_content') return;

        $toolbar = $this->getApplication()->getDocument()->getToolbar('toolbar');

        $lang = $this->getApplication()->getLanguage('site');
        $tag  = $lang->getTag();
        $this->getApplication()->getLanguage()
            ->load('plg_content_wtindexnowcontent', JPATH_ADMINISTRATOR, $tag, true);

        $button = (new BasicButton('send-to-indexnow'))
            ->text(Text::_('PLG_WTINDEXNOWCONTENT_BUTTON_LABEL'))
            ->icon('fa-solid fa-arrow-up-right-dots')
            ->onclick("window.wtindexnowcontent()")
            ->listCheck(true);
        $toolbar->appendButton($button);


        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $this->getApplication()->getDocument()
            ->getWebAssetManager();
        $wa->registerAndUseScript(
            'wtindexnow.content.ajax.send',
            'plg_content_wtindexnowcontent/ajaxsend.js'
        );
    }

    /**
     * Main ajax job. Send to IndexNow array of $article_ids here
     * from the button in the toolbar in the articles list
     * and article edit page
     *
     * @param   AjaxEvent  $event
     *
     * @since 1.0.0
     */
    public function onAjaxWtindexnowcontent(AjaxEvent $event): void
    {
        if (!Session::checkToken('GET')) {
            return;
        }

        if (!$this->getApplication()->isClient('administrator')) {
            return;
        }

        $data        = $this->getApplication()->getInput()->json->getArray();
        $article_ids = $data['article_ids'];

        if (!count($article_ids)) {
            $event->setArgument('result', false);

            return;
        }
        $result  = $this->triggerIndexNowEvent($this->prepareUrls($article_ids));
        $message = $result ? Text::sprintf(
            'PLG_WTINDEXNOWCONTENT_ARTICLES_SENT_SUCCESSFULLY',
            count($article_ids)
        ) : Text::sprintf('PLG_WTINDEXNOWCONTENT_ARTICLES_SENT_UNSUCCESSFULLY', count($article_ids));
        $event->setArgument('result', $message);
    }

    /**
     * Returns the URL of the article
     *
     * @param array $article_ids
     *
     * @return array
     *
     * @since 1.0.0
     */
    private function prepareUrls(array $article_ids): array
    {

        $linkMode = $this->getApplication()->get('force_ssl', 0) >= 1 ? Route::TLS_FORCE : Route::TLS_IGNORE;
        $sent_articles = [];
        foreach ($article_ids as $article_id) {
            $model = $this->getApplication()->bootComponent('com_content')
                ->getMVCFactory()
                ->createModel('Article', 'Administrator', ['ignore_request' => true]);
            // Trick due to bug in core populateState() method
            // @see https://github.com/joomla/joomla-cms/issues/46311
            $model->getState('category.id');
            $model->setState('params', (new Registry()));
            $article = $model->getItem($article_id);

            // Don't send unpublished articles
            if (!$this->params->get('send_unpublished', 0) && $article->state < 1) {
                continue;
            }

            $sent_articles[] = Route::link(
                'site',
                RouteHelper::getArticleRoute($article->id, $article->catid, $article->language),
                true,
                $linkMode,
                true
            );
        }
        return $sent_articles;
    }
}