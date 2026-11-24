<?php

namespace Pair\Push;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Sets the raw notification type on requests addressed to Microsoft WNS. @internal */
final class WindowsPushClient implements ClientInterface {

	/** Wraps the same PSR-18 client used by Minishlink. */
	public function __construct(private ClientInterface $client) {}

	/** Sends the encrypted Web Push payload as a WNS raw notification. */
	public function sendRequest(RequestInterface $request): ResponseInterface {

		if (PushEndpoint::isWindowsHost($request->getUri()->getHost())) {
			$request = $request->withHeader('X-WNS-Type', 'wns/raw');
		}

		return $this->client->sendRequest($request);

	}

}
