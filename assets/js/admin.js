(function ($) {
	'use strict';

	const selectors = {
		departments: {
			add: '#doc-booker-add-department',
			tableBody: '#doc-booker-departments-table tbody',
			template: '#doc-booker-department-template',
			row: '.doc-booker-department-row',
			remove: '.doc-booker-remove-row',
		},
		timeSlots: {
			add: '#doc-booker-add-time-slot',
			tableBody: '#doc-booker-time-slots-table tbody',
			template: '#doc-booker-time-slot-template',
			row: '.doc-booker-time-slot-row',
			remove: '.doc-booker-remove-slot',
		},
		dayCard: '.doc-booker-day-card',
	};

	const animateRow = ($row) => {
		$row.css({ opacity: 0, transform: 'translateY(12px)' });
		requestAnimationFrame(() => {
			$row.css({ transition: 'all 0.28s ease', opacity: 1, transform: 'translateY(0)' });
		});
	};

	const cloneRow = (templateSelector, rowSelector) => {
		const template = document.querySelector(templateSelector);

		if (!template || !template.content) {
			return null;
		}

		const sourceEl = template.content.firstElementChild || template.content.children?.[0];

		if (!sourceEl) {
			return null;
		}

		const $clone = $(sourceEl.cloneNode(true));

		if ($clone.is(rowSelector)) {
			return $clone;
		}

		const $row = $clone.find(rowSelector).first();
		return $row.length ? $row : null;
	};

	const addDepartmentRow = () => {
		const $row = cloneRow(selectors.departments.template, selectors.departments.row);

		if (!$row) {
			return;
		}

		$row.find('input, textarea').each(function () {
			this.value = '';
		});

		$(selectors.departments.tableBody).append($row);
		animateRow($row);
	};

	const removeDepartmentRow = (button) => {
		const $row = $(button).closest(selectors.departments.row);
		const $rows = $(selectors.departments.tableBody).find(selectors.departments.row);

		if ($rows.length <= 1) {
			$row.find('input, textarea').val('');
			return;
		}

		$row.css({ transition: 'all 0.2s ease', opacity: 0, transform: 'translateY(-8px)' });
		setTimeout(() => {
			$row.remove();
		}, 220);
	};

	const addTimeSlotRow = () => {
		const $row = cloneRow(selectors.timeSlots.template, selectors.timeSlots.row);

		if (!$row) {
			return;
		}

		$row.find('input[type="time"]').each(function () {
			this.value = '';
		});

		$(selectors.timeSlots.tableBody).append($row);
		animateRow($row);
	};

	const removeTimeSlotRow = (button) => {
		const $row = $(button).closest(selectors.timeSlots.row);
		const $rows = $(selectors.timeSlots.tableBody).find(selectors.timeSlots.row);

		if ($rows.length <= 1) {
			$row.find('input[type="time"]').val('');
			return;
		}

		$row.css({ transition: 'all 0.2s ease', opacity: 0, transform: 'translateY(-8px)' });
		setTimeout(() => {
			$row.remove();
		}, 220);
	};

	const syncDayCardState = ($card) => {
		const $checkbox = $card.find('input[type="checkbox"]');
		const isChecked = $checkbox.is(':checked');
		const $status = $card.find('.doc-booker-day-card__status');
		const activeText = $status.data('active-text');
		const inactiveText = $status.data('inactive-text');

		$card.toggleClass('is-active', isChecked);
		$status.text(isChecked ? activeText : inactiveText);
	};

	const animateExistingRows = (tableSelector, rowSelector) => {
		const $rows = $(tableSelector).find(rowSelector);

		$rows.each(function (index) {
			const delay = index * 40;
			$(this).css({ opacity: 0, transform: 'translateY(18px)' });
			setTimeout(() => {
				$(this).css({ transition: 'all 0.25s ease', opacity: 1, transform: 'translateY(0)' });
			}, delay);
		});
	};

	$(document).on('click', selectors.departments.add, (event) => {
		event.preventDefault();
		addDepartmentRow();
	});

	$(document).on('click', selectors.departments.remove, function (event) {
		event.preventDefault();
		removeDepartmentRow(this);
	});

	$(document).on('click', selectors.timeSlots.add, (event) => {
		event.preventDefault();
		addTimeSlotRow();
	});

	$(document).on('click', selectors.timeSlots.remove, function (event) {
		event.preventDefault();
		removeTimeSlotRow(this);
	});

	$(document).on('change', `${selectors.dayCard} input[type="checkbox"]`, function () {
		syncDayCardState($(this).closest(selectors.dayCard));
	});

	$(function () {
		animateExistingRows(selectors.departments.tableBody, selectors.departments.row);
		animateExistingRows(selectors.timeSlots.tableBody, selectors.timeSlots.row);

		$(selectors.dayCard).each(function () {
			syncDayCardState($(this));
		});
	});
})(jQuery);
