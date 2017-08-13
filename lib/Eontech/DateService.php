<?php
/**
 * DateService class
 *
 * @author    Serge <serge@stigmi.eu>
 * @version   1.30.0
 * @copyright Copyright (c), Eontech.net All rights reserved.
 * @license   BSD License
 */

if (!defined('_PS_VERSION_'))
	exit;

class EontechDateService
{
	const DT_FORMAT = 'Ymd';
	const DEF_DAYS = 30;
	const CONFIG_KEY = 'BPOST_CACHED_DATES';

	protected $cached_dates = array();
	protected $sat_del = false;

	public function __construct($sat_del = false)
	{
		$this->sat_del = (bool)$sat_del;
		$stored_dates = (string)Configuration::get(static::CONFIG_KEY);
		if (!empty($stored_dates))
		{
			$stored_dates = Tools::jsonDecode($stored_dates, true);
			$expires = (int)$stored_dates['expires'];
			if ((int)date(static::DT_FORMAT) < $expires)
			{
				$this->setCachedDates($stored_dates['dates']);
				return;
			}
		}

		$this->generateNextDates();
	}

	protected function setCachedDates($all_dates = array())
	{
		if (empty($all_dates) || !is_array($all_dates))
			throw new Exception('cannot get descent dates or no cache');

		$cached_dates = array(
			'all' => $all_dates,
			'workdays' => array_intersect($all_dates, range(1, 5)),
			);
		$cached_dates['deliverable'] = $this->sat_del ? array_intersect($all_dates, range(1, 6)) : $cached_dates['workdays'];
		$this->cached_dates = $cached_dates;
	}

	protected function generateNextDates()
	{
		$dates = static::generateDates(static::DEF_DAYS);
		$this->setCachedDates($dates);
		$update_func = version_compare(_PS_VERSION_, '1.5', '>=') ? 'updateGlobalValue' : 'updateValue';
		$tomorrow = new DateTime();
		$dt_stored = array(
			'expires' => (int)$tomorrow->modify('+1 day')->format(static::DT_FORMAT),
			'dates' => $dates,
			);

		return Configuration::$update_func(static::CONFIG_KEY, Tools::jsonEncode($dt_stored));
	}

	protected static function generateDates($n_days = 0, $timestamp = '')
	{
		$n_days = ($n_days <= 0) ? static::DEF_DAYS : (int)$n_days;
		$timestamp = empty($timestamp) ? time() : (int)$timestamp;
		// $iso = 'R30/'.date('Y-m-d').'T00:00:00Z/P1D';
		$iso = 'R'.(int)$n_days.'/'.date('Y-m-d', $timestamp).'T00:00:00Z/P1D';
		$period = new DatePeriod($iso);
		$dates = array();

		$easter = new DateTime(date('d-m-Y', easter_date()));
		$paak = clone $easter;
		$paak->modify('first monday');
		$ascension = clone $easter;
		$ascension->modify('+40 days');
		$pentecost = clone $easter;
		$pentecost->modify('+51 days');
		$hols_fixed = array(
			'01-01-*',
			'01-05-*',
			'21-07-*',
			'15-08-*',
			'01-11-*',
			'11-01-*',
			'25-12-*',
		);
		$hols_var = array($paak, $ascension, $pentecost);
		foreach ($period as $dt)
		{
			$is_holiday = in_array($dt->format('d-m-*'), $hols_fixed) || in_array($dt, $hols_var);
			// $date = array(
			// 	'n' => $is_holiday ? 0 : (int)$dt->format('N'),
			// 	'dt' => $dt->format('d-m-Y'),
			// 	);

			$dates[$dt->format(static::DT_FORMAT)] = $is_holiday ? 0 : (int)$dt->format('N');
		}

		return $dates;
	}

	/* helpers */
	protected function getNthDate($n = 0, $from = 0, $deliverable = false)
	{
		if (empty($n)) return $from;

		$dates = $deliverable ? 'deliverable' : 'workdays';
		$dates = $this->cached_dates[$dates];
		if ((int)$n < 0)
			$dates = array_reverse($dates, true);

		$count = abs((int)$n);
		$from = empty($from) ? time() : strtotime((string)$from);
		$from = date(static::DT_FORMAT, $from);

		$nth_date = false;
		foreach ($dates as $dt => $day)
		{
			$not_yet = $n > 0 ? $dt < $from : $dt > $from;
			if ($not_yet)
				continue;
			elseif (0 == $count--)
			{
				$nth_date = $dt;
				break;
			}
		}

		return $nth_date;
	}

	/**
	 * [no-frills version: use carefully]
	 * @param  int 		$n_days >= 0
	 * @param  int 		$from  start key YYYYmmdd
	 * @param  array 	$dates  workdays or deliverable
	 * @return string   n days from (using dates)
	 */
	protected function getNDaysAfter($n_days, $from, $dates)
	{
		$n_days_after = $from;
		if ((bool)$n_days)
			foreach ($dates as $dt)
				if ($dt < $from)
					continue;
				elseif (0 == $n_days--)
				{
					$n_days_after = $dt;
					break;
				}

		return $n_days_after;
	}

	protected function getNDeliveryDatesFrom($n_dates, $from)
	{
		$n_dates = (int)$n_dates;
		if ($n_dates < 1)
			throw new Exception('invalid number of delivery dates');

		$del_dates = array_keys($this->cached_dates['deliverable']);
		$first_date = $this->getNDaysAfter(1, $from, $del_dates);
		if ($n_dates > 1 || 6 !== $this->cached_dates['all'][$first_date])
			$n_dates--;

		$dates = array($first_date);
		if ((bool)$n_dates)
			foreach ($del_dates as $dt)
				if ($dt <= $first_date)
					continue;
				elseif ($n_dates-- > 0)
					$dates[] = $dt;

		return $dates;
	}

	protected function getDropDateToday()
	{
		$delay_days = (int)Configuration::get('BPOST_SHIP_DELAY_DAYS');
		$cutoff = (string)Configuration::get('BPOST_CUTOFF_TIME');

		$today = date(static::DT_FORMAT);
		$workdays = $this->cached_dates['workdays'];
		$work_dates = array_keys($workdays);
		$is_workday = in_array($today, $work_dates);

		$now = date('Hi');
		if ($is_workday && $now >= $cutoff)
			$delay_days++;

		$start_day = $today;
		foreach ($work_dates as $dt)
			if ($dt >= $start_day)
			{
				$start_day = $dt;
				break;
			}

		return $this->getNDaysAfter($delay_days, $start_day, $work_dates);
	}

	public function getDeliveryDates()
	{
		$n_dates = (bool)Configuration::get('BPOST_CHOOSE_DELIVERY_DATE') ? (int)Configuration::get('BPOST_NUM_DATES_SHOWN') : 1;
		$drop_date = $this->getDropDateToday();

		return $this->getNDeliveryDatesFrom($n_dates, $drop_date);
	}

	public function getDropDate($dt_from = 0)
	{
		$drop_date = false;
		$dt_from = (int)$dt_from;
		if ($dt_from > 0)
		{
			$workdays = $this->cached_dates['workdays'];
			$work_dates = array_keys($workdays);
			foreach ($work_dates as $dt)
				if ($dt < $dt_from)
					$drop_date = $dt;
				else
					break;
		}
		else
			$drop_date = $this->getDropDateToday();

		return $drop_date;
	}

	public function getDay($date)
	{
		return isset($this->cached_dates['all'][$date]) ? (int)$this->cached_dates['all'][$date] : false;
	}

	public function isSaturday($date)
	{
		return 6 === $this->getDay($date);
	}
}
