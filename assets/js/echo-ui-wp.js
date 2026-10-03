(function (window, document) {
	'use strict';

	var settings = window.echoUiToastrSettings || {};
	var pageContext = settings.context || 'frontend';

	function ready(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
			return;
		}

		callback();
	}

	function canShow(toast, isRemote) {
		var context = toast && toast.context ? toast.context : '';

		if (pageContext === 'frontend') {
			if (context === 'admin') {
				return false;
			}

			if (isRemote && context !== 'frontend' && context !== 'public') {
				return false;
			}
		}

		if (pageContext === 'admin' && (context === 'frontend' || context === 'public')) {
			return false;
		}

		return true;
	}

	// PHP cannot send callbacks, so `action: { label, url }` becomes an Echo action that navigates.
	function prepare(toast) {
		var action = toast && toast.action;

		if (!action || typeof action.onClick === 'function') {
			return toast;
		}

		var copy = Object.assign({}, toast);
		var url = String(action.url || '');

		if (action.label && (/^https?:\/\//i.test(url) || /^\/(?!\/)/.test(url))) {
			copy.action = {
				label: String(action.label),
				onClick: function () {
					window.location.href = url;
				}
			};
		} else {
			delete copy.action;
		}

		return copy;
	}

	function show(toast, isRemote) {
		if (!window.Echo || !toast) {
			return null;
		}

		var type = toast.type || 'info';
		var title = toast.title || toast.message || '';

		if (!title || typeof window.Echo[type] !== 'function' || !canShow(toast, isRemote)) {
			return null;
		}

		var id = window.Echo[type](title, prepare(toast));
		enhanceToastDescription(id);

		return id;
	}

	function enhanceToastDescription(id) {
		if (!id) {
			return;
		}

		window.setTimeout(function () {
			var escapedId = window.CSS && window.CSS.escape ? window.CSS.escape(String(id)) : String(id).replace(/"/g, '\\"');
			var toast = document.querySelector('[data-echo-toast][data-echo-id="' + escapedId + '"]');
			var description = toast && toast.querySelector('.echo-toast__description');

			if (!toast || !description || description.hidden || description.dataset.echoUiEnhanced === 'true') {
				return;
			}

			description.dataset.echoUiEnhanced = 'true';
			description.title = description.textContent || '';
			toast.classList.add('echo-toast--has-description');
			toast.setAttribute('tabindex', '0');
			toast.setAttribute('aria-expanded', 'false');
		}, 0);
	}

	function enhanceExistingDescriptions() {
		document.querySelectorAll('[data-echo-toast][data-echo-id]').forEach(function (toast) {
			enhanceToastDescription(toast.dataset.echoId);
		});
	}

	window.echoUiToast = function (type, title, options) {
		return show(Object.assign({}, options || {}, {
			type: type,
			title: title
		}));
	};

	window.echoUiProcessResponse = function (response) {
		processResponse(response, true);
	};

	function normalizeList(value) {
		if (!value) {
			return [];
		}

		return Array.isArray(value) ? value : [value];
	}

	function processResponse(response, isRemote) {
		if (!response || typeof response !== 'object') {
			return;
		}

		normalizeList(response.toast).forEach(function (toast) {
			show(toast, isRemote);
		});

		normalizeList(response.toasts).forEach(function (toast) {
			show(toast, isRemote);
		});

		if (response.data && typeof response.data === 'object') {
			processResponse(response.data, isRemote);
		}
	}

	function parseJson(text) {
		if (!text || typeof text !== 'string') {
			return null;
		}

		try {
			return JSON.parse(text);
		} catch (error) {
			return null;
		}
	}

	function installFetchInterceptor() {
		if (!window.fetch) {
			return;
		}

		var originalFetch = window.fetch;

		window.fetch = function () {
			return originalFetch.apply(this, arguments).then(function (response) {
				var contentType = response.headers && response.headers.get('content-type');

				if (contentType && contentType.indexOf('application/json') !== -1) {
					response.clone().json().then(function (json) {
						processResponse(json, true);
					}).catch(function () {});
				}

				return response;
			});
		};
	}

	function installXhrInterceptor() {
		if (!window.XMLHttpRequest || !window.XMLHttpRequest.prototype) {
			return;
		}

		var originalOpen = window.XMLHttpRequest.prototype.open;

		window.XMLHttpRequest.prototype.open = function () {
			this.addEventListener('load', function () {
				var contentType = xhr.getResponseHeader('content-type') || '';

				if (contentType.indexOf('application/json') !== -1 || /^\s*[\[{]/.test(xhr.responseText || '')) {
					processResponse(parseJson(xhr.responseText), true);
				}
			});

			var xhr = this;
			return originalOpen.apply(this, arguments);
		};
	}

	// WooCommerce Cart/Checkout blocks keep their notices in the core/notices data store
	// (contexts like wc/cart, wc/checkout/payments) instead of PHP sessions.
	function installWooBlocksBridge() {
		var wpData = window.wp && window.wp.data;

		if (!settings.wooBlocks || !wpData || !wpData.select('core/notices')) {
			return;
		}

		var baseContexts = ['wc/cart', 'wc/checkout', 'wc/blocks'];
		var statusMap = { error: 'error', success: 'success', warning: 'warning', info: 'info' };
		var seen = {};
		var busy = false;

		function contexts() {
			var list = baseContexts.slice();
			var registry = wpData.select('wc/store/store-notices');
			var registered = registry && registry.getRegisteredContainers ? registry.getRegisteredContainers() : [];

			(registered || []).forEach(function (context) {
				if (typeof context === 'string' && list.indexOf(context) === -1) {
					list.push(context);
				}
			});

			return list;
		}

		function plainText(content) {
			var node = document.createElement('div');
			node.innerHTML = String(content || '');
			return (node.textContent || '').replace(/\s+/g, ' ').trim();
		}

		function sync() {
			if (busy) {
				return;
			}

			busy = true;

			try {
				var notices = wpData.select('core/notices');
				var dispatch = wpData.dispatch('core/notices');

				contexts().forEach(function (context) {
					(notices.getNotices(context) || []).forEach(function (notice) {
						var title = plainText(notice.content);

						if (seen[notice.id] || !title) {
							return;
						}

						seen[notice.id] = true;
						show({ type: statusMap[notice.status] || 'info', title: title, context: 'frontend' }, false);
						dispatch.removeNotice(notice.id, context);
					});
				});
			} finally {
				busy = false;
			}
		}

		wpData.subscribe(sync);
		sync();
	}

	function ensureWrapperId() {
		var root = document.querySelector('[data-echo-root]');

		if (root && !root.id) {
			root.id = 'echo-ui-wrapper';
		}
	}

	ready(function () {
		if (!window.Echo) {
			return;
		}

		window.Echo.configure(settings.options || {});
		window.Echo.boot((settings.toasts || []).map(prepare));
		ensureWrapperId();
		enhanceExistingDescriptions();
		installFetchInterceptor();
		installXhrInterceptor();
		installWooBlocksBridge();

		// Delegated so shortcode buttons added later (popups, AJAX content, tabs) work too.
		document.addEventListener('click', function (event) {
			var button = event.target.closest && event.target.closest('[data-echo-ui-toast]');

			var toast = event.target.closest && event.target.closest('[data-echo-toast].echo-toast--has-description');

			if (toast && !event.target.closest('button')) {
				var expanded = toast.classList.toggle('echo-toast--expanded');
				toast.setAttribute('aria-expanded', expanded ? 'true' : 'false');
				return;
			}

			if (!button) {
				return;
			}

			try {
				show(JSON.parse(button.getAttribute('data-echo-ui-toast') || '{}'));
			} catch (error) {
				return;
			}
		});

		document.dispatchEvent(new CustomEvent('echo-ui-toasts-ready', {
			detail: {
				Echo: window.Echo
			}
		}));
	});
})(window, document);
