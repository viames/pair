<?php

namespace Pair\Services;

use CurlHandle;

use Pair\Core\Env;
use Pair\Exceptions\ErrorCodes;
use Pair\Exceptions\PairException;
use Pair\Helpers\Utilities;

/**
 * ELK Stack is a popular log management platform. The ELK Stack is a collection of three open-source
 * products — Elasticsearch, Logstash, and Kibana. This class sends log messages to Elasticsearch.
 */
class ElkLogger {

	/**
	 * Elasticsearch URL (for example http://localhost:9200).
	 */
    private string $elkUrl;

	/**
	 * Name of the index to log to.
	 */
    private string $elkIndex;

	/**
	 * Username (if authentication is active).
	 */
    private ?string $elkUser = null;

	/**
	 * Password (if authentication is active).
	 */
    private ?string $elkPassword = null;

    /**
     * Constructor.
     *
     * @param string		Elasticsearch URL (for example http://localhost:9200).
     * @param string|null	Username (if authentication is active).
     * @param string|null	Password (if authentication is active).
     */
    public function __construct(string $elkUrl, ?string $elkUser = null, ?string $elkPassword = null) {

		$env = Env::get('ENVIRONMENT');
		$shortEnv = 'production' == $env ? 'prod' : ('staging' == $env ? 'stag' : 'dev');

        $this->elkUrl		= rtrim($elkUrl, '/');
        $this->elkIndex		= Utilities::cleanUp(Env::get('APP_NAME'),'') . '-' . $shortEnv;
        $this->elkUser		= $elkUser;
        $this->elkPassword	= $elkPassword;

    }

    /**
	 * Register a log message to Elasticsearch.
     *
     * @param	string	Log message.
     * @param	string	Log level info|warning|error, default info.
     */
    public function log(string $message, string $level = 'info'): void {

        $logData = [
            '@timestamp'  => date('c'), // ISO 8601 timestamp
            'level'       => $level,
            'message'     => $message,
            'application' => Env::get('APP_NAME') . ' ' . Env::get('APP_VERSION'),
            'server'      => $_SERVER['SERVER_NAME'] ?? 'localhost',
            'ip'          => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ];

        $this->sendToElk($logData);
    
    }

    /**
     * Send log data to Elasticsearch.
     *
     * @param array Log data to send.
     */
    private function sendToElk(array $logData): void {

        $url = "{$this->elkUrl}/{$this->elkIndex}/_doc";

        $ch = curl_init($url);

        if (!$ch instanceof CurlHandle) {
            throw new PairException('Curl initialization of Elasticsearch failed', ErrorCodes::LOGGER_FAILURE);
        }

		$payload = json_encode($logData, JSON_THROW_ON_ERROR);

        $headers = [
			'Content-Type: application/json',
			'Content-Length: ' . strlen($payload)
		];

        if ($this->elkUser and $this->elkPassword) {
            curl_setopt($ch, CURLOPT_USERPWD, "{$this->elkUser}:{$this->elkPassword}");
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!in_array($httpCode, [200, 201])) {
            throw new PairException($response, ErrorCodes::LOGGER_FAILURE);
        }

    }

}