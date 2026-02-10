<?php

namespace Pair\Helpers;

use Pair\Core\Application;
use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\Translator;
use Pair\Models\Session;
use Pair\Models\User;

/**
 * Base class for sending e-mails.
 */
abstract class Mailer {

	/**
	 * The list of admin email addresses.
	 */
	protected array $adminEmails = [];

	/**
	 * The URL of the application logo.
	 */
	protected ?string $applicationLogo = null;

	/**
	 * The charset of the email.
	 */
	protected string $charSet = 'utf-8';

	/**
	 * The email address of the sender.
	 */
	protected ?string $fromAddress = null;

	/**
	 * The name of the sender.
	 */
	protected ?string $fromName = null;

	/**
	 * Default application logo.
	 */
	const PAIR_LOGO = 'https://github.com/viames/pair/wiki/files/pair-logo.png';

	/**
	 * Set the configuration of the email sender.
	 */
	public function __construct(array $config) {

		$this->setConfig($config);

		$this->checkConfig();

	}

	/**
	 * Adapt an array of recipients to a standard object.
	 *
	 * @throws PairException
	 */
	private function adaptRecipients(array $recipients): array {

		$list = [];

		foreach ($recipients as $recipient) {

			if (is_string($recipient)) {

				$list[] = (object)['name'=>$recipient, 'email'=>$recipient];

			} else if (is_array($recipient)) {

				if (!isset($recipient['email'])) {
					throw new PairException('Missing e-mail address in recipient', ErrorCodes::MISSING_CONFIGURATION);
				}

				$list[] = (object)$recipient;

			} else if (is_object($recipient)) {

				if (!isset($recipient->email)) {
					throw new PairException('Missing e-mail address in recipient', ErrorCodes::MISSING_CONFIGURATION);
				}

				$list[] = $recipient;

			} else {

				throw new PairException('Invalid recipient type: ' . gettype($recipient), ErrorCodes::MISSING_CONFIGURATION);

			}

		}

		return $list;

	}

	/**
	 * Check if the required configuration is set. Throw an exception if not.
	 */
	protected function checkBaseConfig(): void {

		if (!$this->fromAddress) {
			throw new PairException('Missing e-mail sender address (fromAddress) in configuration', ErrorCodes::MISSING_CONFIGURATION);
		}

		if (!filter_var($this->fromAddress, FILTER_VALIDATE_EMAIL)) {
			throw new PairException('Invalid e-mail sender address (fromAddress) in configuration', ErrorCodes::MISSING_CONFIGURATION);
		}

		if (!$this->fromName) {
			throw new PairException('Missing e-mail sender name (fromName) in configuration', ErrorCodes::MISSING_CONFIGURATION);
		}

	}

	/**
	 * Check if the required configuration is set and throw an exception if not. Overwritten in child classes.
	 */
	public function checkConfig(): void {

		$this->checkBaseConfig();

	}

	/**
	 * Returns a list of carbon copy addresses only in production environment.
	 */
	protected function convertCarbonCopy(array $desiredCcs): array {

		return 'production' == Application::getEnvironment()
			? $this->adaptRecipients($desiredCcs)
			: [];

	}

	/**
	 * Based on run environment, returns the list of final recipients.
	 *
	 * @throws PairException
	 */
	protected function convertRecipients(array $desiredRecipients): array {

		$recipients = [];

		$setAdmins = function () use (&$recipients) {
			foreach ($this->adminEmails as $adminEmail) {
				$recipients[] = (object)['name'=>Env::get('APP_NAME') . ' Admin', 'email'=>$adminEmail];
			}
			if (!count($recipients)) {
				throw new PairException('In development environment there are no admin e-mail addresses', ErrorCodes::MISSING_CONFIGURATION);
			}
		};

		switch (Application::getEnvironment()) {

			case 'development':

				$setAdmins();
				break;

			case 'staging':

				$cUser = User::current();
				$session = Session::current();

				// check if the user is impersonating another user
				if ($session->hasFormerUser()) {

					$realUser = $session->getFormerUser();
					$recipients[] = (object)['name'=>$realUser->fullName, 'email'=>$realUser->email];

				} else if ($cUser) {

					$recipients[] = (object)['name'=>$cUser->fullName, 'email'=>$cUser->email];

				} else {

					$setAdmins();

				}
				break;

			case 'production':

				$recipients = $this->adaptRecipients($desiredRecipients);
				break;

			default:

				throw new PairException('Unexpected environment');

		}

		return $recipients;

	}

	/**
	 * Return the HTML code of an email.
	 *
	 * @param	string	Pre-header for content preview on certain devices.
	 * @param	string	Content title.
	 * @param	string	Content text.
	 */
	protected function getBody(string $preHeader, string $title, string $text): string {

		$logo = $this->applicationLogo ?? self::PAIR_LOGO;
		$appName = Env::get('APP_NAME');

		return
'<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
	<head>
		<title>' . $title . '</title>
		<meta charset="UTF-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<meta name="format-detection" content="telephone=no, address=no, email=no, date=no" />
		<meta name="color-scheme" content="light dark" />
		<meta name="supported-color-schemes" content="light dark" />
		<meta http-equiv="X-UA-Compatible" content="IE=edge" />
		<!--[if gte mso 9]><xml>
		<o:OfficeDocumentSettings>
			<o:AllowPNG/>
			<o:PixelsPerInch>96</o:PixelsPerInch>
		</o:OfficeDocumentSettings>
		</xml><![endif]-->
		<!--[if mso]>
		<style>body, table tr, table td, a, span, table.MsoNormalTable { font-family: Arial, Helvetica, sans-serif !important; }</style>
		<![endif]-->
		<style>
		:root {
			color-scheme: light dark;
		}
		body {
			margin: 0;
			padding: 0;
			width: 100%;
			-webkit-text-size-adjust: 100%;
			-ms-text-size-adjust: 100%;
			font-family: Arial, Helvetica, sans-serif;
		}
		table {
			border-spacing: 0;
			border-collapse: collapse;
			mso-table-lspace: 0pt;
			mso-table-rspace: 0pt;
		}
		img {
			-ms-interpolation-mode: bicubic;
			border: 0;
			outline: none;
		}
		a {
			text-decoration: none;
			color: inherit;
		}
		a[x-apple-data-detectors] {
			color: inherit !important;
			text-decoration: none !important;
			font-size: inherit !important;
			font-family: inherit !important;
			font-weight: inherit !important;
			line-height: inherit !important;
		}
		@media only screen and (max-width: 640px) {
			.main {
				width: 100% !important;
				min-width: 100% !important;
			}
			.inner_table {
				width: 90% !important;
			}
			.full-width {
				width: 100% !important;
				height: auto !important;
			}
		}
		@media (prefers-color-scheme: dark) {
			body, .email-bg {
				background-color: #1e1e1e !important;
			}
			.email-title, .email-text {
				color: #e0e0e0 !important;
			}
			.email-footer {
				color: #bbbbbb !important;
				border-top-color: #444444 !important;
			}
		}
		</style>
	</head>
	<body style="margin: 0; padding: 0; width: 100%; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; background-color: #ffffff;">
		<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0 auto;">
			<tbody>
				<tr>
					<td class="email-bg" style="background-color: #ffffff;">

						<!-- PREHEADER -->
						<div style="display: none; max-height: 0; overflow: hidden; mso-hide: all;">' . $preHeader . '</div>

						<!-- LOGO -->
						<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin: 0 auto;">
							<tbody>
								<tr>
									<td class="email-bg" style="background-color: #ffffff;">
										<table role="presentation" class="inner_table" cellpadding="0" cellspacing="0" border="0" align="center" width="600" style="margin: 0 auto; width: 600px;">
											<tbody>
												<tr>
													<td height="15" style="line-height: 1px; font-size: 1px;">&nbsp;</td>
												</tr>
												<tr>
													<td style="text-align: center;">
														<img alt="' . $appName . '" src="' . $logo . '" width="160" style="display: block; margin: 0 auto; border: 0; outline: none;" />
													</td>
												</tr>
												<tr>
													<td height="15" style="line-height: 1px; font-size: 1px;">&nbsp;</td>
												</tr>
											</tbody>
										</table>
									</td>
								</tr>
							</tbody>
						</table>

						<!-- CONTENT -->
						<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" width="100%" style="margin: 0 auto;">
							<tbody>
								<tr>
									<td>
										<table role="presentation" class="main" cellpadding="0" cellspacing="0" border="0" align="center" width="640" style="margin: 0 auto; width: 640px;">
											<tbody>
												<tr>
													<td class="email-bg" style="background-color: #ffffff;">
														<table role="presentation" class="inner_table" cellpadding="0" cellspacing="0" border="0" align="center" width="600" style="margin: 0 auto; width: 600px;">
															<tbody>
																<tr>
																	<td height="25" style="line-height: 1px; font-size: 1px;">&nbsp;</td>
																</tr>
																<tr>
																	<td class="email-title" style="font-size: 25px; font-family: Helvetica, Arial, sans-serif; color: #333333; line-height: 30px; text-align: center;" align="center">
																		<p style="margin: 0;">' . $title . '</p>
																	</td>
																</tr>
																<tr>
																	<td height="10" style="line-height: 1px; font-size: 1px;">&nbsp;</td>
																</tr>
																<tr>
																	<td class="email-text" style="font-size: 16px; font-family: Helvetica, Arial, sans-serif; color: #333333; line-height: 24px;" align="left">
																		' . $text . '
																	</td>
																</tr>
															</tbody>
														</table>
													</td>
												</tr>
											</tbody>
										</table>
									</td>
								</tr>
							</tbody>
						</table>

						<!-- FOOTER -->
						<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" width="100%" style="margin: 20px auto;">
							<tbody>
								<tr>
									<td>
										<table role="presentation" class="main" cellpadding="0" cellspacing="0" border="0" align="center" width="640" style="margin: 0 auto; width: 640px;">
											<tbody>
												<tr>
													<td class="email-bg" style="background-color: #ffffff;">
														<table role="presentation" class="inner_table" cellpadding="0" cellspacing="0" border="0" align="center" width="600" style="margin: 0 auto; width: 600px;">
															<tbody>
																<tr>
																	<td class="email-footer" style="border-top: 1px solid #d7d7d7; padding-top: 10px; font-size: 10px; font-family: Helvetica, Arial, sans-serif; color: #999999; line-height: 16px; text-align: center;" align="center">
																		' . Translator::do('EMAIL_COMMON_FOOTER', $appName) . '
																	</td>
																</tr>
																<tr>
																	<td height="15" style="line-height: 1px; font-size: 1px;">&nbsp;</td>
																</tr>
															</tbody>
														</table>
													</td>
												</tr>
											</tbody>
										</table>
									</td>
								</tr>
							</tbody>
						</table>

					</td>
				</tr>
			</tbody>
		</table>
	</body>
</html>';

	}

	/**
	 * Send the e-mail to the recipients.
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
	abstract public function send(array $recipients, string $subject, string $title, string $text, array $attachments = [], array $ccs = []);

	/**
	 * Create an HTML email with link to reset-password.
	 *
	 * @param	User	Recipient user.
	 * @param	string	Random string to reset password.
	 */
	public function sendPasswordReset(User $user, string $randomString): void {

		$recipient = new \stdClass;
		$recipient->email = $user->email;
		$recipient->name  = $user->fullName;

		// email subject
		$subject = Translator::do('PASSWORD_RESET_REQUEST_SUBJECT', Env::get('APP_NAME'));

		// body title
		$title = Translator::do('PASSWORD_RESET_REQUEST_TITLE');

		$replacements = [
			'{{baseurl}}'		=> BASE_HREF,
			'{{buttonlink}}'	=> BASE_HREF . 'user/newPassword/' . $randomString,
			'{{userfullname}}'	=> $user->fullName,
			'{{productname}}'	=> Env::get('APP_NAME')
		];

		// body content
		$body = str_replace(array_keys($replacements),array_values($replacements),Translator::do('PASSWORD_RESET_REQUEST_BODY'));

		// send the email
		$this->send([$recipient], $subject, $title, $body);

	}

	/**
	 * Set the base required configuration.
	 */
	public function setBaseConfig(array $config): void {

		$stringOptions = [
			'applicationLogo',
			'charSet',
			'fromAddress',
			'fromName'
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

	/**
	 * Set the configuration of the email sender.
	 *
	 * @param	array	Associative array with configuration options (fromAddress, fromName, applicationLogo, charSet, adminEmails).
	 */
	public function setConfig(array $config): void {

		$this->setBaseConfig($config);

	}

	/**
	 * Send a test email.
	 * 
	 * @param	string|null	$testToSend		Custom text to send in the email body.
	 * @param	string|null	$recipientName	Name of the recipient.
	 * @param	string|null	$recipientEmail	Email address of the recipient. If null, the email is sent to all administratos.
	 */
	public function test(?string $textToSend = null, ?string $recipientName = null, $recipientEmail = null): void {

		// custom recipient or admin emails
		if ($recipientEmail) {
			$recipients = [(object)['name'=>$recipientName, 'email'=>$recipientEmail]];
		} else {
			$recipients = [];
			foreach ($this->adminEmails as $adminEmail) {
				$recipients[] = (object)['name'=>'Admin', 'email'=>$adminEmail];
			}
		}

		$subject = 'Test e-mail';

		// e-mail subject and body
		$content = '<p>' . (trim($textToSend) ? $textToSend : $subject) . '</p>';

		$this->send($recipients, $subject, $subject, $content);

	}

}