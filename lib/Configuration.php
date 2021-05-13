<?php
declare(strict_types=1);
namespace PKAwake;

use Dotenv\Dotenv;

class Configuration {
	/** @var ?Dotenv $dotenv */
	private static $dotenv = null;

	/**
	 * Create or return a Dotenv instance
	 *
	 * @param string $mode
	 * @return Dotenv
	 */
	public static function load(string $mode = 'base'): Dotenv {
		if (self::$dotenv === null) {
			self::$dotenv = self::create($mode);
		}

		return self::$dotenv;
	}

	/**
	 * Construct a new Dotenv instance
	 *
	 * @param string $mode
	 * @return Dotenv
	 */
	private static function create(string $mode = 'base'): Dotenv {
		// Construct and load Dotenv
		$dotenv = Dotenv::createImmutable(IX_BASE);
		$dotenv->load();

		// Require an environment
		$dotenv->required('APP_ENV')->allowedValues(['development', 'test', 'production']);

		// PluralKit API token and system ID
		$dotenv->ifPresent(IX_ENVBASE . '_PLURALKIT_TOKEN')->notEmpty();
		$dotenv->required(IX_ENVBASE . '_PLURALKIT_SYSTEM')->notEmpty();

		// Toggle for "awake/asleep" (true) or "switched in/out" (false)
		$dotenv->required(IX_ENVBASE . '_DISPLAY_AWAKE')->isBoolean();

		// Toggle showing member cards
		$dotenv->required(IX_ENVBASE . '_DISPLAY_MEMBERS')->isBoolean();

		// Toggle showing time since switch
		$dotenv->required(IX_ENVBASE . '_DISPLAY_TIME_SINCE')->isBoolean();

		// If displaying time since switch is enabled, this toggles whether that
		// is only shown if the time exceeds the defined threshold (in minutes)
		$dotenv->required(IX_ENVBASE . '_DISPLAY_TIME_SINCE_GATED')->isBoolean();
		if (boolval($_ENV[IX_ENVBASE . '_DISPLAY_TIME_SINCE_GATED'])) {
			$dotenv->required(IX_ENVBASE . '_DISPLAY_TIME_SINCE_THRESHOLD')->isInteger();
		}

		// If API response caching is enabled, require a Redis URL
		$dotenv->required(IX_ENVBASE . '_CACHE_ENABLED')->isBoolean();
		if (boolval($_ENV[IX_ENVBASE . '_CACHE_ENABLED'])) {
			$dotenv->ifPresent(IX_ENVBASE . '_CACHE_KEY')->notEmpty();
			$dotenv->required('REDIS_URL')->notEmpty();
		}

		return $dotenv;
	}
}
