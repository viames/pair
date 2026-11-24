<?php

namespace Pair\Tests\Unit\Push;

use Pair\Push\PushEndpoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PushEndpointTest extends TestCase {

	/** Allows only well-formed HTTPS endpoints for trusted browser push services. */
	#[DataProvider('endpoints')]
	public function testValidation(mixed $endpoint, bool $expected): void {

		self::assertSame($expected, PushEndpoint::isSupported($endpoint));

	}

	/** Supplies supported subscriptions and URLs that only resemble trusted endpoints. */
	public static function endpoints(): iterable {

		yield 'Edge' => ['https://wns2-db5p.notify.windows.com/w/?token=fixture%2Fvalue', true];
		yield 'Edge legacy regional host' => ['https://DB5P.NOTIFY.WINDOWS.COM:443/w/fixture', true];
		yield 'Chrome' => ['https://fcm.googleapis.com/fcm/send/fixture', true];
		yield 'Firefox' => ['https://updates.push.services.mozilla.com/wpush/v2/fixture', true];
		yield 'Safari' => ['https://web.push.apple.com/fixture', true];
		yield 'bare WNS domain' => ['https://notify.windows.com/w/fixture', false];
		yield 'nested subdomain' => ['https://extra.wns2-db5p.notify.windows.com/w/fixture', false];
		yield 'host prefix' => ['https://notnotify.windows.com/w/fixture', false];
		yield 'host suffix' => ['https://wns2-db5p.notify.windows.com.example/w/fixture', false];
		yield 'user info' => ['https://someone@wns2-db5p.notify.windows.com/w/fixture', false];
		yield 'non-HTTPS' => ['http://wns2-db5p.notify.windows.com/w/fixture', false];
		yield 'nonstandard port' => ['https://wns2-db5p.notify.windows.com:8443/w/fixture', false];
		yield 'fragment' => ['https://wns2-db5p.notify.windows.com/w/fixture#x', false];
		yield 'backslash' => ['https://wns2-db5p.notify.windows.com/\\fixture', false];
		yield 'no path' => ['https://wns2-db5p.notify.windows.com', false];
		yield 'non-string' => [['https://wns2-db5p.notify.windows.com/w/fixture'], false];

	}

}
