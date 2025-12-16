/**
 * @package       WT IndexNow package
 * @version     1.0.0
 * @Author      Sergey Tolkachyov, https://web-tolk.ru
 * @copyright   Copyright (C) 2024 Sergey Tolkachyov
 * @license     GNU/GPL 3
 * @since       1.0.0
 */
/**
 * Perhaps we will make one file for all the providers'
 * plugins, instead of duplicating similar functionality.
 */
(() => {
	window.wtindexnowjshopping = () => {

		let items_ids = [];

		let currentUrl = new URL(window.location.href);
		const controller = currentUrl.searchParams.get('controller');
		const task = currentUrl.searchParams.get('task');
		if ((controller === 'categories' || controller === 'products') && task === 'edit') {
			items_ids.push(currentUrl.searchParams.get('id'))
		} else {
			let checkboxes = document.querySelectorAll('#adminForm input[name="cid[]"]:checked');

			if (checkboxes.length === 0) {
				alert('There is no items selected');
				return;
			}
			checkboxes.forEach(checkbox => {
				items_ids.push(checkbox.value);
			});
		}
		let context =  (controller === 'categories') ? 'com_jshopping.category' : 'com_jshopping.product';
		Joomla.request({
			url: 'index.php?option=com_ajax&plugin=wtindexnowjshopping&group=jshoppingadmin&format=json&' + Joomla.getOptions('csrf.token') + '=1',
			method: 'POST',
			data: JSON.stringify({
				'items_ids': items_ids,
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