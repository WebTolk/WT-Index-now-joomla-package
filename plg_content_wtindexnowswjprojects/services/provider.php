<?php
/**
 * @package       WT IndexNow package
 * @subpackage    WT IndexNow - SW JProjects
 * @version       1.0.1
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2025 Sergey Tolkachyov
 * @license       GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Content\Wtindexnowswjprojects\Extension\Wtindexnowswjprojects;

\defined('_JEXEC') or die;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param Container $container The DI container.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function register(Container $container)
    {
        $container->set(
            PluginInterface::class,
            function (Container $container) {
                $subject = $container->get(DispatcherInterface::class);
                $config = (array)PluginHelper::getPlugin('content', 'wtindexnowswjprojects');
                $plugin = new Wtindexnowswjprojects($subject, $config);
                $plugin->setApplication(Factory::getApplication());
                $plugin->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));
                return $plugin;
            }
        );
    }
};