<?php

namespace Pair\Helpers;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use Throwable;

use Pair\Core\Application;

/**
 * Represents a half-open date range [start, end).
 * This convention avoids off-by-one issues (e.g. 23:59:59) and works well with SQL filters:
 * `>= start AND < end`.
 */
final class DateRange {

	public readonly DateTimeImmutable $start;
	public readonly DateTimeImmutable $end;

	/**
	 * Construct a new DateRange instance expressing the interval [start, end).
	 *
	 * @param DateTimeImmutable $start Inclusive start date.
	 * @param DateTimeImmutable $end   Exclusive end date.
	 * @throws InvalidArgumentException If end is not greater than start.
	 */
	public function __construct(DateTimeInterface $start, DateTimeInterface $end) {

		$this->start = DateTimeImmutable::createFromInterface($start);
		$this->end = DateTimeImmutable::createFromInterface($end);

		if ($this->end <= $this->start) {
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
	 * Clamp a date into the range.
	 * If the date is before start, start is returned.
	 * If the date is after (or equal to) end, endInclusive() is returned.
	 *
	 * Note: this is meant for UI convenience. For strict comparisons, use contains().
	 */
	public function clamp(DateTimeInterface $dt): DateTimeImmutable {

		$imm = DateTimeImmutable::createFromInterface($dt);

		if ($imm < $this->start) {
			return $this->start;
		}

		if ($imm >= $this->end) {
			return $this->endInclusive();
		}

		return $imm;

	}

	/**
	 * Check if the given DateTimeInterface is within the range.
	 *
	 * @param DateTimeInterface $dt The date to check.
	 * @return bool
	 */
	public function contains(DateTimeInterface $dt): bool {

		return $dt >= $this->start and $dt < $this->end;

	}

	/**
	 * Get a DateRange for the current month.
	 *
	 * @param DateTimeZone|null $tz Optional timezone (default: Europe/Rome).
	 * @return DateRange
	 */
	public static function currentMonth(?DateTimeZone $tz = null): self {

		$tz = self::resolveTimeZone($tz);
		$now = new DateTimeImmutable('now', $tz);

		return self::month((int) $now->format('Y'), (int) $now->format('n'), $tz);

	}

	/**
	 * Create a DateRange for the day containing the given date (00:00 to next day 00:00).
	 */
	public static function day(DateTimeInterface $date, ?DateTimeZone $tz = null): self {

		$tz = self::resolveTimeZone($tz);

		$dt = DateTimeImmutable::createFromInterface($date)->setTimezone($tz);

		$start = $dt->setTime(0, 0, 0);
		$end = $start->modify('+1 day');

		return new self($start, $end);

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
	 * Returns the day range containing this range end (considered as inclusive for this purpose).
	 * If $tz is null, the timezone is resolved by resolveTimeZone().
	 *
	 * @param DateTimeZone|null $tz Optional timezone.
	 * @return DateRange
	 */
	public function endOfDay(?DateTimeZone $tz = null): self {

		return self::day($this->endInclusive(), $tz);

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
	 * Get a DateRange for the month containing this range start.
	 *
	 * @return DateRange
	 */
	public function forMonth(): self {

		$year = (int)$this->start->format('Y');
		$month = (int)$this->start->format('n');

		return self::month($year, $month, $this->start->getTimezone());

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
	 * This is mainly useful when you have a UI concept like "end of month 23:59:59".
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
	 * Get the end date formatted in the local language using IntlDateFormatter.
	 *
	 * @param	string	$format Formatting pattern, for example “dd MMMM Y hh:mm”.
	 * @return	string			Formatted date string.
	 */
	public function intlEnd(string $format = 'd MMMM Y hh:mm'): string {

		return Utilities::intlFormat($format, $this->endInclusive());

	}

	/**
	 * Get the start date formatted in the local language using IntlDateFormatter.
	 *
	 * @param	string	$format Formatting pattern, for example “dd MMMM Y hh:mm”.
	 * @return	string			Formatted date string.
	 */
	public function intlStart(string $format = 'd MMMM Y hh:mm'): string {

		return Utilities::intlFormat($format, $this->start);

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

		$tz = self::resolveTimeZone($tz);

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

		return self::month(
			(int) $dt->format('Y'),
			(int) $dt->format('n'),
			$tz ?? $dt->getTimezone(),
		);

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

		return $this->start < $other->end and $this->end > $other->start;

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
	 * Resolve a timezone using Pair conventions.
	 *
	 * Priority:
	 * 1) Explicit $tz.
	 * 2) Logged user timezone (if Pair Application is available).
	 * 3) BASE_TIMEZONE constant (if defined).
	 * 4) UTC fallback.
	 *
	 * @param DateTimeZone|null $tz
	 * @return DateTimeZone
	 */
	private static function resolveTimeZone(?DateTimeZone $tz): DateTimeZone {

		if ($tz !== null) {
			return $tz;
		}

		// try to integrate with Pair when available, but keep this class usable standalone.
		try {
			$app = Application::getInstance();
			if ($app->currentUser) {
				return $app->currentUser->getDateTimeZone();
			}
		} catch (Throwable $e) {
			// ignore and fallback.
		}

		if (defined('BASE_TIMEZONE')) {
			return new DateTimeZone((string) BASE_TIMEZONE);
		}

		return new DateTimeZone('UTC');

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
	 * Split the range into consecutive buckets of the given step.
	 *
	 * Example: using P1D returns an array of daily ranges.
	 * The last bucket end is clamped to this range end.
	 *
	 * @return DateRange[]
	 */
	public function split(DateInterval $step): array {

		$ranges = [];
		$period = $this->toPeriod($step);

		$prev = null;
		foreach ($period as $dt) {

			if ($prev !== null) {
				$bucketEnd = $dt <= $this->end ? $dt : $this->end;
				$ranges[] = new self($prev, $bucketEnd);
			}

			$prev = DateTimeImmutable::createFromInterface($dt);

		}

		if ($prev !== null and $prev < $this->end) {
			$ranges[] = new self($prev, $this->end);
		}

		return $ranges;

	}

	/**
	 * Returns the day range containing this range start (00:00 to next day 00:00).
	 *
	 * If $tz is null, the timezone is resolved by resolveTimeZone().
	 */
	public function startOfDay(?DateTimeZone $tz = null): self {

		return self::day($this->start, $tz);

	}

	/**
	 * Create a DateRange for today (00:00 to tomorrow 00:00).
	 */
	public static function today(?DateTimeZone $tz = null): self {

		$tz = self::resolveTimeZone($tz);

		return self::day(new DateTimeImmutable('now', $tz), $tz);

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
	 * Returns the ISO week info for this range start.
	 *
	 * If $tz is provided, the start date is normalized to that timezone before extracting ISO year/week.
	 *
	 * @return array{isoYear:int, isoWeek:int}
	 */
	public function toIsoWeek(?DateTimeZone $tz = null): array {

		$dt = $this->start;

		if ($tz !== null) {
			$dt = $dt->setTimezone($tz);
		}

		return [
			'isoYear' => (int) $dt->format('o'),
			'isoWeek' => (int) $dt->format('W'),
		];

	}

	/**
	 * Create a DatePeriod to iterate over the range with the given step.
	 *
	 * @param DateInterval $step    The step interval (e.g., P1D for days).
	 * @param int          $options Optional DatePeriod options, default 0.
	 * @return DatePeriod
	 */
	public function toPeriod(DateInterval $step, int $options = 0): DatePeriod {

		// useful for iterating over days, months, etc.
		return new DatePeriod($this->start, $step, $this->end, $options);

	}

	/**
	 * Build parameters for SQL queries. Returns an associative array like ['start' => '...', 'end' => '...'].
	 * If $tz is provided, dates will be formatted after timezone conversion.
	 *
	 * @param string $startKey The start parameter key.
	 * @param string $endKey   The end parameter key.
	 * @param string $format   Date format (default: Y-m-d H:i:s).
	 * @param DateTimeZone|null $tz Optional timezone for formatting.
	 * @return array<string,string>
	 */
	public function toSqlParams(
		string $startKey = 'start',
		string $endKey = 'end',
		string $format = 'Y-m-d H:i:s',
		?DateTimeZone $tz = null,
	): array {

		$start = $tz !== null ? $this->start->setTimezone($tz) : $this->start;
		$end = $tz !== null ? $this->end->setTimezone($tz) : $this->end;

		return [
			$startKey => $start->format($format),
			$endKey => $end->format($format),
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

	/**
	 * Create a DateRange for the given ISO week (Monday 00:00 to next Monday 00:00).
	 *
	 * @param int $isoYear ISO-8601 week-numbering year (use format('o')).
	 * @param int $isoWeek ISO week number (1-53).
	 */
	public static function week(int $isoYear, int $isoWeek, ?DateTimeZone $tz = null): self {

		$tz = self::resolveTimeZone($tz);

		$start = (new DateTimeImmutable('now', $tz))
			->setISODate($isoYear, $isoWeek, 1)
			->setTime(0, 0, 0);

		$end = $start->modify('+1 week');

		return new self($start, $end);

	}

	/**
	 * Create a DateRange for the ISO week containing the given date.
	 */
	public static function weekOf(DateTimeInterface $date, ?DateTimeZone $tz = null): self {

		$dt = DateTimeImmutable::createFromInterface($date);

		if ($tz !== null) {
			$dt = $dt->setTimezone($tz);
		}

		$isoYear = (int) $dt->format('o');
		$isoWeek = (int) $dt->format('W');

		return self::week($isoYear, $isoWeek, $tz ?? $dt->getTimezone());

	}

	/**
	 * Convert start/end to the given timezone keeping the same instant in time.
	 *
	 * This is useful for formatting and display.
	 * If you need boundaries aligned to local days/months/years, use the factory methods
	 * (day(), week(), month(), year()) passing the desired timezone.
	 */
	public function withTimeZone(DateTimeZone $tz): self {

		return new self(
			$this->start->setTimezone($tz),
			$this->end->setTimezone($tz),
		);

	}

	/**
	 * Create a DateRange for the given year.
	 *
	 * @param int $year The year (e.g., 2024).
	 * @param DateTimeZone|null $tz Optional timezone (default: Europe/Rome).
	 * @return DateRange
	 */
	public static function year(int $year, ?DateTimeZone $tz = null): self {

		$tz = self::resolveTimeZone($tz);

		$start = (new DateTimeImmutable('now', $tz))
			->setDate($year, 1, 1)
			->setTime(0, 0, 0);

		$end = $start->modify('+1 year');

		return new self($start, $end);

	}

	/**
	 * Create a DateRange for the year containing the given date.
	 *
	 * @param DateTimeInterface $date The reference date.
	 * @param DateTimeZone|null $tz   Optional timezone to normalize the date.
	 * @return DateRange
	 */
	public static function yearOf(DateTimeInterface $date, ?DateTimeZone $tz = null): self {

		$dt = DateTimeImmutable::createFromInterface($date);

		if ($tz !== null) {
			$dt = $dt->setTimezone($tz);
		}

		return self::year((int) $dt->format('Y'), $tz ?? $dt->getTimezone());

	}

}