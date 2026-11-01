<?php

declare(strict_types=1);

namespace LukasNielsen\NfonSso;

final class Response
{
	public string $href = "";
	public int $offset = 0;
	public int $total = 0;
	public int $size = 0;
	public array $links = [];
	public array $data = [];
	public array $items = [];

	public static function fromArray(array $data): self
	{
		$response = new self();
		$response->href = (string) ($data["href"] ?? "");
		$response->offset = (int) ($data["offset"] ?? 0);
		$response->total = (int) ($data["total"] ?? 0);
		$response->size = (int) ($data["size"] ?? 0);
		$response->links = self::mapLinks($data["links"] ?? []);
		$response->data = self::mapData($data["data"] ?? []);

		foreach ($data["items"] ?? [] as $item) {
			$response->items[] = Item::fromArray($item);
		}

		return $response;
	}

	private static function mapLinks(array $links): array
	{
		$result = [];
		foreach ($links as $key => $value) {
			if (is_array($value)) {
				$rel = (string) ($value["rel"] ?? $key);
				$href = (string) ($value["href"] ?? "");
				$result[$rel] = $href;
				continue;
			}

			$result[(string) $key] = (string) $value;
		}

		return $result;
	}

	private static function mapData(array $data): array
	{
		$result = [];
		foreach ($data as $entry) {
			if (is_array($entry)) {
				$name = (string) ($entry["name"] ?? $entry["key"] ?? "");
				$value = $entry["value"] ?? null;
				if ($name !== "") {
					$result[$name] = $value;
				}
			}
		}

		return $result;
	}
}

final class Item
{
	public string $href = "";
	public array $links = [];
	public array $data = [];

	public static function fromArray(array $item): self
	{
		$result = new self();
		$result->href = (string) ($item["href"] ?? "");
		$result->links = [];
		$result->data = [];

		foreach ($item["links"] ?? [] as $key => $value) {
			if (\is_array($value)) {
				$rel = (string) ($value["rel"] ?? $key);
				$href = (string) ($value["href"] ?? "");
				$result->links[$rel] = $href;
				continue;
			}

			$result->links[(string) $key] = (string) $value;
		}

		foreach ($item["data"] ?? [] as $entry) {
			if (\is_array($entry)) {
				$name = (string) ($entry["name"] ?? $entry["key"] ?? "");
				if ($name !== "") {
					$result->data[$name] = $entry["value"] ?? null;
				}
			}
		}

		return $result;
	}
}
