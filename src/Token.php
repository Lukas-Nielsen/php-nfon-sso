<?php

namespace ln\nfon_sso;

class Token
{
	public string $accessToken;
	public int $expiresIn;
	public int $refreshExpiresIn;
	public string $refreshToken;
	public string $tokenType;
	public string $idToken;
	public string $sessionState;
	public string $scope;

	public function __construct(
		string $accessToken,
		int $expiresIn,
		int $refreshExpiresIn,
		string $refreshToken,
		string $tokenType,
		string $idToken,
		string $sessionState,
		string $scope
	) {
		$this->accessToken = $accessToken;
		$this->expiresIn = $expiresIn;
		$this->refreshExpiresIn = $refreshExpiresIn;
		$this->refreshToken = $refreshToken;
		$this->tokenType = $tokenType;
		$this->idToken = $idToken;
		$this->sessionState = $sessionState;
		$this->scope = $scope;
	}
}

