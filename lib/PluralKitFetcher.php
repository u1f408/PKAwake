<?php
declare(strict_types=1);
namespace PKAwake;

use IrisHelpers\CurlHelpers;
use ix\Database\RedisContainer;

class PluralKitFetcher {
	/** @const string API_BASE */
	const API_BASE = "https://api.pluralkit.me/v1";

	/** @var string $systemID */
	public $systemID;

	/** @var ?string $token */
	public $token;

	/**
	 * Create a new PluralKitFetcher.
	 *
	 * @param string $systemID The system ID to fetch
	 * @param ?string $token The authentication token to use
	 */
	public function __construct(string $systemID, ?string $token = null) {
		$this->systemID = $systemID;
		$this->token = $token;
	}

	/**
	 * Create a new PluralKitFetcher from the application environment.
	 * @return self
	 */
	public static function from_env(): self {
		$systemID = $_ENV[IX_ENVBASE . '_PLURALKIT_SYSTEM'];
		$token = array_key_exists(IX_ENVBASE . '_PLURALKIT_TOKEN', $_ENV)
			? $_ENV[IX_ENVBASE . '_PLURALKIT_TOKEN']
			: null;

		return new self($systemID, $token);
	}

	/**
	 * Whether or not to use Redis for caching API response data.
	 *
	 * @return bool
	 */
	public function cache_enabled(): bool {
		return boolval($_ENV[IX_ENVBASE . '_CACHE_ENABLED']);
	}

	/**
	 * Returns the Redis key used for caching the API response data.
	 * If the cache key is not overridden by the environment, this is
	 * derived from the PluralKit system ID.
	 *
	 * @return string
	 */
	public function cache_key(): string {
		if (array_key_exists(IX_ENVBASE . '_CACHE_KEY', $_ENV)) {
			return $_ENV[IX_ENVBASE . '_CACHE_KEY'];
		}

		return "PKAwake:system:{$this->systemID}";
	}

	/**
	 * Fetch the basic system data and the current fronter list from
	 * the PluralKit API. This does not handle caching.
	 *
	 * Returns `null` if any of the fetch steps failed.
	 *
	 * @return ?array<string, mixed> System and fronter data
	 */
	public function api_fetch(): ?array {
		/** @var array<string, mixed> $result */
		$result = [];

		// TODO: add support for request header `Authorization: {$this->token}`

		/* First off - get the basic system information.
		 * This has, among other things, the system name and timezone.
		 */

		/** @var string $system_info_url */
		$system_info_url = self::API_BASE . "/s/{$this->systemID}";

		/** @var array<string, mixed>|false $system_info */
		$system_info = CurlHelpers::fetchUrl($system_info_url, [], true);
		if ($system_info === false)
			return null;

		/* Gather the basic system information that we need
		 */

		$result['system'] = [
			'id' => $system_info['id'],
			'name' => $system_info['name'],
			'timezone' => $system_info['tz'] ?? 'UTC',
			'avatar_url' => $system_info['avatar_url'],
		];

		/* Next - get the current fronter information.
		 *
		 * This has the member information for the currently fronting members,
		 * as well as the timestamp of the switch.
		 */

		/** @var string $fronter_info_url */
		$fronter_info_url = self::API_BASE . "/s/{$this->systemID}/fronters";

		/** @var array<string, mixed>|false $fronter_info */
		$fronter_info = CurlHelpers::fetchUrl($fronter_info_url, [], true);
		if ($fronter_info === false)
			return null;

		/* Gather information about the fronting members
		 */

		$result['members'] = [];
		foreach ($fronter_info['members'] as $idx => $member) {
			$result['members'][$member['id']] = [
				'name' => $member['name'],
				'display_name' => $member['display_name'],
				'color' => $member['color'],
				'pronouns' => $member['pronouns'],
				'avatar_url' => $member['avatar_url'],
			];
		}

		/* Gather information about the current switch
		 */

		$result['currentswitch'] = [
			'timestamp' => $fronter_info['timestamp'],
			'members' => array_keys($result['members']),
		];

		/* Lastly - get the list of switches, and walk it backwards to find
		 * the switch that happened immediately after the last switch-out.
		 */

		/** @var string $switch_info_url */
		$switch_info_url = self::API_BASE . "/s/{$this->systemID}/switches";

		/** @var array<int, mixed>|false $switch_info */
		$switch_info = CurlHelpers::fetchUrl($switch_info_url, [], true);
		if ($switch_info === false)
			return null;

		/* Walk the switch list
		 */

		$switch_out_idx = null;
		foreach ($switch_info as $idx => $switch) {
			if (empty($switch['members'])) {
				$switch_out_idx = $idx;
				break;
			}
		}

		/* Gather information about the switch immediately after the last
		 * switch-out.
		 *
		 * If `$switch_out_idx` is `0`, we're currently in a switch-out, so
		 * just leave `$result['awakeswitch']` as null.
		 *
		 * If `$switch_out_idx` is `null`, we couldn't find a switch-out in
		 * the walk, so use the earliest switch returned by the PluralKit API.
		 */

		$result['awakeswitch'] = null;
		if ($switch_out_idx !== 0) {
			$awakeswitch_idx = intval((is_null($switch_out_idx) ? count($switch_info) : $switch_out_idx)) - 1;
			$awakeswitch = $switch_info[$awakeswitch_idx];
			if (!empty($awakeswitch)) {
				$result['awakeswitch'] = [
					'timestamp' => $awakeswitch['timestamp'],
					'members' => array_values($awakeswitch['members']),
				];
			}
		}

		/* And we're done here!
		 */

		return $result;
	}

	/**
	 * Get the basic system data and the current fronter list.
	 *
	 * If caching is enabled, this function prefers the cached value.
	 *
	 * If the cache is empty, this function will make requests to the
	 * PluralKit API, and store the newly-retrieved value in the cache.
	 *
	 * If caching is disabled, this function will always make requests
	 * to the PluralKit API.
	 *
	 * Returns `null` if any of the PluralKit API requests failed, and
	 * the cache (if enabled) did not contain a valid value.
	 *
	 * @return ?array<string, mixed> System and fronter data
	 */
	public function retrieve(): ?array {
		/** @var String $redis_key */
		$redis_key = $this->cache_key();
		
		/** @var ?\Redis $redis */
		$redis = $this->cache_enabled() ? RedisContainer::get() : null;

		/** @var ?array<string, mixed> $result */
		$result = null;

		/* First, if cache is enabled, try to pull the cached value
		 */

		if ($this->cache_enabled()) {
			if (0 < $redis->exists($redis_key)) {
				$value = $redis->get($redis_key);
				if ($value === false)
					$value = '';

				$result = json_decode($value, true);
				if (empty($result) || json_last_error() !== JSON_ERROR_NONE) {
					$result = null;
				} else {
					return $result;
				}
			}
		}

		/* If we get here, a cache hit failed, let's hit the API
		 */

		$result = $this->api_fetch();
		if ($result === null)
			return null;

		/* Okay, we have a value!
		 *
		 * If the cache is enabled, store this value, with an expiry of 5 minutes.
		 */

		if ($this->cache_enabled()) {
			$redis->del($redis_key);
			$redis->set($redis_key, json_encode($result));
			$redis->expire($redis_key, 60 * 5);
		}

		/* Return the result!
		 */

		return $result;
	}
}
