<?php

namespace Pair\Support;

class Schedule {

	/**
	 * Current DateTime.
	 */
	private \DateTime $now;

	/**
	 * Function to call.
	 * @var NULL|callable
	 */
	private $functionToRun;

	/**
	 * Callable function's parameters (optional).
	 */
	private mixed $params;

	/**
	 * Flag to enable action execution.
	 */
	private bool $timeToRun = FALSE;

	public function __construct() {

		$this->now = new \DateTime();

	}

	/**
	 * Assign the method to be launched and any parameters to the object.
	 *
	 * @param callable $functionToRun
	 * @param mixed|null $params
	 * @return Schedule
	 */
	public function command(callable $functionToRun, mixed $params=NULL): self {

		$this->params = $params;

		// reset the value for next calls on the same instance object
		$this->timeToRun = FALSE;

		if (is_callable($functionToRun)) {
			$this->functionToRun = $functionToRun;
		}

		return $this;

	}

	/**
	 * Run the required user function.
	 */
	private function handle(): bool {

		if (!$this->timeToRun or !$this->functionToRun) {
			return FALSE;
		}

		try {

			$result = call_user_func($this->functionToRun, $this->params);

		} catch (\Exception $e) {

			return FALSE;

		}

		return (bool)$result;

	}

	/**
	 * Run the task every minute.
	 */
	public function everyMinute(): bool {

		$this->timeToRun = TRUE;

		return $this->handle();

	}

	/**
	 * Run the task every two minutes.
	 */
	public function everyTwoMinutes(): bool {

		if (intval($this->now->format('i'))%2 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every three minutes.
	 */
	public function everyThreeMinutes(): bool {

		if (intval($this->now->format('i'))%3 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every four minutes.
	 */
	public function everyFourMinutes(): bool {

		if (intval($this->now->format('i'))%4 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every five minutes.
	 */
	public function everyFiveMinutes(): bool {

		if (intval($this->now->format('i'))%5 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every ten minutes.
	 */
	public function everyTenMinutes(): bool {

		if (intval($this->now->format('i'))%10 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every fifteen minutes.
	 */
	public function everyFifteenMinutes(): bool {

		if (intval($this->now->format('i'))%15 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every thirty minutes.
	 */
	public function everyThirtyMinutes(): bool {

		if (intval($this->now->format('i'))%30 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every hour.
	 */
	public function hourly(): bool {

		if (intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every hour at {$minutes} minutes past the hour.
	 */
	public function hourlyAt(int $minutes): bool {

		if (intval($this->now->format('i')) == $minutes) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every two hours.
	 */
	public function everyTwoHours(): bool {

		if (intval($this->now->format('H'))%2 == 0 and intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every three hours.
	 */
	public function everyThreeHours(): bool {

		if (intval($this->now->format('H'))%3 == 0 and intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every four hours.
	 */
	public function everyFourHours(): bool {

		if (intval($this->now->format('H'))%4 == 0 and intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every six hours.
	 */
	public function everySixHours(): bool {

		if (intval($this->now->format('H'))%6 == 0 and intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every day at midnight.
	 */
	public function daily(): bool {

		if ($this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every day at {$time}.
	 */
	public function dailyAt(string $time): bool {

		if ($this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task daily at {$time1} & {$time2}.
	 */
	public function twiceDaily(string $time1, string $time2): bool {

		if (in_array($this->now->format('H:i'), [$time1, $time2])) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every Sunday at 00:00.
	 */
	public function weekly(): bool {

		if (intval($this->now->format('w')) == 0 and $this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every {dayOfTheWeek} at {time}.
	 */
	public function weeklyOn(int $dayOfTheWeek, string $time): bool {

		if (intval($this->now->format('w')) == $dayOfTheWeek and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the first day of every month at 00:00.
	 */
	public function monthly(): bool {

		if (intval($this->now->format('d')) == 1 and $this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every month on the {$dayOfTheMonth} at {$time}.
	 */
	public function monthlyOn(int $dayOfTheMonth, string $time): bool {

		if (intval($this->now->format('d')) == $dayOfTheMonth and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task monthly on the {$dayOfTheMonth1} and {$dayOfTheMonth2} at {$time}.
	 */
	public function twiceMonthly(int $dayOfTheMonth1, int $dayOfTheMonth2, string $time): bool {

		if (in_array(intval($this->now->format('d')), [$dayOfTheMonth1,$dayOfTheMonth2]) and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the last day of the month at {$time}.
	 */
	public function lastDayOfMonth(string $time): bool {

		if (intval($this->now->format('d')) == intval($this->now->format('t')) and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the first day of every quarter at 00:00.
	 */
	public function quarterly(): bool {

		if ((intval($this->now->format('m'))-1) %3 == 0 and intval($this->now->format('d')) == 1 and $this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the first day of every year at 00:00.
	 */
	public function yearly(): bool {

		if (intval($this->now->format('m')) == 1 and intval($this->now->format('d')) == 1 and $this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every year in {$month}, on day of month {$dayOfTheMonth1} at {$time}.
	 */
	public function yearlyOn(int $month, int $dayOfTheMonth, string $time): bool {

		if (intval($this->now->format('m')) == $month and intval($this->now->format('d')) == $dayOfTheMonth and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

}