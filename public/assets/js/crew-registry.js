(function () {
	'use strict';

	function qs(sel, root) {
		return (root || document).querySelector(sel);
	}

	function qsa(sel, root) {
		return Array.prototype.slice.call((root || document).querySelectorAll(sel));
	}

	var formDlg = qs('#bvf-crew-form-dialog');
	var delDlg = qs('#bvf-crew-delete-dialog');
	var form = qs('#bvf-crew-save-form');
	var btnAdd = qs('#bvf-crew-open-add');
	var titleEl = qs('#bvf-crew-dialog-title');
	var dn = qs('#bvf-crew-dn');
	var role = qs('#bvf-crew-role');
	var vessel = qs('#bvf-crew-vessel');
	var appUser = qs('#bvf-crew-appuser');

	if (!formDlg || !form || !btnAdd) {
		return;
	}

	function closeAll() {
		if (formDlg.open) {
			formDlg.close();
		}
		if (delDlg && delDlg.open) {
			delDlg.close();
		}
	}

	qsa('.bvf-dialog-cancel').forEach(function (b) {
		b.addEventListener('click', closeAll);
	});

	formDlg.addEventListener('cancel', function (e) {
		e.preventDefault();
		formDlg.close();
	});
	if (delDlg) {
		delDlg.addEventListener('cancel', function (e) {
			e.preventDefault();
			delDlg.close();
		});
	}

	btnAdd.addEventListener('click', function () {
		form.action = '/app/crew';
		if (titleEl) {
			titleEl.textContent = 'Add crew member';
		}
		if (dn) {
			dn.value = '';
		}
		if (role) {
			role.value = 'crew';
		}
		if (vessel) {
			vessel.value = '';
		}
		if (appUser) {
			appUser.value = '';
		}
		formDlg.showModal();
		if (dn) {
			dn.focus();
		}
	});

	qsa('.bvf-crew-edit').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var id = btn.getAttribute('data-id');
			if (!id) {
				return;
			}
			form.action = '/app/crew/' + id + '/update';
			if (titleEl) {
				titleEl.textContent = 'Edit crew member';
			}
			if (dn) {
				dn.value = btn.getAttribute('data-display-name') || '';
			}
			if (role) {
				role.value = btn.getAttribute('data-role-slug') || 'crew';
			}
			if (vessel) {
				vessel.value = btn.getAttribute('data-vessel-id') || '';
			}
			if (appUser) {
				appUser.value = btn.getAttribute('data-app-user-id') || '';
			}
			formDlg.showModal();
			if (dn) {
				dn.focus();
			}
		});
	});

	qsa('.bvf-crew-delete').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!delDlg) {
				return;
			}
			var id = btn.getAttribute('data-id');
			var name = btn.getAttribute('data-display-name') || '';
			var idInput = qs('#bvf-crew-delete-id');
			var msg = qs('#bvf-crew-delete-msg');
			if (idInput) {
				idInput.value = id || '';
			}
			if (msg) {
				msg.textContent =
					'Remove ' + name + ' from the registry? Trip assignments for this person are also cleared.';
			}
			delDlg.showModal();
		});
	});
})();
