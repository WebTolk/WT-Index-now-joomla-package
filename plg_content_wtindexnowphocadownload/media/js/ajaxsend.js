/**
 * @package       WT IndexNow package
 * @subpackage    WT IndexNow - Phoca Download
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
	window.wtindexnowphocadownload = () => {

		let item_ids = [];

		let currentUrl = new URL(window.location.href);
		let view = currentUrl.searchParams.get('view');
		if ((view === 'phocadownloadfile' || view === 'phocadownloadcat') && currentUrl.searchParams.get('layout') === 'edit') {
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
		let context =  (view === 'phocadownloadfile' || view === 'phocadownloadfiles' ) ? 'com_phocadownload.phocadownloadfile' : 'com_phocadownload.phocadownloadcats';

		Joomla.request({
			url: 'index.php?option=com_ajax&plugin=wtindexnowphocadownload&group=content&format=json&' + Joomla.getOptions('csrf.token') + '=1',
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