<?php

namespace Pair\Services;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\Mailer;

use SimpleEmailService;
use SimpleEmailServiceMessage;

/**
 * Class specialized in sending emails in HTML format with PHPMailer or Amazon Simple Email Service (SES).
 * Extends the Mailer base class.
 * Requires the daniel-zahariev/php-aws-ses package: composer require daniel-zahariev/php-aws-ses
 */
class AmazonSes extends Mailer {

	/**
	 * The Amazon SES access key ID.
	 */
	protected ?string $sesAccessKeyId = null;

	/**
	 * The Amazon SES secret access key.
	 */
	protected ?string $sesSecretAccessKey = null;

	/**
	 * The Amazon SES region.
	 */
	protected ?string $sesRegion = null;

	/**
	 * Check if the required configuration is set. Throw an exception if not.
	 */
	public function checkConfig(): void {

		$this->checkBaseConfig();

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
	 * of super users specified in the options.
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

		$ses = new SimpleEmailService($this->sesAccessKeyId, $this->sesSecretAccessKey, $this->sesRegion);
		
		$message = new SimpleEmailServiceMessage();
		
		// set sender data
		$message->setFrom($this->fromAddress, $this->fromName);
		
		// recipients and carbon copy are replaced in development and staging environment
		$realRecipients = $this->convertRecipients($recipients);
		$message->addTo($realRecipients);
		
		$realCcs = $this->convertCarbonCopy($ccs);
		if (count($realCcs)) {
			$message->addCC($ccs);
		}

		$message->setSubject($subject);

		// set the email body content
		$message->setMessageFromString($text, $this->getBody($text, $title, $text));

		// add each attachment
		foreach ($attachments as $att) {
			$message->addAttachmentFromFile($att->name, $att->filePath);
		}

		if (false === $ses->sendEmail($message)) {
			throw new PairException('Error sending email with Amazon SES');
		}

	}

	/**
	 * Set the configuration of the email sender.
	 * 
	 * @param	array	Associative array with configuration options (fromAddress, fromName, sesAccessKeyId, sesSecretAccessKey, sesRegion).
	 */
	public function setConfig(array $config): void {

		$this->setBaseConfig($config);

		$stringOptions = [
			'sesAccessKeyId',
			'sesSecretAccessKey',
			'sesRegion'
		];

		foreach ($stringOptions as $option) {
			if (isset($config[$option])) {
				$this->$option = $config[$option];
			}
		}

	}

}