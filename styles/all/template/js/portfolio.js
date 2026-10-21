/**
 * Portfolio — verturin/portfolio
 * JavaScript sans dépendance : visionneuse, notation, sélection multiple, envoi glisser-déposer.
 */
(function () {
	'use strict';

	var root = document.getElementById('pf');
	if (!root) {
		return;
	}

	function $(sel, ctx) { return (ctx || document).querySelector(sel); }
	function $$(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

	function decode(html) {
		var t = document.createElement('textarea');
		t.innerHTML = html;
		return t.value;
	}

	function toast(message, isError) {
		var el = document.createElement('div');
		el.className = 'pf-toast' + (isError ? ' is-error' : '');
		el.setAttribute('role', 'status');
		el.textContent = decode(message);
		document.body.appendChild(el);
		setTimeout(function () { el.remove(); }, 3200);
	}

	function ajax(url, options) {
		options = options || {};
		var headers = { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
		return fetch(url, {
			method: options.method || 'GET',
			body: options.body || null,
			headers: headers,
			credentials: 'same-origin'
		}).then(function (res) {
			return res.json().catch(function () { return {}; }).then(function (data) {
				if (!res.ok || data.success === false) {
					throw new Error(data.message || data.MESSAGE_TEXT || res.statusText);
				}
				return data;
			});
		});
	}

	/* ------------------------------------------------------------------
	 * Visionneuse plein écran
	 * ------------------------------------------------------------------ */
	var lightbox = document.getElementById('pf-lightbox');
	var lbItems = [];
	var lbIndex = 0;
	var lastFocus = null;

	function lbShow(index) {
		if (!lbItems.length) {
			return;
		}
		lbIndex = (index + lbItems.length) % lbItems.length;
		var link = lbItems[lbIndex];
		var img = $('img', lightbox);
		img.src = link.getAttribute('data-pf-lightbox');
		img.alt = decode(link.getAttribute('data-pf-title') || '');
		var by = link.getAttribute('data-pf-by');
		$('.pf-lightbox__title', lightbox).textContent = decode(link.getAttribute('data-pf-title') || '') + (by ? ' — ' + decode(by) : '');
		$('.pf-lightbox__count', lightbox).textContent = lbItems.length > 1 ? (lbIndex + 1) + ' / ' + lbItems.length : '';
		var more = $('.pf-lightbox__more', lightbox);
		var url = link.getAttribute('data-pf-url');
		more.hidden = !url;
		if (url) {
			more.href = url;
		}
		$('[data-pf-prev]', lightbox).hidden = lbItems.length < 2;
		$('[data-pf-next]', lightbox).hidden = lbItems.length < 2;
		lbPanel(link.getAttribute('data-pf-quick'));

		// Préchargement de la suivante
		if (lbItems.length > 1) {
			var next = new Image();
			next.src = lbItems[(lbIndex + 1) % lbItems.length].getAttribute('data-pf-lightbox');
		}
	}

	/* Aperçu rapide : notes, réactions et commentaires à côté de la photo (v1.6.0) */
	var panel = lightbox ? $('[data-pf-panel]', lightbox) : null;
	var panelRequest = 0;

	function lbPanel(url) {
		if (!panel) {
			return;
		}
		if (!url) {
			panel.hidden = true;
			panel.innerHTML = '';
			lightbox.classList.remove('has-panel');
			return;
		}
		var ticket = ++panelRequest;
		var moreLink = $('.pf-lightbox__more', lightbox);
		if (moreLink) {
			moreLink.hidden = true;
		}
		panel.hidden = false;
		panel.classList.add('is-loading');
		lightbox.classList.add('has-panel');
		ajax(url).then(function (data) {
			if (ticket !== panelRequest) {
				return;
			}
			panel.innerHTML = data.html;
			panel.scrollTop = 0;
			// Pas d'accès à la fiche pour qui n'est ni le posteur ni co-gestionnaire
			var more = $('.pf-lightbox__more', lightbox);
			if (more) {
				more.hidden = !data.url;
			}
		}).catch(function () {
			if (ticket === panelRequest) {
				lbPanel('');
			}
		}).then(function () {
			panel.classList.remove('is-loading');
		});
	}

	function lbOpen(link) {
		var gallery = link.closest('[data-pf-gallery]');
		lbItems = gallery ? $$('[data-pf-lightbox]', gallery) : [link];
		lastFocus = document.activeElement;
		lightbox.hidden = false;
		document.documentElement.style.overflow = 'hidden';
		lbShow(lbItems.indexOf(link));
		$('[data-pf-close]', lightbox).focus();
	}

	function lbClose() {
		lightbox.hidden = true;
		lbPanel('');
		document.documentElement.style.overflow = '';
		if (lastFocus) {
			lastFocus.focus();
		}
	}

	if (lightbox) {
		document.body.appendChild(lightbox);

		root.addEventListener('click', function (e) {
			var link = e.target.closest('[data-pf-lightbox]');
			if (!link || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) {
				return;
			}
			// En mode sélection, un clic coche la photo au lieu d'ouvrir la visionneuse
			var grid = link.closest('.is-selecting');
			if (grid) {
				var box = link.parentNode.querySelector('.pf-select input');
				if (box) {
					e.preventDefault();
					box.checked = !box.checked;
					box.dispatchEvent(new Event('change', { bubbles: true }));
					return;
				}
			}
			e.preventDefault();
			lbOpen(link);
		});

		lightbox.addEventListener('click', function (e) {
			if (e.target.closest('[data-pf-panel]')) {
				return;
			}
			if (e.target.closest('[data-pf-close]') || e.target === lightbox) {
				lbClose();
			} else if (e.target.closest('[data-pf-prev]')) {
				lbShow(lbIndex - 1);
			} else if (e.target.closest('[data-pf-next]')) {
				lbShow(lbIndex + 1);
			}
		});

		// Balayage tactile
		var touchX = null;
		lightbox.addEventListener('touchstart', function (e) { touchX = e.touches[0].clientX; }, { passive: true });
		lightbox.addEventListener('touchend', function (e) {
			if (touchX === null) {
				return;
			}
			var dx = e.changedTouches[0].clientX - touchX;
			if (Math.abs(dx) > 50) {
				lbShow(lbIndex + (dx < 0 ? 1 : -1));
			}
			touchX = null;
		});
	}

	document.addEventListener('keydown', function (e) {
		if (lightbox && !lightbox.hidden) {
			var typing = /^(input|textarea|select)$/i.test(e.target.tagName || '');
			if (e.key === 'Escape') { lbClose(); }
			if (typing) { return; }
			if (e.key === 'ArrowLeft') { lbShow(lbIndex - 1); }
			if (e.key === 'ArrowRight') { lbShow(lbIndex + 1); }
			return;
		}

		// Navigation clavier sur la page photo (← / →)
		var tag = (e.target.tagName || '').toLowerCase();
		if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.target.isContentEditable || e.altKey || e.ctrlKey || e.metaKey) {
			return;
		}
		var nav = $('[data-pf-key="' + e.key + '"]', root);
		if (nav) {
			window.location.href = nav.href;
		}
	});

	/* Pellicule : la photo courante est visible sans faire défiler */
	var current = $('.pf-strip__item.is-current', root);
	if (current && current.parentNode.scrollWidth > current.parentNode.clientWidth) {
		current.parentNode.scrollLeft = current.offsetLeft - (current.parentNode.clientWidth - current.offsetWidth) / 2;
	}

	/* ------------------------------------------------------------------
	 * Notation par étoiles et réactions : délégation, pour fonctionner aussi
	 * dans l'aperçu rapide chargé dans la visionneuse (v1.6.0)
	 * ------------------------------------------------------------------ */
	function paintStars(stars, value, cls) {
		$$('.pf-star', stars).forEach(function (link, i) {
			link.classList.toggle(cls, i < value);
		});
	}

	document.addEventListener('mouseover', function (e) {
		var star = e.target.closest && e.target.closest('[data-pf-stars].is-interactive .pf-star');
		if (!star) {
			return;
		}
		var stars = star.closest('[data-pf-stars]');
		stars.classList.add('is-hovering');
		paintStars(stars, $$('.pf-star', stars).indexOf(star) + 1, 'is-hover');
	});

	document.addEventListener('mouseout', function (e) {
		var stars = e.target.closest && e.target.closest('[data-pf-stars].is-interactive');
		if (stars && !stars.contains(e.relatedTarget)) {
			stars.classList.remove('is-hovering');
			paintStars(stars, 0, 'is-hover');
		}
	});

	/** Met à jour la carte de la grille après une note ou une réaction donnée depuis l'aperçu rapide. */
	function refreshCard(photoId, kind, data) {
		var card = document.getElementById('p' + photoId);
		if (!card || !photoId) {
			return;
		}
		card.setAttribute('data-pf-dirty', '1');
		if (kind === 'comments') {
			var meta = $('.pf-photo__caption', card);
			if (meta) {
				meta.setAttribute('data-comments', data);
			}
		}
	}

	document.addEventListener('click', function (e) {
		// Notation
		var rate = e.target.closest && e.target.closest('[data-pf-rating] a[data-value]');
		if (rate && rate.closest('[data-pf-rating]').querySelector('[data-pf-stars].is-interactive')) {
			e.preventDefault();
			var box = rate.closest('[data-pf-rating]');
			var stars = $('[data-pf-stars]', box);
			ajax(rate.href).then(function (data) {
				$$('.pf-star', stars).forEach(function (l, i) {
					l.classList.toggle('is-filled', i + 1 <= data.avg_num + 0.25);
				});
				$('[data-pf-avg]', box).textContent = data.avg;
				$('[data-pf-rcount]', box).textContent = decode(data.label);
				var mine = $('[data-pf-mine]', box);
				if (mine) {
					mine.hidden = !data.mine;
					var b = $('b', mine);
					if (b) {
						b.textContent = data.mine;
					}
				}
				stars.setAttribute('data-avg', data.avg_num);
				var quick = box.closest('.pf-quick');
				if (quick) {
					refreshCard(quick.getAttribute('data-photo-id'), 'rating', data);
				}
			}).catch(function (err) {
				toast(err.message, true);
			});
			return;
		}

		// Réactions
		var react = e.target.closest && e.target.closest('[data-pf-reactions].is-interactive a[data-code]');
		if (react) {
			e.preventDefault();
			var bar = react.closest('[data-pf-reactions]');
			ajax(react.href).then(function (data) {
				$$('a[data-code]', bar).forEach(function (a) {
					var code = a.getAttribute('data-code');
					var n = data.counts[code] || 0;
					$('[data-count]', a).textContent = n ? n : '';
					a.classList.toggle('is-empty', !n);
					a.classList.toggle('is-mine', data.mine === code);
					a.setAttribute('aria-pressed', data.mine === code ? 'true' : 'false');
				});
				if (data.mine) {
					react.classList.remove('is-pop');
					void react.offsetWidth;
					react.classList.add('is-pop');
				}
			}).catch(function (err) {
				toast(err.message, true);
			});
			return;
		}

		// Copier un lien / un BBCode
		var copy = e.target.closest && e.target.closest('[data-pf-copy]');
		if (copy) {
			var field = copy.parentNode.querySelector('[data-pf-copy-source]');
			field.select();
			var done = function () {
				var label = copy.textContent;
				copy.classList.add('is-done');
				copy.textContent = decode(copy.getAttribute('data-done'));
				setTimeout(function () { copy.classList.remove('is-done'); copy.textContent = label; }, 1600);
			};
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(field.value).then(done, function () { document.execCommand('copy'); done(); });
			} else {
				document.execCommand('copy');
				done();
			}
		}
	});

	/* Confirmation avant une action (partage : changer le rôle, retirer un membre) — v1.8.3 */
	document.addEventListener('click', function (e) {
		var btn = e.target.closest && e.target.closest('[data-pf-confirm]');
		if (btn && !window.confirm(decode(btn.getAttribute('data-pf-confirm')))) {
			e.preventDefault();
		}
	}, true);

	/* Le bouton « Modifier » n'apparaît que si le rôle choisi a changé */
	document.addEventListener('change', function (e) {
		var select = e.target.closest && e.target.closest('[data-pf-role-select]');
		if (!select) {
			return;
		}
		var save = select.parentNode.querySelector('[data-pf-role-save]');
		if (save) {
			save.hidden = select.value === select.getAttribute('data-initial');
		}
	});

	/* Menu « ⋯ » : se ferme en cliquant ailleurs */
	document.addEventListener('click', function (e) {
		$$('details.pf-menu[open]').forEach(function (menu) {
			if (!menu.contains(e.target)) {
				menu.removeAttribute('open');
			}
		});
	});

	/* Commentaire envoyé depuis l'aperçu rapide */
	document.addEventListener('submit', function (e) {
		var form = e.target.closest && e.target.closest('[data-pf-quick-comment]');
		if (!form || !window.FormData) {
			return;
		}
		e.preventDefault();
		var btn = $('button[type="submit"]', form);
		btn.disabled = true;
		ajax(form.action, { method: 'POST', body: new FormData(form) }).then(function (data) {
			var box = form.closest('[data-pf-quick-comments]');
			$('[data-pf-comment-list]', box).insertAdjacentHTML('beforeend', data.html);
			$('[data-pf-comment-count]', box).textContent = data.count;
			var empty = $('[data-pf-no-comment]', box);
			if (empty) {
				empty.remove();
			}
			$('textarea', form).value = '';
			toast(data.message);
		}).catch(function (err) {
			toast(err.message, true);
		}).then(function () {
			btn.disabled = false;
		});
	});

	/* Rotation enregistrée en un clic : la photo est mise à jour sans recharger la page */
	$$('[data-pf-rotate-bar]', root).forEach(function (bar) {
		bar.addEventListener('click', function (e) {
			var btn = e.target.closest('[data-pf-rotate-btn]');
			if (!btn) {
				return;
			}
			e.preventDefault();
			bar.classList.add('is-busy');
			ajax(btn.href).then(function (data) {
				$$('.pf-viewer__image img, .pf-viewer__image', root).forEach(function (el) {
					if (el.tagName === 'IMG') {
						el.src = data.medium;
						el.width = data.width;
						el.height = data.height;
					} else {
						el.href = data.medium;
						el.setAttribute('data-pf-lightbox', data.medium);
					}
				});
				$$('.pf-strip__item.is-current img', root).forEach(function (img) {
					img.src = data.medium.replace('/medium', '/thumb');
				});
				toast(data.message);
			}).catch(function () {
				toast(bar.getAttribute('data-msg-error'), true);
			}).then(function () {
				bar.classList.remove('is-busy');
			});
		});
	});

	/* ------------------------------------------------------------------
	 * Sélection multiple (corbeille, déplacement, validation)
	 * ------------------------------------------------------------------ */
	var bulk = $('[data-pf-bulk]', root);
	if (bulk) {
		var toggle = $('[data-pf-select-toggle]', root);
		var grid = $('[data-pf-gallery]', bulk.closest('.pf-section') || root) || $('.pf-grid', root);
		var count = $('[data-pf-count]', bulk);
		var all = $('[data-pf-select-all]', bulk);
		var actionSelect = $('[data-pf-bulk-action]', bulk);
		var target = $('[data-pf-bulk-target]', bulk);

		var boxes = function () {
			return $$('input[name="photo_ids[]"]', root);
		};

		var refresh = function () {
			var n = boxes().filter(function (b) { return b.checked; }).length;
			count.textContent = decode(count.getAttribute('data-label')).replace('%d', n);
			if (all) {
				all.checked = n > 0 && n === boxes().length;
				all.indeterminate = n > 0 && n < boxes().length;
			}
		};

		if (toggle) {
			toggle.addEventListener('click', function () {
				var on = bulk.hidden;
				bulk.hidden = !on;
				if (grid) {
					grid.classList.toggle('is-selecting', on);
				}
				toggle.classList.toggle('is-active', on);
				if (!on) {
					boxes().forEach(function (b) { b.checked = false; });
				}
				refresh();
			});
		}

		root.addEventListener('change', function (e) {
			if (e.target.matches('input[name="photo_ids[]"]')) {
				refresh();
			}
		});

		if (all) {
			all.addEventListener('change', function () {
				boxes().forEach(function (b) { b.checked = all.checked; });
				refresh();
			});
		}

		var reason = $('[data-pf-bulk-reason]', bulk);
		var syncAction = function () {
			if (target) {
				target.hidden = !actionSelect || actionSelect.value !== 'move';
			}
			if (reason) {
				var del = actionSelect && actionSelect.value === 'delete';
				reason.hidden = !del;
				reason.required = del && reason.getAttribute('data-required') === '1';
			}
		};
		if (actionSelect) {
			actionSelect.addEventListener('change', syncAction);
		}
		syncAction();

		bulk.addEventListener('submit', function (e) {
			var n = boxes().filter(function (b) { return b.checked; }).length;
			if (!n) {
				e.preventDefault();
				return;
			}
			if (actionSelect && actionSelect.value === 'delete') {
				var msg = decode(bulk.getAttribute('data-confirm-delete') || '').replace('%d', n);
				if (!window.confirm(msg)) {
					e.preventDefault();
				}
			}
		});

		refresh();
	}

	/* ------------------------------------------------------------------
	 * Formulaire d'album : groupes visibles uniquement en mode « groupes »
	 * ------------------------------------------------------------------ */
	var groupsBox = $('[data-pf-groups]', root);
	if (groupsBox) {
		$$('[data-pf-visibility]', root).forEach(function (radio) {
			radio.addEventListener('change', function () {
				groupsBox.hidden = radio.value !== '2';
			});
		});
	}

	/* Aperçu de la rotation (édition de photo) */
	var rotatePreview = $('[data-pf-rotate-preview]', root);
	if (rotatePreview) {
		$$('[data-pf-rotate]', root).forEach(function (radio) {
			radio.addEventListener('change', function () {
				rotatePreview.style.transform = 'rotate(' + radio.value + 'deg)';
			});
		});
	}

	/* Changement d'album de destination (page d'envoi) */
	var gotoSelect = $('[data-pf-goto]', root);
	if (gotoSelect) {
		gotoSelect.addEventListener('change', function () {
			window.location.href = gotoSelect.value;
		});
	}

	/* Création d'archive : le bouton indique que le travail est en cours */
	$$('[data-pf-busy]', root).forEach(function (btn) {
		btn.form.addEventListener('submit', function () {
			setTimeout(function () {
				btn.classList.add('is-busy');
				btn.textContent = decode(btn.getAttribute('data-pf-busy'));
			}, 0);
		});
	});

	/* ------------------------------------------------------------------
	 * Envoi multiple par glisser-déposer
	 * ------------------------------------------------------------------ */
	var form = $('[data-pf-upload]', root);
	if (form && window.FormData && window.XMLHttpRequest) {
		var input = $('[data-pf-file]', form);
		var zone = $('[data-pf-dropzone]', form);
		var list = $('[data-pf-queue]', form);
		var footer = $('[data-pf-upload-footer]', form);
		var startBtn = $('[data-pf-start]', form);
		var summary = $('[data-pf-summary]', form);
		var maxBytes = parseInt(form.getAttribute('data-max-bytes'), 10) || 0;
		var accept = (form.getAttribute('data-accept') || '').toLowerCase().split(',');
		var queue = [];
		var running = 0;
		var sentSome = false;
		var CONCURRENCY = 2;

		var stat = function () {
			var done = queue.filter(function (q) { return q.state === 'done'; }).length;
			var failed = queue.filter(function (q) { return q.state === 'error'; }).length;
			var waiting = queue.filter(function (q) { return q.state === 'waiting'; }).length;
			summary.textContent = done + ' / ' + queue.length + (failed ? ' · ' + failed + ' ✕' : '');
			startBtn.hidden = !waiting;
			footer.hidden = !queue.length;
			if (sentSome && queue.length && !waiting && !running) {
				sentSome = false;
				toast(form.getAttribute('data-msg-done'));
			}
		};

		var setStatus = function (item, state, text) {
			item.state = state;
			item.el.classList.toggle('is-done', state === 'done');
			item.el.classList.toggle('is-error', state === 'error');
			item.status.textContent = decode(text || '');
		};

		var add = function (files) {
			Array.prototype.forEach.call(files, function (file) {
				var ext = '.' + (file.name.split('.').pop() || '').toLowerCase();
				var li = document.createElement('li');
				li.className = 'pf-queue__item';
				li.innerHTML = '<img class="pf-queue__thumb" alt="">' +
					'<div class="pf-queue__main"><input type="text" class="pf-input pf-queue__title" maxlength="255">' +
					'<div class="pf-progress"><span></span></div><span class="pf-queue__status"></span></div>' +
					'<button type="button" class="pf-queue__remove" aria-label="×">✕</button>';

				var item = {
					file: file,
					el: li,
					title: $('.pf-queue__title', li),
					bar: $('.pf-progress > span', li),
					status: $('.pf-queue__status', li),
					state: 'waiting'
				};
				item.title.value = file.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' ');
				item.status.textContent = Math.round(file.size / 1024) + ' Ko';

				if (accept.length && accept[0] && accept.indexOf(ext) === -1) {
					setStatus(item, 'error', form.getAttribute('data-msg-type'));
				} else if (maxBytes && file.size > maxBytes) {
					setStatus(item, 'error', form.getAttribute('data-msg-too-big'));
				}

				if (file.type.indexOf('image/') === 0 && window.URL) {
					var thumb = $('.pf-queue__thumb', li);
					thumb.src = URL.createObjectURL(file);
					thumb.onload = function () { URL.revokeObjectURL(thumb.src); };
				}

				$('.pf-queue__remove', li).addEventListener('click', function () {
					if (item.state === 'uploading') {
						return;
					}
					queue.splice(queue.indexOf(item), 1);
					li.remove();
					stat();
				});

				queue.push(item);
				list.appendChild(li);
			});
			stat();
		};

		var send = function (item) {
			running++;
			sentSome = true;
			setStatus(item, 'uploading', '…');
			item.title.disabled = true;

			var data = new FormData();
			data.append('photo', item.file, item.file.name);
			data.append('title', item.title.value);
			data.append('upload', '1');
			$$('input[type="hidden"]', form).forEach(function (h) {
				if (h.name !== 'upload') {
					data.append(h.name, h.value);
				}
			});

			var xhr = new XMLHttpRequest();
			xhr.open('POST', form.action, true);
			xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
			xhr.setRequestHeader('Accept', 'application/json');
			xhr.upload.addEventListener('progress', function (e) {
				if (e.lengthComputable) {
					item.bar.style.width = Math.round(e.loaded / e.total * 100) + '%';
				}
			});
			xhr.onload = function () {
				var res = null;
				try { res = JSON.parse(xhr.responseText); } catch (err) { res = null; }
				if (xhr.status === 200 && res && res.success) {
					item.bar.style.width = '100%';
					setStatus(item, 'done', res.message);
				} else if (res && (res.message || res.MESSAGE_TEXT)) {
					setStatus(item, 'error', res.message || res.MESSAGE_TEXT);
				} else {
					// Réponse illisible (page d'erreur du serveur, pare-feu de l'hébergeur…) : on montre ce qu'elle contient
					var text = (xhr.responseText || '').replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/gi, ' ').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
					if (window.console) {
						console.error('Portfolio : réponse inattendue du serveur (HTTP ' + xhr.status + ')', xhr.responseText);
					}
					setStatus(item, 'error', decode(form.getAttribute('data-msg-server')).replace('%1$s', xhr.status).replace('%2$s', text.substring(0, 180) || '—'));
				}
				finish();
			};
			xhr.onerror = function () {
				setStatus(item, 'error', form.getAttribute('data-msg-network'));
				finish();
			};
			xhr.send(data);
		};

		var finish = function () {
			running--;
			pump();
			stat();
		};

		var pump = function () {
			while (running < CONCURRENCY) {
				var next = queue.filter(function (q) { return q.state === 'waiting'; })[0];
				if (!next) {
					break;
				}
				send(next);
			}
		};

		input.addEventListener('change', function () {
			add(input.files);
			input.value = '';
		});

		['dragenter', 'dragover'].forEach(function (type) {
			zone.addEventListener(type, function (e) {
				e.preventDefault();
				zone.classList.add('is-over');
			});
		});
		['dragleave', 'drop'].forEach(function (type) {
			zone.addEventListener(type, function (e) {
				e.preventDefault();
				zone.classList.remove('is-over');
			});
		});
		zone.addEventListener('drop', function (e) {
			if (e.dataTransfer && e.dataTransfer.files) {
				add(e.dataTransfer.files);
			}
		});

		startBtn.addEventListener('click', pump);
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			pump();
		});
	}
})();
