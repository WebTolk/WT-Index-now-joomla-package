<?php
/**
 * @package       WT IndexNow package
 * @subpackage    WT IndexNow - SW JProjects
 * @version       1.0.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2025 Sergey Tolkachyov
 * @license       GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Joomla\Plugin\Content\Wtindexnowswjprojects\Extension;

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
use Joomla\Component\SWJProjects\Site\Helper\RouteHelper;
use Joomla\Event\SubscriberInterface;

use function count;
use function defined;
use function in_array;

// No direct access
defined('_JEXEC') or die;

final class Wtindexnowswjprojects extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    /**
     * @var array $allowedContext
     * @since 2.6.0
     */
    private array $allowedContext = [
        'com_swjprojects.projects',
        'com_swjprojects.project',
        'com_swjprojects.versions',
        'com_swjprojects.version',
        'com_swjprojects.documentation',
        'com_swjprojects.document',
        'com_swjprojects.categories',
        'com_swjprojects.category',
    ];

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
            'onAjaxWtindexnowswjprojects' => 'onAjaxWtindexnowswjprojects',
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
        if(!in_array($context = $event->getContext(), $this->allowedContext)) return;
        if(!$this->main_plugin_params) return;
        if($this->main_plugin_params->get('mode', 'now') === 'manual') return;
        $article = $event->getItem();
        $this->triggerIndexNowEvent($this->prepareUrls([$article->id], $context));
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
        if(!in_array($context = $event->getContext(), $this->allowedContext)) return;
        if(!$this->main_plugin_params) return;
        if($this->main_plugin_params->get('mode', 'now') === 'manual') return;
        $ids = $event->getPks();
        $this->triggerIndexNowEvent($this->prepareUrls($ids, $context));
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
        if ($this->getApplication()->getInput()->get('option') !== 'com_swjprojects') return;

        $toolbar = $this->getApplication()->getDocument()->getToolbar('toolbar');

        $lang = $this->getApplication()->getLanguage('site');
        $tag  = $lang->getTag();
        $this->getApplication()->getLanguage()
            ->load('plg_content_wtindexnowswjprojects', JPATH_ADMINISTRATOR, $tag, true);

        $button = (new BasicButton('send-to-indexnow'))
            ->text(Text::_('PLG_WTINDEXNOWSWJPROJECTS_BUTTON_LABEL'))
            ->icon('fa-solid fa-arrow-up-right-dots')
            ->onclick("window.wtindexnowswjprojects()");

        if(in_array($this->getApplication()->getInput()->get('view'), ['categories','documentation','projects','versions'])) {
            $button->listCheck(true);
        }
        $toolbar->appendButton($button);


        /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
        $wa = $this->getApplication()->getDocument()
            ->getWebAssetManager();
        $wa->registerAndUseScript(
            'wtindexnow.swjprojects.ajax.send',
            'plg_content_wtindexnowswjprojects/ajaxsend.js'
        );
    }

    /**
     * Main ajax job. Send to IndexNow array of projects,categories,versions,documents ids here
     * from the button in the toolbar in the elements list
     * and element edit page
     *
     * @param   AjaxEvent  $event
     *
     * @since 1.0.0
     */
    public function onAjaxWtindexnowswjprojects(AjaxEvent $event): void
    {
        if (!Session::checkToken('GET'))return;
        if (!$this->getApplication()->isClient('administrator')) return;

        $data        = $this->getApplication()->getInput()->json->getArray();
        $element_ids = $data['element_ids'];
        $context = $data['context'];

        if (!count($element_ids)) {
            $event->setArgument('result', false);

            return;
        }
        $result  = $this->triggerIndexNowEvent($this->prepareUrls($element_ids, $context));
        $message = $result ? Text::sprintf(
            'PLG_WTINDEXNOWSWJPROJECTS_ELEMENTS_SENT_SUCCESSFULLY',
            count($element_ids)
        ) : Text::sprintf('PLG_WTINDEXNOWSWJPROJECTS_ELEMENTS_SENT_UNSUCCESSFULLY', count($element_ids));
        $event->setArgument('result', $message);
    }

    /**
     * Returns the URL of the article
     *
     * @param   array   $element_ids
     * @param   string  $context
     *
     * @return array
     *
     * @since 1.0.0
     */
    private function prepareUrls(array $element_ids, string $context): array
    {
        $app = $this->getApplication();

        $linkMode  = $app->get('force_ssl', 0) >= 1 ? Route::TLS_FORCE : Route::TLS_IGNORE;
        $sent_urls = [];
        switch ($context) {
            case 'com_swjprojects.projects': // категория
            case 'com_swjprojects.project':
                $model = $app->bootComponent('com_swjprojects')
                    ->getMVCFactory()
                    ->createModel('Project', 'Administrator', ['ignore_request' => true]);
                break;
            case 'com_swjprojects.versions':
            case 'com_swjprojects.version':
                $model = $app->bootComponent('com_swjprojects')
                    ->getMVCFactory()
                    ->createModel('Version', 'Administrator', ['ignore_request' => true]);
                break;
            case 'com_swjprojects.documentation':
            case 'com_swjprojects.document':
                $model = $app->bootComponent('com_swjprojects')
                    ->getMVCFactory()
                    ->createModel('Document', 'Administrator', ['ignore_request' => true]);
                break;
            case 'com_swjprojects.categories':
            case 'com_swjprojects.category':
                $model = $app->bootComponent('com_swjprojects')
                    ->getMVCFactory()
                    ->createModel('Category', 'Administrator', ['ignore_request' => true]);
                break;
        }

        foreach ($element_ids as $element_id) {
            $element = $model->getItem($element_id);

            // Don't send unpublished articles
            if (!$this->params->get('send_unpublished', 0) && $element->state < 1) {
                continue;
            }

            switch ($context) {
                case 'com_swjprojects.projects': // категория
                case 'com_swjprojects.project':
                    $url = RouteHelper::getProjectRoute($element->id, $element->catid);
                    break;
                case 'com_swjprojects.versions':
                case 'com_swjprojects.version':
                    $url = RouteHelper::getVersionRoute($element->id, $element->project_id);
                    break;
                case 'com_swjprojects.documentation':
                case 'com_swjprojects.document':
                    $project_model = $app->bootComponent('com_swjprojects')
                        ->getMVCFactory()
                        ->createModel('Project', 'Administrator', ['ignore_request' => true]);
                    $project       = $project_model->getItem($element->project_id);
                    $url           = RouteHelper::getDocumentRoute($element->id, $element->project_id, $project->catid);
                    unset($project_model);
                    break;
                case 'com_swjprojects.categories':
                case 'com_swjprojects.category':
                default:
                    $url = RouteHelper::getProjectsRoute($element->id);
                    break;
            }

            $sent_urls[] = Route::link(
                'site',
                $url,
                true,
                $linkMode,
                true
            );
        }

        return $sent_urls;
    }
}