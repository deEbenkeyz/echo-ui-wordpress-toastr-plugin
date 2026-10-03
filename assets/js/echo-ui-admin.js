(function (window, document) {
	'use strict';

	var data = window.echoUiAdmin;
	var root = document.querySelector('.echo-ui-nc');

	if (!data || !root) {
		return;
	}

	var i18n = data.i18n;
	var TYPES = data.types;
	var ICONS = { success: '✓', error: '!', warning: '▲', info: 'i', loading: '◌' };
	var LISTS = ['history_types', 'sound_types'];
	var form = root.querySelector('form');
	var optionName = 'echo_ui_toasts_options';
	var saved = normalize(data.saved);
	var s = clone(saved);
	var ptype = 'success';
	var ctx = 'frontend';
	var vis = [];
	var queue = [];
	var audio;
	var submitting = false;

	function $(q) {
		return root.querySelector(q);
	}

	function $$(q) {
		return Array.prototype.slice.call(root.querySelectorAll(q));
	}

	function clone(value) {
		return JSON.parse(JSON.stringify(value));
	}

	// Keep history types in canonical order so dirty checks are order-independent.
	function normalize(state) {
		var next = clone(state);
		LISTS.forEach(function (key) {
			next[key] = TYPES.filter(function (type) {
				return (next[key] || []).indexOf(type) !== -1;
			});
		});
		return next;
	}

	// Minimal sprintf for translated strings: supports %s, %d and %1$d style placeholders.
	function fmt(str) {
		var args = arguments;
		var index = 0;

		return String(str).replace(/%(\d+\$)?[sd]/g, function (match, position) {
			index++;
			return args[position ? parseInt(position, 10) : index];
		});
	}

	function el(tag, className, text) {
		var node = document.createElement(tag);

		if (className) {
			node.className = className;
		}

		if (text !== undefined) {
			node.textContent = text;
		}

		return node;
	}

	function pill(label, value) {
		var node = el('span', 'nc-pill');

		if (label) {
			node.appendChild(document.createTextNode(label + ' '));
		}

		node.appendChild(el('b', '', value));
		return node;
	}

	function render() {
		$$('[data-k]').forEach(function (input) {
			var value = s[input.dataset.k];

			if (input.type === 'checkbox') {
				input.checked = !!value;
			} else if (input.type === 'radio') {
				input.checked = input.value === value;
			} else if (input !== document.activeElement || input.type === 'range') {
				input.value = value;
			}
		});

		$$('#nc-pos button').forEach(function (button) {
			button.setAttribute('aria-pressed', String(button.dataset.p === s.position));
		});
		$$('[data-list]').forEach(function (button) {
			button.setAttribute('aria-pressed', String(s[button.dataset.list].indexOf(button.dataset.type) !== -1));
		});
		$$('[data-pt]').forEach(function (button) {
			button.setAttribute('aria-pressed', String(button.dataset.pt === ptype));
		});
		$$('[data-cx]').forEach(function (button) {
			button.setAttribute('aria-pressed', String(button.dataset.cx === ctx));
		});

		TYPES.forEach(function (type) {
			var hint = $('[data-h="duration_' + type + '"]');
			var ms = +s['duration_' + type];

			if (!hint) {
				return;
			}

			hint.textContent = ms === 0 ? i18n.staysOpen : fmt(i18n.seconds, (ms / 1000).toFixed(ms % 1000 ? 1 : 0));
			hint.classList.toggle('on', ms === 0);
		});

		$('#nc-volv').textContent = '· ' + (+s.sound_volume).toFixed(2);
		$('#nc-volwrap').classList.toggle('off', !s.sound_enabled);
		$('#nc-hwrap').classList.toggle('off', !s.history_enabled);
		$('#nc-tc').className = 'nc-tc ' + s.position;

		var sum = $('#nc-sum');
		sum.textContent = '';
		sum.appendChild(pill(i18n.admin, s.enable_admin ? i18n.on : i18n.off));
		sum.appendChild(pill(i18n.frontend, s.enable_frontend ? i18n.on : i18n.off));
		sum.appendChild(pill('', i18n.positions[s.position] || s.position));
		sum.appendChild(pill(i18n.stack, String(s.max)));

		$('#nc-code').textContent = JSON.stringify({
			toast: { type: ptype, title: MSG()[ptype][0], context: ctx }
		}, null, 2);

		// Chips are not form controls, so mirror them into hidden inputs for options.php.
		$$('[data-list-inputs]').forEach(function (holder) {
			var key = holder.dataset.listInputs;

			holder.textContent = '';
			s[key].forEach(function (type) {
				var input = document.createElement('input');
				input.type = 'hidden';
				input.name = optionName + '[' + key + '][]';
				input.value = type;
				holder.appendChild(input);
			});
		});

		document.body.classList.remove('echo-ui-theme-auto', 'echo-ui-theme-dark', 'echo-ui-theme-light');
		document.body.classList.add('echo-ui-theme-' + s.settings_theme);

		var dirty = isDirty();
		$('#nc-savebar').classList.toggle('dirty', dirty);
		$('#nc-save').disabled = !dirty;
		$('#nc-stxt').textContent = dirty ? i18n.unsaved : i18n.allSaved;
		renderQueue();
	}

	function isDirty() {
		return JSON.stringify(s) !== JSON.stringify(saved);
	}

	function renderQueue() {
		$('#nc-qinfo').textContent = queue.length ? fmt(i18n.queued, vis.length, queue.length) : fmt(i18n.visible, vis.length);
	}

	function initEnhancedSelects() {
		var jq = window.jQuery;

		if (!jq || (!jq.fn.select2 && !jq.fn.selectWoo)) {
			return;
		}

		$$('.echo-ui-admin__select').forEach(function (select) {
			var $select = jq(select);
			var plugin = jq.fn.select2 ? 'select2' : 'selectWoo';

			if ($select.data('select2') || $select.data('selectWoo')) {
				return;
			}

			$select[plugin]({
				width: '100%',
				minimumResultsForSearch: 8,
				dropdownCssClass: 'echo-ui-nc-select-dropdown'
			});

			$select.on('change', function () {
				s[select.dataset.k] = select.value;
				render();
			});
		});
	}

	var savedOverride = null;

	function MSG() {
		if (!savedOverride) {
			return i18n.messages;
		}

		var messages = clone(i18n.messages);
		messages.success = savedOverride;
		return messages;
	}

	// Preview toasts.
	function speed() {
		return data.speeds[s.motion_speed] || data.speeds.normal;
	}

	function slideOffset() {
		if (/left$/.test(s.position)) {
			return '-24px';
		}

		return /center$/.test(s.position) ? '0px' : '24px';
	}

	function clearStage() {
		vis.forEach(function (item) {
			clearTimeout(item.timer);
		});
		$('#nc-tc').textContent = '';
		vis = [];
		queue = [];
	}

	function show(type) {
		if (vis.length >= s.max) {
			queue.push(type);
			renderQueue();
			return;
		}

		var message = MSG()[type];
		var toast = el('div', 'nc-toast' + (type === 'loading' ? ' nc-spin' : ''));
		var body = el('div');
		var close = el('button', 'nc-x', '×');
		var item = { node: toast, timer: null };

		toast.style.setProperty('--c', 'var(--nc-' + type + ')');
		toast.style.setProperty('--sx', slideOffset());
		toast.style.animation = 'nc-in-' + s.enter + ' ' + speed().enter + 'ms ease both';
		body.appendChild(el('div', 'nc-tt', message[0]));
		body.appendChild(el('div', 'nc-tb', message[1]));
		close.type = 'button';
		close.setAttribute('aria-label', i18n.dismiss);
		toast.appendChild(el('div', 'nc-ic', ICONS[type]));
		toast.appendChild(body);
		toast.appendChild(close);

		vis.push(item);
		$('#nc-tc').appendChild(toast);

		function kill() {
			clearTimeout(item.timer);

			if (vis.indexOf(item) === -1) {
				return;
			}

			vis = vis.filter(function (other) {
				return other !== item;
			});
			var exitMs = speed().exit;

			toast.style.animation = 'nc-out-' + s.exit + ' ' + exitMs + 'ms ease forwards';
			setTimeout(function () {
				toast.remove();

				if (queue.length) {
					show(queue.shift());
				}

				renderQueue();
			}, exitMs);
			renderQueue();
		}

		close.addEventListener('click', kill);

		var duration = +s['duration_' + type];
		if (duration > 0) {
			item.timer = setTimeout(kill, duration);
		}

		if (s.sound_enabled && +s.sound_volume > 0 && s.sound_types.indexOf(type) !== -1) {
			beep(type);
		}

		renderQueue();
	}

	function beep(type) {
		try {
			audio = audio || new (window.AudioContext || window.webkitAudioContext)();

			var osc = audio.createOscillator();
			var gain = audio.createGain();

			osc.frequency.value = type === 'error' ? 220 : type === 'warning' ? 330 : 660;
			osc.type = 'sine';
			gain.gain.setValueAtTime(+s.sound_volume || 0.0001, audio.currentTime);
			gain.gain.exponentialRampToValueAtTime(0.0001, audio.currentTime + 0.18);
			osc.connect(gain);
			gain.connect(audio.destination);
			osc.start();
			osc.stop(audio.currentTime + 0.2);
		} catch (error) {
			return;
		}
	}

	function clampInput(input) {
		if (input.type !== 'number' || !input.dataset.k) {
			return;
		}

		var min = input.min === '' ? -Infinity : +input.min;
		var max = input.max === '' ? Infinity : +input.max;
		var value = input.value === '' ? min : +input.value;

		s[input.dataset.k] = Math.min(max, Math.max(min, isNaN(value) ? min : value));
		input.value = s[input.dataset.k];
	}

	// Events.
	root.addEventListener('input', function (event) {
		var input = event.target;
		var key = input.dataset && input.dataset.k;

		if (!key) {
			return;
		}

		if (input.type === 'checkbox') {
			s[key] = input.checked;
		} else if (input.type === 'radio') {
			if (input.checked) {
				s[key] = input.value;
			}
		} else if (input.type === 'number' || input.type === 'range') {
			s[key] = input.value === '' ? 0 : +input.value;
		} else {
			s[key] = input.value;
		}

		render();
	});

	root.addEventListener('change', function (event) {
		clampInput(event.target);
		render();
	});

	root.addEventListener('click', function (event) {
		var button = event.target.closest('button');

		if (!button || !root.contains(button) || button.type === 'submit') {
			return;
		}

		if (button.dataset.p) {
			s.position = button.dataset.p;
			render();
			clearStage();
			show(ptype);
			return;
		}

		if (button.dataset.list) {
			var list = button.dataset.list;
			var types = s[list].slice();
			var index = types.indexOf(button.dataset.type);

			if (index === -1) {
				types.push(button.dataset.type);
			} else {
				types.splice(index, 1);
			}

			s[list] = types;
			s = normalize(s);
			render();
		}

		if (button.dataset.pt) {
			ptype = button.dataset.pt;
			render();
			show(ptype);
		}

		if (button.dataset.cx) {
			ctx = button.dataset.cx;
			render();
		}

		if (button.dataset.step) {
			var key = button.dataset.for;
			var input = $('input[data-k="' + key + '"]');
			var min = input ? +input.min : 1;
			var max = input ? +input.max : 100;

			s[key] = Math.min(max, Math.max(min, +s[key] + +button.dataset.step));
			render();
		}
	});

	$('#nc-play').addEventListener('click', function () {
		show(ptype);
	});

	$('#nc-stack').addEventListener('click', function () {
		for (var i = 0; i < +s.max + 2; i++) {
			setTimeout(show.bind(null, TYPES[i % TYPES.length]), i * 140);
		}
	});

	$('#nc-tsnd').addEventListener('click', function () {
		beep('info');
	});

	$('#nc-copy').addEventListener('click', function () {
		var button = this;
		var done = function (label) {
			button.textContent = label;
			setTimeout(function () {
				button.textContent = i18n.copy;
			}, 1400);
		};

		if (!navigator.clipboard) {
			done(i18n.copyFailed);
			return;
		}

		navigator.clipboard.writeText($('#nc-code').textContent).then(function () {
			done(i18n.copied);
		}, function () {
			done(i18n.copyFailed);
		});
	});

	$('#nc-reset').addEventListener('click', function () {
		s = normalize(data.defaults);
		render();
	});

	form.addEventListener('submit', function (event) {
		if ($('#nc-save').disabled) {
			event.preventDefault();
			return;
		}

		$$('input[type="number"][data-k]').forEach(clampInput);
		submitting = true;
		render();
	});

	// Browsers show their own generic "Leave site?" prompt; custom text is ignored.
	window.addEventListener('beforeunload', function (event) {
		if (submitting || !isDirty()) {
			return;
		}

		event.preventDefault();
		event.returnValue = '';
	});

	initEnhancedSelects();
	render();

	setTimeout(function () {
		if (data.justSaved) {
			savedOverride = i18n.savedToast;
			show('success');
			savedOverride = null;
			return;
		}

		show('info');
	}, 400);
})(window, document);
