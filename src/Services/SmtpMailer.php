<?php

namespace Pair\Services;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\Mailer;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Class specialized in sending emails in HTML format with PHPMailer or Amazon Simple Email Service (SES).
 */
class SmtpMailer extends Mailer {

	/**
	 * The SMTP authentication flag.
	 */
	protected bool $smtpAuth = TRUE;

	/**
	 * The SMTP host.
	 */
	protected ?string $smtpHost = NULL;

	/**
	 * The SMTP port.
	 */
	protected ?int $smtpPort = NULL;

	/**
	 * The SMTP secure protocol (NULL|ssl|tls)
	 */
	protected ?string $smtpSecure = NULL;

	/**
	 * The SMTP username.
	 */
	protected ?string $smtpUsername = NULL;

	/**
	 * The SMTP password.
	 */
	protected ?string $smtpPassword = NULL;

	/**
	 * The SMTP debug level.
	 */
	protected ?int $smtpDebug = 0;

	/**
	 * Check if the required configuration is set. Throw an exception if not.
	 */
	public function checkConfig(): void {

		$this->checkBaseConfig();

		if (!$this->smtpHost) {
			throw new PairException('Missing SMTP Host (smtpHost) in configuration', ErrorCodes::MISSING_CONFIGURATION);
		}

		if (!$this->smtpPort) {
			throw new PairException('Missing SMTP Port (smtpPort) in configuration', ErrorCodes::MISSING_CONFIGURATION);
		}

		if ($this->smtpAuth) {

			if (!$this->smtpUsername) {
				throw new PairException('Missing SMTP Username (smtpUsername) in configuration', ErrorCodes::MISSING_CONFIGURATION);
			}

			if (!$this->smtpPassword) {
				throw new PairException('Missing SMTP Password (smtpPassword) in configuration', ErrorCodes::MISSING_CONFIGURATION);
			}

		}

	}

	/**
	 * Configure a PhpMailer object preconfigured with charset, auth type, etc.
	 * If the Development option is active, the recipient becomes the list of admin in
	 * the options.
	 *
	 * @param	array		List of recipients as stdClass objects with email and name properties.
	 * @param	string		Email subject.
	 * @param	string		Content title.
	 * @param	string		Content text.
	 * @param	stdClass[]	Optional attachment objects {filePath, name}.
	 * @param	stdClass[]	List of carbon copy addresses as stdClass objects with email and name properties.
	 *
	 * @throws	PairException
	 */
	public function send(array $recipients, string $subject, string $title, string $text, array $attachments = [], array $ccs = []): void {

		// throw an exception if the required configuration is not set
		$this->checkConfig();

		$phpMailer = new PHPMailer();
		
		// smtp settings
		$phpMailer->isSMTP();
		$phpMailer->CharSet		= $this->charSet;
		$phpMailer->SMTPAuth	= $this->smtpAuth;
		$phpMailer->Host		= $this->smtpHost;
		$phpMailer->Port		= $this->smtpPort;
		$phpMailer->SMTPSecure	= $this->smtpSecure;
		$phpMailer->Username	= $this->smtpUsername;
		$phpMailer->Password	= $this->smtpPassword;
		$phpMailer->SMTPDebug	= $this->smtpDebug;
		
		// set sender data
		$phpMailer->setFrom($this->fromAddress, $this->fromName);		
		
		// recipients and carbon copy are replaced in development and staging environment
		$realRecipients = $this->convertRecipients($recipients);
		foreach ($realRecipients as $r) {
			$phpMailer->addAddress($r->email, $r->name);
		}
		
		$realCcs = $this->convertCarbonCopy($ccs);
		foreach ($realCcs as $cc) {
			$phpMailer->addCC($cc->email, $cc->name);
		}

		// email subject and body by form
		$phpMailer->Subject = $subject;

		// set the email body content
		$phpMailer->msgHTML(static::getBody($text, $title, $text));

		// real file attachments
		foreach ($attachments as $att) {
			$phpMailer->addAttachment($att->filePath, $att->name);
		}

		// send the email
		try {
			$phpMailer->send();
		} catch (\Exception $e) {
			throw new PairException($e->getMessage(), ErrorCodes::EMAIL_SEND_ERROR, $e);
		}

	}

	/**
	 * Set the configuration of the email sender.
	 * 
	 * @param	array	Associative array with configuration options (fromAddress, fromName, smtpHost, smtpPort, smtpSecure, smtpUsername, smtpPassword, smtpAuth, smtpDebug).
	 */
	public function setConfig(array $config): void {

		$this->setBaseConfig($config);

		$stringOptions = [
			'smtpHost',
			'smtpSecure',
			'smtpUsername',
			'smtpPassword',
		];

		foreach ($stringOptions as $option) {
			if (isset($config[$option])) {
				$this->$option = $config[$option];
			}
		}

		// int options
		$this->smtpPort	 = (isset($config['smtpPort'])) ? (int)$config['smtpPort'] : NULL;
		$this->smtpDebug = (isset($config['smtpDebug'])) ? (int)$config['smtpDebug'] : 0;

		// bool options with default TRUE
		$this->smtpAuth	 = (isset($config['smtpAuth']) and FALSE===$config['smtpAuth']) ? FALSE : TRUE;

	}

}