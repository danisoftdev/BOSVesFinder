(function () {
	'use strict';

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function qsa(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	document.addEventListener('DOMContentLoaded', function () {
		qsa('[data-bvf-open]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var id = btn.getAttribute('data-bvf-open');
				var d = id && document.getElementById(id);
				if (d && typeof d.showModal === 'function') {
					d.showModal();
				}
			});
		});

		qsa('[data-bvf-close]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var id = btn.getAttribute('data-bvf-close');
				var d = id ? document.getElementById(id) : btn.closest('dialog');
				if (d && d.open) {
					d.close();
				}
			});
		});

		qsa('[data-bvf-trip-complete]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var tid = btn.getAttribute('data-bvf-trip-complete');
				var form = qs('#bvf-trip-complete-form');
				var dlg = qs('#bvf-trip-complete-dialog');
				if (form && dlg && tid) {
					form.action = '/app/trips/' + tid + '/complete';
					dlg.showModal();
				}
			});
		});

		var gfDelForm = qs('#bvf-geofence-delete-form');
		var gfDelId = qs('#bvf-geofence-delete-id');
		var gfDelMsg = qs('#bvf-geofence-delete-msg');
		var gfDelDlg = qs('#bvf-geofence-delete-dialog');
		qsa('[data-bvf-geofence-del]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var id = btn.getAttribute('data-bvf-geofence-del');
				var name = btn.getAttribute('data-bvf-geofence-name') || '';
				if (gfDelForm && gfDelId && gfDelDlg) {
					gfDelId.value = id || '';
					if (gfDelMsg) {
						gfDelMsg.textContent = name
							? 'Delete geofence “' + name + '”? This cannot be undone.'
							: 'Delete this geofence? This cannot be undone.';
					}
					gfDelDlg.showModal();
				}
			});
		});

		var obForm = qs('#bvf-trip-crew-onboard-form');
		var obCrew = qs('#bvf-trip-crew-onboard-crew-id');
		var obFlag = qs('#bvf-trip-crew-onboard-flag');
		var obMsg = qs('#bvf-trip-crew-onboard-msg');
		var obDlg = qs('#bvf-trip-crew-onboard-dialog');
		qsa('[data-bvf-crew-onboard]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var cid = btn.getAttribute('data-crew-id');
				var next = btn.getAttribute('data-next-onboard');
				var label = btn.getAttribute('data-crew-label') || '';
				if (obForm && obCrew && obFlag && obDlg) {
					obCrew.value = cid || '';
					obFlag.value = next === '1' ? '1' : '0';
					if (obMsg) {
						obMsg.textContent =
							next === '1'
								? 'Mark ' + label + ' as onboard?'
								: 'Mark ' + label + ' as not onboard?';
					}
					obDlg.showModal();
				}
			});
		});

		var rmForm = qs('#bvf-trip-crew-remove-form');
		var rmCrew = qs('#bvf-trip-crew-remove-crew-id');
		var rmMsg = qs('#bvf-trip-crew-remove-msg');
		var rmDlg = qs('#bvf-trip-crew-remove-dialog');
		qsa('[data-bvf-crew-remove]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var cid = btn.getAttribute('data-crew-id');
				var label = btn.getAttribute('data-crew-label') || '';
				if (rmForm && rmCrew && rmDlg) {
					rmCrew.value = cid || '';
					if (rmMsg) {
						rmMsg.textContent = label
							? 'Remove ' + label + ' from this trip?'
							: 'Remove this crew member from the trip?';
					}
					rmDlg.showModal();
				}
			});
		});

	});
})();
