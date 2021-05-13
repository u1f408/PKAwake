<?php
declare(strict_types=1);
namespace PKAwake;

final class CurlHelpers {
	/**
	 * Fetches the given URL.
	 *
	 * If the response contains a `Content-Type: application/json` header,
	 * this function decodes the response body as JSON and returns the decoded
	 * value. For all other `Content-Type` values, the response body is returned
	 * unmodified.
	 *
	 * If the `curl_exec` call fails (i.e. it returns `false`), this function
	 * will return `false`.
	 *
	 * @param string $url The URL to retrieve
	 * @param mixed[] $curlopts Options to pass to cURL
	 * @param bool $json_only If `true`, return `false` when response is not JSON
	 * @return mixed|string|false The potentially-decoded response body
	 */
	public static function fetchUrl(string $url, array $curlopts = [], bool $json_only = false): mixed {
		$ch = curl_init();
		curl_setopt_array($ch, $curlopts);
		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_HEADER => true,
			CURLOPT_RETURNTRANSFER => true,
		]);

		/* Get full HTTP response
		 */
		
		/** @var string|false $response */
		$response = curl_exec($ch);
		if ($response === false)
			return false;

		/* Pull metadata out of cURL
		 */

		$req_info = curl_getinfo($ch);

		/* Get the length of the HTTP header and split the response
		 */

		$header_length = array_key_exists('header_size', $req_info) ? $req_info['header_size'] : 0;
		list($headers, $body) = [substr($response, 0, $header_length), substr($response, $header_length)];

		/* Check the content type for JSON!
		 *
		 * Explode on `;` and take the first entry (so we don't get the charset, etc)
		 * and if it's `application/json` then throw `$body` through the decoder
		 */

		if (array_key_exists('content_type', $req_info)) {
			list($content_type, $_) = explode(';', $req_info['content_type'], 2);
			if (trim($content_type) === 'application/json') {
				$body = json_decode($body, true);
			} else {
				if ($json_only === true) {
					return false;
				}
			}
		}

		return $body;
	}
}
