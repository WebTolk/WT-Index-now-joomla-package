<?php
/**
 * @package       WT IndexNow package
 * @version       1.0.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2025 Sergey Tolkachyov
 * @license       GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

namespace Joomla\Plugin\Task\Wtindexnowcron\Extension;

use CURLFile;
use Exception;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use Joomla\Filter\OutputFilter;
use Joomla\Registry\Registry;
use SimpleXMLElement;
use function defined;

\defined('_JEXEC') or die;

/**
 * A task plugin. For send urls to IndexNow service.
 *
 * @since 5.0.0
 */
final class Wtindexnowcron extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;
    use TaskPluginTrait;

    /**
     * @var string[]
     * @since 5.0.0
     */
    private const TASKS_MAP = [
        'plg_task_wtindexnowcron' => [
            'langConstPrefix' => 'PLG_WTINDEXNOWCRON',
            'method'          => 'processIndexNowQueue',
        ],
    ];
    /**
     * @var bool
     * @since 5.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * @inheritDoc
     *
     * @return string[]
     *
     * @since 5.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }


    /**
     * @param   ExecuteTaskEvent  $event  The `onExecuteTask` event.
     *
     * @return integer  The routine exit code.
     *
     * @throws Exception
     * @since  5.0.0
     */
    private function processIndexNowQueue(ExecuteTaskEvent $event): int
    {
        /** @var Registry $params Current task params */
        $params = new Registry($event->getArgument('params'));
        /** @var int $task_id The task id */
        $task_id = $event->getTaskId();
        $db = $this->getDatabase();
        $main_index_now_plugin = PluginHelper::getPlugin('system', 'wtindexnow');
        $main_plugin_params = new Registry($main_index_now_plugin->params);
        $query = $db->createQuery();
        $query->select($db->quoteName('url'))
            ->from($db->quoteName('#__plg_system_wtindexnow_urls_queue'))
            ->order($db->quoteName('created_at') . ' ASC')
            ->setLimit((int)$main_plugin_params->get('index_now_urls_limit', 10000));

        $urls = $db->setQuery($query)->loadColumn();

        if (empty($urls)) {
            return STATUS::OK;
        }

        $db->transactionStart();
        $result = false;
        try
        {

            $result = $this->getApplication()
                           ->bootPlugin('wtindexnow', 'system')
                           ->sendUrlsToIndexNow($urls);
            if($result) {
                $query->clear();
                $query->delete($db->quoteName('#__plg_system_wtindexnow_urls_queue'))
                    ->whereIn($db->quoteName('url'), $urls, ParameterType::STRING);
                $db->setQuery($query)->execute();
            }

            $db->transactionCommit();
        } catch (Exception $e)
        {
            $db->transactionRollback();
        }

        return $result ? Status::OK : Status::KNOCKOUT;
    }
}
