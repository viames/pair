<?php

namespace Pair\Services;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

use SimpleEmailService;
use SimpleEmailServiceMessage;

/**
 * Class specialized in sending emails in HTML format with PHPMailer or Amazon Simple Email Service (SES).
 */
class AmazonSes extends Mailer {

	/**
	 * The Amazon SES access key ID.
	 */
	protected ?string $sesAccessKeyId = NULL;

	/**
	 * The Amazon SES secret access key.
	 */
	protected ?string $sesSecretAccessKey = NULL;

	/**
	 * The Amazon SES region.
	 */
	protected ?string $sesRegion = NULL;

	protected function checkConfig(): void {

		if (!$this->fromAddress) {
			throw new PairException('Missing e-mail sender address (fromAddress) in configuration', ErrorCodes::MISSING_CONFIGURATION);
		}

		if (!filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL)) {
			throw new PairException('Invalid e-mail sender address (fromAddress) in configuration', ErrorCodes::MISSING_CONFIGURATION);
		}

		if (!$this->fromName) {
			throw new PairException('Missing e-mail sender name (fromName) in configuration', ErrorCodes::MISSING_CONFIGURATION);
		}

		if (!$this->sesAccessKeyId) {
			throw new PairException('Missing Amazon S3 Access Key ID (sesAccessKeyId) in configuration', ErrorCodes::MISSING_CONFIGURATION);
		}

		if (!$this->sesSecretAccessKey) {
			throw new PairException('Missing Amazon S3 Secret Access Key (sesSecretAccessKey) in configuration', ErrorCodes::MISSING_CONFIGURATION);
		}

		if (!$this->sesRegion) {
			throw new PairException('Missing Amazon S3 Region (sesRegion) in configuration', ErrorCodes::MISSING_CONFIGURATION);
		}

	}

	/**
	 * Configure a SimpleEmailServiceMessage object. If is development environment, the recipient becomes the list
	 * of admin specified in the options.
	 *
	 * @param	array		List of recipients as stdClass objects with email and name properties.
	 * @param	string		Email subject.
	 * @param	string		Content title.
	 * @param	string		Content text.
	 * @param	stdClass[]	Optional attachment objects {filePath, name}.
	 * @param	stdClass[]	Optional list of carbon copy addresses as stdClass objects with email and name properties.
	 * 
	 * @throws	PairException
	 */
	public function send(array $recipients, string $subject, string $title, string $text, array $attachments = [], array $ccs = []): void {

		// throw an exception if the required configuration is not set
		$this->checkConfig();

		$realRecipient = $this->getRealRecipients($recipients);
		$realCc = $this->getRealCarbonCopy($ccs);

		$ses = new SimpleEmailService($this->sesAccessKeyId, $this->sesSecretAccessKey, $this->sesRegion);

		$message = new SimpleEmailServiceMessage();

		// set sender data
		$message->setFrom($this->fromAddress, $this->fromName);

		// recipients and carbon copy are replaced in development and staging environment
		$message->addTo($realRecipient);

		if (count($realCc)) {
			$message->addCC($ccs);
		}

		$message->setSubject($subject);

		// set the email body content
		$message->setMessageFromString($text, $this->getBody($text, $title, $text));

		// add each attachment
		foreach ($attachments as $att) {
			$message->addAttachmentFromFile($att->name, $att->filePath);
		}

		if (FALSE === $ses->sendEmail($message)) {
			throw new PairException('Error sending email with Amazon SES');
		}

	}

	/**
	 * Set the configuration of the email sender.
	 */
	public function setConfig(array $config): void {

		$stringOptions = [
			'applicationLogo',
			'fromAddress',
			'fromName',
			'sesAccessKeyId',
			'sesSecretAccessKey',
			'sesRegion'
		];

		foreach ($stringOptions as $option) {
			if (isset($config[$option])) {
				$this->$option = $config[$option];
			}
		}

		if (isset($config['adminEmails'])) {
			$this->adminEmails = (array)$config['adminEmails'];
		}

	}

}