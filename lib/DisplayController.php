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

		/* Get our HTML title
		 */

		$title = empty($pk_data['switch']['members']) ? \L('status_switched_out') : \L('status_switched_in');
		if (boolval($_ENV[IX_ENVBASE . '_DISPLAY_AWAKE']) === true) {
			$title = empty($pk_data['switch']['members']) ? \L('status_asleep') : \L('status_awake');
		}

		/* Pls can has HtmlRenderer?
		 */

		$html = $this->container->get('html');

		/* Render the front duration, if enabled
		 */
		$duration = [];
		if (boolval($_ENV[IX_ENVBASE . '_DISPLAY_TIME_SINCE'])) {
			$switch_ts = \DateTime::createFromFormat(
				\DateTimeInterface::RFC3339,
				preg_replace('#\.\d+#', '', $pk_data['switch']['timestamp']),
			);
			
			if ($switch_ts !== false) {
				$diff_now = $switch_ts->diff(new \DateTime('now'));

				/** @phpstan-ignore-next-line */
				$diff_abs_seconds = intval(\DateTime::createFromFormat('U', '0')->add($diff_now)->format('U'));

				$display_duration = true;
				if (boolval($_ENV[IX_ENVBASE . '_DISPLAY_TIME_SINCE_GATED'])) {
					$threshold = intval($_ENV[IX_ENVBASE . '_DISPLAY_TIME_SINCE_THRESHOLD']);
					$display_duration = ($diff_abs_seconds >= $threshold);
				}

				if ($display_duration) {
					/* XXX: Fix this later */
					$diff_hours = $diff_now->h
						+ ($diff_now->d * 24)
						+ ($diff_now->m * 24 * 30)
						+ ($diff_now->y * 24 * 365);

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
								...[
									"{$diff_hours}h ",
									"{$diff_now->i}m ",
								]
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
		if (boolval($_ENV[IX_ENVBASE . '_DISPLAY_MEMBERS'])) {
			foreach ($pk_data['switch']['members'] as $memberID) {
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
				$html->tagHasChildren('title', [], $title),
			],
			[
				$html->tagHasChildren('main', ['data-system-id' => $pk_data['system']['id']], ...[
					/* Main text */
					$html->tagHasChildren('h1', ['class' => 'big-status'], ...[
						$html->tagHasChildren('span', [], \L('string_system_is', [$pk_data['system']['name']])),
						$html->tagHasChildren('strong', ['class' => 'current-status'], $title),
					]),

					/* Duration */
					$duration,

					/* Member cards */
					empty($member_cards) ? '' : $html->tagHasChildren('div', ['class' => 'member-cards'], ...$member_cards),
				]),
			],
		));

		return $response;
	}
}
