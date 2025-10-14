(function ($, window) {
	'use strict';

	const settings = window.DocBookerDirectory || {};

	const collectFiltersFromForm = ($form) => {
		return {
			department: $form.find('[name="department"]').val() || '',
			name: $form.find('[name="name"]').val() || '',
			date: $form.find('[name="date"]').val() || '',
			availability: $form.find('[name="availability"]').val() || '',
			letter: ($form.closest('.doc-booker-directory').data('active-letter') || 'all').toLowerCase(),
		};
	};

	const setLoading = ($root, isLoading) => {
		$root.toggleClass('is-loading', !!isLoading);
	};

	const showNotice = ($notice, message, type = 'info') => {
		if (!message) {
			$notice.attr('hidden', true).text('');
			return;
		}

		$notice
			.removeAttr('hidden')
			.text(message)
			.attr('data-type', type);
	};

	const syncLetterState = ($root, letter) => {
		const lower = (letter || 'all').toLowerCase();
		$root.data('active-letter', lower);
		$root
			.find('.doc-booker-directory__letter')
			.each(function () {
				const $btn = $(this);
				$btn.toggleClass('is-active', $btn.data('letter') === lower);
			});
	};

	const updateResults = ($root, payload) => {
		const $results = $root.find('#doc-booker-directory-results');
		const $counter = $root.find('#doc-booker-directory-count');

		if (typeof payload.html === 'string') {
			$results.html(payload.html);
		}

		if (typeof payload.total !== 'undefined') {
			$counter.text(payload.total);
		}
	};

	const requestFilters = ($root, filters) => {
		const ajaxUrl = settings.ajaxUrl;

		if (!ajaxUrl) {
			return;
		}

		const $notice = $root.find('#doc-booker-directory-notice');

		setLoading($root, true);
		showNotice($notice, '');

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			dataType: 'json',
			data: {
				action: settings.action,
				nonce: settings.nonce,
				filters,
			},
		})
			.done((response) => {
				if (!response || !response.success) {
					const message = response?.data?.message || settings.i18n?.error;
					showNotice($notice, message, 'error');
					return;
				}

				updateResults($root, response.data);
			})
			.fail(() => {
				showNotice($notice, settings.i18n?.error, 'error');
			})
			.always(() => {
				setLoading($root, false);
			});
	};

	$(function () {
		const $directories = $('.doc-booker-directory');

		if (!$directories.length) {
			return;
		}

		$directories.each(function () {
			const $root = $(this);
			const $form = $root.find('.doc-booker-directory__form');
			const $letters = $root.find('.doc-booker-directory__letter');

			let filters = $.extend({}, settings.defaultFilters || {}, collectFiltersFromForm($form));

			syncLetterState($root, filters.letter);

			$form.on('submit', function (event) {
				event.preventDefault();
				filters = $.extend({}, filters, collectFiltersFromForm($form));
				syncLetterState($root, filters.letter);
				requestFilters($root, filters);
			});

			$letters.on('click', function (event) {
				event.preventDefault();
				const $button = $(this);
				const letter = ($button.data('letter') || 'all').toLowerCase();

				if (letter === filters.letter) {
					return;
				}

				filters.letter = letter;
				syncLetterState($root, letter);
				requestFilters($root, filters);
			});
		});
	});
})(jQuery, window);
