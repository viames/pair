<?php

namespace Pair\Helpers;

class Schedule {

	/**
	 * Current DateTime.
	 */
	private \DateTime $now;

	/**
	 * Function to call.
	 * @var null|callable
	 */
	private $functionToRun = null;

	/**
	 * Callable function's arguments.
	 */
	private mixed $args;

	/**
	 * Flag to enable action execution.
	 */
	private bool $timeToRun = false;

	public function __construct() {

		$this->now = new \DateTime();

	}

	/**
	 * Assigns a function to be called later according to the scheduling methods.
	 *
	 * @param	callable	$functionToRun	The function to be called.
	 * @param	mixed|null	$args			The arguments to pass to the function.
	 * @return	self						The current Schedule instance.
	 */
	public function command(callable $functionToRun, mixed $args = null): self {

		$this->functionToRun = $functionToRun;
		$this->args = $args;
		$this->timeToRun = false; // reset flag

		return $this;

	}

	/**
	 * Handles the execution of the assigned function if the timeToRun flag is set.
	 *
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	private function handle(): mixed {

		if (!$this->timeToRun or !$this->functionToRun) {
			return null;
		}

		// optional: reset the function and timeToRun flag after execution
        $result = call_user_func($this->functionToRun, $this->args);
        $this->functionToRun = null;
        $this->timeToRun     = false;

		return $result;

	}

	/**
	 * Run the task every minute.
	 *
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyMinute(): mixed {

		$this->timeToRun = true;

		return $this->handle();

	}

	/**
	 * Run the task every two minutes.
	 *
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyTwoMinutes(): mixed {

		$minute = (int) $this->now->format('i');

		if (0 === $minute % 2) {
			$this->timeToRun = true;
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
			$this->timeToRun = true;
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
			$this->timeToRun = true;
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
			$this->timeToRun = true;
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
			$this->timeToRun = true;
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
			$this->timeToRun = true;
		}

		return $this->handle();

	}

	/**
	 * Run the task every twenty minutes.
	 *
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function everyTwentyMinutes(): mixed {

		$minute = (int) $this->now->format('i');

		if (0 === $minute % 20) {
			$this->timeToRun = true;
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
			$this->timeToRun = true;
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
			$this->timeToRun = true;
		}

		return $this->handle();

	}

	/**
	 * Run the task every hour at {$minutes} minutes past the hour.
	 *
	 * @param	int	$minutes	Minutes past the hour (0-59).
	 * @return	mixed			The return value of the called function, or null if not executed.
	 */
	public function hourlyAt(int $minutes): mixed {

		// ensure minutes format is MM
		$minutes = $minutes % 60;
		$minutes = sprintf('%02d', $minutes);

		if (intval($this->now->format('i')) == $minutes) {
			$this->timeToRun = true;
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
			$this->timeToRun = true;
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
			$this->timeToRun = true;
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
			$this->timeToRun = true;
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
			$this->timeToRun = true;
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
			$this->timeToRun = true;
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
			$this->timeToRun = true;
		}

		return $this->handle();

	}

	/**
	 * Run the task every day at {$time}.
	 *
	 * @param	string	$time	Time in HH:MM format.
	 * @return	mixed			The return value of the called function, or null if not executed.
	 */
	public function dailyAt(string $time): mixed {

		// ensure time format is HH:MM
		$time = sprintf('%05s', $time);

		if ($this->now->format('H:i') == $time) {
			$this->timeToRun = true;
		}

		return $this->handle();

	}

	/**
	 * Run the task daily at {$time1} & {$time2}.
	 *
	 * @param	string	$time1	Time in HH:MM format.
	 * @param	string	$time2	Time in HH:MM format.
	 * @return	mixed			The return value of the called function, or null if not executed.
	 */
	public function twiceDaily(string $time1, string $time2): mixed {

		// ensure time format is HH:MM
		$time1 = sprintf('%05s', $time1);
		$time2 = sprintf('%05s', $time2);

		if (in_array($this->now->format('H:i'), [$time1, $time2])) {
			$this->timeToRun = true;
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
			$this->timeToRun = true;
		}

		return $this->handle();

	}

	/**
	 * Run the task every {dayOfTheWeek} at {time}.
	 *
	 * @param	int		$dayOfTheWeek	Day of the week (0 for Sunday, 6 for Saturday).
	 * @param	string	$time			Time in HH:MM format.
	 * @return	mixed					The return value of the called function, or null if not executed.
	 */
	public function weeklyOn(int $dayOfTheWeek, string $time): mixed {

		// ensure time format is HH:MM
		$time = sprintf('%05s', $time);

		if (intval($this->now->format('w')) == $dayOfTheWeek and $this->now->format('H:i') == $time) {
			$this->timeToRun = true;
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
			$this->timeToRun = true;
		}

		return $this->handle();

	}

	/**
	 * Run the task every month on the {$dayOfTheMonth} at {$time}.
	 *
	 * @param	int		$dayOfTheMonth	Day of the month (1-31).
	 * @param	string	$time			Time in HH:MM format.
	 * @return	mixed					The return value of the called function, or null if not executed.
	 */
	public function monthlyOn(int $dayOfTheMonth, string $time): mixed {

		// ensure time format is HH:MM
		$time = sprintf('%05s', $time);

		if (intval($this->now->format('d')) == $dayOfTheMonth and $this->now->format('H:i') == $time) {
			$this->timeToRun = true;
		}

		return $this->handle();

	}

	/**
	 * Run the task monthly on the {$dayOfTheMonth1} and {$dayOfTheMonth2} at {$time}.
	 *
	 * @param	int		$dayOfTheMonth1	First day of the month (1-31).
	 * @param	int		$dayOfTheMonth2	Second day of the month (1-31).
	 * @param	string	$time			Time in HH:MM format.
	 * @return	mixed					The return value of the called function, or null if not executed.
	 */
	public function twiceMonthly(int $dayOfTheMonth1, int $dayOfTheMonth2, string $time): mixed {

		// ensure time format is HH:MM
		$time = sprintf('%05s', $time);

		if (in_array(intval($this->now->format('d')), [$dayOfTheMonth1,$dayOfTheMonth2]) and $this->now->format('H:i') == $time) {
			$this->timeToRun = true;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the last day of the month at {$time}.
	 *
	 * @param	string	$time	Time in HH:MM format.
	 * @return	mixed			The return value of the called function, or null if not executed.
	 */
	public function lastDayOfMonth(string $time): mixed {

		// ensure time format is HH:MM
		$time = sprintf('%05s', $time);

		if (intval($this->now->format('d')) == intval($this->now->format('t')) and $this->now->format('H:i') == $time) {
			$this->timeToRun = true;
		}

		return $this->handle();

	}

	/**
	 * Run the task on the first day of every quarter at 00:00.
	 *
	 * @return mixed The return value of the called function, or null if not executed.
	 */
	public function quarterly(): mixed {

		if (in_array($this->now->format('n'), [1,4,7,10]) and intval($this->now->format('d')) == 1 and $this->now->format('H:i') == '00:00') {
			$this->timeToRun = true;
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
			$this->timeToRun = true;
		}

		return $this->handle();

	}

	/**
	 * Run the task every year in {$month}, on day of month {$dayOfTheMonth1} at {$time}.
	 *
	 * @param	int		$month			Month of the year (1-12).
	 * @param	int		$dayOfTheMonth	Day of the month (1-31).
	 * @param	string	$time			Time in HH:MM format.
	 * @return	mixed					The return value of the called function, or null if not executed.
	 */
	public function yearlyOn(int $month, int $dayOfTheMonth, string $time): mixed {

		// ensure time format is HH:MM
		$time = sprintf('%05s', $time);

		if (intval($this->now->format('m')) == $month and intval($this->now->format('d')) == $dayOfTheMonth and $this->now->format('H:i') == $time) {
			$this->timeToRun = true;
		}

		return $this->handle();

	}

}