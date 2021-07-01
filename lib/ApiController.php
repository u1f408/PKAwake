<?php
declare(strict_types=1);
namespace PKAwake;

use ix\Controller\Controller;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpInternalServerErrorException;
use PKAwake\PluralKitFetcher;

class ApiController extends Controller {
	/**
	 * @param Request $request The Request object
	 * @param Response $response The Response object
	 * @param mixed[] $args Arguments passed from the router (if any)
	 * @return Response The resulting Response object
	 */
	public function requestGET(Request $request, Response $response, ?array $args = []): Response {
		/* Get the current PluralKit status
		 */

		$systemID = null;
		$routeArgs = $request->getAttribute('__routingResults__')->getRouteArguments();
		if (array_key_exists('system', $routeArgs))
			$systemID = $routeArgs['system'];
		if ($systemID === null)
			$systemID = $_ENV[IX_ENVBASE . '_PLURALKIT_SYSTEM'];

		$pk_data = (new PluralKitFetcher($systemID))->retrieve();
		if ($pk_data === null)
			throw new HttpInternalServerErrorException($request);

		// Create an array that we'll encode to JSON later
		$output = [
			'system' => $pk_data['system'],
			'awake' => !empty($pk_data['currentswitch']['members']),
			'switch_ts' => null,
			'fronters' => [],
		];

		// Get a DateTime for our "awake" switch
		$switch_ts = \DateTime::createFromFormat(
			\DateTimeInterface::RFC3339,
			preg_replace('#\.\d+#', '', $pk_data['awakeswitch']['timestamp']),
		);

		// If we actually _have_ a switch timestamp, fill in our switch_ts key
		if ($switch_ts !== false) {
			$diff_now = $switch_ts->diff(new \DateTime('now'));

			/** @phpstan-ignore-next-line */
			$diff_abs_seconds = intval(\DateTime::createFromFormat('U', '0')->add($diff_now)->format('U'));

			list($diff_minutes, $diff_seconds) = [intdiv($diff_abs_seconds, 60), ($diff_abs_seconds % 60)];
			list($diff_hours, $diff_minutes) = [intdiv($diff_minutes, 60), ($diff_minutes % 60)];

			$output['switch_ts'] = [
				'unix' => $switch_ts->format('U'),
				'friendly' => "{$diff_hours}h {$diff_minutes}m",
			];
		}

		// Fill in the currently switched in members, if any
		foreach ($pk_data['currentswitch']['members'] as $memberID) {
			$member = $pk_data['members'][$memberID];

			$output['fronters'][] = [
				'id' => $memberID,
				'name' => $member['name'],
				'display_name' => $member['display_name'],
				'color' => '#' . ($member['color'] ?? '000'),
				'pronouns' => $member['pronouns'],
				'avatar_url' => $member['avatar_url'],
			];
		}

		// And, return the JSON
		$response->getBody()->write((string) json_encode($output));
		$response = $response->withHeader('Content-Type', 'application/json');
		return $response;
	}
}
