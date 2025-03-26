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
	protected ?string $applicationLogo = NULL;

	/**
	 * The charset of the email.
	 */
	protected string $charSet = 'utf-8';

	/**
	 * The email address of the sender.
	 */
	protected ?string $fromAddress = NULL;

	/**
	 * The name of the sender.
	 */
	protected ?string $fromName = NULL;

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

		return
'<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "https://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="https://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
	<head>
		<title>' . $title . '</title>
			<link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800" rel="stylesheet" />
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
			<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1" />
			<meta name="robots" content="noindex,nofollow" />
			<meta http-equiv="X-UA-Compatible" content="IE=edge" />
			<meta name="format-detection" content="telephone=no, address=no, email=no, date=no">
			<link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet" />
			<!--[if gte mso 9]><xml>
			 <o:OfficeDocumentSettings>
			  <o:AllowPNG/>
			  <o:PixelsPerInch>96</o:PixelsPerInch>
			 </o:OfficeDocumentSettings>
			</xml><![endif]-->
			<!--[if mso]>
			<style> body,table tr,table td,a, span,table.MsoNormalTable {  font-family:Arial, Helvetica, sans-serif !important;  }</style>
			<![endif]-->
			<style media="all">
			/* CLIENT-SPECIFIC STYLES */
			#outlook a {
				padding: 0;
			}
			/* Force Outlook to provide a "view in browser" message */
			.ReadMsgBody {
				width: 100%;
			}
			.ExternalClass {
				width: 100%;
			}
			/* Force Hotmail to display emails at full width */
			.ExternalClass, .ExternalClass p, .ExternalClass span, .ExternalClass font,
				.ExternalClass td, .ExternalClass div {
				line-height: 100%;
			}
			/* Force Hotmail to display normal line spacing */
			/* iOS BLUE LINKS */
			@
			-ms-viewport {
				width: device-width;
			}
			</style>
			<style media="all">
		html {
			-webkit-text-size-adjust: none;
		}
		body {
			font-family: Arial, Helvetica, sans-serif;
		}
		a[x-apple-data-detectors] {
			color: inherit !important;
			text-decoration: none !important;
			font-size: inherit !important;
			font-family: inherit !important;
			font-weight: inherit !important;
			line-height: inherit !important;
		}
		a {
			outline: none !important;
			text-decoration: none !important;
		}
		@media only screen and (max-width: 640px) {
			.main {
				width: 100% !important;
				min-width: 100% !important;
			}
			.inner_table {
				width: 90% !important;
			}
			.res {
				width: 100% !important;
				display: block !important;
			}
			.left_align {
				text-align: left !important;
			}
			.center_align {
				text-align: center !important;
				margin: 0 auto;
			}
			.full-width {
				width: 100% !important;
				height: auto !important;
			}
			.hidden {
				display: none !important;
			}
			.padding_top {
				padding-top: 18px !important;
			}
			.padding-btm {
				padding-bottom: 18px !important;
			}
			.noSpace {
				height: 0px !important;
			}
			.padLeft {
				padding-left: 15px !important;
			}
			.floatNone {
				float: left !important;
			}
		}
		@media only screen and (max-width: 640px) {
			*[class].main {
				width: 100% !important;
				min-width: 100% !important;
			}
			*[class].inner_table {
				width: 90% !important;
			}
			*[class].res {
				width: 100% !important;
				display: block !important;
			}
			*[class].left_align {
				text-align: left !important;
			}
			*[class].center_align {
				text-align: center !important;
				margin: 0 auto;
			}
			*[class].full-width {
				width: 100% !important;
				height: auto !important;
			}
			*[class].hidden {
				display: none !important;
			}
			*[class].padding_top {
				padding-top: 18px !important;
			}
			*[class].padding-btm {
				padding-bottom: 18px !important;
			}
			*[class].noSpace {
				height: 0px !important;
			}
			*[class].padLeft {
				padding-left: 15px !important;
			}
			*[class].floatNone {
				float: left !important;
			}
		}
		</style>
	</head>
	<body style="margin-bottom: 0; -webkit-text-size-adjust: 100%; padding-bottom: 0; margin-top: 0; margin-right: 0; -ms-text-size-adjust: 100%; margin-left: 0; padding-top: 0; padding-right: 0; padding-left: 0; width: 100%;">
		<table border="0" cellpadding="0" cellspacing="0" width="100%" style="-webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; mso-table-lspace: 0pt; mso-table-rspace: 0pt; border-spacing: 0; border-collapse: collapse; margin: 0 auto;">
			<tbody>
				<tr>
					<td class="mktoContainer" id="template-wrapper" style="background-color: #ffffff;" bgcolor="#ffffff">

						<!-- PREHEADER SECTION START -->

						<table class="mktoModule" id="Hidden-Pre-Header" cellpadding="0" cellspacing="0" align="center" border="0" width="100%" style="border-collapse: collapse; margin: 0 auto; min-width: 100%;">
							<tbody>
								<tr>
									<td style="display: none !important; visibility: hidden; mso-hide: all; font-size: 1px; color: #ffffff; line-height: 1px; max-height: 0px; max-width: 0px; opacity: 0; overflow: hidden;">
										<div class="mktoText" id="preheader_section">' . $preHeader . '</div>
									</td>
								</tr>
							</tbody>
						</table>

						<!-- PREHEADER SECTION END -->
						<!-- LOGO START -->

						<table class="mktoModule" id="header_sec" cellpadding="0" cellspacing="0" align="center" border="0" width="100%" style="margin: 0 auto; min-width: 100%;">
							<tbody>
								<tr>
									<td>
										<div class="mktoText" id="header_section">
											<table width="100%" cellpadding="0" cellspacing="0" border="0">
												<tbody>
													<tr>
														<td valign="top" style="vertical-align: top; background-color: #ffffff;">
															<table class="inner_table" cellpadding="0" cellspacing="0" align="center" border="0" width="600" style="border-collapse: collapse; margin: 0 auto; width: 600px;">
																<tbody>
																	<tr>
																		<td height="15" style="line-height: 1px; font-size: 1px;">&nbsp;</td>
																	</tr>
																	<tr>
																		<td class="center_align" style="text-align: center; vertical-align: top;">
																			<img alt="Logo ' . Env::get('APP_NAME') . '" src="' . $this->applicationLogo . '" style="-ms-interpolation-mode: bicubic; outline-style: none; border-style: none;" width="160" border="0" />
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
										</div>
									</td>
								</tr>
							</tbody>
						</table>

						<!-- LOGO END -->

						<!-- CONTENT START -->

						<table class="mktoModule" cellpadding="0" cellspacing="0" align="center" border="0" width="100%" style="border-collapse: collapse; margin: 0 auto; min-width: 100%;">
							<tbody>
								<tr>
									<td>
										<table class="main" cellpadding="0" cellspacing="0" align="center" border="0" width="640" style="border-collapse: collapse; margin: 0 auto; width: 640px;">
											<tbody>
												<tr>
													<td bgcolor="#ffffff" style="background-color: #ffffff;">
														<table class="inner_table" cellpadding="0" cellspacing="0" align="center" border="0" width="600" style="border-collapse: collapse; margin: 0 auto; width: 600px;">
															<tbody>
																<tr>
																	<td height="25" style="line-height: 1px; font-size: 1px;">&nbsp;</td>
																</tr>

																<!-- TITLE START -->

																<tr>
																	<td style="font-size: 25px; font-family: Helvetica, Arial, sans-serif; color: #333333; line-height: 30px; text-align: center" align="center">
																		<div class="mktoText"><p>' . $title . '</p></div>
																	</td>
																</tr>

																<!-- TITLE END -->

																<tr>
																	<td height="10" style="line-height: 1px; font-size: 1px;">&nbsp;</td>
																</tr>

																<!-- PARAGRAPH START -->

																<tr>
																	<td style="font-size: 16px; font-family: Helvetica, Arial, sans-serif; color: #333333; line-height: 24px;" align="left">
																		<div class="mktoText">' . $text . '</div>
																	</td>
																</tr>

																<!-- PARAGRAPH END -->

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

						<!-- CONTENT END -->

						<!-- FOOTER START -->

						<table class="mktoModule" cellpadding="0" cellspacing="0" align="center" border="0" width="100%" style="border-collapse: collapse; margin: 20px auto; min-width: 100%;">
							<tbody>
								<tr>
									<td>
										<table class="main" cellpadding="0" cellspacing="0" align="center" border="0" width="640" style="border-collapse: collapse; margin: 0 auto; width: 640px;">
											<tbody>
												<tr>
													<td bgcolor="#ffffff" style="background-color: #ffffff;">
														<table class="inner_table" cellpadding="0" cellspacing="0" align="center" border="0" width="600" style="border-collapse: collapse; margin: 0 auto; width: 600px;">
															<tbody>
																<tr>
																	<td style="font-family: Helvetica, Arial, Sans-serif; font-size: 10px; line-height: 14px; color: #333333;" align="center">
																		<div class="mktoText" id="Footer_Top_bar">
																			<div class="mktEditable" id="Social-Media">
																				<table width="100%" border="0" cellspacing="0" cellpadding="10" align="center" style="max-width: 600px;" class="responsive-table">
																					<tbody>
																						<tr>
																							<td align="center" style="font-family: Helvetica, Arial, Sans-serif; font-size: 10px; line-height: 14px; color: #333333; border-top-color: #d7d7d7=; border-top-style: solid; border-top-width: 1px; padding-top: 10px;"></td>
																						</tr>
																					</tbody>
																				</table>
																			</div>
																		</div>
																	</td>
																</tr>
																<tr>
																	<td style="font-size: 12px; line-height: 18px; font-family: Helvetica, Arial, sans-serif; color: #666666;" align="center">
																		<div class="mktoText" id="Footer_Top_bar3">
																			<table width="100%" cellpadding="0" cellspacing="0" border="0">
																				<tbody>
																					<tr>
																						<td>
																							<table width="100%" cellpadding="0" cellspacing="0" border="0">
																								<tbody>
																									<tr>
																										<td align="center" width="100%" style="font-size: 10px; font-family: Helvetica, Arial, sans-serif; color: #999999; line-height: 16px;">
																											' . Translator::do('EMAIL_COMMON_FOOTER', Env::get('APP_NAME')) . '
																										</td>
																									</tr>
																								</tbody>
																							</table>
																						</td>
																					</tr>
																				</tbody>
																			</table>

																		</div>
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

						<!-- FOOTER END -->

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
	 */
	public function test(?string $textToSend=NULL, ?string $recipientName=NULL, $recipientEmail=NULL): void {

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