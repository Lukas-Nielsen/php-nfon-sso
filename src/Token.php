<?php

declare(strict_types=1);

namespace ln\NfonSso;

final class Token
{
	public string $access_token = "";
	public int $expires_in = 0;
	public int $refresh_expires_in = 0;
	public string $refresh_token = "";
	public string $token_type = "";
	public string $id_token = "";
	public string $session_state = "";
	public string $scope = "";

	public static function fromArray(array $data): self
	{
		$token = new self();
		$token->access_token = (string) ($data["access_token"] ?? "");
		$token->expires_in = (int) ($data["expires_in"] ?? 0);
		$token->refresh_expires_in = (int) ($data["refresh_expires_in"] ?? 0);
		$token->refresh_token = (string) ($data["refresh_token"] ?? "");
		$token->token_type = (string) ($data["token_type"] ?? "");
		$token->id_token = (string) ($data["id_token"] ?? "");
		$token->session_state = (string) ($data["session_state"] ?? "");
		$token->scope = (string) ($data["scope"] ?? "");

		return $token;
	}

	public function toArray(): array
	{
		return [
			"access_token" => $this->access_token,
			"expires_in" => $this->expires_in,
			"refresh_expires_in" => $this->refresh_expires_in,
			"refresh_token" => $this->refresh_token,
			"token_type" => $this->token_type,
			"id_token" => $this->id_token,
			"session_state" => $this->session_state,
			"scope" => $this->scope,
		];
	}
}
