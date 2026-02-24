<?php
namespace Pair\Services;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;

/**
 * Aircall API wrapper with dedicated helpers for the main endpoints.
 * Authentication uses HTTP basic auth with API ID and API token.
 */
class AircallClient {

	/**
	 * Aircall API host.
	 */
	private string $apiHost;

	/**
	 * Aircall API ID.
	 */
	private string $apiId;

	/**
	 * Aircall API token.
	 */
	private string $apiToken;

	/**
	 * HTTP connect timeout in seconds.
	 */
	private int $connectTimeout;

	/**
	 * Maximum retries on transient failures.
	 */
	private int $maxRetries;

	/**
	 * Base backoff in milliseconds when retry-after is not returned.
	 */
	private int $retryDelayMs;

	/**
	 * Request timeout in seconds.
	 */
	private int $timeout;

	/**
	 * Build a new Aircall client from parameters or environment values.
	 *
	 * Required Env keys: AIRCALL_API_ID, AIRCALL_API_TOKEN.
	 * Optional Env keys: AIRCALL_API_HOST, AIRCALL_BASE_URL, AIRCALL_TIMEOUT,
	 * AIRCALL_CONNECT_TIMEOUT, AIRCALL_MAX_RETRIES, AIRCALL_RETRY_DELAY_MS.
	 */
	public function __construct(?string $apiId = null, ?string $apiToken = null, ?string $apiHost = null, ?int $timeout = null, ?int $maxRetries = null, ?int $retryDelayMs = null, ?int $connectTimeout = null) {

		$this->apiId = trim((string)($apiId ?? Env::get('AIRCALL_API_ID')));
		$this->apiToken = trim((string)($apiToken ?? Env::get('AIRCALL_API_TOKEN')));
		$this->apiHost = $this->sanitizeApiHost((string)($apiHost ?? Env::get('AIRCALL_API_HOST') ?? Env::get('AIRCALL_BASE_URL') ?? 'https://api.aircall.io'));
		$this->timeout = (int)($timeout ?? Env::get('AIRCALL_TIMEOUT') ?? 20);
		$this->connectTimeout = (int)($connectTimeout ?? Env::get('AIRCALL_CONNECT_TIMEOUT') ?? 10);
		$this->maxRetries = (int)($maxRetries ?? Env::get('AIRCALL_MAX_RETRIES') ?? 2);
		$this->retryDelayMs = (int)($retryDelayMs ?? Env::get('AIRCALL_RETRY_DELAY_MS') ?? 500);

		if (($this->apiId === '') or ($this->apiToken === '')) {
			throw new PairException('Missing Aircall credentials. Set AIRCALL_API_ID and AIRCALL_API_TOKEN.', ErrorCodes::AIRCALL_ERROR);
		}

		if ($this->timeout < 1) {
			$this->timeout = 20;
		}

		if ($this->connectTimeout < 1) {
			$this->connectTimeout = 10;
		}

		if ($this->maxRetries < 0) {
			$this->maxRetries = 0;
		}

		if ($this->retryDelayMs < 1) {
			$this->retryDelayMs = 500;
		}

	}

	/**
	 * Add an email to a contact.
	 */
	public function addContactEmail(int $contactId, array $payload): array {

		$this->assertPositiveId($contactId, 'contact');
		return $this->request('POST', '/v1/contacts/' . $contactId . '/emails', [], $payload);

	}

	/**
	 * Add a phone number to a contact.
	 */
	public function addContactPhoneNumber(int $contactId, array $payload): array {

		$this->assertPositiveId($contactId, 'contact');
		return $this->request('POST', '/v1/contacts/' . $contactId . '/phone_numbers', [], $payload);

	}

	/**
	 * Add a tag to a call.
	 */
	public function addCallTag(int $callId, int $tagId): array {

		$this->assertPositiveId($callId, 'call');
		$this->assertPositiveId($tagId, 'tag');
		return $this->request('POST', '/v1/calls/' . $callId . '/tags/' . $tagId);

	}

	/**
	 * Add a phone number to a dialer campaign.
	 */
	public function addPhoneNumberToDialerCampaign(int $campaignId, string $phoneNumber): array {

		$this->assertPositiveId($campaignId, 'dialer campaign');
		return $this->request('POST', '/v1/dialer_campaigns/' . $campaignId . '/phone_numbers/' . $this->encodeSegment($phoneNumber));

	}

	/**
	 * Add a user to a team.
	 */
	public function addUserToTeam(int $teamId, int $userId): array {

		$this->assertPositiveId($teamId, 'team');
		$this->assertPositiveId($userId, 'user');
		return $this->request('POST', '/v1/teams/' . $teamId . '/users/' . $userId);

	}

	/**
	 * Archive a call.
	 */
	public function archiveCall(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('POST', '/v1/calls/' . $callId . '/archive');

	}

	/**
	 * Check user availability.
	 */
	public function checkUserAvailability(int $userId): array {

		$this->assertPositiveId($userId, 'user');
		return $this->request('GET', '/v1/users/' . $userId . '/available');

	}

	/**
	 * Create a call comment.
	 */
	public function createCallComment(int $callId, array $payload): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('POST', '/v1/calls/' . $callId . '/comments', [], $payload);

	}

	/**
	 * Create a contact.
	 */
	public function createContact(array $payload): array {

		return $this->request('POST', '/v1/contacts', [], $payload);

	}

	/**
	 * Create a dialer campaign.
	 */
	public function createDialerCampaign(array $payload): array {

		return $this->request('POST', '/v1/dialer_campaigns', [], $payload);

	}

	/**
	 * Create or update number configuration for messaging API.
	 */
	public function createMessageNumberConfiguration(int $numberId, array $payload): array {

		$this->assertPositiveId($numberId, 'number');
		return $this->request('POST', '/v1/numbers/' . $numberId . '/messages/configuration', [], $payload);

	}

	/**
	 * Create a tag.
	 */
	public function createTag(array $payload): array {

		return $this->request('POST', '/v1/tags', [], $payload);

	}

	/**
	 * Create a team.
	 */
	public function createTeam(array $payload): array {

		return $this->request('POST', '/v1/teams', [], $payload);

	}

	/**
	 * Create a user (v1 API).
	 */
	public function createUser(array $payload): array {

		return $this->request('POST', '/v1/users', [], $payload);

	}

	/**
	 * Create a user (v2 API).
	 */
	public function createUserV2(array $payload): array {

		return $this->request('POST', '/v2/users', [], $payload);

	}

	/**
	 * Create a webhook.
	 */
	public function createWebhook(array $payload): array {

		return $this->request('POST', '/v1/webhooks', [], $payload);

	}

	/**
	 * Delete call recording.
	 */
	public function deleteCallRecording(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('DELETE', '/v1/calls/' . $callId . '/recording');

	}

	/**
	 * Delete call voicemail.
	 */
	public function deleteCallVoicemail(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('DELETE', '/v1/calls/' . $callId . '/voicemail');

	}

	/**
	 * Delete a contact.
	 */
	public function deleteContact(int $contactId): array {

		$this->assertPositiveId($contactId, 'contact');
		return $this->request('DELETE', '/v1/contacts/' . $contactId);

	}

	/**
	 * Delete a contact email.
	 */
	public function deleteContactEmail(int $contactId, int $emailId): array {

		$this->assertPositiveId($contactId, 'contact');
		$this->assertPositiveId($emailId, 'email');
		return $this->request('DELETE', '/v1/contacts/' . $contactId . '/emails/' . $emailId);

	}

	/**
	 * Delete a contact phone number.
	 */
	public function deleteContactPhoneNumber(int $contactId, int $phoneNumberId): array {

		$this->assertPositiveId($contactId, 'contact');
		$this->assertPositiveId($phoneNumberId, 'phone number');
		return $this->request('DELETE', '/v1/contacts/' . $contactId . '/phone_numbers/' . $phoneNumberId);

	}

	/**
	 * Delete a dialer campaign.
	 */
	public function deleteDialerCampaign(int $campaignId): array {

		$this->assertPositiveId($campaignId, 'dialer campaign');
		return $this->request('DELETE', '/v1/dialer_campaigns/' . $campaignId);

	}

	/**
	 * Delete number configuration for messaging API.
	 */
	public function deleteMessageNumberConfiguration(int $numberId): array {

		$this->assertPositiveId($numberId, 'number');
		return $this->request('DELETE', '/v1/numbers/' . $numberId . '/messages/configuration');

	}

	/**
	 * Delete a tag.
	 */
	public function deleteTag(int $tagId): array {

		$this->assertPositiveId($tagId, 'tag');
		return $this->request('DELETE', '/v1/tags/' . $tagId);

	}

	/**
	 * Delete a team.
	 */
	public function deleteTeam(int $teamId): array {

		$this->assertPositiveId($teamId, 'team');
		return $this->request('DELETE', '/v1/teams/' . $teamId);

	}

	/**
	 * Delete a user.
	 */
	public function deleteUser(int $userId): array {

		$this->assertPositiveId($userId, 'user');
		return $this->request('DELETE', '/v1/users/' . $userId);

	}

	/**
	 * Delete a webhook.
	 */
	public function deleteWebhook(int $webhookId): array {

		$this->assertPositiveId($webhookId, 'webhook');
		return $this->request('DELETE', '/v1/webhooks/' . $webhookId);

	}

	/**
	 * Dial a phone number in user's phone app.
	 */
	public function dialPhoneNumberInPhone(int $userId, array $payload): array {

		$this->assertPositiveId($userId, 'user');
		return $this->request('POST', '/v1/users/' . $userId . '/dial_phone_number_in_phone', [], $payload);

	}

	/**
	 * Disable an integration.
	 */
	public function disableIntegration(string $integrationName): array {

		return $this->request('POST', '/v1/integrations/' . $this->encodeSegment($integrationName) . '/disable');

	}

	/**
	 * Enable an integration.
	 */
	public function enableIntegration(string $integrationName): array {

		return $this->request('POST', '/v1/integrations/' . $this->encodeSegment($integrationName) . '/enable');

	}

	/**
	 * Fetch a single message.
	 */
	public function fetchMessage(string $messageId): array {

		if (trim($messageId) === '') {
			throw new PairException('Aircall message ID is required.', ErrorCodes::AIRCALL_ERROR);
		}

		return $this->request('GET', '/v1/messages/' . $this->encodeSegment($messageId));

	}

	/**
	 * Fetch number configuration for messaging API.
	 */
	public function fetchMessageNumberConfiguration(int $numberId): array {

		$this->assertPositiveId($numberId, 'number');
		return $this->request('GET', '/v1/numbers/' . $numberId . '/messages/configuration');

	}

	/**
	 * Get all pages for a paginated endpoint and merge items.
	 */
	public function getAll(string $path, array $query = [], ?string $collectionKey = null, int $maxPages = 100): array {

		return $this->paginate($path, $query, $collectionKey, $maxPages);

	}

	/**
	 * Get call details.
	 */
	public function getCall(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('GET', '/v1/calls/' . $callId);

	}

	/**
	 * Get call action items.
	 */
	public function getCallActionItems(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('GET', '/v1/calls/' . $callId . '/action_items');

	}

	/**
	 * Get custom summary for a call.
	 */
	public function getCallCustomSummary(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('GET', '/v1/calls/' . $callId . '/custom_summary');

	}

	/**
	 * Get call evaluations.
	 */
	public function getCallEvaluations(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('GET', '/v1/calls/' . $callId . '/evaluations');

	}

	/**
	 * Get call insight cards.
	 */
	public function getCallInsightCards(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('GET', '/v1/calls/' . $callId . '/insight_cards');

	}

	/**
	 * Get call playbook result.
	 */
	public function getCallPlaybookResult(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('GET', '/v1/calls/' . $callId . '/playbook_result');

	}

	/**
	 * Get call realtime transcription.
	 */
	public function getCallRealtimeTranscription(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('GET', '/v1/calls/' . $callId . '/realtime_transcription');

	}

	/**
	 * Get call sentiments.
	 */
	public function getCallSentiments(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('GET', '/v1/calls/' . $callId . '/sentiments');

	}

	/**
	 * Get call summary.
	 */
	public function getCallSummary(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('GET', '/v1/calls/' . $callId . '/summary');

	}

	/**
	 * Get call topics.
	 */
	public function getCallTopics(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('GET', '/v1/calls/' . $callId . '/topics');

	}

	/**
	 * Get call transcription.
	 */
	public function getCallTranscription(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('GET', '/v1/calls/' . $callId . '/transcription');

	}

	/**
	 * Get calls list.
	 */
	public function getCalls(array $query = []): array {

		return $this->request('GET', '/v1/calls', $query);

	}

	/**
	 * Get company details.
	 */
	public function getCompany(): array {

		return $this->request('GET', '/v1/companies/me');

	}

	/**
	 * Get contact details.
	 */
	public function getContact(int $contactId): array {

		$this->assertPositiveId($contactId, 'contact');
		return $this->request('GET', '/v1/contacts/' . $contactId);

	}

	/**
	 * Get contacts list.
	 */
	public function getContacts(array $query = []): array {

		return $this->request('GET', '/v1/contacts', $query);

	}

	/**
	 * Get dialer campaign details.
	 */
	public function getDialerCampaign(int $campaignId): array {

		$this->assertPositiveId($campaignId, 'dialer campaign');
		return $this->request('GET', '/v1/dialer_campaigns/' . $campaignId);

	}

	/**
	 * Get dialer campaign phone numbers.
	 */
	public function getDialerCampaignPhoneNumbers(int $campaignId, array $query = []): array {

		$this->assertPositiveId($campaignId, 'dialer campaign');
		return $this->request('GET', '/v1/dialer_campaigns/' . $campaignId . '/phone_numbers', $query);

	}

	/**
	 * Get one integration details.
	 */
	public function getIntegration(string $integrationName): array {

		return $this->request('GET', '/v1/integrations/' . $this->encodeSegment($integrationName));

	}

	/**
	 * Get integrations list.
	 */
	public function getIntegrations(): array {

		return $this->request('GET', '/v1/integrations');

	}

	/**
	 * Get all calls and merge pages.
	 */
	public function getAllCalls(array $query = [], int $maxPages = 100): array {

		return $this->paginate('/v1/calls', $query, 'calls', $maxPages);

	}

	/**
	 * Get all contacts and merge pages.
	 */
	public function getAllContacts(array $query = [], int $maxPages = 100): array {

		return $this->paginate('/v1/contacts', $query, 'contacts', $maxPages);

	}

	/**
	 * Get all numbers and merge pages.
	 */
	public function getAllNumbers(array $query = [], int $maxPages = 100): array {

		return $this->paginate('/v1/numbers', $query, 'numbers', $maxPages);

	}

	/**
	 * Get all tags and merge pages.
	 */
	public function getAllTags(array $query = [], int $maxPages = 100): array {

		return $this->paginate('/v1/tags', $query, 'tags', $maxPages);

	}

	/**
	 * Get all teams and merge pages.
	 */
	public function getAllTeams(array $query = [], int $maxPages = 100): array {

		return $this->paginate('/v1/teams', $query, 'teams', $maxPages);

	}

	/**
	 * Get all users and merge pages.
	 */
	public function getAllUsers(array $query = [], int $maxPages = 100): array {

		return $this->paginate('/v1/users', $query, 'users', $maxPages);

	}

	/**
	 * Get all users (v2) and merge pages.
	 */
	public function getAllUsersV2(array $query = [], int $maxPages = 100): array {

		return $this->paginate('/v2/users', $query, 'users', $maxPages);

	}

	/**
	 * Get all webhooks and merge pages.
	 */
	public function getAllWebhooks(array $query = [], int $maxPages = 100): array {

		return $this->paginate('/v1/webhooks', $query, 'webhooks', $maxPages);

	}

	/**
	 * Get number details.
	 */
	public function getNumber(int $numberId): array {

		$this->assertPositiveId($numberId, 'number');
		return $this->request('GET', '/v1/numbers/' . $numberId);

	}

	/**
	 * Get number registration status.
	 */
	public function getNumberRegistrationStatus(int $numberId): array {

		$this->assertPositiveId($numberId, 'number');
		return $this->request('GET', '/v1/numbers/' . $numberId . '/registration_status');

	}

	/**
	 * Get numbers list.
	 */
	public function getNumbers(array $query = []): array {

		return $this->request('GET', '/v1/numbers', $query);

	}

	/**
	 * Get tag details.
	 */
	public function getTag(int $tagId): array {

		$this->assertPositiveId($tagId, 'tag');
		return $this->request('GET', '/v1/tags/' . $tagId);

	}

	/**
	 * Get tags list.
	 */
	public function getTags(array $query = []): array {

		return $this->request('GET', '/v1/tags', $query);

	}

	/**
	 * Get team details.
	 */
	public function getTeam(int $teamId): array {

		$this->assertPositiveId($teamId, 'team');
		return $this->request('GET', '/v1/teams/' . $teamId);

	}

	/**
	 * Get teams list.
	 */
	public function getTeams(array $query = []): array {

		return $this->request('GET', '/v1/teams', $query);

	}

	/**
	 * Get user details (v1 API).
	 */
	public function getUser(int $userId): array {

		$this->assertPositiveId($userId, 'user');
		return $this->request('GET', '/v1/users/' . $userId);

	}

	/**
	 * Get user availabilities.
	 */
	public function getUserAvailabilities(int $userId): array {

		$this->assertPositiveId($userId, 'user');
		return $this->request('GET', '/v1/users/' . $userId . '/availabilities');

	}

	/**
	 * Get numbers attached to a user (v2 API).
	 */
	public function getUserNumbersV2(int $userId): array {

		$this->assertPositiveId($userId, 'user');
		return $this->request('GET', '/v2/users/' . $userId . '/numbers');

	}

	/**
	 * Get users list (v1 API).
	 */
	public function getUsers(array $query = []): array {

		return $this->request('GET', '/v1/users', $query);

	}

	/**
	 * Get user details (v2 API).
	 */
	public function getUserV2(int $userId): array {

		$this->assertPositiveId($userId, 'user');
		return $this->request('GET', '/v2/users/' . $userId);

	}

	/**
	 * Get users list (v2 API).
	 */
	public function getUsersV2(array $query = []): array {

		return $this->request('GET', '/v2/users', $query);

	}

	/**
	 * Get webhook details.
	 */
	public function getWebhook(int $webhookId): array {

		$this->assertPositiveId($webhookId, 'webhook');
		return $this->request('GET', '/v1/webhooks/' . $webhookId);

	}

	/**
	 * Get webhooks list.
	 */
	public function getWebhooks(array $query = []): array {

		return $this->request('GET', '/v1/webhooks', $query);

	}

	/**
	 * Pause call recording.
	 */
	public function pauseCallRecording(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('POST', '/v1/calls/' . $callId . '/pause_recording');

	}

	/**
	 * Send a raw request to Aircall endpoint.
	 *
	 * If $path does not include an API version, v1 is used by default.
	 */
	public function raw(string $method, string $path, array $query = [], ?array $body = null): array {

		return $this->request($method, $path, $query, $body);

	}

	/**
	 * Remove a tag from a call.
	 */
	public function removeCallTag(int $callId, int $tagId): array {

		$this->assertPositiveId($callId, 'call');
		$this->assertPositiveId($tagId, 'tag');
		return $this->request('DELETE', '/v1/calls/' . $callId . '/tags/' . $tagId);

	}

	/**
	 * Remove a phone number from a dialer campaign.
	 */
	public function removePhoneNumberFromDialerCampaign(int $campaignId, string $phoneNumber): array {

		$this->assertPositiveId($campaignId, 'dialer campaign');
		return $this->request('DELETE', '/v1/dialer_campaigns/' . $campaignId . '/phone_numbers/' . $this->encodeSegment($phoneNumber));

	}

	/**
	 * Remove a user from a team.
	 */
	public function removeUserFromTeam(int $teamId, int $userId): array {

		$this->assertPositiveId($teamId, 'team');
		$this->assertPositiveId($userId, 'user');
		return $this->request('DELETE', '/v1/teams/' . $teamId . '/users/' . $userId);

	}

	/**
	 * Resume call recording.
	 */
	public function resumeCallRecording(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('POST', '/v1/calls/' . $callId . '/resume_recording');

	}

	/**
	 * Search calls.
	 */
	public function searchCalls(array $query = []): array {

		return $this->request('GET', '/v1/calls/search', $query);

	}

	/**
	 * Search contacts.
	 */
	public function searchContacts(array $query = []): array {

		return $this->request('GET', '/v1/contacts/search', $query);

	}

	/**
	 * Send message from a number.
	 */
	public function sendMessage(int $numberId, array $payload): array {

		$this->assertPositiveId($numberId, 'number');
		return $this->request('POST', '/v1/numbers/' . $numberId . '/messages/send', [], $payload);

	}

	/**
	 * Send message in native agent conversation.
	 */
	public function sendMessageInAgentConversation(int $numberId, array $payload): array {

		$this->assertPositiveId($numberId, 'number');
		return $this->request('POST', '/v1/numbers/' . $numberId . '/messages/native/send', [], $payload);

	}

	/**
	 * Start an outbound call from a user.
	 */
	public function startOutboundCallFromUser(int $userId, array $payload): array {

		$this->assertPositiveId($userId, 'user');
		return $this->request('POST', '/v1/users/' . $userId . '/calls', [], $payload);

	}

	/**
	 * Transfer a call.
	 */
	public function transferCall(int $callId, array $payload): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('POST', '/v1/calls/' . $callId . '/transfers', [], $payload);

	}

	/**
	 * Unarchive a call.
	 */
	public function unarchiveCall(int $callId): array {

		$this->assertPositiveId($callId, 'call');
		return $this->request('POST', '/v1/calls/' . $callId . '/unarchive');

	}

	/**
	 * Update a contact.
	 */
	public function updateContact(int $contactId, array $payload): array {

		$this->assertPositiveId($contactId, 'contact');
		return $this->request('PUT', '/v1/contacts/' . $contactId, [], $payload);

	}

	/**
	 * Update a contact email.
	 */
	public function updateContactEmail(int $contactId, int $emailId, array $payload): array {

		$this->assertPositiveId($contactId, 'contact');
		$this->assertPositiveId($emailId, 'email');
		return $this->request('PUT', '/v1/contacts/' . $contactId . '/emails/' . $emailId, [], $payload);

	}

	/**
	 * Update a contact phone number.
	 */
	public function updateContactPhoneNumber(int $contactId, int $phoneNumberId, array $payload): array {

		$this->assertPositiveId($contactId, 'contact');
		$this->assertPositiveId($phoneNumberId, 'phone number');
		return $this->request('PUT', '/v1/contacts/' . $contactId . '/phone_numbers/' . $phoneNumberId, [], $payload);

	}

	/**
	 * Update a number.
	 */
	public function updateNumber(int $numberId, array $payload): array {

		$this->assertPositiveId($numberId, 'number');
		return $this->request('PUT', '/v1/numbers/' . $numberId, [], $payload);

	}

	/**
	 * Update number messages configuration.
	 */
	public function updateNumberMessages(int $numberId, array $payload): array {

		$this->assertPositiveId($numberId, 'number');
		return $this->request('PUT', '/v1/numbers/' . $numberId . '/messages', [], $payload);

	}

	/**
	 * Update number music configuration.
	 */
	public function updateNumberMusic(int $numberId, array $payload): array {

		$this->assertPositiveId($numberId, 'number');
		return $this->request('PUT', '/v1/numbers/' . $numberId . '/music', [], $payload);

	}

	/**
	 * Update a tag.
	 */
	public function updateTag(int $tagId, array $payload): array {

		$this->assertPositiveId($tagId, 'tag');
		return $this->request('PUT', '/v1/tags/' . $tagId, [], $payload);

	}

	/**
	 * Update a user (v1 API).
	 */
	public function updateUser(int $userId, array $payload): array {

		$this->assertPositiveId($userId, 'user');
		return $this->request('PUT', '/v1/users/' . $userId, [], $payload);

	}

	/**
	 * Update a user (v2 API).
	 */
	public function updateUserV2(int $userId, array $payload): array {

		$this->assertPositiveId($userId, 'user');
		return $this->request('PATCH', '/v2/users/' . $userId, [], $payload);

	}

	/**
	 * Update a webhook.
	 */
	public function updateWebhook(int $webhookId, array $payload): array {

		$this->assertPositiveId($webhookId, 'webhook');
		return $this->request('PUT', '/v1/webhooks/' . $webhookId, [], $payload);

	}

	/**
	 * Build full endpoint URL with query string.
	 */
	private function buildUrl(string $path, array $query = []): string {

		$path = trim($path);

		if ($path === '') {
			throw new PairException('Aircall endpoint path cannot be empty.', ErrorCodes::AIRCALL_ERROR);
		}

		if (!preg_match('/^https?:\/\//i', $path)) {
			$path = $this->normalizePath($path);
			$path = $this->apiHost . $path;
		}

		if (!$query) {
			return $path;
		}

		$separator = str_contains($path, '?') ? '&' : '?';
		return $path . $separator . http_build_query($query, '', '&', PHP_QUERY_RFC3986);

	}

	/**
	 * Normalize endpoint path and default to v1 when no version is provided.
	 */
	private function normalizePath(string $path): string {

		$path = '/' . ltrim($path, '/');

		if (preg_match('#^/v[0-9]+/#', $path)) {
			return $path;
		}

		return '/v1' . $path;

	}

	/**
	 * Encode a path segment.
	 */
	private function encodeSegment(string $segment): string {

		$segment = trim($segment);

		if ($segment === '') {
			throw new PairException('Aircall path segment cannot be empty.', ErrorCodes::AIRCALL_ERROR);
		}

		return rawurlencode($segment);

	}

	/**
	 * Validate positive numeric IDs.
	 */
	private function assertPositiveId(int $id, string $label): void {

		if ($id < 1) {
			throw new PairException('Aircall ' . $label . ' ID is not valid (' . $id . ').', ErrorCodes::AIRCALL_ERROR);
		}

	}

	/**
	 * Build a readable error from payload and status code.
	 */
	private function extractErrorMessage(array $payload, string $rawBody, int $httpCode): string {

		$keys = ['error', 'message', 'errors', 'detail', 'description'];

		foreach ($keys as $key) {
			if (isset($payload[$key]) and is_string($payload[$key]) and trim($payload[$key]) !== '') {
				return 'Aircall API error: ' . $payload[$key] . ' (HTTP ' . $httpCode . ').';
			}
		}

		if (trim($rawBody) !== '') {
			$short = mb_substr(trim($rawBody), 0, 250);
			return 'Aircall API returned HTTP ' . $httpCode . ': ' . $short;
		}

		return 'Aircall API returned HTTP ' . $httpCode . '.';

	}

	/**
	 * Extract retry-after header in milliseconds.
	 */
	private function extractRetryDelayMs(string $headers): int {

		if ($headers === '') {
			return 0;
		}

		$parts = preg_split("/\r\n\r\n|\n\n/", trim($headers));
		$last = is_array($parts) ? trim((string)end($parts)) : trim($headers);

		if ($last === '') {
			return 0;
		}

		$lines = preg_split('/\r\n|\n/', $last);
		if (!is_array($lines)) {
			return 0;
		}

		foreach ($lines as $line) {
			if (!is_string($line)) {
				continue;
			}

			if (stripos($line, 'Retry-After:') !== 0) {
				continue;
			}

			$value = trim(substr($line, strlen('Retry-After:')));
			if ($value === '') {
				return 0;
			}

			if (is_numeric($value)) {
				$seconds = (int)$value;
				return $seconds > 0 ? ($seconds * 1000) : 0;
			}

			$timestamp = strtotime($value);
			if (false === $timestamp) {
				return 0;
			}

			$delta = $timestamp - time();
			return $delta > 0 ? ($delta * 1000) : 0;
		}

		return 0;

	}

	/**
	 * Extract list items from a paged response.
	 */
	private function extractItems(array $response, ?string $collectionKey = null): array {

		if ($collectionKey and isset($response[$collectionKey]) and is_array($response[$collectionKey])) {
			return $response[$collectionKey];
		}

		if (array_is_list($response)) {
			return $response;
		}

		foreach ($response as $value) {
			if (is_array($value) and array_is_list($value)) {
				return $value;
			}
		}

		return [];

	}

	/**
	 * Extract the next-page link from response metadata.
	 */
	private function extractNextPageLink(array $response): ?string {

		if (isset($response['meta']['next_page_link']) and is_string($response['meta']['next_page_link']) and trim($response['meta']['next_page_link']) !== '') {
			return $response['meta']['next_page_link'];
		}

		if (isset($response['meta']['next']) and is_string($response['meta']['next']) and trim($response['meta']['next']) !== '') {
			return $response['meta']['next'];
		}

		if (isset($response['next_page_link']) and is_string($response['next_page_link']) and trim($response['next_page_link']) !== '') {
			return $response['next_page_link'];
		}

		return null;

	}

	/**
	 * Execute paginated requests and merge items.
	 */
	private function paginate(string $path, array $query = [], ?string $collectionKey = null, int $maxPages = 100): array {

		$items = [];
		$nextPath = $path;
		$nextQuery = $query;

		if ($maxPages < 1) {
			$maxPages = 1;
		}

		for ($page = 1; $page <= $maxPages; $page++) {

			$response = $this->request('GET', $nextPath, $nextQuery);
			$chunk = $this->extractItems($response, $collectionKey);

			if ($chunk) {
				$items = array_merge($items, $chunk);
			}

			$nextLink = $this->extractNextPageLink($response);

			if (!$nextLink) {
				break;
			}

			$nextPath = $nextLink;
			$nextQuery = [];
		}

		return $items;

	}

	/**
	 * Perform an HTTP request against Aircall API and return decoded JSON.
	 */
	private function request(string $method, string $path, array $query = [], ?array $body = null): array {

		$method = strtoupper(trim($method));
		$url = $this->buildUrl($path, $query);
		$auth = base64_encode($this->apiId . ':' . $this->apiToken);

		$headers = [
			'Accept: application/json',
			'Authorization: Basic ' . $auth,
			'Content-Type: application/json'
		];

		$jsonBody = null;
		if (null !== $body) {
			$jsonBody = json_encode($body);

			if (false === $jsonBody) {
				throw new PairException('Unable to encode Aircall request body to JSON.', ErrorCodes::AIRCALL_ERROR);
			}
		}

		for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_HEADER, true);

			if (null !== $jsonBody) {
				curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
			}

			$rawResponse = curl_exec($ch);

			if (false === $rawResponse) {
				$error = curl_error($ch);
				curl_close($ch);

				if ($attempt < $this->maxRetries) {
					usleep(($this->retryDelayMs * ($attempt + 1)) * 1000);
					continue;
				}

				throw new PairException('Aircall request failed: ' . ($error ?: 'unknown cURL error') . '.', ErrorCodes::AIRCALL_ERROR);
			}

			$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
			$rawHeaders = substr($rawResponse, 0, $headerSize);
			$rawBody = substr($rawResponse, $headerSize);
			curl_close($ch);

			if (($httpCode === 429 or $httpCode >= 500) and ($attempt < $this->maxRetries)) {

				$retryDelayMs = $this->extractRetryDelayMs($rawHeaders);
				if ($retryDelayMs < 1) {
					$retryDelayMs = $this->retryDelayMs * ($attempt + 1);
				}

				usleep($retryDelayMs * 1000);
				continue;
			}

			if (trim($rawBody) === '') {
				if (($httpCode >= 200) and ($httpCode < 300)) {
					return [];
				}

				throw new PairException('Aircall API returned HTTP ' . $httpCode . '.', ErrorCodes::AIRCALL_ERROR);
			}

			$data = json_decode($rawBody, true);
			if (!is_array($data)) {
				if (($httpCode >= 200) and ($httpCode < 300)) {
					throw new PairException('Aircall returned invalid JSON response.', ErrorCodes::AIRCALL_ERROR);
				}

				throw new PairException('Aircall API returned HTTP ' . $httpCode . ' and invalid JSON response.', ErrorCodes::AIRCALL_ERROR);
			}

			if (($httpCode < 200) or ($httpCode >= 300)) {
				throw new PairException($this->extractErrorMessage($data, $rawBody, $httpCode), ErrorCodes::AIRCALL_ERROR);
			}

			return $data;
		}

		throw new PairException('Aircall request failed after retries.', ErrorCodes::AIRCALL_ERROR);

	}

	/**
	 * Normalize API host and strip path if provided.
	 */
	private function sanitizeApiHost(string $apiHost): string {

		$apiHost = trim($apiHost);

		if ($apiHost === '') {
			return 'https://api.aircall.io';
		}

		if (!preg_match('/^https?:\/\//i', $apiHost)) {
			$apiHost = 'https://' . ltrim($apiHost, '/');
		}

		$parts = parse_url($apiHost);
		if (!$parts or !isset($parts['host'])) {
			throw new PairException('AIRCALL_API_HOST is not valid.', ErrorCodes::AIRCALL_ERROR);
		}

		$scheme = $parts['scheme'] ?? 'https';
		$host = $parts['host'];
		$port = isset($parts['port']) ? ':' . $parts['port'] : '';

		return $scheme . '://' . $host . $port;

	}

}
