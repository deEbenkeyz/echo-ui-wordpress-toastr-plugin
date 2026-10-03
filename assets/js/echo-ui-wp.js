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

		return window.Echo[type](title, prepare(toast));
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
		installFetchInterceptor();
		installXhrInterceptor();

		document.querySelectorAll('[data-echo-ui-toast]').forEach(function (button) {
			button.addEventListener('click', function () {
				try {
					show(JSON.parse(button.getAttribute('data-echo-ui-toast') || '{}'));
				} catch (error) {
					return null;
				}
			});
		});

		document.dispatchEvent(new CustomEvent('echo-ui-toasts-ready', {
			detail: {
				Echo: window.Echo
			}
		}));
	});
})(window, document);
