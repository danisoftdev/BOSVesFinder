(function () {
	'use strict';

	function statusClass(status) {
		if (status === 'moving') {
			return 'bvf-marker-moving';
		}
		if (status === 'idle') {
			return 'bvf-marker-idle';
		}
		return 'bvf-marker-offline';
	}

	function tripColor(tripId) {
		var palette = ['#1565c0', '#2e7d32', '#6a1b9a', '#ef6c00', '#00838f', '#c62828'];
		return palette[Math.abs(parseInt(tripId, 10) || 0) % palette.length];
	}

	function initMap(container) {
		var map = L.map(container, { scrollWheelZoom: false }).setView([0, 0], 2);
		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '&copy; OpenStreetMap',
		}).addTo(map);
		return map;
	}

	function setWrapLoading(wrap, loading) {
		if (!wrap) {
			return;
		}
		if (loading) {
			wrap.classList.add('bvf-fleet-map-wrap--loading');
		} else {
			wrap.classList.remove('bvf-fleet-map-wrap--loading');
		}
	}

	function loadFleet() {
		var cfg = window.bvfFleetMap;
		if (!cfg || !cfg.restUrl) {
			return;
		}

		var containers = document.querySelectorAll('.bvf-fleet-map');
		if (!containers.length) {
			return;
		}

		containers.forEach(function (container) {
			if (container.dataset.bvfInitialized === '1') {
				return;
			}
			container.dataset.bvfInitialized = '1';

			var wrap = container.closest('.bvf-fleet-map-wrap');
			var map = initMap(container);
			var underlay = L.layerGroup().addTo(map);
			var layer = L.layerGroup().addTo(map);
			var heatLayer = null;

			function renderTracks(tracksPayload) {
				underlay.clearLayers();
				if (heatLayer && map.hasLayer(heatLayer)) {
					map.removeLayer(heatLayer);
					heatLayer = null;
				}
				if (!tracksPayload || !tracksPayload.length) {
					return;
				}

				var heatPoints = [];

				tracksPayload.forEach(function (tr) {
					if (!tr.points || !tr.points.length) {
						return;
					}
					var latlngs = tr.points.map(function (p) {
						return [p[0], p[1]];
					});
					var col = tripColor(tr.trip_id);
					L.polyline(latlngs, {
						color: col,
						weight: 3,
						opacity: 0.55,
						smoothFactor: 1,
					}).addTo(underlay);

					if (cfg.heatmap) {
						tr.points.forEach(function (p) {
							heatPoints.push([p[0], p[1], 0.35]);
						});
					}
				});

				if (cfg.heatmap && heatPoints.length && typeof L.heatLayer === 'function') {
					heatLayer = L.heatLayer(heatPoints, { radius: 28, blur: 22, maxZoom: 14 });
					heatLayer.addTo(underlay);
				}
			}

			function render(items) {
				layer.clearLayers();
				var bounds = [];

				(items || []).forEach(function (item) {
					if (item.latitude == null || item.longitude == null) {
						return;
					}
					var latlng = [item.latitude, item.longitude];
					bounds.push(latlng);

					var color =
						item.computed_status === 'moving'
							? '#2e7d32'
							: item.computed_status === 'idle'
								? '#f9a825'
								: '#c62828';
					var icon = L.divIcon({
						className: 'bvf-fleet-marker ' + statusClass(item.computed_status),
						html:
							'<span style="display:block;width:14px;height:14px;border-radius:50%;background:' +
							color +
							';border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35)"></span>',
						iconSize: [18, 18],
						iconAnchor: [9, 9],
					});

					var m = L.marker(latlng, { icon: icon }).addTo(layer);
					var title = item.vessel_name || 'Vessel';
					var esc = function (s) {
						return String(s)
							.replace(/&/g, '&amp;')
							.replace(/</g, '&lt;')
							.replace(/>/g, '&gt;')
							.replace(/"/g, '&quot;');
					};
					var lines = [
						'<strong>' + esc(title) + '</strong>',
						'Status: ' + (item.computed_status || '—'),
					];
					if (item.speed_knots != null) {
						lines.push('Speed: ' + Number(item.speed_knots).toFixed(1) + ' kn');
					}
					if (item.last_report_at) {
						lines.push('Last report: ' + item.last_report_at);
					}
					if (item.trip_id) {
						lines.push('Trip #' + item.trip_id);
					}
					m.bindPopup(lines.join('<br/>'));
				});

				if (bounds.length) {
					map.fitBounds(bounds, { padding: [24, 24], maxZoom: 14 });
				}
			}

			function fetchTracksThen(done) {
				if (!cfg.tracksUrl) {
					done(null);
					return;
				}
				fetch(cfg.tracksUrl, {
					credentials: 'same-origin',
					headers: { 'X-BVF-Nonce': cfg.nonce },
				})
					.then(function (r) {
						if (!r.ok) {
							throw new Error('tracks HTTP ' + r.status);
						}
						return r.json();
					})
					.then(function (data) {
						done(data);
					})
					.catch(function () {
						done(null);
					});
			}

			function fetchLive(includeTracks) {
				fetch(cfg.restUrl, {
					credentials: 'same-origin',
					headers: { 'X-BVF-Nonce': cfg.nonce },
				})
					.then(function (r) {
						if (!r.ok) {
							throw new Error('HTTP ' + r.status);
						}
						return r.json();
					})
					.then(function (items) {
						function done() {
							render(items);
							setWrapLoading(wrap, false);
						}
						if (includeTracks && cfg.tracksUrl) {
							fetchTracksThen(function (tracks) {
								if (tracks && tracks.length) {
									renderTracks(tracks);
								}
								done();
							});
						} else {
							done();
						}
					})
					.catch(function () {
						setWrapLoading(wrap, false);
						if (window.console && console.warn) {
							console.warn(cfg.i18n && cfg.i18n.error ? cfg.i18n.error : 'Fleet load failed');
						}
					});
			}

			fetchLive(true);
			var intervalMs = typeof cfg.pollMs === 'number' ? cfg.pollMs : 15000;
			if (intervalMs > 0) {
				setInterval(function () {
					fetchLive(false);
				}, intervalMs);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', loadFleet);
	} else {
		loadFleet();
	}
})();
