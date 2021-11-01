<?php
declare(strict_types=1);
namespace PKAwake;

use ix\Container\Container;
use Slim\Views\Twig;
use Twig\Extra\Intl\IntlExtension;

final class ContainerHooksTwig {
	/**
	 * @param string[] $key Hook key (unused)
	 * @param Container $container The Container instance
	 * @return Container The Container instance
	 */
	public static function hookContainerTwig(array $key, Container $container): Container {
		$container->set('view', function() {
			$twig = Twig::create(
				array_filter([IX_BASE . '/templates']),
				[
					'cache' => IX_BASE . '/cache/templates',
					'debug' => in_array($_ENV['APP_ENV'], ['development', 'test']),
				],
			);

			$twig->addExtension(new \PKAwake\TwigExtension());
			$twig->addExtension(new IntlExtension());
			return $twig;
		});

		return $container;
	}
}
