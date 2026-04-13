(function () {
	'use strict';

	function $(sel, root) {
		return (root || document).querySelector(sel);
	}

	function initPanel(root) {
		var cfg = window.bvfCaptain;
		if (!cfg || !cfg.restBase) {
			return;
		}

		var tripId = null;
		var captainTripStatus = null;
		var watchId = null;
		var intervalId = null;
		var tracking = false;

		var map = null;
		var mapMarker = null;
		var headingLine = null;
		var mapDidFit = false;
		var lastGpsHeadingAt = 0;
		var lastCoordCache = null;
		var orientationHandler = null;
		var lastShownLat = null;
		var lastShownLng = null;

		var navSection = document.getElementById('bvf-captain-nav');
		var speedEl = document.getElementById('bvf-captain-speed');
		var compassNeedleG = document.getElementById('bvf-compass-needle-g');
		var compassDegEl = document.getElementById('bvf-captain-compass-deg');
		var navHintEl = document.getElementById('bvf-captain-nav-hint');
		var sosSentStrip = document.getElementById('bvf-sos-sent-strip');
		var sosSentTitle = document.getElementById('bvf-sos-sent-title');
		var sosSentText = document.getElementById('bvf-sos-sent-text');
		var SOS_KEY_TRIP = 'bvf_sos_sent_trip';
		var SOS_KEY_AT = 'bvf_sos_sent_at';

		function hideSosSentStrip() {
			if (sosSentStrip) {
				sosSentStrip.hidden = true;
			}
			try {
				sessionStorage.removeItem(SOS_KEY_TRIP);
				sessionStorage.removeItem(SOS_KEY_AT);
			} catch (ignore) {}
		}

		function restoreSosSentStrip() {
			if (!sosSentStrip || !sosSentText || !tripId) {
				return;
			}
			var sid = null;
			try {
				sid = sessionStorage.getItem(SOS_KEY_TRIP);
			} catch (e) {
				return;
			}
			if (sid !== String(tripId)) {
				return;
			}
			var iso = null;
			try {
				iso = sessionStorage.getItem(SOS_KEY_AT);
			} catch (ignore) {}
			var t = iso ? new Date(iso).toLocaleString() : new Date().toLocaleString();
			if (sosSentTitle && cfg.i18n && cfg.i18n.sosSentStripTitle) {
				sosSentTitle.textContent = cfg.i18n.sosSentStripTitle;
			}
			var detail =
				cfg.i18n && cfg.i18n.sosSentStripDetail
					? cfg.i18n.sosSentStripDetail
					: 'Notified at {time}.';
			sosSentText.textContent = detail.replace('{time}', t);
			sosSentStrip.hidden = false;
		}

		function recordSosSent() {
			if (!tripId) {
				return;
			}
			try {
				sessionStorage.setItem(SOS_KEY_TRIP, String(tripId));
				sessionStorage.setItem(SOS_KEY_AT, new Date().toISOString());
			} catch (ignore) {}
			restoreSosSentStrip();
		}

		function destinationPoint(latDeg, lngDeg, bearingDeg, distanceM) {
			var R = 6378137;
			var brng = (bearingDeg * Math.PI) / 180;
			var lat1 = (latDeg * Math.PI) / 180;
			var lng1 = (lngDeg * Math.PI) / 180;
			var ang = distanceM / R;
			var lat2 = Math.asin(
				Math.sin(lat1) * Math.cos(ang) + Math.cos(lat1) * Math.sin(ang) * Math.cos(brng)
			);
			var lng2 =
				lng1 +
				Math.atan2(
					Math.sin(brng) * Math.sin(ang) * Math.cos(lat1),
					Math.cos(ang) - Math.sin(lat1) * Math.sin(lat2)
				);
			return [(lat2 * 180) / Math.PI, (lng2 * 180) / Math.PI];
		}

		function stopOrientationListener() {
			if (orientationHandler) {
				window.removeEventListener('deviceorientation', orientationHandler, true);
				orientationHandler = null;
			}
		}

		function tryDeviceOrientationListener() {
			stopOrientationListener();
			orientationHandler = function (e) {
				if (!tracking) {
					return;
				}
				if (Date.now() - lastGpsHeadingAt < 2500) {
					return;
				}
				var h = null;
				if (e.webkitCompassHeading != null && !isNaN(e.webkitCompassHeading)) {
					h = e.webkitCompassHeading;
				} else if (e.absolute && e.alpha != null && !isNaN(e.alpha)) {
					h = (360 - e.alpha + 360) % 360;
				}
				if (h == null || isNaN(h)) {
					return;
				}
				setCompassDeg(h);
				if (lastCoordCache && lastCoordCache.lat != null && lastCoordCache.lng != null) {
					updateMapFromPosition(lastCoordCache.lat, lastCoordCache.lng, h);
				}
			};
			if (
				typeof DeviceOrientationEvent !== 'undefined' &&
				typeof DeviceOrientationEvent.requestPermission === 'function'
			) {
				DeviceOrientationEvent.requestPermission()
					.then(function (s) {
						if (s === 'granted' && orientationHandler) {
							window.addEventListener('deviceorientation', orientationHandler, true);
						}
					})
					.catch(function () {
						orientationHandler = null;
					});
			} else {
				window.addEventListener('deviceorientation', orientationHandler, true);
			}
		}

		function setCompassDeg(deg) {
			if (compassNeedleG) {
				compassNeedleG.setAttribute('transform', 'rotate(' + deg + ' 50 50)');
			}
			if (compassDegEl) {
				compassDegEl.textContent = String(Math.round(deg)) + '°';
			}
		}

		function resetCompassDisplay() {
			if (compassNeedleG) {
				compassNeedleG.setAttribute('transform', 'rotate(0 50 50)');
			}
			if (compassDegEl) {
				compassDegEl.textContent = '—';
			}
		}

		function boatDivIcon(headingDeg) {
			var rot = headingDeg != null && !isNaN(headingDeg) ? headingDeg : 0;
			return L.divIcon({
				className: 'bvf-boat-marker-outer',
				html:
					'<div class="bvf-boat-marker__rotate" style="transform:rotate(' +
					rot +
					'deg)"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 56" width="34" height="40" aria-hidden="true" focusable="false"><path fill="#0d47a1" stroke="#fff" stroke-width="1.2" stroke-linejoin="round" d="M24 3 L7 38 l4 15h26l4-15L24 3z"/><path fill="#64b5f6" d="M24 12 L15 36h18L24 12z"/></svg></div>',
				iconSize: [40, 44],
				iconAnchor: [20, 22],
			});
		}

		function setBoatMarkerRotation(deg) {
			if (!mapMarker || deg == null || isNaN(deg)) {
				return;
			}
			var wrap = mapMarker.getElement();
			if (!wrap) {
				return;
			}
			var rotEl = wrap.querySelector('.bvf-boat-marker__rotate');
			if (rotEl) {
				rotEl.style.transform = 'rotate(' + deg + 'deg)';
			}
		}

		function ensureMap() {
			if (map || typeof L === 'undefined') {
				return;
			}
			var el = document.getElementById('bvf-captain-map-el');
			if (!el) {
				return;
			}
			map = L.map(el, { zoomControl: true, scrollWheelZoom: true });
			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '&copy; OpenStreetMap',
			}).addTo(map);
			mapMarker = L.marker([0, 0], { icon: boatDivIcon(0), zIndexOffset: 800 }).addTo(map);
			headingLine = L.polyline(
				[
					[0, 0],
					[0, 0],
				],
				{ color: '#1565c0', weight: 4, dashArray: '10 12', opacity: 0 }
			).addTo(map);
		}

		function updateMapFromPosition(lat, lng, headingOpt) {
			ensureMap();
			if (!map || !mapMarker || !headingLine) {
				return;
			}
			var ll = [lat, lng];
			lastShownLat = lat;
			lastShownLng = lng;

			mapMarker.setLatLng(ll);

			var showLine =
				headingOpt != null && !isNaN(headingOpt) && headingOpt >= 0 && headingOpt <= 360;
			if (showLine) {
				setBoatMarkerRotation(headingOpt);
				var far = destinationPoint(lat, lng, headingOpt, 450);
				headingLine.setLatLngs([ll, far]);
				headingLine.setStyle({ opacity: 0.9 });
			} else {
				headingLine.setLatLngs([ll, ll]);
				headingLine.setStyle({ opacity: 0 });
			}
			if (!mapDidFit) {
				map.setView(ll, 16);
				mapDidFit = true;
				requestAnimationFrame(function () {
					map.invalidateSize();
				});
			} else {
				map.panTo(ll, { animate: true, duration: 0.3, easeLinearity: 0.2 });
			}
		}

		function setNavDashVisible(show) {
			if (navSection) {
				navSection.hidden = !show;
			}
			if (!show) {
				stopOrientationListener();
				lastCoordCache = null;
				lastGpsHeadingAt = 0;
				lastShownLat = null;
				lastShownLng = null;
				mapDidFit = false;
				resetCompassDisplay();
				if (speedEl) {
					speedEl.hidden = true;
					speedEl.textContent = '';
				}
				if (navHintEl) {
					navHintEl.textContent = '';
				}
				return;
			}
			requestAnimationFrame(function () {
				if (map) {
					map.invalidateSize();
				}
			});
		}

		var statusEl = $('.bvf-captain__status', root);
		var msgEl = $('.bvf-captain__msg', root);
		var btnStart = $('.bvf-captain__start', root);
		var btnPause = $('.bvf-captain__pause', root);
		var btnEnd = $('.bvf-captain__end', root);
		var btnSos = $('.bvf-captain__sos', root);
		var fuelForm = $('.bvf-captain-fuel__form', root) || document.querySelector('.bvf-captain-fuel__form');
		var fuelLoaded = document.getElementById('bvf-fuel-loaded');
		var fuelConsumed = document.getElementById('bvf-fuel-consumed');
		var fuelNotes = document.getElementById('bvf-fuel-notes');
		var fuelSubmit =
			$('.bvf-captain-fuel__submit', root) || document.querySelector('.bvf-captain-fuel__submit');
		var fuelOpen = document.getElementById('bvf-captain-fuel-open');
		var fuelDialog = document.getElementById('bvf-captain-fuel-dialog');
		var fuelRecent = $('.bvf-captain-fuel__recent', root);
		var fuelList = $('.bvf-captain-fuel__list', root);
		var startTripDialog = document.getElementById('bvf-captain-start-trip-dialog');
		var startTripForm = document.getElementById('bvf-captain-start-trip-form');
		var startTripVessel = document.getElementById('bvf-start-trip-vessel');
		var startTripOrigin = document.getElementById('bvf-start-trip-origin');
		var startTripDest = document.getElementById('bvf-start-trip-dest');
		var startTripErr = document.getElementById('bvf-start-trip-err');
		var startTripSubmit = document.getElementById('bvf-start-trip-submit');
		var startTripCaptainId = document.getElementById('bvf-start-trip-captain-id');
		var startTripStatus = document.getElementById('bvf-start-trip-status');
		var jobCertWrap = document.getElementById('bvf-captain-job-cert-wrap');
		var jobCertLink = document.getElementById('bvf-captain-job-cert-link');

		function setMsg(text, isErr) {
			if (!msgEl) {
				return;
			}
			msgEl.textContent = text || '';
			msgEl.classList.toggle('bvf-captain__msg--err', !!isErr);
		}

		function setFuelUiEnabled(on) {
			var els = [fuelLoaded, fuelConsumed, fuelNotes, fuelSubmit, fuelOpen];
			els.forEach(function (el) {
				if (el) {
					el.disabled = !on;
				}
			});
			if (fuelRecent) {
				if (!on) {
					fuelRecent.hidden = true;
				}
			}
			if (fuelList && !on) {
				fuelList.innerHTML = '';
			}
		}

		function parseLitresInput(el) {
			if (!el || el.value === undefined) {
				return null;
			}
			var v = String(el.value).trim();
			if (v === '') {
				return null;
			}
			var n = parseFloat(v);
			if (isNaN(n) || n < 0) {
				return null;
			}
			return n;
		}

		function formatFuelRow(row) {
			var parts = [];
			if (row.fuel_loaded_litres != null && row.fuel_loaded_litres !== '') {
				parts.push('+' + row.fuel_loaded_litres + ' L loaded');
			}
			if (row.fuel_consumed_litres != null && row.fuel_consumed_litres !== '') {
				parts.push('-' + row.fuel_consumed_litres + ' L used');
			}
			var line = parts.length ? parts.join(' · ') : 'Entry';
			var note = row.notes ? String(row.notes) : '';
			if (note.length > 80) {
				note = note.slice(0, 77) + '…';
			}
			var when = row.created_at ? String(row.created_at) : '';
			return { line: line, note: note, when: when };
		}

		function refreshFuelLogs() {
			if (!tripId || !fuelList) {
				return;
			}
			api('trips/' + tripId + '/fuel-logs', { method: 'GET' })
				.then(function (data) {
					if (!Array.isArray(data)) {
						throw new Error('Bad response');
					}
					if (fuelRecent) {
						fuelRecent.hidden = false;
					}
					fuelList.innerHTML = '';
					var slice = data.slice(0, 10);
					if (!slice.length) {
						var empty = document.createElement('li');
						empty.textContent =
							cfg.i18n && cfg.i18n.fuelNoEntries ? cfg.i18n.fuelNoEntries : 'No fuel entries yet.';
						fuelList.appendChild(empty);
						return;
					}
					slice.forEach(function (row) {
						var fmt = formatFuelRow(row);
						var li = document.createElement('li');
						li.textContent = fmt.line;
						if (fmt.note) {
							var sub = document.createElement('span');
							sub.className = 'bvf-captain-fuel__list-meta';
							sub.textContent = fmt.note;
							li.appendChild(document.createElement('br'));
							li.appendChild(sub);
						}
						if (fmt.when) {
							var meta = document.createElement('span');
							meta.className = 'bvf-captain-fuel__list-meta';
							meta.textContent = fmt.when;
							li.appendChild(document.createElement('br'));
							li.appendChild(meta);
						}
						fuelList.appendChild(li);
					});
				})
				.catch(function () {
					if (fuelRecent) {
						fuelRecent.hidden = false;
					}
					if (fuelList) {
						fuelList.innerHTML = '';
						var li = document.createElement('li');
						li.textContent =
							cfg.i18n && cfg.i18n.fuelLoadFailed
								? cfg.i18n.fuelLoadFailed
								: 'Could not load fuel history.';
						fuelList.appendChild(li);
					}
				});
		}

		function api(path, opts) {
			opts = opts || {};
			opts.credentials = 'same-origin';
			opts.headers = opts.headers || {};
			opts.headers['X-BVF-Nonce'] = cfg.nonce;
			if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
				opts.headers['Content-Type'] = 'application/json';
				opts.body = JSON.stringify(opts.body);
			}
			return fetch(cfg.restBase + path, opts).then(function (r) {
				return r.text().then(function (text) {
					var j = null;
					if (text) {
						try {
							j = JSON.parse(text);
						} catch (e) {
							j = null;
						}
					}
					if (!r.ok) {
						var err =
							(j && (j.message || (j.data && j.data.message))) ||
							'Request failed (' + r.status + ')';
						throw new Error(err);
					}
					if (r.status === 204 || text === '') {
						return null;
					}
					return j;
				});
			});
		}

		function postPosition(pos) {
			if (!tripId || !pos || !pos.coords) {
				return;
			}
			var c = pos.coords;
			var body = {
				latitude: c.latitude,
				longitude: c.longitude,
				accuracy_m: c.accuracy != null ? c.accuracy : undefined,
				speed_knots:
					c.speed != null && !isNaN(c.speed) && c.speed >= 0
						? c.speed * 1.94384
						: undefined,
				heading_degrees: c.heading != null && !isNaN(c.heading) ? c.heading : undefined,
				altitude_m: c.altitude != null ? c.altitude : undefined,
				source: 'mobile',
			};
			api('trips/' + tripId + '/positions', { method: 'POST', body: body }).catch(function (e) {
				setMsg(e.message || 'Sync failed', true);
			});
		}

		function syncActionButtons() {
			if (!btnStart) {
				return;
			}
			var i = cfg.i18n || {};
			if (!tripId) {
				btnStart.disabled = true;
				if (btnPause) {
					btnPause.disabled = true;
				}
				if (btnEnd) {
					btnEnd.disabled = true;
				}
				if (btnSos) {
					btnSos.disabled = true;
				}
				btnStart.textContent = i.btnBeginTrip || 'Start trip';
				return;
			}
			if (btnSos) {
				btnSos.disabled = false;
			}
			if (btnEnd) {
				btnEnd.disabled = false;
			}
			if (captainTripStatus === 'pending') {
				btnStart.textContent = i.btnBeginTrip || 'Start trip';
				btnStart.disabled = tracking;
				if (btnPause) {
					btnPause.disabled = true;
				}
			} else {
				btnStart.textContent = i.btnStartTracking || 'Start tracking';
				btnStart.disabled = tracking;
				if (btnPause) {
					btnPause.disabled = !tracking;
				}
			}
		}

		function buildCaptainStatusLine(data) {
			if (!data || !data.id) {
				return '';
			}
			var i = cfg.i18n || {};
			var st = String(data.status || '').toLowerCase();
			var stLabel =
				st === 'pending'
					? i.statusPending || 'Pending'
					: i.statusActive || 'Active';
			var vessel =
				data.vessel_name
					? String(data.vessel_name)
					: data.vessel_id
						? 'Vessel #' + data.vessel_id
						: '';
			var base =
				(i.tripLabel || 'Trip') + ' #' + data.id + (vessel ? ' · ' + vessel : '');
			return base + ' · ' + stLabel;
		}

		function startTracking() {
			if (!tripId || tracking) {
				return;
			}
			if (captainTripStatus === 'pending') {
				return;
			}
			if (!navigator.geolocation) {
				setMsg('Geolocation is not available in this browser.', true);
				return;
			}
			tracking = true;
			syncActionButtons();
			setMsg('Tracking…');

			resetCompassDisplay();
			lastGpsHeadingAt = 0;
			lastCoordCache = null;
			setNavDashVisible(true);
			if (navHintEl && cfg.i18n) {
				var hi = [];
				if (cfg.i18n.navHeadingLine) {
					hi.push(cfg.i18n.navHeadingLine);
				}
				if (cfg.i18n.navHeadingWait) {
					hi.push(cfg.i18n.navHeadingWait);
				}
				navHintEl.textContent = hi.join(' ');
			}
			tryDeviceOrientationListener();

			function onPos(pos) {
				postPosition(pos);
				if (!tracking || !pos || !pos.coords) {
					return;
				}
				var c = pos.coords;
				var lat = c.latitude;
				var lng = c.longitude;
				lastCoordCache = { lat: lat, lng: lng };

				if (speedEl) {
					if (c.speed != null && !isNaN(c.speed) && c.speed > 0.05) {
						var kn = c.speed * 1.94384;
						speedEl.hidden = false;
						var su =
							cfg.i18n && cfg.i18n.speedUnitKn ? cfg.i18n.speedUnitKn : 'kn';
						var sl = cfg.i18n && cfg.i18n.navSpeed ? cfg.i18n.navSpeed : 'Speed';
						speedEl.textContent = sl + ': ' + kn.toFixed(1) + ' ' + su;
					} else {
						speedEl.hidden = true;
					}
				}

				var hd = c.heading;
				if (hd != null && !isNaN(hd) && hd >= 0 && hd <= 360) {
					lastGpsHeadingAt = Date.now();
					setCompassDeg(hd);
					updateMapFromPosition(lat, lng, hd);
				} else {
					updateMapFromPosition(lat, lng, null);
				}
			}

			watchId = navigator.geolocation.watchPosition(onPos, function (err) {
				setMsg(err && err.message ? err.message : 'GPS error', true);
			}, {
				enableHighAccuracy: true,
				maximumAge: 0,
				timeout: 20000,
			});

			intervalId = window.setInterval(function () {
				navigator.geolocation.getCurrentPosition(onPos, function () {}, {
					enableHighAccuracy: true,
					maximumAge: 0,
					timeout: 15000,
				});
			}, cfg.intervalMs || 30000);
		}

		function stopTracking(silent) {
			tracking = false;
			if (watchId != null && navigator.geolocation) {
				navigator.geolocation.clearWatch(watchId);
				watchId = null;
			}
			if (intervalId) {
				window.clearInterval(intervalId);
				intervalId = null;
			}
			setNavDashVisible(false);
			syncActionButtons();
			if (!silent) {
				setMsg(
					(cfg.i18n && cfg.i18n.trackingPaused) || 'Tracking paused. Resume with Start tracking or end the trip.'
				);
			}
		}

		function beginTripOrTracking() {
			if (!tripId || tracking) {
				return;
			}
			if (captainTripStatus === 'pending') {
				if (btnStart) {
					btnStart.disabled = true;
				}
				api('trips/' + tripId + '/activate', { method: 'POST' })
					.then(function (row) {
						captainTripStatus =
							row && row.status ? String(row.status).toLowerCase() : 'active';
						if (statusEl) {
							statusEl.textContent = buildCaptainStatusLine(row || { id: tripId, status: captainTripStatus });
						}
						setFuelUiEnabled(captainTripStatus === 'active');
						if (captainTripStatus === 'active') {
							refreshFuelLogs();
						}
						syncActionButtons();
						startTracking();
						setMsg('');
					})
					.catch(function (e) {
						setMsg(
							e.message ||
								((cfg.i18n && cfg.i18n.activateTripFailed) || 'Could not start this trip.'),
							true
						);
						syncActionButtons();
					});
				return;
			}
			startTracking();
		}

		function showStartTripErr(msg) {
			if (!startTripErr) {
				return;
			}
			startTripErr.textContent = msg || '';
			startTripErr.hidden = !msg;
		}

		function loadVesselsForStartTrip() {
			if (!startTripVessel) {
				return;
			}
			showStartTripErr('');
			startTripVessel.innerHTML = '';
			var ph = document.createElement('option');
			ph.value = '';
			ph.textContent =
			(cfg.i18n && cfg.i18n.startTripVesselLoading) || 'Loading vessels…';
			startTripVessel.appendChild(ph);
			startTripVessel.disabled = true;
			if (startTripSubmit) {
				startTripSubmit.disabled = true;
			}
			api('vessels/mine', { method: 'GET' })
				.then(function (rows) {
					startTripVessel.innerHTML = '';
					var opt0 = document.createElement('option');
					opt0.value = '';
					opt0.textContent =
						(cfg.i18n && cfg.i18n.startTripVesselPlaceholder) || 'Choose vessel…';
					startTripVessel.appendChild(opt0);
					if (!Array.isArray(rows) || !rows.length) {
						opt0.textContent =
							(cfg.i18n && cfg.i18n.startTripNoVessels) ||
							'No vessels available. Ask operations to assign you.';
						return;
					}
					startTripVessel.disabled = false;
					if (startTripSubmit) {
						startTripSubmit.disabled = false;
					}
					rows.forEach(function (row) {
						var opt = document.createElement('option');
						opt.value = String(row.id);
						var lab = row.name ? String(row.name) : 'Vessel ' + row.id;
						if (row.external_id) {
							lab += ' · ' + String(row.external_id);
						}
						opt.textContent = lab;
						startTripVessel.appendChild(opt);
					});
				})
				.catch(function () {
					startTripVessel.innerHTML = '';
					var o = document.createElement('option');
					o.value = '';
					o.textContent =
						(cfg.i18n && cfg.i18n.startTripVesselLoadFailed) ||
						'Could not load vessels.';
					startTripVessel.appendChild(o);
				});
		}

		if (startTripDialog) {
			startTripDialog.addEventListener('toggle', function () {
				if (startTripDialog.open) {
					loadVesselsForStartTrip();
					if (startTripOrigin) {
						startTripOrigin.value = '';
					}
					if (startTripDest) {
						startTripDest.value = '';
					}
					showStartTripErr('');
				}
			});
		}

		if (startTripForm && startTripVessel) {
			startTripForm.addEventListener('submit', function (ev) {
				ev.preventDefault();
				showStartTripErr('');
				var vid = parseInt(String(startTripVessel.value || ''), 10);
				if (!vid || vid <= 0) {
					showStartTripErr(
						(cfg.i18n && cfg.i18n.startTripPickVessel) || 'Choose a vessel.'
					);
					return;
				}
				var body = {
					vessel_id: vid,
					status: 'active',
				};
				if (startTripOrigin && startTripOrigin.value.trim()) {
					body.origin_label = startTripOrigin.value.trim();
				}
				if (startTripDest && startTripDest.value.trim()) {
					body.destination_label = startTripDest.value.trim();
				}
				if (cfg.canManageFleet) {
					var capRaw = startTripCaptainId ? String(startTripCaptainId.value || '').trim() : '';
					var capId = parseInt(capRaw, 10);
					if (!capId || capId <= 0) {
						showStartTripErr(
							(cfg.i18n && cfg.i18n.startTripCaptainRequired) ||
								'Enter a valid captain user ID.'
						);
						return;
					}
					body.captain_user_id = capId;
					if (startTripStatus && startTripStatus.value) {
						body.status = startTripStatus.value;
					}
				}
				if (startTripSubmit) {
					startTripSubmit.disabled = true;
				}
				api('trips', { method: 'POST', body: body })
					.then(function () {
						if (startTripDialog && startTripDialog.open) {
							startTripDialog.close();
						}
						setMsg(
							(cfg.i18n && cfg.i18n.startTripCreated) || 'Trip created.',
							false
						);
						loadCaptainTrip();
					})
					.catch(function (e) {
						showStartTripErr(e.message || 'Could not create trip.');
					})
					.then(function () {
						if (startTripSubmit) {
							startTripSubmit.disabled = !!(startTripVessel && startTripVessel.disabled);
						}
					});
			});
		}

		function loadCaptainTrip() {
			setMsg(cfg.i18n && cfg.i18n.loading ? cfg.i18n.loading : '');
			api('trips/mine/current')
				.then(function (data) {
					if (!data || !data.id) {
						tripId = null;
						captainTripStatus = null;
						hideSosSentStrip();
						setFuelUiEnabled(false);
						if (jobCertWrap) {
							jobCertWrap.hidden = true;
						}
						if (jobCertLink) {
							jobCertLink.setAttribute('href', '#');
						}
						if (statusEl) {
							statusEl.textContent =
								cfg.i18n && cfg.i18n.noTrip
									? cfg.i18n.noTrip
									: 'No trip assigned.';
						}
						syncActionButtons();
						return;
					}
					tripId = data.id;
					if (jobCertWrap) {
						jobCertWrap.hidden = false;
					}
					if (jobCertLink) {
						jobCertLink.setAttribute('href', '/app/trips/' + tripId + '/job-certificate');
					}
					captainTripStatus = String(data.status || '').toLowerCase() || null;
					setFuelUiEnabled(captainTripStatus === 'active');
					if (captainTripStatus === 'active') {
						refreshFuelLogs();
					} else if (fuelRecent) {
						fuelRecent.hidden = true;
					}
					if (statusEl) {
						statusEl.textContent = buildCaptainStatusLine(data);
					}
					syncActionButtons();
					restoreSosSentStrip();
					setMsg('');
				})
				.catch(function (e) {
					tripId = null;
					captainTripStatus = null;
					hideSosSentStrip();
					setFuelUiEnabled(false);
					if (jobCertWrap) {
						jobCertWrap.hidden = true;
					}
					syncActionButtons();
					setMsg(e.message || 'Could not load trip', true);
				});
		}

		btnStart.addEventListener('click', beginTripOrTracking);
		if (btnPause) {
			btnPause.addEventListener('click', function () {
				stopTracking(false);
			});
		}
		if (btnEnd) {
			btnEnd.addEventListener('click', function () {
				if (!tripId) {
					return;
				}
				var msg =
					(cfg.i18n && cfg.i18n.endTripConfirm) ||
					'End this trip? Tracking will stop and the trip will be completed.';
				if (!window.confirm(msg)) {
					return;
				}
				var tid = tripId;
				stopTracking(true);
				btnEnd.disabled = true;
				api('trips/' + tid + '/complete', { method: 'POST' })
					.then(function () {
						tripId = null;
						captainTripStatus = null;
						hideSosSentStrip();
						setFuelUiEnabled(false);
						syncActionButtons();
						loadCaptainTrip();
						setMsg(
							(cfg.i18n && cfg.i18n.tripEndedComplete) ||
								'Trip completed. Tracking has stopped.',
							false
						);
					})
					.catch(function (e) {
						setMsg(
							e.message ||
								((cfg.i18n && cfg.i18n.endTripFailed) || 'Could not end the trip.'),
							true
						);
						loadCaptainTrip();
					});
			});
		}

		if (fuelForm && fuelSubmit) {
			fuelForm.addEventListener('submit', function (ev) {
				ev.preventDefault();
				if (!tripId) {
					setMsg(cfg.i18n && cfg.i18n.noTrip ? cfg.i18n.noTrip : 'No active trip.', true);
					return;
				}
				var body = {};
				var L = parseLitresInput(fuelLoaded);
				var C = parseLitresInput(fuelConsumed);
				if (L == null || C == null) {
					setMsg(
						cfg.i18n && cfg.i18n.fuelNeedAmount
							? cfg.i18n.fuelNeedAmount
							: 'Enter both loaded and consumed litres (use 0 if not applicable).',
						true
					);
					return;
				}
				body.fuel_loaded_litres = L;
				body.fuel_consumed_litres = C;
				if (fuelNotes && fuelNotes.value.trim()) {
					body.notes = fuelNotes.value.trim();
				}
				fuelSubmit.disabled = true;
				api('trips/' + tripId + '/fuel-logs', { method: 'POST', body: body })
					.then(function () {
						if (fuelLoaded) {
							fuelLoaded.value = '';
						}
						if (fuelConsumed) {
							fuelConsumed.value = '';
						}
						if (fuelNotes) {
							fuelNotes.value = '';
						}
						setMsg(cfg.i18n && cfg.i18n.fuelSaved ? cfg.i18n.fuelSaved : 'Fuel entry saved.');
						refreshFuelLogs();
						if (fuelDialog && fuelDialog.open) {
							fuelDialog.close();
						}
					})
					.catch(function (e) {
						setMsg(e.message || 'Fuel save failed', true);
					})
					.then(function () {
						if (tripId) {
							fuelSubmit.disabled = false;
						}
					});
			});
		}

		setFuelUiEnabled(false);

		var sosDlg = document.getElementById('bvf-sos-dialog');
		var sosNote = document.getElementById('bvf-sos-note');
		var sosSend = document.getElementById('bvf-sos-send');
		var sosCancel = document.getElementById('bvf-sos-cancel');
		var sosTitle = document.getElementById('bvf-sos-dialog-title');
		var sosIntro = document.getElementById('bvf-sos-dialog-intro');
		var sosLabel = document.querySelector('label[for="bvf-sos-note"]');
		var sosFieldErr = document.getElementById('bvf-sos-field-err');

		function applySosDialogI18n() {
			if (!cfg.i18n) {
				return;
			}
			var i = cfg.i18n;
			if (sosTitle && i.sosDialogTitle) {
				sosTitle.textContent = i.sosDialogTitle;
			}
			if (sosIntro && i.sosDialogIntro) {
				sosIntro.textContent = i.sosDialogIntro;
			}
			if (sosLabel && i.sosNoteLabel) {
				sosLabel.textContent = i.sosNoteLabel;
			}
			if (sosNote && i.sosNotePlaceholder) {
				sosNote.placeholder = i.sosNotePlaceholder;
			}
			if (sosSend && i.sosSend) {
				sosSend.textContent = i.sosSend;
			}
			if (sosCancel && i.sosCancel) {
				sosCancel.textContent = i.sosCancel;
			}
		}

		function closeSosDialog() {
			if (sosDlg && sosDlg.open) {
				sosDlg.close();
			}
			if (sosFieldErr) {
				sosFieldErr.hidden = true;
				sosFieldErr.textContent = '';
			}
		}

		if (sosDlg) {
			applySosDialogI18n();
			sosDlg.addEventListener('close', function () {
				if (sosFieldErr) {
					sosFieldErr.hidden = true;
					sosFieldErr.textContent = '';
				}
			});
		}
		if (sosCancel) {
			sosCancel.addEventListener('click', closeSosDialog);
		}

		if (btnSos) {
			btnSos.addEventListener('click', function () {
				if (!tripId) {
					setMsg(cfg.i18n && cfg.i18n.noTrip ? cfg.i18n.noTrip : 'No active trip.', true);
					return;
				}
				if (!sosDlg || !sosNote) {
					return;
				}
				sosNote.value = '';
				if (sosFieldErr) {
					sosFieldErr.hidden = true;
					sosFieldErr.textContent = '';
				}
				sosDlg.showModal();
				sosNote.focus();
			});
		}

		if (sosSend && sosNote) {
			sosSend.addEventListener('click', function () {
				if (!tripId) {
					closeSosDialog();
					setMsg(cfg.i18n && cfg.i18n.noTrip ? cfg.i18n.noTrip : 'No active trip.', true);
					return;
				}
				var note = String(sosNote.value || '').trim();
				var minLen = 10;
				if (note.length < minLen) {
					if (sosFieldErr) {
						sosFieldErr.textContent =
							cfg.i18n && cfg.i18n.sosNoteRequired
								? cfg.i18n.sosNoteRequired
								: 'Describe the emergency in at least 10 characters.';
						sosFieldErr.hidden = false;
					}
					sosNote.focus();
					return;
				}
				if (sosFieldErr) {
					sosFieldErr.hidden = true;
				}
				sosSend.disabled = true;
				api('alerts/sos', { method: 'POST', body: { trip_id: tripId, note: note } })
					.then(function () {
						recordSosSent();
						closeSosDialog();
						setMsg(cfg.i18n && cfg.i18n.sosSent ? cfg.i18n.sosSent : 'SOS recorded. Stay safe.');
					})
					.catch(function (e) {
						setMsg(e.message || 'SOS failed', true);
					})
					.then(function () {
						sosSend.disabled = false;
					});
			});
		}

		loadCaptainTrip();
	}

	function boot() {
		document.querySelectorAll('.bvf-captain').forEach(function (root) {
			if (root.dataset.bvfCaptainInit === '1') {
				return;
			}
			root.dataset.bvfCaptainInit = '1';
			initPanel(root);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
