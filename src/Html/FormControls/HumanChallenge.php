<?php

declare(strict_types=1);

namespace Pair\Html\FormControls;

use Pair\Html\FormControl;
use Pair\Services\HumanChallenge as HumanChallengeService;

/**
 * Renders the configured human challenge widget inside a Pair form.
 */
class HumanChallenge extends FormControl {

	/**
	 * Provider-neutral service used to render the widget.
	 */
	private HumanChallengeService $challenge;

	/**
	 * Provider widget options.
	 *
	 * @var	array<string, mixed>
	 */
	private array $options = [];

	/**
	 * Build a human challenge control with an optional injected service.
	 */
	public function __construct(string $name = 'human_challenge', array $attributes = [], ?HumanChallengeService $challenge = null) {

		parent::__construct($name, $attributes);

		$this->challenge = $challenge ?? new HumanChallengeService();

	}

	/**
	 * Set the provider action attached to the rendered widget.
	 */
	public function action(string $action): static {

		$this->options['action'] = $action;
		return $this;

	}

	/**
	 * Add a single provider widget option.
	 */
	public function option(string $name, mixed $value): static {

		$this->options[$name] = $value;
		return $this;

	}

	/**
	 * Add multiple provider widget options.
	 *
	 * @param	array<string, mixed>	$options
	 */
	public function options(array $options): static {

		foreach ($options as $name => $value) {
			$this->option((string)$name, $value);
		}

		return $this;

	}

	/**
	 * Render the configured human challenge widget.
	 */
	public function render(): string {

		$options = array_merge($this->attributes, $this->options);

		if ($this->id) {
			$options['id'] = $this->id;
		}

		if (count($this->class)) {
			$options['class'] = trim(implode(' ', $this->class) . ' ' . (string)($options['class'] ?? ''));
		}

		return $this->challenge->widgetHtml($options);

	}

	/**
	 * Server-side validation must be handled by the controller before accepting the write.
	 */
	public function validate(): bool {

		return true;

	}

}
