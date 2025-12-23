# WT IndexNow package for Joomla

## EN
The plugin package is designed to send the URLs of Joomla sites to search engines for reindexing using the IndexNow protocol. According to the documentation, the protocol is supported by all major search engines except Google. The protocol is used to receive URLs from sites that need to be reindexed or indexed for the first time as quickly as possible. Sending site addresses in this way will significantly speed up the indexing of new or modified content by search engines. Search engines use this protocol to exchange data with each other, so by sending a URL to one of them, you share it with everyone at once.

> After some time, more than 10,000 URLs can be found in the index. Keep this in mind when working with content.

### We work with IndexNow users for Joomla
#### IndexNow System Service
The WT IndexNow system plugin creates a key to identify site requests and sends addresses according to the settings. Go to its parameters and save them for automatic key generation. A file with the same name and content will appear in the root of the site. This is normal and necessary for using the IndexNow protocol.
- **Sending now** - In this mode, the URL is sent when the item is saved/published.
- **Sending to queue** - URLs for reindexing are recorded in the database, and the task scheduler plugin sends them for reindexing according to a schedule (for example, once a minute, once every 5 minutes).
- **Manually** - It is also possible to send the URL manually using the toolbar button in the list of items or on the item editing page.

#### Plugins providers
IndexNow provider plugins are created for different components. They add buttons to the toolbar of Joomla components and create links to send through the main plugin.

At the moment, the package includes providers for the following components:
- Joomla articles and categories of articles (`com_content`)
- Joomla contacts and categories of contacts (`com_contact`) 
- [SW JProjects](https://web-tolk.ru/dev/components/sw-jprojects) ([GitHub repository](https://github.com/WebTolk/SWJProjects))
- [JoomShopping](https://www.webdesigner-profi.de/joomla-webdesign/shop.html) [Joomla Extensions Catalog](https://extensions.joomla.org/extension/joomshopping/)
- [Phoca Download](https://www.phoca.cz/phocadownload)
- [Phoca Shopping Cart](https://www.phoca.cz/phocacart)
- [RadicalMart](https://radicalmart.ru)

---
## RU
Пакет плагинов предназначен для отправки URL сайтов на Joomla в поисковые системы на переиндексацию по протоколу IndexNow. Согласно документации протокол поддерживают все крупнейшие поисковые системы, кроме Google. Протокол служит для получения от сайтов URL, которые нужно переиндексировать заново или проиндексировать в первый раз как можно быстрее. Отправка адресов сайта таким образом существенно ускорит индексацию нового или изменённого контента поисковыми системами. Поисковые системы по этому протоколу обмениваются друг с другом данными, поэтому отправив URL в одну из них вы сообщаете их сразу всем.

> По протоколу IndexNow в сутки можно отправить не более 10000 URL адресов. Учитывайте это во время работы с контентом.

### Режимы работы плагинов IndexNow для Joomla
#### Системный плагин WT IndexNow
Системный плагин WT IndexNow создаёт ключ для идентификации запросов сайта и занимается отправкой адресов согласно настройкам. Зайдите в его параметры и сохраните их для автоматической генерации ключа. В корне сайта появится файл с таким же именем и содержанием. Это нормально и необходимо для использования протокола IndexNow.
- **Отправка сейчас** - в этом режиме отправка URL происходит в момент сохранения/публикации элемента.
- **Отправка в очередь** - URL для переиндексации записываются в базу данных, а на переиндексацию их отправляет плагин планировщика задач по расписанию (например раз в минуту, раз в 5 минут).
- **Вручную** - также есть возможность отправлять URL вручную с помощью кнопки в тулбаре в списке элементов или на странице редактирования элемента.

#### Плагины-провайдеры
Плагины-провайдеры IndexNow созданы для разных компонентов. Они добавляют кнопки в тулбар компонентов Joomla и создают ссылки для отправки через основной плагин.

На данный момент в пакете представлены провайдеры для следующих компонентов:
- материалы и категории материалов Joomla
- контакты и категории контактов Joomla
- [SW JProjects](https://web-tolk.ru/dev/components/sw-jprojects) ([GitHub repo](https://github.com/WebTolk/SWJProjects))
- [JoomShopping](https://www.webdesigner-profi.de/joomla-webdesign/shop.html) [Joomla Extensions Directory](https://extensions.joomla.org/extension/joomshopping/)
- [Phoca Download](https://www.phoca.cz/phocadownload)
- [Phoca Cart](https://www.phoca.cz/phocacart)
- [RadicalMart](https://radicalmart.ru)