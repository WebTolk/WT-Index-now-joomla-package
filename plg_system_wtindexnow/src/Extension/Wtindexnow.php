<?php
/**
 * @package       WT IndexNow package
 * @version        1.0.0
 * @Author         Sergey Tolkachyov, https://web-tolk.ru
 * @copyright  (c) 2024 - September 2025 Sergey Tolkachyov. All rights reserved.
 * @license        GNU/GPL3 http://www.gnu.org/licenses/gpl-3.0.html
 * @since          1.0.0
 */

namespace Joomla\Plugin\System\Wtindexnow\Extension;

use Exception;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Http\HttpFactory;
use function count;
use function defined;

// No direct access
defined('_JEXEC') or die;

final class Wtindexnow extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	protected $autoloadLanguage = true;

	/**
	 *
	 * @throws Exception
	 * @return array
	 *
	 * @since 1.0.0
	 *
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onWtIndexNowSendUrls' => 'onWtIndexNowSendUrls'
		];
	}

	/**
	 * Get urls and send them to IndexNow servers.
	 *
	 * @param   Event  $event
	 *
	 *
	 * @since 1.0.0
	 */
	public function onWtIndexNowSendUrls(Event $event): void
	{
		// Получили массив с url материалов
		$urls = $event->getArgument('urls');

		if (empty($urls)) return;

		/**
		 * Режимы работы: отправка сейчас или пишем в очередь
		 * Отправка сейчас - при редактировании материала на onContentAfterSave, onContentChangeState и т.д.
		 * Отправка сейчас - при ajax запросе
		 * Отправка в очередь - при режиме, включённом в настройках плагина
		 */
		$result_message = 'ТУт будет результат';
		$mode           = $this->params->get('mode', 'now');

		if ($mode == 'now')
		{
			$result = $this->sendUrlsToIndexNow($urls);
			$result_message = $result ? 'Успешно отправлено' : 'Ошибка отправки';
		}
		elseif ($mode == 'queue')
		{
			$result = $this->enqueueUrls($urls);
			$result_message = $result ? 'URL успешно добавлены в очередь' : 'Ошибка добавления URL в очередь';
		}

		$event->setArgument('result_message', $result_message);
		$event->setArgument('urls_sent', $urls);
		$event->setArgument('result', $result);
	}

	/**
	 * Send urls to IndexNow servers.
	 * You can use this method in your code via
	 * $app->bootPlugin('wtindexnow','system')->sendUrlsToIndexNow($urls);
	 * But it's better to use `indexNowSendUrls` event
	 *
	 * @param   array  $urls  Array of urls like ['https://example.com/page-1','https://example.com/page-2'] etc
	 *
	 * @return bool true on success
	 * @since 1.0.0
	 */
	public function sendUrlsToIndexNow(array $urls = []): bool
	{

		if (empty($key = $this->params->get('key', '')))
		{
			$this->saveToLog('IndexNow: there is empty key file param in plugin params', Log::ERROR);
			return false;
		}

		if (!file_exists(JPATH_SITE . DIRECTORY_SEPARATOR . $key . '.txt'))
		{
			$this->saveToLog('IndexNow: There is no key file ' . JPATH_SITE . DIRECTORY_SEPARATOR . $key . '.txt', Log::ERROR);
			return false;
		}

		// 10k limit per day
		// @link       https://web-tolk.ru
		$index_now_urls_limit = (int)$this->params->get('index_now_urls_limit', 10000);
		if((int)$this->params->get('urls_today_sent_count', 0) > $index_now_urls_limit) {
			$this->saveToLog('IndexNow: Too many requests per day. Limit is '.$index_now_urls_limit, Log::ERROR);
			return false;
		}

		$indexnow_url = $this->params->get('indexnow_url', 'https://api.indexnow.org/indexnow');

		$headers = [
			'Content-Type' => 'application/json',
			'Charset'      => 'UTF-8',
		];

		$body_request = [
			'host'    => (new Uri(Uri::root()))->getHost(),
			'key'     => $key,
			'urlList' => $urls
		];

		$http     = (new HttpFactory())->getHttp([], ['curl', 'stream']);
		$response = false;

		try
		{
			$response = $http->post($indexnow_url, $body_request, $headers, 5);
		}
		catch (Exception $e)
		{
			$this->saveToLog('IndexNow: . ' . $indexnow_url . ' ' . json_encode($body_request) . ' File: ' . $e->getFile() . ' Line: ' . $e->getLine() . ' ' . $e->getMessage() . ' ', Log::ERROR);
		}

		/**
		 * @link       https://web-tolk.ru
		 */
		switch ($response->getStatusCode())
		{
			case 400:
				$this->saveToLog('IndexNow: Bad request. Invalid format. ' . $indexnow_url . ' ' . json_encode($body_request), Log::ERROR);
			case 403:
				$this->saveToLog('IndexNow: Forbidden. In case of key not valid (e.g. key not found, file found but key not in the file). ' . $indexnow_url . ' ' . json_encode($body_request), Log::ERROR);
			case 422:
				$this->saveToLog('IndexNow: In case of URLs which don’t belong to the host or the key is not matching the schema in the protocol. ' . $indexnow_url . ' ' . json_encode($body_request), Log::ERROR);
			case 429:
				$this->saveToLog('IndexNow: Too Many Requests. ' . $indexnow_url . ' ' . json_encode($body_request), Log::ERROR);
				break;
			case 202:
			case 200:
			default:
				return true;
		}

		return false;
	}

	/**
	 * Function for to log plugin errors in plg_system_wtindexnow.log.php in
	 * Joomla log path. Default Log category plg_system_wtindexnow
	 *
	 * @param   string      $data      error message
	 * @param   int|string  $priority  Joomla Log priority
	 *
	 * @return  void
	 * @since   1.3.0
	 */
	private function saveToLog(string $data, int|string $priority = Log::NOTICE): void
	{
		Log::addLogger(
			[
				// Sets file name
				'text_file' => 'plg_system_wtindexnow.log.php',
			],
			// Sets all but DEBUG log level messages to be sent to the file
			Log::ALL & ~Log::DEBUG,
			['plg_system_wtindexnow']
		);

		Log::add($data, $priority, 'plg_system_wtindexnow');
	}

	/**
	 * Enqueue urls to database. Then task scheduler will send them to IndexNow servers.
	 *
	 * @param   array  $urls  Additional message params for current message
	 *
	 *
	 * @return bool true on success
	 * @since      1.0.0
	 */
	private function enqueueUrls(array $urls = []): bool
	{

		$db = $this->getDatabase();

		try
		{
			$query = $db->getQuery(true);
			$query->insert($db->quoteName('#__plg_system_wtindexnow_urls_queue'));
			$columns = ['url', 'created_at'];
			$date    = (new Date('now'))->toSql();
			$query->columns($db->quoteName($columns));
			foreach ($urls as $url)
			{
				$query->values(implode(',', $query->bindArray([$url, $date], ParameterType::STRING)));

			}

			$db->setQuery($query);

			if ($result = $db->execute())
			{
				$this->updateTodaySentUrlsCounter(\count($urls));

				return true;
			}
			else
			{
				return false;
			}
		}
		catch (Exception $e)
		{
			$this->saveToLog('IndexNow: ' . $e->getMessage(), Log::ERROR);
			$this->getApplication()->enqueueMessage('IndexNow: ' . $e->getMessage(), 'error');

			return false;
		}
	}

	/**
	 * Save the plugin parameters
	 *
	 * @param   int  $urls_count
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function updateTodaySentUrlsCounter(int $urls_count = 0)
	{
		$lustrun = $this->params->get('lastrun', null);
		$currentDate = \date('Y-m-d');

		if ($lustrun !== $currentDate) {
			$this->params->set('urls_today_sent_count', 0);
			$this->params->set('lastrun', $currentDate);
		}
		$count = $this->params->get('urls_today_sent_count', 0);
		$count = $count + $urls_count;

		$this->params->set('urls_today_sent_count', $count);

		$paramsJson = $this->params->toString();
		$db         = $this->getDatabase();
		$query      = $db->createQuery()
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('params') . ' = :params')
			->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
			->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
			->where($db->quoteName('element') . ' = ' . $db->quote('wtindexnow'))
			->bind(':params', $paramsJson);

		try
		{
			// Lock the tables to prevent multiple plugin executions causing a race condition
			$db->lockTable('#__extensions');
		}
		catch (Exception $e)
		{
			// If we can't lock the tables it's too risky to continue execution
			return false;
		}

		try
		{
			// Update the plugin parameters
			$result = $db->setQuery($query)->execute();

			$this->clearCacheGroups(['com_plugins']);
		}
		catch (Exception)
		{
			// If we failed to execute
			$db->unlockTables();
			$result = false;
		}

		try
		{
			// Unlock the tables after writing
			$db->unlockTables();
		}
		catch (Exception)
		{
			// If we can't lock the tables assume we have somehow failed
			$result = false;
		}

		return $result;

	}

	/**
	 * Clears cache groups. We use it to clear the plugins cache after we update the last run timestamp.
	 *
	 * @param   array  $clearGroups  The cache groups to clean
	 *
	 * @return  void
	 *
	 * @since   3.5
	 */
	private function clearCacheGroups(array $clearGroups)
	{
		foreach ($clearGroups as $group)
		{
			try
			{
				$options = [
					'defaultgroup' => $group,
					'cachebase'    => $this->getApplication()->get('cache_path', JPATH_CACHE),
				];

				$cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)->createCacheController('callback', $options);
				$cache->clean();
			}
			catch (Exception)
			{
				// Ignore it
			}
		}
	}
}