/**
 * @package       WT IndexNow package
 * @subpackage    WT IndexNow - Contacts (com_contact)
 * @version       1.0.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2025 Sergey Tolkachyov
 * @license       GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */
/**
 * Perhaps we will make one file for all the providers'
 * plugins, instead of duplicating similar functionality.
 */
(() => {
	window.wtindexnowcontacts = () => {

		let item_ids = [];

		let currentUrl = new URL(window.location.href);
		let view = currentUrl.searchParams.get('view');
		if ((view === 'contact' || view === 'category') && currentUrl.searchParams.get('layout') === 'edit') {
			item_ids.push(currentUrl.searchParams.get('id'))
		} else {
			let checkboxes = document.querySelectorAll('#adminForm input[name="cid[]"]:checked');

			if (checkboxes.length === 0) {
				alert('There is no items selected');
				return;
			}
			checkboxes.forEach(checkbox => {
				item_ids.push(checkbox.value);
			});
		}

		const component = currentUrl.searchParams.get('option');
		let context =  (component === 'com_contact') ? 'com_contact.contact' : 'com_categories.category';

		Joomla.request({
			url: 'index.php?option=com_ajax&plugin=wtindexnowcontacts&group=content&format=json&' + Joomla.getOptions('csrf.token') + '=1',
			method: 'POST',
			data: JSON.stringify({
				'item_ids': item_ids,
				'context': context,
			}),
			onSuccess: function (response, xhr) {
				if (response !== '') {
					let result = JSON.parse(response);
					console.log(result);
					if (result.success === false) {
						Joomla.renderMessages({
							'error': [result.message]
						});

					} else {
						Joomla.renderMessages({
							'info': [result.data]
						});
					}
				}
			},
		});
	};
})();