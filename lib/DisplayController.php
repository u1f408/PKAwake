<?php
declare(strict_types=1);
namespace PKAwake;

use ix\Controller\Controller;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Exception\HttpInternalServerErrorException;
use PKAwake\PluralKitFetcher;

class DisplayController extends Controller {
	/**
	 * @param Request $request The Request object
	 * @param Response $response The Response object
	 * @param mixed[] $args Arguments passed from the router (if any)
	 * @return Response The resulting Response object
	 */
	public function requestGET(Request $request, Response $response, ?array $args = []): Response {
		$routeArgs = $request->getAttribute('__routingResults__')->getRouteArguments();

		/* Get our Twig renderer and the query values array.
		 */

		$twig = $this->container->get('view');
		$query_values = (array) $request->getQueryParams();

		/* Get the current PluralKit status
		 */

		$systemID = $_ENV[IX_ENVBASE . '_PLURALKIT_SYSTEM'];
		if (array_key_exists('system', $routeArgs))
			$systemID = $routeArgs['system'];

		$pk_data = (new PluralKitFetcher($systemID))->retrieve();
		if ($pk_data === null) {
			$twig->render($response, 'pluralkit_error.html.twig');
			return $response->withStatus(500);
		}

		/* Render the front duration
		 */

		$display_duration = boolval($_ENV[IX_ENVBASE . '_DISPLAY_TIME_SINCE']);
		if (array_key_exists('ts', $query_values))
			$display_duration = boolval($query_values['ts']);

		$switch_ts = $switch_rel = null;
		if ($display_duration) {
			$switch_key = 'awakeswitch';
			if (array_key_exists('tc', $query_values) && boolval($query_values['tc']))
				$switch_key = 'currentswitch';
			if ($pk_data['awakeswitch'] === null)
				$switch_key = 'currentswitch';

			$switch_ts = \DateTime::createFromFormat(
				\DateTimeInterface::RFC3339,
				preg_replace('#\.\d+#', '', $pk_data[$switch_key]['timestamp']),
			);

			$switch_rel = null;
			if ($switch_ts !== false) {
				$diff_now = $switch_ts->diff(new \DateTime('now'));

				/** @phpstan-ignore-next-line */
				$diff_abs_seconds = intval(\DateTime::createFromFormat('U', '0')->add($diff_now)->format('U'));

				list($diff_minutes, $diff_seconds) = [intdiv($diff_abs_seconds, 60), ($diff_abs_seconds % 60)];
				list($diff_hours, $diff_minutes) = [intdiv($diff_minutes, 60), ($diff_minutes % 60)];

				$switch_rel = "{$diff_hours}h {$diff_minutes}m";
			}
		}

		/* Choose whether to render member cards
		 */

		$display_members = boolval($_ENV[IX_ENVBASE . '_DISPLAY_MEMBERS']);
		if (array_key_exists('fm', $query_values))
			$display_members = boolval($query_values['fm']);

		$display_members_dn = boolval($_ENV[IX_ENVBASE . '_DISPLAY_MEMBERS_DISPLAY_NAME']);
		if (array_key_exists('dn', $query_values))
			$display_members_dn = boolval($query_values['dn']);

		/* Pick up some other settings from either the environment or
		 * query values
		 */

		$display_awake = boolval($_ENV[IX_ENVBASE . '_DISPLAY_AWAKE']);
		if (array_key_exists('da', $query_values))
			$display_awake = boolval($query_values['da']);

		/* Render the main document
		 */

		$body_classes = $status_internal = empty($pk_data['currentswitch']['members']) ? 'asleep' : 'awake';
		$status = empty($pk_data['currentswitch']['members']) ? \L('status_switched_out') : \L('status_switched_in');
		if ($display_awake)
			$status = empty($pk_data['currentswitch']['members']) ? \L('status_asleep') : \L('status_awake');

		$twig->render($response, 'index.html.twig', [
			'body_classes' => $body_classes,
			'status' => $status,
			'status_internal' => $status_internal,
			'pk_data' => $pk_data,
			'switch_ts' => $switch_ts,
			'switch_rel' => $switch_rel,
			'display_awake' => $display_awake,
			'display_duration' => $display_duration,
			'display_members' => $display_members,
			'display_members_dn' => $display_members_dn,
		]);

		return $response;
	}
}
