<?php

declare(strict_types=1);

namespace ln\NfonSso;

use DateTimeImmutable;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class Client
{
	private const string USER_AGENT = "php-nfon-sso";
	private const string AUTH_BASE_URL = "https://sso.cloud-cfg.com/realms/login/protocol/openid-connect";

	private string $portalBaseUrl;
	private string $clientId;
	private string $codeVerifier = "";
	private string $username = "";
	private string $password = "";
	private string $otpSecret = "";
	private bool $debug = false;
	private int $requestCount = 0;
	private Token $token;
	private CookieJar $cookieJar;
	private HttpClient $httpClient;
	private ?DateTimeImmutable $tokenExpiresAt = null;

	public function __construct(string $portalBaseUrl, string $clientId, ?HttpClient $httpClient = null)
	{
		$this->portalBaseUrl = $portalBaseUrl;
		$this->clientId = $clientId;
		$this->token = new Token();
		$this->cookieJar = CookieJar::fromArray([], $this->portalBaseUrl);
		$this->httpClient = $httpClient ?? new HttpClient([
			"http_errors" => false,
			"allow_redirects" => ["max" => 20, "strict" => false],
			"cookies" => $this->cookieJar,
			"headers" => ["User-Agent" => self::USER_AGENT],
		]);
	}

	public function setHttpClient(HttpClient $httpClient): self
	{
		$this->httpClient = $httpClient;
		return $this;
	}

	public function setPortalBaseUrl(string $portalBaseUrl): self
	{
		$this->portalBaseUrl = $portalBaseUrl;
		return $this;
	}

	public function setClientId(string $clientId): self
	{
		$this->clientId = $clientId;
		return $this;
	}

	public function setDebug(bool $debug): self
	{
		$this->debug = $debug;
		return $this;
	}

	public function setUser(string $username, string $password, string $otpSecret): self
	{
		$this->username = $username;
		$this->password = $password;
		$this->otpSecret = $otpSecret;
		return $this;
	}

	public function login(string $username, string $password): string
	{
		$state = Helpers::generateUnique(32);
		$nonce = Helpers::generateUnique(32);
		$this->codeVerifier = Helpers::generateCodeVerifier(43);
		$codeChallenge = Helpers::generateCodeChallenge($this->codeVerifier);

		$query = [
			"client_id" => $this->clientId,
			"redirect_uri" => $this->portalBaseUrl,
			"state" => $state,
			"response_mode" => "fragment",
			"response_type" => "code",
			"scope" => "openid",
			"nonce" => $nonce,
			"code_challenge" => $codeChallenge,
			"code_challenge_method" => "S256",
		];

		$response = $this->request("GET", self::AUTH_BASE_URL . "/auth", ["query" => $query], false);
		$formUrl = Helpers::extractFormActionFromBody((string) $response->getBody(), Helpers::FORM_LOGIN);

		$response = $this->request(
			"POST",
			Helpers::resolveUrl(self::AUTH_BASE_URL, $formUrl),
			[
				"form_params" => ["username" => $username],
				"allow_redirects" => false,
			],
			false,
		);
		$formUrl = Helpers::extractFormActionFromBody((string) $response->getBody(), Helpers::FORM_LOGIN);

		$response = $this->request(
			"POST",
			Helpers::resolveUrl(self::AUTH_BASE_URL, $formUrl),
			[
				"form_params" => [
					"username" => $username,
					"password" => $password,
					"rememberMe" => "on",
					"credentialId" => "",
				],
				"allow_redirects" => false,
			],
			false,
		);

		if ($response->getStatusCode() === 200) {
			$otpFormUrl = Helpers::extractFormActionFromBody((string) $response->getBody(), Helpers::FORM_OTP);
			return $otpFormUrl;
		}

		if ($response->getStatusCode() === 302) {
			$this->fetchToken(Helpers::getCodeFromUrl((string) $response->getHeaderLine("Location")));
			return "";
		}

		throw new RuntimeException("Unexpected response while logging in: " . $response->getStatusCode());
	}

	public function otp(string $url, string $secret): string
	{
		if (\strlen($secret) <= 6) {
			throw new RuntimeException("OTP secret is too short");
		}

		$otp = Helpers::generateTotp($secret);
		$response = $this->request(
			"POST",
			Helpers::resolveUrl(self::AUTH_BASE_URL, $url),
			[
				"form_params" => ["otp" => $otp, "login" => "Loggen Sie sich ein"],
				"allow_redirects" => false,
			],
			false,
		);

		if ($response->getStatusCode() === 302) {
			$this->fetchToken(Helpers::getCodeFromUrl((string) $response->getHeaderLine("Location")));
			return "";
		}

		$formUrl = Helpers::extractFormActionFromBody((string) $response->getBody(), Helpers::FORM_OTP);
		throw new RuntimeException("OTP validation failed: " . $response->getStatusCode() . " - " . $formUrl);
	}

	public function auth(): void
	{
		$uri = $this->login($this->username, $this->password);
		if ($uri !== "") {
			$this->otp($uri, $this->otpSecret);
		}
	}

	public function logout(): void
	{
		try {
			$this->request(
				"GET",
				self::AUTH_BASE_URL . "/logout",
				[
					"query" => [
						"client_id" => $this->clientId,
						"post_logout_redirect_uri" => $this->portalBaseUrl,
						"id_token_hint" => $this->token->id_token,
					],
					"allow_redirects" => false,
				],
				false,
			);
		} catch (GuzzleException|RuntimeException $e) {
			// The logout request is best-effort only.
		}

		$this->cookieJar = CookieJar::fromArray([], $this->portalBaseUrl);
		$this->httpClient = new HttpClient([
			"http_errors" => false,
			"allow_redirects" => ["max" => 20, "strict" => false],
			"cookies" => $this->cookieJar,
			"headers" => ["User-Agent" => self::USER_AGENT],
		]);
	}

	public function refreshToken(): void
	{
		$response = $this->request(
			"POST",
			self::AUTH_BASE_URL . "/token",
			[
				"form_params" => [
					"grant_type" => "refresh_token",
					"refresh_token" => $this->token->refresh_token,
					"client_id" => $this->clientId,
				],
			],
			false,
		);

		$payload = json_decode((string) $response->getBody(), true);
		if (!\is_array($payload)) {
			throw new RuntimeException("Invalid token refresh payload");
		}

		$this->token = Token::fromArray($payload);
		$this->updateExpiry();
	}

	public function fetchToken(string $code): void
	{
		$response = $this->request(
			"POST",
			self::AUTH_BASE_URL . "/token",
			[
				"form_params" => [
					"code" => $code,
					"grant_type" => "authorization_code",
					"client_id" => $this->clientId,
					"redirect_uri" => $this->portalBaseUrl,
					"code_verifier" => $this->codeVerifier,
				],
			],
			false,
		);

		$payload = json_decode((string) $response->getBody(), true);
		if (!\is_array($payload) || empty($payload["access_token"])) {
			throw new RuntimeException("Token fetch failed: " . (string) $response->getBody());
		}

		$this->token = Token::fromArray($payload);
		$this->updateExpiry();
	}

	public function getToken(): Token
	{
		return $this->token;
	}

	public function setToken(Token $token): void
	{
		$this->token = $token;
		$this->updateExpiry();
	}

	public function getRequestCount(): int
	{
		return $this->requestCount;
	}

	public function resetRequestCount(): void
	{
		$this->requestCount = 0;
	}

	public function tokenFromJsonFile(string $path): void
	{
		$content = file_get_contents($path);
		if ($content === false) {
			throw new RuntimeException("Unable to read token file: {$path}");
		}

		$this->tokenFromJson($content);
	}

	public function tokenToJsonFile(string $path): void
	{
		$content = $this->tokenToJson();
		if (file_put_contents($path, $content) === false) {
			throw new RuntimeException("Unable to write token file: {$path}");
		}
	}

	public function tokenFromJson(string $json): void
	{
		$data = json_decode($json, true);
		if (!\is_array($data)) {
			throw new RuntimeException("Invalid JSON token payload");
		}

		$this->token = Token::fromArray($data);
		$this->updateExpiry();
	}

	public function tokenToJson(): string
	{
		return json_encode($this->token->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: "{}";
	}

	public function get(
		string $customer,
		string $uri,
		array $query = [],
		array $header = [],
	): \Psr\Http\Message\ResponseInterface {
		return $this->doRequest($customer, "GET", $uri, null, $query, $header);
	}

	public function post(
		string $customer,
		string $uri,
		mixed $payload = null,
		array $query = [],
		array $header = [],
	): \Psr\Http\Message\ResponseInterface {
		return $this->doRequest($customer, "POST", $uri, $payload, $query, $header);
	}

	public function put(
		string $customer,
		string $uri,
		mixed $payload = null,
		array $query = [],
		array $header = [],
	): \Psr\Http\Message\ResponseInterface {
		return $this->doRequest($customer, "PUT", $uri, $payload, $query, $header);
	}

	public function patch(
		string $customer,
		string $uri,
		mixed $payload = null,
		array $query = [],
		array $header = [],
	): \Psr\Http\Message\ResponseInterface {
		return $this->doRequest($customer, "PATCH", $uri, $payload, $query, $header);
	}

	public function delete(
		string $customer,
		string $uri,
		array $query = [],
		array $header = [],
	): \Psr\Http\Message\ResponseInterface {
		return $this->doRequest($customer, "DELETE", $uri, null, $query, $header);
	}

	public function getPortalApi(string $customer, string $uri, array $query = [], array $header = []): Response
	{
		$response = $this->doRequest($customer, "GET", $uri, null, $query, $header);
		$payload = json_decode((string) $response->getBody(), true);
		return \is_array($payload) ? Response::fromArray($payload) : new Response();
	}

	public function postPortalApi(
		string $customer,
		string $uri,
		mixed $payload = null,
		array $query = [],
		array $header = [],
	): Response {
		$response = $this->doRequest($customer, "POST", $uri, $payload, $query, $header);
		$payload = json_decode((string) $response->getBody(), true);
		return \is_array($payload) ? Response::fromArray($payload) : new Response();
	}

	public function putPortalApi(
		string $customer,
		string $uri,
		mixed $payload = null,
		array $query = [],
		array $header = [],
	): Response {
		$response = $this->doRequest($customer, "PUT", $uri, $payload, $query, $header);
		$payload = json_decode((string) $response->getBody(), true);
		return \is_array($payload) ? Response::fromArray($payload) : new Response();
	}

	public function uploadFile(
		string $customer,
		string $uri,
		array $query = [],
		array $header = [],
		string $fieldName,
		string $fileName,
		string $mimeType,
		array $formData = [],
	): \Psr\Http\Message\ResponseInterface {
		$this->requestCount++;
		$this->validateToken();

		$multipart = [
			[
				"name" => $fieldName,
				"contents" => fopen($fileName, "rb"),
				"filename" => basename($fileName),
				"headers" => ["Content-Type" => $mimeType],
			],
		];

		foreach ($formData as $key => $value) {
			$multipart[] = ["name" => $key, "contents" => (string) $value];
		}

		$options = [
			"query" => $query,
			"headers" => [...["User-Agent" => self::USER_AGENT . " (" . $customer . ")"], ...$header],
			"multipart" => $multipart,
			"allow_redirects" => true,
		];

		$options["headers"]["Authorization"] = $this->token->token_type . " " . $this->token->access_token;

		return $this->httpClient->request("POST", $uri, $options);
	}

	private function doRequest(
		string $customer,
		string $method,
		string $uri,
		mixed $payload = null,
		array $query = [],
		array $header = [],
	): \Psr\Http\Message\ResponseInterface {
		$this->requestCount++;
		$this->validateToken();

		$options = [
			"query" => $query,
			"headers" => [
				...[
					"User-Agent" => self::USER_AGENT . " (" . $customer . ")",
					"Authorization" => $this->token->token_type . " " . $this->token->access_token,
				],
				...$header,
			],
			"allow_redirects" => true,
		];

		if ($payload !== null) {
			if (\is_array($payload) || \is_object($payload)) {
				$options["json"] = $payload;
			} else {
				$options["body"] = (string) $payload;
			}
		}

		return $this->request($method, $uri, $options, true);
	}

	private function request(
		string $method,
		string $uri,
		array $options,
		bool $checkStatus,
	): \Psr\Http\Message\ResponseInterface {
		try {
			$response = $this->httpClient->request($method, $uri, $options);
		} catch (GuzzleException $e) {
			throw new RuntimeException("HTTP request failed: " . $e->getMessage(), 0, $e);
		}

		if ($checkStatus && $response->getStatusCode() >= 400) {
			throw new RuntimeException(
				"Request failed with status " . $response->getStatusCode() . ": " . (string) $response->getBody(),
			);
		}

		return $response;
	}

	private function validateToken(): void
	{
		if ($this->token->access_token === "") {
			$this->auth();
			return;
		}

		if ($this->tokenExpiresAt === null) {
			$this->updateExpiry();
		}

		if ($this->tokenExpiresAt !== null && time() >= ($this->tokenExpiresAt->getTimestamp() - 600)) {
			$this->refreshToken();
		}
	}

	private function updateExpiry(): void
	{
		if ($this->token->access_token === "") {
			$this->tokenExpiresAt = null;
			return;
		}

		try {
			$this->tokenExpiresAt = $this->parseJwtExpiry($this->token->access_token);
			return;
		} catch (RuntimeException $e) {
			$expiresIn = max(0, (int) $this->token->expires_in);
			$this->tokenExpiresAt = new DateTimeImmutable("@" . (time() + $expiresIn));
		}
	}

	private function parseJwtExpiry(string $jwt): DateTimeImmutable
	{
		$parts = explode(".", $jwt);
		if (\count($parts) < 2) {
			throw new RuntimeException("Invalid JWT token");
		}

		$payload = $parts[1];
		$payload = str_replace(["-", "_"], ["+", "/"], $payload);
		$padding = \strlen($payload) % 4;
		if ($padding > 0) {
			$payload .= str_repeat("=", 4 - $padding);
		}

		$decoded = base64_decode($payload, true);
		if ($decoded === false) {
			throw new RuntimeException("JWT payload could not be decoded");
		}

		$claims = json_decode($decoded, true);
		if (!\is_array($claims) || !isset($claims["exp"])) {
			throw new RuntimeException("JWT exp claim is missing");
		}

		return new DateTimeImmutable("@" . (int) $claims["exp"]);
	}
}
