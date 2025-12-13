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
	window.wtindexnowcontent = () => {

		let article_ids = [];

		let currentUrl = new URL(window.location.href);

		if (currentUrl.searchParams.get('view') === 'article' && currentUrl.searchParams.get('layout') === 'edit') {
			article_ids.push(currentUrl.searchParams.get('id'))
		} else {
			let checkboxes = document.querySelectorAll('#adminForm input[name="cid[]"]:checked');

			if (checkboxes.length === 0) {
				alert('There is no articles selected');
				return;
			}
			checkboxes.forEach(checkbox => {
				article_ids.push(checkbox.value);
			});
		}

		Joomla.request({
			url: 'index.php?option=com_ajax&plugin=wtindexnowcontent&group=content&format=json&' + Joomla.getOptions('csrf.token') + '=1',
			method: 'POST',
			data: JSON.stringify({
				'article_ids': article_ids,
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