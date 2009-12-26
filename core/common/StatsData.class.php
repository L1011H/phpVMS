<?php
/**
 * phpVMS - Virtual Airline Administration Software
 * Copyright (c) 2008 Nabeel Shahzad
 * For more information, visit www.phpvms.net
 *	Forums: http://www.phpvms.net/forum
 *	Documentation: http://www.phpvms.net/docs
 *
 * phpVMS is licenced under the following license:
 *   Creative Commons Attribution Non-commercial Share Alike (by-nc-sa)
 *   View license.txt in the root, or visit http://creativecommons.org/licenses/by-nc-sa/3.0/
 *
 * @author Nabeel Shahzad 
 * @copyright Copyright (c) 2008, Nabeel Shahzad
 * @link http://www.phpvms.net
 * @license http://creativecommons.org/licenses/by-nc-sa/3.0/
 */
 
class StatsData extends CodonData
{
	
	public static function GetStartDate()
	{
		$sql = 'SELECT `pirepid`, `submitdate`
				FROM '.TABLE_PREFIX.'pireps
				ORDER BY `submitdate` ASC
				LIMIT 1';
					
		return DB::get_row($sql);
	}
	
	/**
	 * Get all of the months since the VA started
	 */
	public static function GetMonthsSinceStart()
	{
		$months = array();
		
		$date = self::GetStartDate();
		
		if(!$date)
			$startdate = time();
		else
			$startdate = $date->submitdate;
			
		return self::GetMonthsSinceDate($startdate);
	}
	
	/**
	 * Get years since the VA started
	 */
	public static function GetYearsSinceStart()
	{
		$months = array();
		
		$date = self::GetStartDate();
		
		if(!$date)
			$startdate = 'Today';
		else
			$startdate = $date->submitdate;
		
		$start = strtotime($startdate);
		$end = date('Y');		
		do
		{
			$year = date('Y', $start);	# Get the months
			$years[$year] = $start;		# Set the timestamp
			$start = strtotime('+1 Year', $start);
			
		} while ( $year < $end ); 
		
		return array_reverse($years, true);	
	}
	
	
	/**
	 * Get all of the months since a certain date
	 */
	public static function GetMonthsSinceDate($start)
	{
		if(!is_numeric($start)){
			$start = strtotime($start);
		}
		
		$end = date('Ym');

		do
		{
			# Get the months
			$month = date('M Y', $start);
			$months[$month] = $start; # Set the timestamp			
			$start = strtotime('+1 month +1 day', strtotime($month));
		
			# Convert to YYYYMM to compare
			$check = intval(date('Ym', $start));
			
		} while ( $check <= $end ); 

		return $months;
	}
	
	/**
	 * Get all the months within a certain range
	 * Pass timestamp, or textual date
	 */
	public static function GetMonthsInRange($start, $end)
	{
		if(!is_numeric($start)){
			$start = strtotime($start);
		}
		
		if(!is_numeric($end))
		{
			$end = strtotime($end);
		}
		
		$end = intval(date('Ym', $end));
		
		/*
			Loop through, adding one month to $start each time
		*/		
		do
		{			
			$month = date('M Y', $start);		# Get the month
			$months[$month] = $start;			# Set the timestamp	
			$start = strtotime('+1 month +1 day', strtotime($month));
			//$start += (SECONDS_PER_DAY * 25);	# Move it up a month
			
			$check = intval(date('Ym', $start));
			
		} while ( $check <= $end );
		
		return $months;		
	}
	
	public static function UpdateTotalHours()
	{
		$pireps = PIREPData::findPIREPS(array('p.accepted'=>1));
		
		if(!$pireps)
		{
			return;
		}
		
		$totaltime = 0;
		foreach($pireps as $pirep)
		{
			$totaltime = Util::AddTime($totaltime, $pirep->flighttime);
		}
		
		SettingsData::SaveSetting('TOTAL_HOURS', $totaltime);		
	}
	
	/**
	 * Get the total number of hours flown by pilots
	 */
	public static function TotalHours()
	{
		return SettingsData::GetSettingValue('TOTAL_HOURS');
	}
	
	/**
	 * Get the total number of flights flown
	 */
	public static function TotalFlights($airline_code = '')
	{
		$sql = 'SELECT COUNT(*) AS `total`
				FROM `'.TABLE_PREFIX.'pireps`
				WHERE `accepted`='.PIREP_ACCEPTED;
		
		if($airline_code != '')
		{
			$sql .= " AND `code`='{$airline_code}' GROUP BY `code`";
		}
				
		$result = DB::get_row($sql);
		
		if(!$result)
		{
			return 0;
		}
		
		return $result->total;
	}
	
	/**
	 * Get the top routes
	 */
	 
	public static function TopRoutes($airline_code='')
	{
		$sql = 'SELECT * 
				FROM `'.TABLE_PREFIX.'schedules`';
				
		if($airline_code != '')
		{
			$sql .= " AND `code`='{$airline_code}' GROUP BY `code`";
		}
		
		$sql ='	ORDER BY `timesflown` DESC
				LIMIT 10';
		
		return DB::get_results($sql);
	}
	
	
	public static function UsersOnline($minutes = '')
	{
		if($minutes == '')
			$minutes = Config::Get('USERS_ONLINE_TIME');
		
		$sql = "SELECT p.*
				FROM ".TABLE_PREFIX."pilots p, ".TABLE_PREFIX."sessions s
				WHERE s.pilotid = p.pilotid 
				AND DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE) <= s.`logintime`";
		
		$users = DB::get_results($sql);
		
		# Check if it's blank, then return an empty array
		if(!$users)
			$users = array();
			
		return $users;
	}
	public static function GuestsOnline($minutes = '')
	{
		if($minutes == '')
			$minutes = Config::Get('USERS_ONLINE_TIME');
		
		$sql = "SELECT s.*
				FROM ".TABLE_PREFIX."sessions s
				WHERE s.pilotid = 0
				AND DATE_SUB(NOW(), INTERVAL {$minutes} MINUTE) <= s.`logintime`";
		
		$guests = DB::get_results($sql);
		
		if(!$guests)
			$guests = array();
			
		return $guests;
	}
	
	
	/**
	 * Get the total number of pilots
	 */
	public static function PilotCount($airline_code='')
	{
		$sql = 'SELECT COUNT(*) AS `total`
				FROM '.TABLE_PREFIX.'pilots ';
				
		if($airline_code != '')
		{
			$sql .= " WHERE `code`='{$airline_code}' GROUP BY `code`";
		}
		
		$result = DB::get_row($sql);
		
		if(!$result)
		{
			return 0;
		}
			
		return $result->total;
	}
	
	/**
	 * Get the current aircraft usage
	 */
	public static function AircraftUsage()
	{
		$sql = 'SELECT a.*, a.name AS aircraft,
					COUNT(p.pirepid) AS routesflown,
					SUM(p.distance) AS distance,
					SEC_TO_TIME(SUM(p.flighttime*60*60)) AS totaltime,
					AVG(p.distance) AS averagedistance,
					AVG(p.flighttime) as averagetime
				FROM   '.TABLE_PREFIX.'aircraft a
				INNER JOIN '.TABLE_PREFIX.'pireps p ON (p.aircraft = a.id)
				GROUP BY a.registration';
		
		return DB::get_results($sql);
	}
	
	/**
	 * Show pie chart for all of the aircraft flown
	 *  by a certain pilot. Outputs image, unless $ret == true,
	 * 	then it returns the URL.
	 */
	public static function AircraftFlownGraph($ret = false)
	{
		//Select aircraft types
		$sql = 'SELECT a.name AS aircraft, COUNT(p.aircraft) AS count
					FROM '.TABLE_PREFIX.'pireps p, '.TABLE_PREFIX.'aircraft a 
					WHERE p.aircraft = a.id
					GROUP BY a.name';
		
		$stats = DB::get_results($sql);
		
		if(!$stats)
		{
			return;
		}
		
		$data = '';
		$labels = '';
		foreach($stats as $stat)
		{
			if($stat->aircraft == '') continue;
			
			$data .= $stat->count . ',';
			$labels .= $stat->aircraft.'|';
		}
		
		// remove that final lone char
		$data = substr($data, 0, strlen($data)-1);
		$labels = substr($labels, 0, strlen($labels)-1);
		
		$chart = new googleChart($data, 'pie');
		$chart->dimensions = '350x200';
		$chart->setLabels($labels);
		
		if($ret == true)
			return $chart->draw(false);
		else
			echo '<img src="'.$chart->draw(false).'" />';
	}
	
	public static function PilotAircraftFlownCounts($pilotid)
	{
		//Select aircraft types
		$sql = 'SELECT a.name AS aircraft, COUNT(p.aircraft) AS count, SUM(p.flighttime) AS hours
				FROM '.TABLE_PREFIX.'pireps p, '.TABLE_PREFIX.'aircraft a 
				WHERE p.aircraft = a.id AND p.pilotid='.intval($pilotid).'
				GROUP BY a.name';
		
		return DB::get_results($sql);		
	}
	
	/**
	 * Show pie chart for all of the aircraft flown
	 *  by a certain pilot. Outputs image, unless $ret == true,
	 * 	then it returns the URL.
	 */
	public static function PilotAircraftFlownGraph($pilotid, $ret = false)
	{
		$stats = self::PilotAircraftFlownCounts($pilotid);
		
		if(!$stats)
		{
			return;
		}
		
		$data = '';
		$labels = '';
		foreach($stats as $stat)
		{
			if($stat->aircraft == '') continue;

			$data .= $stat->count . ',';
			$labels .= $stat->aircraft.'|';
		}

		// remove that final lone char
		$data = substr($data, 0, strlen($data)-1);
		$labels = substr($labels, 0, strlen($labels)-1);

		$chart = new GoogleChart($data, 'pie');
		$chart->dimensions = '350x200';
		$chart->setLabels($labels);

		
		$url = $chart->draw(false);
		unset($chart);
		
		if($ret == true)
			return $url;
		else
			echo '<img src="'.$url.'" />';
	}
	
	
	/* These contributed by simpilot from phpVMS forums
	 */
	
	
	/**
	 * Return the total number of passengers carried
	 *
	 * @return mixed This is the return value description
	 *
	 */
	public static function TotalPaxCarried($airline_code = '')
	{
		$sql = 'SELECT SUM(`load`) AS `total`
					FROM '.TABLE_PREFIX.'pireps
					WHERE `accepted`='.PIREP_ACCEPTED.'
						AND `flighttype`=\'P\'';
			
		if($airline_code != '')
		{
			$sql .= " AND `code`='{$airline_code}' GROUP BY `code`";
		}
		
		$result=DB::get_row($sql);
		
		if(!$result)
		{
			return 0;
		}
		
		return $result->total;		
	}
	
	
	/**
	 * Return the number of flights flown today
	 *
	 * @return int Total number of flights
	 *
	 */
	public static function TotalFlightsToday($airline_code='')
	{
		$sql = 'SELECT COUNT(*) AS `total`
				FROM '.TABLE_PREFIX.'pireps
				WHERE DATE(`submitdate`) = CURDATE()';
					
		if($airline_code != '')
		{
			$sql .= " AND `code`='{$airline_code}' GROUP BY `code`";
		}
		
		$result=DB::get_row($sql);
		
		if(!$result)
		{
			return 0;
		}
		
		return $result->total;
		
	}
	
	
	/**
	 * Total amount of fuel burned among all accepted PIREPS
	 *
	 * @return float In units specified in config
	 *
	 */
	public static function TotalFuelBurned($airline_code='')
	{
		$sql = 'SELECT SUM(`fuelused`) AS `total`
				FROM '.TABLE_PREFIX.'pireps
				WHERE `accepted`='.PIREP_ACCEPTED;
		
		if($airline_code != '')
		{
			$sql .= " AND `code`='{$airline_code}' GROUP BY `code`";
		}
		
		$result=DB::get_row($sql);
		
		if(!$result)
		{
			return 0;
		}
		
		return $result->total;
	}
	
	
	/**
	 * Get the total miles/km flown
	 *
	 * @return float Total distance flown in units in config
	 *
	 */
	public static function TotalMilesFlown($airline_code='')
	{
		$sql = 'SELECT SUM(`distance`) AS `total`
				FROM '.TABLE_PREFIX.'pireps
				WHERE `accepted`='.PIREP_ACCEPTED;
					
		if($airline_code != '')
		{
			$sql .= " AND `code`='{$airline_code}' GROUP BY `code`";
		}
		
		$result=DB::get_row($sql);
		
		if(!$result)
		{
			return 0;
		}
		
		return $result->total;
	}
	
	
	/**
	 * Return the total number of aircraft in the fleet
	 *
	 * @return int Total
	 *
	 */
	public static function TotalAircraftInFleet($airline_code='')
	{
		$sql = 'SELECT COUNT(`id`) AS `total` 
				FROM '.TABLE_PREFIX.'aircraft';
					
		if($airline_code != '')
		{
			$sql .= " WHERE `code`='{$airline_code}' GROUP BY `code`";
		}
		
		$result=DB::get_row($sql);
		
		if(!$result)
		{
			return 0;
		}
		
		return $result->total;
	}
	
	
	/**
	 * Return the total number of news stories
	 *
	 * @return int Total
	 *
	 */
	public static function TotalNewsItems()
	{
		$sql = 'SELECT COUNT(`id`) AS `total` 
				FROM '.TABLE_PREFIX.'news';
		
		$result=DB::get_row($sql);
		
		if(!$result)
		{
			return 0;
		}
		
		return $result->total;
	}
	
	
	/**
	 * Return the total number of schedules in the system
	 *
	 * @return int Total number
	 *
	 */
	public static function TotalSchedules($airline_code='')
	{
		$sql = 'SELECT COUNT(`id`) AS `total` 
				FROM '.TABLE_PREFIX.'schedules';
		
		if($airline_code != '')
		{
			$sql .= " WHERE `code`='{$airline_code}' GROUP BY `code`";
		}
		
		$result=DB::get_row($sql);
		
		if(!$result)
		{
			return 0;
		}
		
		return $result->total;
	}
}