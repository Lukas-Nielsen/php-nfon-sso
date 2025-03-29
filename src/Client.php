<?php
namespace ln\nfon_sso;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Http\Message\ResponseInterface;


class Client
{
	protected string $portalBaseUrl;
	protected string $clientId;
	protected string $codeVerifier;
	protected Token $token;
	protected GuzzleClient $client;

	public function __construct(string $portalBaseUrl, string $clientId)
	{
		$this->portalBaseUrl = $portalBaseUrl;
		$this->clientId = $clientId;
	}

	public function setPortalBaseUrl(string $portalBaseUrl)
	{
		$this->portalBaseUrl = $portalBaseUrl;
	}

	public function setClientId(string $clientId)
	{
		$this->clientId = $clientId;
	}

	public function Login(string $username, string $password)
	{
		$this->setup();

		$state = (new Helper())->generateUnique();
		$nonce = (new Helper())->generateUnique();
		$this->codeVerifier = (new Helper())->generateCodeVerifier();
		$code_challenge = (new Helper())->generateCodeChallenge($this->codeVerifier);

		// get login form
		$response = $this->client->request("GET", "https://sso.cloud-cfg.com/realms/login/protocol/openid-connect/auth", [
			"query" => [
				"client_id" => $this->clientId,
				"redirect_uri" => $this->portalBaseUrl,
				"state" => $state,
				"response_mode" => "fragment",
				"response_type" => "code",
				"scope" => "openid",
				"nonce" => $nonce,
				"code_challenge" => $code_challenge,
				"code_challenge_method" => "S256",
			],
		]);

		$html = $response->getBody()->__toString();
		libxml_use_internal_errors(true);
		$dom = new \DOMDocument();
		$dom->loadHTML($html);
		$form = $dom->getElementById("kc-form-login");
		$formUrl = $form->getAttribute("action");

		// login
		$response = $this->client->request("POST", $formUrl, [
			"form_params" => [
				"username" => $username,
				"password" => $password,
				"rememberMe" => "on",
				"credentialId" => ""
			],
			"allow_redirects" => false
		]);

		if ($response->getStatusCode() === 200) {
			// get otp form url
			$html = $response->getBody()->__toString();
			libxml_use_internal_errors(true);
			$dom = new \DOMDocument();
			$dom->loadHTML($html);
			$form = $dom->getElementById("kc-otp-login-form");
			$formUrl = $form->getAttribute("action");
			return new LoginResponse($formUrl, false);
		} else if ($response->getStatusCode() === 302) {
			// fetch access token
			return new LoginResponse("", !$this->fetchToken((new Helper())->getCodeFromUrl($response->getHeader("location")[0])));
		}

		return new LoginResponse("", true);
	}

	public function OTP(string $url, int $otp)
	{
		// do otp
		$response = $this->client->request("POST", $url, [
			"form_params" => [
				"otp" => $otp,
				"login" => "Loggen+Sie+sich+ein"
			],
			"allow_redirects" => false
		]);

		if ($response->getStatusCode() === 302) {
			// fetch access token
			return new LoginResponse("", !$this->fetchToken((new Helper())->getCodeFromUrl($response->getHeader("location")[0])));
		}

		return new LoginResponse("", true);
	}

	private function setup()
	{
		$this->client = new GuzzleClient(
			[
				"headers" => [
					"User-Agent" => "php-nfon-sso",
				],
				"cookies" => true,
				"http_errors" => false,
			]
		);
	}

	protected function fetchToken(string $code)
	{
		$response = $this->client->request("POST", "https://sso.cloud-cfg.com/realms/login/protocol/openid-connect/token", [
			"form_params" => [
				"code" => $code,
				"grant_type" => "authorization_code",
				"client_id" => $this->clientId,
				"redirect_uri" => $this->portalBaseUrl,
				"code_verifier" => $this->codeVerifier
			]
		]);
		if ($response->getStatusCode() === 200) {
			$token = json_decode($response->getBody()->__tostring());
			$this->token = new Token($token->access_token, $token->expires_in, $token->refresh_expires_in, $token->refresh_token, $token->token_type, $token->id_token, $token->session_state, $token->scope);
			return true;
		}
		return false;
	}

	public function RefreshToken(): bool
	{
		$response = $this->client->request("POST", "https://sso.cloud-cfg.com/realms/login/protocol/openid-connect/token", [
			"form_params" => [
				"grant_type" => "refresh_token",
				"refresh_token" => $this->token->refreshToken,
				"client_id" => $this->clientId
			]
		]);

		if ($response->getStatusCode() === 200) {
			$token = json_decode($response->getBody()->__tostring());
			$this->token = new Token($token->access_token, $token->expires_in, $token->refresh_expires_in, $token->refresh_token, $token->token_type, $token->id_token, $token->session_state, $token->scope);
			return true;
		}
		return false;
	}

	public function GetToken(): Token
	{
		return $this->token;
	}

	public function SetToken(Token $token): void
	{
		$this->token = $token;
	}

	public function TokenFromJsonFile(string $path): bool
	{
		$data = file_get_contents($path);
		if ($data === false)
			return false;
		$token = json_decode($data);
		$this->token = new Token($token->access_token, $token->expires_in, $token->refresh_expires_in, $token->refresh_token, $token->token_type, $token->id_token, $token->session_state, $token->scope);
		return true;
	}

	public function TokenToJsonFile(string $path): bool
	{
		return file_put_contents($path, json_encode($this->token)) !== false;
	}

	public function get(string $uri, array $query = [], array $header = []): ResponseInterface
	{
		return $this->client->get($uri, [
			"headers" => array_merge(
				$header,
				[
					"Authorization" => "{$this->token->tokenType} {$this->token->accessToken}",
				]
			),
			"query" => $query,
		]);
	}

	public function delete(string $uri, array $query = [], array $header = []): ResponseInterface
	{
		return $this->client->delete($uri, [
			"headers" => array_merge(
				$header,
				[
					"Authorization" => "{$this->token->tokenType} {$this->token->accessToken}",
				]
			),
			"query" => $query,
		]);
	}

	public function post(string $uri, array $payload, array $query = [], array $header = []): ResponseInterface
	{
		return $this->client->post($uri, [
			"headers" => array_merge(
				$header,
				[
					"Authorization" => "{$this->token->tokenType} {$this->token->accessToken}",
				]
			),
			"query" => $query,
			"json" => $payload,
		]);
	}

	public function put(string $uri, array $payload, array $query = [], array $header = []): ResponseInterface
	{
		return $this->client->put($uri, [
			"headers" => array_merge(
				$header,
				[
					"Authorization" => "{$this->token->tokenType} {$this->token->accessToken}",
				]
			),
			"query" => $query,
			"json" => $payload,
		]);
	}

	public function patch(string $uri, array $payload, array $query = [], array $header = []): ResponseInterface
	{
		return $this->client->patch($uri, [
			"headers" => array_merge(
				$header,
				[
					"Authorization" => "{$this->token->tokenType} {$this->token->accessToken}",
				]
			),
			"query" => $query,
			"json" => $payload,
		]);
	}
}
