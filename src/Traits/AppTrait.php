<?php

namespace Pair\Traits;

use Pair\Core\Application;
use Pair\Html\IziToast;
use Pair\Html\SweetAlert;

/**
 * Convenience trait that exposes shortcuts to the Application singleton.
 * Provides helper methods for common tasks such as:
 * - enabling headless mode
 * - managing persistent and in-memory state
 * - loading CSS/JS assets
 * - showing modals and toast notifications
 * - redirecting the current request
 *
 * Intended to be used in controllers or other classes that need access to the application
 * core without calling Application::getInstance() directly.
 */
trait AppTrait {

	/**
	 * Sets the application to headless mode (no view rendering).
	 * Proxy to Application::headless().
	 */
	public function headless(bool $headless = true): void {

		Application::getInstance()->headless($headless);

	}

	/**
	 * Retrieves a persistent state value from cookies.
	 * Proxy to Application::getPersistentState().
	 *
	 * @param  string $key Name of the state variable.
	 * @return mixed       The stored value, or null if not found.
	 */
	public function getPersistentState(string $key): mixed {

		return Application::getInstance()->getPersistentState($key);

	}

	/**
	 * Registers a CSS file to be included in the page head.
	 * Proxy to Application::loadCss().
	 *
	 * @param string $href Stylesheet path, absolute or relative without trailing slash.
	 */
	public function loadCss(string $href): void {

		Application::getInstance()->loadCss($href);

	}

	/**
	 * Registers an external script file to be loaded, with optional attributes.
	 * Proxy to Application::loadScript().
	 *
	 * @param string $src     Script path, absolute or relative without trailing slash.
	 * @param bool   $defer   Whether to add the "defer" attribute (default false).
	 * @param bool   $async   Whether to add the "async" attribute (default false).
	 * @param array  $attribs Optional attributes (type, integrity, crossorigin, charset).
	 */
	public function loadScript(string $src, bool $defer = false, bool $async = false, array $attribs = []): void {

		Application::getInstance()->loadScript($src, $defer, $async, $attribs);

	}

	/**
	 * Registers a web app manifest file to be included in page head.
	 * Proxy to Application::loadManifest().
	 *
	 * @param string $href Manifest path, absolute or relative without trailing slash.
	 */
	public function loadManifest(string $href): void {

		Application::getInstance()->loadManifest($href);

	}

	/**
	 * Loads Pair PWA helper scripts.
	 * Proxy to Application::loadPwaScripts().
	 *
	 * @param string $assetsPath      Base assets path (default /assets).
	 * @param bool   $includePairUi   Whether to include PairUI.js.
	 * @param bool   $includePairPush Whether to include PairPush.js.
	 * @param bool   $includePairPasskey Whether to include PairPasskey.js.
	 */
	public function loadPwaScripts(string $assetsPath = '/assets', bool $includePairUi = false, bool $includePairPush = false, bool $includePairPasskey = false): void {

		Application::getInstance()->loadPwaScripts($assetsPath, $includePairUi, $includePairPush, $includePairPasskey);

	}

	/**
	 * Adds an alert modal to the page and returns it for further customization.
	 * Proxy to Application::modal().
	 *
	 * @param string $title   Modal title (bold).
	 * @param string $message Modal message.
	 * @param string $icon    Modal icon: info|success|error|warning|question (default 'info').
	 * @return SweetAlert
	 */
	public function modal(string $title, string $message, string $icon = 'info'): SweetAlert {

		return Application::getInstance()->modal($title, $message, $icon);

	}

	/**
	 * Sets the URL of the currently selected menu item.
	 * Proxy to Application::menuUrl().
	 *
	 * @param string $url Menu item URL.
	 */
	public function menuUrl(string $url): void {

		Application::getInstance()->menuUrl($url);

	}

	/**
	 * Queues a persistent alert modal to be shown on the next page load.
	 * Proxy to Application::persistentModal().
	 *
	 * @param string $title   Modal title.
	 * @param string $message Modal message.
	 * @param string $type    Modal icon/type (info|success|error|warning|question), default 'info'.
	 */
	public function persistentModal(string $title, string $message, string $type = 'info'): void {

		Application::getInstance()->persistentModal($title, $message, $type);

	}

	/**
	 * Redirects the client to the given URL. If a relative URL is provided, the application
	 * base URL is automatically prepended (unless $externalUrl is true). Any queued toast
	 * notifications and modal are stored in cookies so they can be retrieved on the next request.
	 * Proxy to Application::redirect().
	 *
	 * @param string|null $url         Target URL. If null, redirects to the current module.
	 * @param bool        $externalUrl If true, treats $url as absolute and does not prepend the base URL.
	 */
	public function redirect(?string $url = null, bool $externalUrl = false): void {

		Application::getInstance()->redirect($url, $externalUrl);

	}

	/**
	 * Sets the web page heading (h1).
	 * Proxy to Application::pageHeading().
	 */
	public function pageHeading(string $heading): void {

		Application::getInstance()->pageHeading($heading);

	}

	/**
	 * Sets the web page title (displayed in the browser tab).
	 * Proxy to Application::pageTitle().
	 */
	public function pageTitle(string $title): void {

		Application::getInstance()->pageTitle($title);

	}

	/**
	 * Stores a persistent state value in a cookie for later retrieval.
	 * Proxy to Application::setPersistentState().
	 *
	 * @param string $key   State variable name.
	 * @param mixed  $value Serializable value to store.
	 */
	public function setPersistentState(string $key, mixed $value): void {

		Application::getInstance()->setPersistentState($key, $value);

	}

	/**
	 * Sets an in-memory state variable on the Application instance.
	 * Proxy to Application::setState().
	 *
	 * @param string $name  State variable name.
	 * @param mixed  $value Value to store.
	 */
	public function setState(string $name, mixed $value): void {

		Application::getInstance()->setState($name, $value);

	}

	/**
	 * Appends a toast notification to the queue.
	 * Proxy to Application::toast().
	 *
	 * @param string      $title   Toast title (bold).
	 * @param string      $message Toast message text.
	 * @param string|null $type    Toast type (info|success|warning|error|question|progress).
	 * @return IziToast
	 */
    public function toast(string $title, string $message = '', ?string $type = null): IziToast {

        return Application::getInstance()->toast($title, $message, $type);

    }

	/**
	 * Queues an error toast notification.
	 * Proxy to Application::toastError().
	 *
	 * @param string $title   Toast title (bold).
	 * @param string $message Error message text.
	 * @return IziToast
	 */
	public function toastError(string $title, string $message = ''): IziToast {

		return Application::getInstance()->toastError($title, $message);

	}

	/**
	 * Queues an error toast notification and then redirects.
	 * Proxy to Application::toastErrorRedirect().
	 *
	 * @param string      $title   Toast title (bold).
	 * @param string      $message Error message text.
	 * @param string|null $url     Redirect URL, optional.
	 */
	public function toastErrorRedirect(string $title, string $message = '', ?string $url = null): void {

		Application::getInstance()->toastErrorRedirect($title, $message, $url);

	}

	/**
	 * Queues a success toast notification and then redirects.
	 * Proxy to Application::toastRedirect().
	 *
	 * @param string      $title   Toast title (bold).
	 * @param string      $message Message text.
	 * @param string|null $url     Redirect URL, optional.
	 */
    public function toastRedirect(string $title, string $message = '', ?string $url = null): void {

		Application::getInstance()->toastRedirect($title, $message, $url);

    }

	/**
	 * Removes a persistent state value from cookies.
	 * Proxy to Application::unsetPersistentState().
	 *
	 * @param string $key State variable name.
	 */
	public function unsetPersistentState(string $key): void {

		Application::getInstance()->unsetPersistentState($key);

	}

	/**
	 * Deletes an in-memory state variable from the Application instance.
	 * Proxy to Application::unsetState().
	 *
	 * @param string $name State variable name.
	 */
	public function unsetState(string $name): void {

		Application::getInstance()->unsetState($name);

	}

}
