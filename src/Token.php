<?php

namespace ln\nfon_sso;

class Token
{
	public string $access_token;
	public int $expires_in;
	public int $refresh_expires_in;
	public string $refresh_token;
	public string $token_type;
	public string $id_token;
	public string $session_state;
	public string $scope;

	public function __construct(
		string $access_token,
		int $expires_in,
		int $refresh_expires_in,
		string $refresh_token,
		string $token_type,
		string $id_token,
		string $session_state,
		string $scope
	) {
		$this->access_token = $access_token;
		$this->expires_in = $expires_in;
		$this->refresh_expires_in = $refresh_expires_in;
		$this->refresh_token = $refresh_token;
		$this->token_type = $token_type;
		$this->id_token = $id_token;
		$this->session_state = $session_state;
		$this->scope = $scope;
	}
}

