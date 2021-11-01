<?php
namespace PKAwake;

use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

class TwigExtension extends AbstractExtension {
	public function getName(): string {
		return 'pkawake';
	}

	/**
	* @return TwigFunction[]
	*/
	public function getFunctions(): array {
		return [
			new TwigFunction('L', function($key, ...$args) {
				return \L($key, $args);
			}),
		];
	}
}
