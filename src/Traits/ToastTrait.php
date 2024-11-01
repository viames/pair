<?php

namespace Pair\Traits;

use Pair\Core\Application;
use Pair\Html\IziToast;

trait ToastTrait {

    /**
     * Add a toast message to the session.
     */
    public function toast(string $title, string $message='', ?string $type=NULL): IziToast {

        return Application::getInstance()->toast($title, $message, $type);

    }

	/**
	 * Proxy method to queue an error with a toast notification.
	 *
	 * @param	string	Message’s text.
	 * @param	string	Optional title.
	 */
	public function toastError(string $title, ?string $message=''): IziToast {

		return Application::getInstance()->toastError($title, $message);

	}

	public function toastErrorRedirect(string $title, string $message='', ?string $url=NULL): IziToast {

		return Application::getInstance()->toastErrorRedirect($message, $title, $url);

	}

    /**
     * Add a toast message to the session and redirect.
     */
    public function toastRedirect(string $title, string $message='', ?string $url=NULL): IziToast {

		return Application::getInstance()->toastRedirect($message, $title, $url);

    }

}