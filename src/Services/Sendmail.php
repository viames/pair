<?php

namespace Pair\Services;

use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\Mailer;

/**
 * Class specialized in sending emails in HTML format.
 */
class Sendmail extends Mailer {

	/**
	 * Send a simple email using sendmail without attachments.
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

		$realRecipients = $this->convertRecipients($recipients);
		$realCcs = $this->convertCarbonCopy($ccs);
		
		$message = $this->getBody($subject, $title, $text);

		// for lines larger than 70 characters
		$message = wordwrap($message, 70, "\r\n");
		
		// recipients
		$to = implode(',', array_map(function($recipient) {
			return $recipient->email;
		}, $realRecipients));
		
		$headers[] = 'From: ' . $this->fromName . ' <' . $this->fromAddress . '>';

		$headers[] = 'To: ' . implode(',', array_map(function($recipient) {
			return $recipient->name . ' <' . $recipient->email . '>';
		}, $realRecipients));
		
		$headers[] = 'Reply-To: ' . $this->fromName . ' <' . $this->fromAddress . '>';
		
		$headers[] = 'MIME-Version: 1.0';
		
		$headers[] = 'Content-type: text/html; charset=' . $this->charSet;
		
		$headers[] = 'CC: ' . implode(',', array_map(function($cc) {
			return $cc->name . ' <' . $cc->email . '>';
		}, $realCcs));

		// send the e-mail using sendmail
		if (!mail($to, $subject, $message, implode("\r\n", $headers))) {

			throw new PairException('Error sending e-mail', ErrorCodes::EMAIL_FAILURE);

		}

	}

}