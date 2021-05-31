<?php

namespace Pair;

class Schedule {

	/**
	 * Current DateTime.
	 * @var DateTime;
	 */
	private $now;

	/**
	 * Function to call.
	 * @var NULL|callable
	 */
	private $functionToRun;

	/**
	 * Callable function's parameters (optional).
	 * @var mixed
	 */
	private $params;

	/**
	 * Flag to enable action execution.
	 * @var bool
	 */
	private $timeToRun = FALSE;

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
	public function command(callable $functionToRun, $params=NULL): self {

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
	 * @return bool
	 */
	private function handle(): bool {

		if (!$this->timeToRun or !$this->functionToRun) {
			return FALSE;
		}

		if (FALSE === call_user_func($this->functionToRun, $this->params)) {
			return FALSE;
		}

		return TRUE;

	}

	/**
	 * Run the task every minute.
	 * @return bool 
	 */
	public function everyMinute(): bool {

		$this->timeToRun = TRUE;

		return $this->handle();

	}

	/**
	 * Run the task every two minutes.
	 * @return bool 
	 */
	public function everyTwoMinutes(): bool {

		if (intval($this->now->format('i'))%2 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every three minutes.
	 * @return bool 
	 */
	public function everyThreeMinutes(): bool {

		if (intval($this->now->format('i'))%3 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every four minutes.
	 * @return bool 
	 */
	public function everyFourMinutes(): bool {

		if (intval($this->now->format('i'))%4 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every five minutes.
	 * @return bool 
	 */
	public function everyFiveMinutes(): bool {

		if (intval($this->now->format('i'))%5 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every ten minutes.
	 * @return bool 
	 */
	public function everyTenMinutes(): bool {

		if (intval($this->now->format('i'))%10 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every fifteen minutes.
	 * @return bool 
	 */
	public function everyFifteenMinutes(): bool {

		if (intval($this->now->format('i'))%15 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every thirty minutes.
	 * @return bool 
	 */
	public function everyThirtyMinutes(): bool {

		if (intval($this->now->format('i'))%30 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every hour.
	 * @return bool 
	 */
	public function hourly(): bool {

		if (intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every hour at {$minutes} minutes past the hour.
	 * @param int $minutes 
	 * @return bool 
	 */
	public function hourlyAt(int $minutes): bool {

		if (intval($this->now->format('i')) == $minutes) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every two hours.
	 * @return bool 
	 */
	public function everyTwoHours(): bool {

		if (intval($this->now->format('H'))%2 == 0 and intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every three hours.
	 * @return bool 
	 */
	public function everyThreeHours(): bool {

		if (intval($this->now->format('H'))%3 == 0 and intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every four hours.
	 * @return bool 
	 */
	public function everyFourHours(): bool {

		if (intval($this->now->format('H'))%4 == 0 and intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every six hours.
	 * @return bool 
	 */
	public function everySixHours(): bool {

		if (intval($this->now->format('H'))%6 == 0 and intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every day at midnight.
	 * @return bool 
	 */
	public function daily(): bool {

		if ($this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every day at {$time}.
	 * @param string $time 
	 * @return bool 
	 */
	public function dailyAt(string $time): bool {

		if ($this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task daily at {$time1} & {$time2}.
	 * @param string $time1 
	 * @param string $time2 
	 * @return bool 
	 */
	public function twiceDaily(string $time1, string $time2): bool {

		if (in_array($this->now->format('H:i'), [$time1, $time2])) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every Sunday at 00:00.
	 * @return bool 
	 */
	public function weekly(): bool {

		if (intval($this->now->format('w')) == 0 and $this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every {dayOfTheWeek} at {time}.
	 * @param int $dayOfTheWeek 
	 * @param string $time 
	 * @return bool 
	 */
	public function weeklyOn(int $dayOfTheWeek, string $time): bool {

		if (intval($this->now->format('w')) == $dayOfTheWeek and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the first day of every month at 00:00.
	 * @return bool 
	 */
	public function monthly(): bool {

		if (intval($this->now->format('d')) == 1 and $this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every month on the {$dayOfTheMonth} at {$time}.
	 * @param int $dayOfTheMonth 
	 * @param string $time 
	 * @return bool 
	 */
	public function monthlyOn(int $dayOfTheMonth, string $time): bool {

		if (intval($this->now->format('d')) == $dayOfTheMonth and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task monthly on the {$dayOfTheMonth1} and {$dayOfTheMonth2} at {$time}.
	 * @param int $dayOfTheMonth1 
	 * @param int $dayOfTheMonth2 
	 * @param string $time 
	 * @return bool 
	 */
	public function twiceMonthly(int $dayOfTheMonth1, int $dayOfTheMonth2, string $time): bool {

		if (in_array(intval($this->now->format('d')), [$dayOfTheMonth1,$dayOfTheMonth2]) and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the last day of the month at {$time}.
	 * @param string $time
	 * @return bool
	 */
	public function lastDayOfMonth(string $time): bool {

		if (intval($this->now->format('d')) == intval($this->now->format('t')) and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the first day of every quarter at 00:00.
	 * @return bool
	 */
	public function quarterly(): bool {

		if ((intval($this->now->format('m'))-1) %3 == 0 and intval($this->now->format('d')) == 1 and $this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the first day of every year at 00:00.
	 * @return bool 
	 */
	public function yearly(): bool {

		if (intval($this->now->format('m')) == 1 and intval($this->now->format('d')) == 1 and $this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every year in {$month}, on day of month {$dayOfTheMonth1} at {$time}.
	 * @param int $month 
	 * @param int $dayOfTheMonth 
	 * @param string $time 
	 * @return bool 
	 */
	public function yearlyOn(int $month, int $dayOfTheMonth, string $time): bool {

		if (intval($this->now->format('m')) == $month and intval($this->now->format('d')) == $dayOfTheMonth and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

}