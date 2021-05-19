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
		/* Get the current PluralKit status
		 */

		$pk_data = PluralKitFetcher::from_env()->retrieve();
		if ($pk_data === null)
			throw new HttpInternalServerErrorException($request);

		/* Get our HtmlRender and the query values array.
		 */

		$html = $this->container->get('html');
		$query_values = (array) $request->getQueryParams();

		/* Get our status string
		 */

		$body_classes = empty($pk_data['currentswitch']['members']) ? 'asleep' : 'awake';
		$status = empty($pk_data['currentswitch']['members']) ? \L('status_switched_out') : \L('status_switched_in');
		if (boolval($_ENV[IX_ENVBASE . '_DISPLAY_AWAKE']) === true) {
			$status = empty($pk_data['currentswitch']['members']) ? \L('status_asleep') : \L('status_awake');
		}

		/* Render the front duration, if enabled
		 */
		$duration = [];
		if (array_key_exists('ts', $query_values) ? boolval($query_values['ts']) : boolval($_ENV[IX_ENVBASE . '_DISPLAY_TIME_SINCE'])) {
			$switch_key = 'awakeswitch';
			if (array_key_exists('tc', $query_values) && boolval($query_values['tc']))
				$switch_key = 'currentswitch';
			if ($pk_data['awakeswitch'] === null)
				$switch_key = 'currentswitch';

			$switch_ts = \DateTime::createFromFormat(
				\DateTimeInterface::RFC3339,
				preg_replace('#\.\d+#', '', $pk_data[$switch_key]['timestamp']),
			);

			if ($switch_ts !== false) {
				$diff_now = $switch_ts->diff(new \DateTime('now'));

				/** @phpstan-ignore-next-line */
				$diff_abs_seconds = intval(\DateTime::createFromFormat('U', '0')->add($diff_now)->format('U'));

				$display_duration = true;
				if (!(array_key_exists('ts', $query_values) && boolval($query_values['ts']))) {
					if (boolval($_ENV[IX_ENVBASE . '_DISPLAY_TIME_SINCE_GATED'])) {
						$threshold = intval($_ENV[IX_ENVBASE . '_DISPLAY_TIME_SINCE_THRESHOLD']);
						$display_duration = ($diff_abs_seconds >= $threshold);
					}
				}

				if ($display_duration) {
					list($diff_minutes, $diff_seconds) = [intdiv($diff_abs_seconds, 60), ($diff_abs_seconds % 60)];
					list($diff_hours, $diff_minutes) = [intdiv($diff_minutes, 60), ($diff_minutes % 60)];

					$duration[] = $html->tagHasChildren(
						'section',
						[
							'class' => 'duration',
						],
						...[
							\L('duration_before'),
							$html->tagHasChildren(
								'span',
								[
									'title' => $switch_ts->format(\DateTimeInterface::RFC3339),
								],
								" {$diff_hours}h {$diff_minutes}m ",
							),
							\L('duration_after')
						]
					);
				}
			}
		}

		/* Render the member cards, if enabled
		 */

		$member_cards = [];
		if (array_key_exists('fm', $query_values) ? boolval($query_values['fm']) : boolval($_ENV[IX_ENVBASE . '_DISPLAY_MEMBERS'])) {
			foreach ($pk_data['currentswitch']['members'] as $memberID) {
				$member = $pk_data['members'][$memberID];
				$member_color = '#' . ($member['color'] ?? '000');
				$member_cards[] = $html->tagHasChildren(
					'section',
					[
						'data-member-id' => $memberID,
						'class' => "member-card member-card--{$memberID}",
						'style' => "border-color:{$member_color};background-image:url({$member['avatar_url']})"
					],
					...[
						$html->tagHasChildren('h2', ['class' => 'member-card-title'], $member['display_name'] ?? $member['name']),
						$html->tagHasChildren('dl', [], ...[
							/* Pronouns */
							$html->tagHasChildren('dt', [], \L('field_pronouns')),
							$html->tagHasChildren('dd', [], $member['pronouns'] ?? \L('unknown')),
						]),
					]
				);
			}
		}

		/* Render the main document
		 */

		$response->getBody()->write($html->renderDocument(
			[
				$html->tag('meta', ['charset' => 'utf-8']),
				$html->tag('meta', ['name' => 'viewport', 'content' => 'initial-scale=1, width=device-width']),
				$html->tag('link', ['rel' => 'stylesheet', 'href' => '/styles.css']),
				$html->tagHasChildren('title', [], implode(" ", [$pk_data['system']['name'], \L('string_is_status', [$status])])),
			],
			[
				$html->tagHasChildren('main', ['data-system-id' => $pk_data['system']['id']], ...[
					/* Main text */
					$html->tagHasChildren('h1', ['class' => 'big-status'], ...[
						$html->tagHasChildren('span', [], $pk_data['system']['name']),
						$html->tagHasChildren('strong', ['class' => 'current-status'], \L('string_is_status', [$status])),
					]),

					/* Duration */
					$duration,

					/* Member cards */
					empty($member_cards) ? '' : $html->tagHasChildren('div', ['class' => 'member-cards'], ...$member_cards),

					/* Link to PKAwake repo */
					$html->tagHasChildren('footer', ['class' => 'pkawake-footer'], ...[
						$html->tagHasChildren('a', ['href' => 'https://github.com/u1f408/PKAwake', 'rel' => 'noopener noreferrer'], \L('powered_by')),
					]),
				]),
			],
			[],
			[
				'class' => $body_classes,
			]
		));

		return $response;
	}
}
