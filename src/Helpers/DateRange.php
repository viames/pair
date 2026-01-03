<?php

namespace Pair\Helpers;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

use Pair\Core\Application;

/**
 * Represents a half-open date range [start, end).
 * Provides utility methods for date range manipulation and querying.
 * @package Pair\Helpers
 */
final class DateRange {

	/**
	 * Construct a new DateRange instance expressing the interval [start, end).
	 *
	 * @param DateTimeImmutable $start Inclusive start date.
	 * @param DateTimeImmutable $end   Exclusive end date.
	 * @throws InvalidArgumentException if end is not greater than start.
	 */
	public function __construct(
		public readonly DateTimeImmutable $start,
		public readonly DateTimeImmutable $end,
	) {

		if ($end <= $start) {
			throw new InvalidArgumentException('End must be greater than start.');
		}

	}

	/**
	 * String representation useful for logs/debug.
	 *
	 * @return string
	 */
	public function __toString(): string {

		return $this->start->format(DateTimeInterface::ATOM) . ' .. ' . $this->end->format(DateTimeInterface::ATOM);

	}

	/**
	 * Check if the given DateTimeInterface is within the range.
	 *
	 * @param DateTimeInterface $dt The date to check.
	 * @return bool
	 */
	public function contains(DateTimeInterface $dt): bool {

		return $dt >= $this->start && $dt < $this->end;

	}

	/**
	 * Get a DateRange for the current month.
	 *
	 * @param DateTimeZone|null $tz Optional timezone (default: Europe/Rome).
	 * @return DateRange
	 */
	public static function currentMonth(?DateTimeZone $tz = null): self {

		$app = Application::getInstance();

		$tz ??= $app->currentUser ? $app->getDateTimeZone() : new DateTimeZone(BASE_TIMEZONE);
		$now = new DateTimeImmutable('now', $tz);

		return self::month((int) $now->format('Y'), (int) $now->format('n'), $tz);

	}

	/**
	 * Get the duration of the range as a DateInterval (end - start).
	 *
	 * @return DateInterval
	 */
	public function duration(): DateInterval {

		return $this->start->diff($this->end);

	}

	/**
	 * Only for convenience, returns the inclusive end date.
	 * Note: if you work with microseconds, consider that this subtracts one second.
	 *
	 * @return DateTimeImmutable
	 */
	public function endInclusive(): DateTimeImmutable {

		return $this->end->modify('-1 second');

	}

	/**
	 * Expand the range by subtracting/adding intervals.
	 *
	 * @param DateInterval|null $before If provided, it will be subtracted from start.
	 * @param DateInterval|null $after  If provided, it will be added to end.
	 * @return DateRange
	 */
	public function expand(?DateInterval $before = null, ?DateInterval $after = null): self {

		$start = $before !== null ? $this->start->sub($before) : $this->start;
		$end = $after !== null ? $this->end->add($after) : $this->end;

		return new self($start, $end);

	}

	/**
	 * Create a DateRange from any DateTimeInterface values.
	 * The returned range always uses DateTimeImmutable instances.
	 *
	 * @param DateTimeInterface $start Inclusive start date.
	 * @param DateTimeInterface $end   Exclusive end date.
	 * @return DateRange
	 */
	public static function from(DateTimeInterface $start, DateTimeInterface $end): self {

	return new self(
		DateTimeImmutable::createFromInterface($start),
		DateTimeImmutable::createFromInterface($end),
	);

	}

	/**
	 * Create a DateRange from an inclusive end date.
	 *
	 * This is mainly useful when you have an UI concept like "end of month 23:59:59".
	 * Internally it will be converted to an exclusive end by adding the given padding.
	 *
	 * Important: this method is NOT suitable if you work with sub-second precision.
	 * In that case, prefer half-open ranges ([start, end)) and avoid inclusive ends.
	 *
	 * @param DateTimeInterface $start         Inclusive start date.
	 * @param DateTimeInterface $endInclusive  Inclusive end date.
	 * @param string            $padding       The padding to add (default: "+1 second").
	 * @return DateRange
	 */
	public static function fromInclusiveEnd(
		DateTimeInterface $start,
		DateTimeInterface $endInclusive,
		string $padding = '+1 second',
	): self {

		$startImmutable = DateTimeImmutable::createFromInterface($start);
		$endExclusive = DateTimeImmutable::createFromInterface($endInclusive)->modify($padding);

		return new self($startImmutable, $endExclusive);

	}

	/**
	 * Get the intersection of this range with another range.
	 *
	 * @param DateRange $other
	 * @return DateRange|null Returns null if the ranges do not overlap.
	 */
	public function intersection(self $other): ?self {

		if (!$this->overlaps($other)) {
			return null;
		}

		$start = $this->start >= $other->start ? $this->start : $other->start;
		$end = $this->end <= $other->end ? $this->end : $other->end;

		return new self($start, $end);

	}

	/**
	 * Get the range length in seconds.
	 *
	 * @return int
	 */
	public function lengthInSeconds(): int {

		return $this->end->getTimestamp() - $this->start->getTimestamp();

	}

	/**
	 * Create a DateRange for the given month.
	 *
	 * @param int $year  The year (e.g., 2024).
	 * @param int $month The month (1-12).
	 * @param DateTimeZone|null $tz Optional timezone (default: Europe/Rome).
	 * @return DateRange
	 */
	public static function month(int $year, int $month, ?DateTimeZone $tz = null): self {

		$tz ??= new DateTimeZone('Europe/Rome'); // o UTC, se preferisci

		// start: first day of month 00:00:00
		$start = (new DateTimeImmutable('now', $tz))
			->setDate($year, $month, 1)
			->setTime(0, 0, 0);

		// end: first day of next month 00:00:00
		$end = $start->modify('first day of next month');

		return new self($start, $end);

	}

	/**
	 * Create a DateRange for the month containing the given date.
	 *
	 * @param DateTimeInterface $date The reference date.
	 * @param DateTimeZone|null $tz   Optional timezone to normalize the date.
	 * @return DateRange
	 */
	public static function monthOf(DateTimeInterface $date, ?DateTimeZone $tz = null): self {

		$dt = DateTimeImmutable::createFromInterface($date);

		if ($tz !== null) {
			$dt = $dt->setTimezone($tz);
		}

		return self::month((int) $dt->format('Y'), (int) $dt->format('n'), $tz ?? $dt->getTimezone());

	}

	/**
	 * Get the next month range (based on this range start).
	 *
	 * @return DateRange
	 */
	public function nextMonth(): self {

		$start = $this->start->modify('first day of next month')->setTime(0, 0, 0);
		$end = $start->modify('first day of next month');

		return new self($start, $end);

	}

	/**
	 * Return true if this range overlaps another range.
	 *
	 * @param DateRange $other
	 * @return bool
	 */
	public function overlaps(self $other): bool {

		return $this->start < $other->end && $this->end > $other->start;

	}

	/**
	 * Get the previous month range (based on this range start).
	 *
	 * @return DateRange
	 */
	public function previousMonth(): self {

		$start = $this->start->modify('first day of previous month')->setTime(0, 0, 0);
		$end = $start->modify('first day of next month');

		return new self($start, $end);

	}

	/**
	 * Shift the whole range by a DateInterval.
	 *
	 * @param DateInterval $delta
	 * @return DateRange
	 */
	public function shift(DateInterval $delta): self {

		return new self(
			$this->start->add($delta),
			$this->end->add($delta),
		);

	}

	/**
	 * Shift the whole range using DateTimeImmutable::modify() syntax.
	 * Examples: "+1 day", "-1 month", "+2 hours".
	 *
	 * @param string $modifier
	 * @return DateRange
	 */
	public function shiftBy(string $modifier): self {

		return new self(
			$this->start->modify($modifier),
			$this->end->modify($modifier),
		);

	}

	/**
	 * Convert this range into an array.
	 *
	 * @param string $format Date format used for both dates (default: Y-m-d H:i:s).
	 * @return array{start:string,end:string}
	 */
	public function toArray(string $format = 'Y-m-d H:i:s'): array {

		return [
			'start' => $this->start->format($format),
			'end' => $this->end->format($format),
		];

	}

	/**
	 * Create a DatePeriod to iterate over the range with the given step.
	 *
	 * @param DateInterval $step    The step interval (e.g., P1D for days).
	 * @param int          $options Optional DatePeriod options.
	 * @return DatePeriod
	 */
	public function toPeriod(DateInterval $step, int $options = 0): DatePeriod {

		// useful for iterating over days, months, etc.
		return new DatePeriod($this->start, $step, $this->end, $options);

	}

	/**
	 * Build parameters for SQL queries.
	 * Returns an associative array like ['start' => '...', 'end' => '...'].
	 *
	 * @param string $startKey The start parameter key.
	 * @param string $endKey   The end parameter key.
	 * @param string $format   Date format (default: Y-m-d H:i:s).
	 * @return array<string,string>
	 */
	public function toSqlParams(string $startKey = 'start', string $endKey = 'end', string $format = 'Y-m-d H:i:s'): array {

		return [
			$startKey => $this->start->format($format),
			$endKey => $this->end->format($format),
		];

	}

	/**
	 * Build a SQL WHERE snippet for half-open ranges.
	 * Example output: "created_at >= :start AND created_at < :end".
	 *
	 * @param string $column     The datetime column name.
	 * @param string $startParam The start placeholder.
	 * @param string $endParam   The end placeholder.
	 * @return string
	 */
	public function toSqlWhere(string $column, string $startParam = ':start', string $endParam = ':end'): string {

		return $column . ' >= ' . $startParam . ' AND ' . $column . ' < ' . $endParam;

	}

}