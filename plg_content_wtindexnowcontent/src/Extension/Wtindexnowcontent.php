<?php

/**
 * @package       WT IndexNow package
 * @version     1.0.0
 * @Author      Sergey Tolkachyov, https://web-tolk.ru
 * @copyright   Copyright (C) 2024 Sergey Tolkachyov
 * @license     GNU/GPL 3
 * @since       1.0.0
 */

namespace Joomla\Plugin\Content\Wtindexnowcontent\Extension;

// No direct access
defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Event\AbstractEvent;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\Button\BasicButton;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;


final class Wtindexnowcontent extends CMSPlugin implements SubscriberInterface
{
	protected $autoloadLanguage = true;

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
			'onContentAfterSave'      => 'onContentAfterSave',   // в момент сохранения материала
			'onContentChangeState'    => 'onContentChangeState', // в момент публикации материала
			'onAfterDispatch'         => 'onAfterDispatch',      // добавляем кнопку для отправки
			'onAjaxWtindexnowcontent' => 'onAjaxWtindexnowcontent'  //при вызове AJAX, при нажатии на кнопку
		];

	}

	/**
	 * @param   Event  $event
	 *
	 *
	 * @since 1.0.0
	 */
	public function onContentAfterSave(Event $event): void
	{
		[$context, $article, $isNew] = array_values($event->getArguments());

		$this->triggerIndexNowEvent([$article->id]);
	}

	/**
	 * @param   array  $articles_links
	 *
	 * @return bool
	 */
	private function triggerIndexNowEvent(array $articles_links = []):bool
	{
		if (empty($articles_links)) return false;

		$event  = AbstractEvent::create('onWtIndexNowSendUrls',
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
	 * @param   Event  $event
	 *
	 *
	 * @since 1.0.0
	 */
	public function onContentChangeState(Event $event): void
	{
		[$context, $ids, $value] = array_values($event->getArguments());
		$this->triggerIndexNowEvent($ids);
	}

	/**
	 * Add a button to Joomla Toolbar for sending to Telegram via ajax
	 *
	 * @since 1.0.0
	 */
	public function onAfterDispatch(): void
	{
		if (!$this->getApplication()->isClient('administrator'))
		{
			return;
		}

		if ($this->getApplication()->getInput()->get('option') !== 'com_content')
		{
			return;
		}

		$toolbar = $this->getApplication()->getDocument()->getToolbar('toolbar');

		$lang = $this->getApplication()->getLanguage('site');
		$tag  = $lang->getTag();
		$this->getApplication()->getLanguage()
			->load('plg_content_wtindexnowcontent', JPATH_ADMINISTRATOR, $tag, true);

		$button = (new BasicButton('send-to-indexnow'))
			->text(Text::_('PLG_WTINDEXNOWCONTENT_BUTTON_LABEL'))
			->icon('fa-solid fa-arrow-up-right-dots')
			->onclick("window.wtindexnowcontent()");
		$toolbar->appendButton($button);


		/** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
		$wa = $this->getApplication()->getDocument()
			->getWebAssetManager();
		$wa->registerAndUseScript('wtindexnow.content.ajax.send', 'plg_content_wtindexnowcontent/ajaxsend.js');
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
		if (!Session::checkToken('GET'))
		{
			return;
		}

		if (!$this->getApplication()->isClient('administrator'))
		{
			return;
		}

		$data        = $this->getApplication()->getInput()->json->getArray();
		$article_ids = $data['article_ids'];

		if (!count($article_ids))
		{
			$event->setArgument('result', false);

			return;
		}

		$sent_articles = [];
		foreach ($article_ids as $article_id)
		{
			$model = $this->getApplication()->bootComponent('com_content')
				->getMVCFactory()
				->createModel('Article', 'Administrator', ['ignore_request' => true]);
			$model->setState('params', (new Registry()));
			$article = $model->getItem($article_id);

			// Don't send unpublished articles
			if (!$this->params->get('send_unpublished', 0) && $article->state < 1) continue;

			$sent_articles[] = $this->prepareUrl($article);
		}

		$result = $this->triggerIndexNowEvent($sent_articles);
        $message = $result ? Text::sprintf('PLG_WTINDEXNOWCONTENT_ARTICLES_SENT_SUCCESSFULLY', \count($sent_articles)): Text::sprintf('PLG_WTINDEXNOWCONTENT_ARTICLES_SENT_UNSUCCESSFULLY', \count($sent_articles));
		$event->setArgument('result', $message);
	}

	/**
	 * Returns the URL of the article
	 *
	 * @param $article
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	private function prepareUrl($article): string
	{

		$linkMode = $this->getApplication()->get('force_ssl', 0) >= 1 ? Route::TLS_FORCE : Route::TLS_IGNORE;

        return Route::link(
			'site',
			RouteHelper::getArticleRoute($article->id, $article->catid, $article->language),
			true,
			$linkMode,
			true
		);
	}
}