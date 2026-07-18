<?php

declare(strict_types=1);

namespace Pair\Tests\Unit\Services;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Services\SmtpMailer;
use Pair\Tests\Support\TestCase;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Minimal optional-dependency stub used when PHPMailer is not installed in Pair itself.
 */
class SmtpMailerLifecycleDependencyStub {

	public string $CharSet = '';
	public bool $SMTPAuth = false;
	public string $Host = '';
	public int $Port = 0;
	public mixed $SMTPSecure = null;
	public ?string $Username = null;
	public ?string $Password = null;
	public int $SMTPDebug = 0;
	public string $Subject = '';
	public string $ErrorInfo = '';

	/**
	 * Accept a recipient without performing address validation in this lifecycle test.
	 */
	public function addAddress(mixed ...$arguments): bool {

		return true;

	}

	/**
	 * Accept an attachment without touching the filesystem in this lifecycle test.
	 */
	public function addAttachment(mixed ...$arguments): bool {

		return true;

	}

	/**
	 * Accept a carbon-copy recipient without performing address validation.
	 */
	public function addCC(mixed ...$arguments): bool {

		return true;

	}

	/**
	 * Select the SMTP transport without creating a network client.
	 */
	public function isSMTP(): void {}

	/**
	 * Accept the HTML body without parsing external resources.
	 */
	public function msgHTML(mixed ...$arguments): string {

		return '';

	}

	/**
	 * Default successful transport result overridden by the specialized fixture.
	 */
	public function postSend(): bool {

		return true;

	}

	/**
	 * Default successful preparation result overridden by the specialized fixture.
	 */
	public function preSend(): bool {

		return true;

	}

	/**
	 * Accept sender metadata without performing address validation.
	 */
	public function setFrom(mixed ...$arguments): bool {

		return true;

	}

}

if (!class_exists(PHPMailer::class)) {
	class_alias(SmtpMailerLifecycleDependencyStub::class, PHPMailer::class);
}

/**
 * PHPMailer fixture that records preparation and transport calls without network access.
 */
final class SmtpMailerLifecyclePhpMailerFixture extends PHPMailer {

	/**
	 * Ordered lifecycle events observed by the fixture.
	 *
	 * @var string[]
	 */
	public array $events = [];

	/**
	 * Result returned by message preparation.
	 */
	public bool $preSendResult = true;

	/**
	 * Result returned by the transport operation.
	 */
	public bool $postSendResult = true;

	/**
	 * Record message preparation and return the configured outcome.
	 */
	public function preSend(): bool {

		$this->events[] = 'prepare';
		if (!$this->preSendResult) {
			$this->ErrorInfo = 'Message preparation failed';
		}

		return $this->preSendResult;

	}

	/**
	 * Record the transport operation and return the configured outcome.
	 */
	public function postSend(): bool {

		$this->events[] = 'transport';
		if (!$this->postSendResult) {
			$this->ErrorInfo = 'SMTP transport failed';
		}

		return $this->postSendResult;

	}

}

/**
 * SMTP mailer fixture exposing a deterministic PHPMailer client.
 */
final class SmtpMailerLifecycleFixture extends SmtpMailer {

	/**
	 * Initialize the mailer with a safe local configuration.
	 */
	public function __construct(private readonly SmtpMailerLifecyclePhpMailerFixture $phpMailer) {

		parent::__construct([
			'fromAddress' => 'sender@example.test',
			'fromName' => 'Pair Test',
			'smtpHost' => '127.0.0.1',
			'smtpPort' => 25,
			'smtpAuth' => false,
		]);

	}

	/**
	 * Record the exact point at which transport may begin.
	 */
	protected function beforeTransportSend(): void {

		$this->phpMailer->events[] = 'hook';

	}

	/**
	 * Return the deterministic PHPMailer fixture.
	 */
	protected function createPhpMailer(): PHPMailer {

		return $this->phpMailer;

	}

	/**
	 * Return a deterministic body without loading application translations.
	 */
	protected function getBody(string $preHeader, string $title, string $text): string {

		return $text;

	}

	/**
	 * Preserve the requested recipients without environment-dependent rewriting.
	 */
	protected function convertRecipients(array $desiredRecipients): array {

		return $desiredRecipients;

	}

	/**
	 * Preserve carbon-copy recipients without environment-dependent rewriting.
	 */
	protected function convertCarbonCopy(array $desiredCcs): array {

		return $desiredCcs;

	}

}

/**
 * Covers the SMTP transport lifecycle boundary exposed to specialized mailers.
 */
final class SmtpMailerLifecycleTest extends TestCase {

	/**
	 * Verify the hook is protected and runs between preparation and transport.
	 */
	public function testTransportHookRunsAtTheDeliveryBoundary(): void {

		$hook = new \ReflectionMethod(SmtpMailer::class, 'beforeTransportSend');
		$phpMailer = new SmtpMailerLifecyclePhpMailerFixture();
		$mailer = new SmtpMailerLifecycleFixture($phpMailer);

		$mailer->send([(object)['email' => 'recipient@example.test', 'name' => 'Recipient']], 'Subject', 'Title', 'Body');

		$this->assertTrue(SmtpMailer::TRANSPORT_START_HOOK);
		$this->assertTrue($hook->isProtected());
		$this->assertSame(['prepare', 'hook', 'transport'], $phpMailer->events);

	}

	/**
	 * Verify a preparation failure never crosses the transport boundary.
	 */
	public function testPreparationFailureDoesNotInvokeTransportHook(): void {

		$phpMailer = new SmtpMailerLifecyclePhpMailerFixture();
		$phpMailer->preSendResult = false;
		$mailer = new SmtpMailerLifecycleFixture($phpMailer);

		try {
			$mailer->send([(object)['email' => 'recipient@example.test', 'name' => 'Recipient']], 'Subject', 'Title', 'Body');
			$this->fail('A failed message preparation must raise PairException.');
		} catch (PairException $e) {
			$this->assertSame(ErrorCodes::EMAIL_SEND_ERROR, $e->getCode());
			$this->assertSame('Message preparation failed', $e->getMessage());
		}

		$this->assertSame(['prepare'], $phpMailer->events);

	}

	/**
	 * Verify a false transport result is never reported as a successful delivery.
	 */
	public function testFalseTransportResultRaisesAfterBoundaryHook(): void {

		$phpMailer = new SmtpMailerLifecyclePhpMailerFixture();
		$phpMailer->postSendResult = false;
		$mailer = new SmtpMailerLifecycleFixture($phpMailer);

		try {
			$mailer->send([(object)['email' => 'recipient@example.test', 'name' => 'Recipient']], 'Subject', 'Title', 'Body');
			$this->fail('A failed SMTP transport must raise PairException.');
		} catch (PairException $e) {
			$this->assertSame(ErrorCodes::EMAIL_SEND_ERROR, $e->getCode());
			$this->assertSame('SMTP transport failed', $e->getMessage());
		}

		$this->assertSame(['prepare', 'hook', 'transport'], $phpMailer->events);

	}

}
