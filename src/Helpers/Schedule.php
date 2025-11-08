<?php

namespace Pair\Helpers;

use Pair\Core\Logger;
use Pair\Exceptions\PairException;

class Schedule {

	/**
	 * Current DateTime.
	 */
	private \DateTime $now;

	/**
	 * Function to call.
	 * @var null|callable
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
	 */
	public function command(callable $functionToRun, mixed $params = null): self {

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
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	private function handle(): mixed {

		if (!$this->timeToRun or !$this->functionToRun) {
			return null;
		}

		return call_user_func($this->functionToRun, $this->params);

	}

	/**
	 * Run the task every minute.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyMinute(): mixed {

		$this->timeToRun = TRUE;

		return $this->handle();

	}

	/**
	 * Run the task every two minutes.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyTwoMinutes(): mixed {

		if (intval($this->now->format('i'))%2 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every three minutes.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyThreeMinutes(): mixed {

		if (intval($this->now->format('i'))%3 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every four minutes.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyFourMinutes(): mixed {

		if (intval($this->now->format('i'))%4 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every five minutes.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyFiveMinutes(): mixed {

		if (intval($this->now->format('i'))%5 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every ten minutes.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyTenMinutes(): mixed {

		if (intval($this->now->format('i'))%10 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every fifteen minutes.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyFifteenMinutes(): mixed {

		if (intval($this->now->format('i'))%15 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every twenty minutes.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyTwentyMinutes(): mixed {

		if (intval($this->now->format('i'))%20 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every thirty minutes.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyThirtyMinutes(): mixed {

		if (intval($this->now->format('i'))%30 == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every hour.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function hourly(): mixed {

		if (intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every hour at {$minutes} minutes past the hour.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function hourlyAt(int $minutes): mixed {

		if (intval($this->now->format('i')) == $minutes) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every two hours.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyTwoHours(): mixed {

		if (intval($this->now->format('H'))%2 == 0 and intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every three hours.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyThreeHours(): mixed {

		if (intval($this->now->format('H'))%3 == 0 and intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every four hours.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyFourHours(): mixed {

		if (intval($this->now->format('H'))%4 == 0 and intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every six hours.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everySixHours(): mixed {

		if (intval($this->now->format('H'))%6 == 0 and intval($this->now->format('i')) == 0) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every day at midnight.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function daily(): mixed {

		if ($this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}
	/**
	 * Run the task every day at noon.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function dailyAtNoon(): mixed {

		if ($this->now->format('H:i') == '12:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every day at {$time}.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function dailyAt(string $time): mixed {

		if ($this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task daily at {$time1} & {$time2}.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function twiceDaily(string $time1, string $time2): mixed {

		if (in_array($this->now->format('H:i'), [$time1, $time2])) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every Sunday at 00:00.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function weekly(): mixed {

		if (intval($this->now->format('w')) == 0 and $this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every {dayOfTheWeek} at {time}.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function weeklyOn(int $dayOfTheWeek, string $time): mixed {

		if (intval($this->now->format('w')) == $dayOfTheWeek and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the first day of every month at 00:00.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function monthly(): mixed {

		if (intval($this->now->format('d')) == 1 and $this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every month on the {$dayOfTheMonth} at {$time}.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function monthlyOn(int $dayOfTheMonth, string $time): mixed {

		if (intval($this->now->format('d')) == $dayOfTheMonth and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task monthly on the {$dayOfTheMonth1} and {$dayOfTheMonth2} at {$time}.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function twiceMonthly(int $dayOfTheMonth1, int $dayOfTheMonth2, string $time): mixed {

		if (in_array(intval($this->now->format('d')), [$dayOfTheMonth1,$dayOfTheMonth2]) and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the last day of the month at {$time}.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function lastDayOfMonth(string $time): mixed {

		if (intval($this->now->format('d')) == intval($this->now->format('t')) and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the first day of every quarter at 00:00.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function quarterly(): mixed {

		if ((intval($this->now->format('m'))-1) %3 == 0 and intval($this->now->format('d')) == 1 and $this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the first day of every year at 00:00.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function yearly(): mixed {

		if (intval($this->now->format('m')) == 1 and intval($this->now->format('d')) == 1 and $this->now->format('H:i') == '00:00') {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

	/**
	 * Run the task every year in {$month}, on day of month {$dayOfTheMonth1} at {$time}.
	 * 
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function yearlyOn(int $month, int $dayOfTheMonth, string $time): mixed {

		if (intval($this->now->format('m')) == $month and intval($this->now->format('d')) == $dayOfTheMonth and $this->now->format('H:i') == $time) {
			$this->timeToRun = TRUE;
		}

		return $this->handle();

	}

}