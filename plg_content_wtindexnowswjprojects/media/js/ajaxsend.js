/**
 * @package       WT IndexNow package
 * @subpackage    WT IndexNow - SW JProjects
 * @version       1.0.0
 * @Author        Sergey Tolkachyov, https://web-tolk.ru
 * @copyright     Copyright (C) 2025 Sergey Tolkachyov
 * @license       GNU/GPL http://www.gnu.org/licenses/gpl-3.0.html
 * @since         1.0.0
 */

(() => {
	window.wtindexnowswjprojects = () => {

		let element_ids = [];

		let currentUrl = new URL(window.location.href);
		const singleElementView = ['project', 'version', 'document','category'];
		const currentView = currentUrl.searchParams.get('view');
		if (singleElementView.includes(currentView)) {
			element_ids.push(currentUrl.searchParams.get('id'))
		} else {
			let checkboxes = document.querySelectorAll('#adminForm input[name="cid[]"]:checked');

			if (checkboxes.length === 0) {
				alert('There is no elements selected');
				return;
			}
			checkboxes.forEach(checkbox => {
				element_ids.push(checkbox.value);
			});
		}

		Joomla.request({
			url: 'index.php?option=com_ajax&plugin=wtindexnowswjprojects&group=content&format=json&'+Joomla.getOptions('csrf.token')+'=1',
			method: 'POST',
			data: JSON.stringify({
				'element_ids': element_ids,
				'context': 'com_swjprojects.'+currentView,
			}),
			onSuccess: function (response, xhr) {
				if (response !== '') {
					let result = JSON.parse(response);
					if (result.success === false) {
						Joomla.renderMessages({
							'error': ['IndexNow: ' + [result.message]]
						});

					} else {
						Joomla.renderMessages({
							'info': ['IndexNow: ' +result.data]
						});
					}

				}
			},
		});

	};
})();