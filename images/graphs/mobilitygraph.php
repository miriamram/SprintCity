<?php
require_once('../../includes/master.inc.php');
require_once('linegraph.php');

// Data
// commented security to allow public reports
//if (ClientSession::hasSession(session_id()))
//{
	$povnData = array(0);
	$travelerData = array(0);

	$width = isset($_REQUEST['width']) ? $_REQUEST['width'] : 480;
	$height = isset($_REQUEST['height']) ? $_REQUEST['height'] : 220;
	
	$gameId = isset($_REQUEST['game']) ? $_REQUEST['game'] : Game::getGameIdOfSession(session_id());
	$stationId = isset($_REQUEST['station']) ? $_REQUEST['station'] : 0;
	
	$povnData = LoadPOVNData($gameId, $stationId);
	$travelerData = LoadTravelerData($gameId, $stationId);
	$initTravelerCount = Station::getInitialTravelerCount($_REQUEST['station']);
	
	if (!isset($povnData))
		$povnData = array(0);
	
	if (isset($travelerData))
		array_unshift($travelerData, $initTravelerCount);
	else
		$travelerData = array($initTravelerCount);
	
	// Get min/max values
	$povnMinMax = array_merge(LoadInitPOVNMinMax(), LoadPOVNDataMinMax($gameId));
	$povnMin = min($povnMinMax) - 10;
	$povnMax = max($povnMinMax);
	$travelerMinMax = array_merge(LoadInitTravelerMinMax(), LoadTravelerDataMinMax($gameId));
	$travelerMin = min($travelerMinMax) - 10;
	$travelerMax = max($travelerMinMax);
	
	// Construct
	//$graph = new LineGraph(720,330);
	$graph = new LineGraph($width, $height);
	
	// Set input
	$graph->SetInputArray($povnData, $povnMin, $povnMax);
	$graph->SetInputArray($travelerData, $travelerMin, $travelerMax);
	$graph->SetToMobilityColors();
	
	// Get image
	$image = $graph->GetImage(); //must fail if there is no width, height or inputArray
	
	// Make .PHP -> .PNG-image
	header('Content-type:image/png');
	
	// Display on screen
	imagepng($image);
	
	// Destroy garbage
	imagedestroy($image);
//}

function LoadInitPOVNMinMax()
{
	$db = Database::getDatabase();
	$query = "
		SELECT 
			MIN(POVN) AS MinInitPOVN,
			MAX(POVN) AS MaxInitPOVN
		FROM 
		(
			SELECT POVN
			FROM Station
		) AS t1 LIMIT 0,1;";
	$result = $db->query($query, array());
	if (mysql_num_rows($result) > 0)
	{
		$data = array();
		$row = mysql_fetch_array($result);
		$data[] = round($row['MinInitPOVN']);
		$data[] = round($row['MaxInitPOVN']);
		return $data;
	}
	return array();
}

function LoadInitTravelerMinMax()
{
	$db = Database::getDatabase();
	$query = "
		SELECT 
			MIN(TravelerCount) AS MinInitTravelers,
			MAX(TravelerCount) AS MaxInitTravelers
		FROM
		(
			SELECT
			ROUND
			(
				Station.area_cultivated_mixed * Constants.average_travelers_per_ha_leisure 
				+
				Station.count_home_total * Constants.average_citizens_per_home * Constants.average_travelers_per_citizen
				+
				Station.count_work_total * Constants.average_workers_per_bvo * Constants.average_travelers_per_worker
			) AS TravelerCount
			FROM Constants, Station
		) AS t1 LIMIT 0,1;";
	$result = $db->query($query, array());
	if (mysql_num_rows($result) > 0)
	{
		$data = array();
		$row = mysql_fetch_array($result);
		if ($row['MinInitTravelers'] != NULL) $data[] = round($row['MinInitTravelers']);
		if ($row['MaxInitTravelers'] != NULL) $data[] = round($row['MaxInitTravelers']);
		return $data;
	}
	else
		return array();
}

function LoadPOVNDataMinMax($game_id)
{
	if (isset($game_id))
	{
		$db = Database::getDatabase();
		$query = "
			SELECT 
				MIN(POVN) AS MinPOVN,
				MAX(POVN) AS MaxPOVN
			FROM
			(
				SELECT RoundInstance.POVN FROM RoundInstance
				INNER JOIN StationInstance ON RoundInstance.station_instance_id = StationInstance.id
				INNER JOIN TeamInstance ON StationInstance.team_instance_id = TeamInstance.id
				INNER JOIN Game ON TeamInstance.game_id = Game.id
				INNER JOIN Round ON RoundInstance.round_id = Round.id
				WHERE Game.id = :game_id 
				AND Round.round_info_id <= Game.current_round_id
			) AS t1 LIMIT 0,1;";
		$args = array('game_id' => $game_id);
		$result = $db->query($query, $args);
		if (mysql_num_rows($result) > 0)
		{
			$data = array();
			$row = mysql_fetch_array($result);
			if ($row['MinPOVN'] != NULL) $data[] = round($row['MinPOVN']);
			if ($row['MaxPOVN'] != NULL) $data[] = round($row['MaxPOVN']);
			return $data;
		}
		else
			return array();
	}
}

function LoadTravelerDataMinMax($game_id)
{
	if (isset($game_id))
	{
		$db = Database::getDatabase();
		$query = "
			SELECT Min(TravelerCount) AS MinTravelerCount, MAX(TravelerCount) AS MaxTravelerCount
			FROM
			( 
				SELECT ROUND
				(
					(
						(
							(
								(
									(
										(
											(
												(
													Station.area_cultivated_home - 
													(
														SUM(Round.new_transform_area) 
														* 
														(transform_area_cultivated_home / (transform_area_cultivated_home + transform_area_cultivated_work + transform_area_cultivated_mixed + transform_area_undeveloped_urban + transform_area_undeveloped_rural))
													)
												)
												* 
												IFNULL(count_home_total / area_cultivated_home, 0)
											) 
											+ 
											SUM(Program.area_home * TypesHome.area_density)
										) 
										* 
										Constants.average_citizens_per_home
										+
										IFNULL(SUM(Facility.citizens), 0)
									)
									*
									IFNULL(1 + SUM(Facility.citizens_percent) / 100, 1)
								) 
								*
								Constants.average_travelers_per_citizen
							) 
							+
							(
								(
									(
										(
											(
												Station.area_cultivated_work - 
												(
													SUM(Round.new_transform_area) 
													* 
													(transform_area_cultivated_work / (transform_area_cultivated_home + transform_area_cultivated_work + transform_area_cultivated_mixed + transform_area_undeveloped_urban + transform_area_undeveloped_rural))
												)
											)
											* 
											IFNULL(count_worker_total / (area_cultivated_work + area_cultivated_mixed), 0)
										) 
										+ 
										SUM(Program.area_work * TypesWork.people_density)
										+
										IFNULL(SUM(Facility.workers), 0)
									)
									*
									IFNULL(1 + SUM(Facility.workers_percent) / 100, 1)
								) 
								*
								Constants.average_travelers_per_worker
							)
							+
							(
								(
									(
										(
											Station.area_cultivated_mixed - 
											(
												SUM(Round.new_transform_area) 
												* 
												(transform_area_cultivated_mixed / (transform_area_cultivated_home + transform_area_cultivated_work + transform_area_cultivated_mixed + transform_area_undeveloped_urban + transform_area_undeveloped_rural))
											)
										)
										* 
										IFNULL(count_worker_total / (area_cultivated_work + area_cultivated_mixed), 0)
									) 
									+ 
									SUM(Program.area_leisure * TypesLeisure.people_density)
								) 
								*
								Constants.average_travelers_per_worker
							)
							+
							IFNULL(SUM(Facility.travelers), 0)
						)
						*
						IFNULL
						(
							(RoundInstance2.POVN - StationInstance.initial_POVN) 
							/ 
							StationInstance.initial_POVN 
							/
							IF((RoundInstance2.POVN - StationInstance.initial_POVN) / StationInstance.initial_POVN > 5, 20, IF((RoundInstance2.POVN - StationInstance.initial_POVN) / StationInstance.initial_POVN > 1, 15, 10))
							+ 1
							, 1
						)
					) * IFNULL(1 + SUM(Facility.travelers_percent) / 100, 1)
				) AS TravelerCount
				FROM Constants, Station
				INNER JOIN StationInstance ON Station.id = StationInstance.station_id 
				INNER JOIN TeamInstance ON StationInstance.team_instance_id = TeamInstance.id
				INNER JOIN RoundInstance ON StationInstance.id = RoundInstance.station_instance_id
				INNER JOIN Program ON RoundInstance.exec_program_id = Program.id
				INNER JOIN Types AS TypesHome ON Program.type_home = TypesHome.id
				INNER JOIN Types AS TypesWork ON Program.type_work = TypesWork.id
				INNER JOIN Types AS TypesLeisure ON Program.type_leisure = TypesLeisure.id
				INNER JOIN Round ON RoundInstance.round_id = Round.id AND Station.id = Round.station_id
				INNER JOIN RoundInfo ON Round.round_info_id = RoundInfo.id
				INNER JOIN RoundInfo AS RoundInfo2 ON RoundInfo.id < RoundInfo2.id
				INNER JOIN Round AS Round2 ON RoundInfo2.id = Round2.round_info_id AND Station.id = Round2.station_id
				INNER JOIN RoundInstance AS RoundInstance2 ON Round2.id = RoundInstance2.round_id AND StationInstance.id = RoundInstance2.station_instance_id
				INNER JOIN Game ON TeamInstance.game_id = Game.id AND RoundInfo2.id <= current_round_id
				LEFT JOIN FacilityInstance ON RoundInstance.id = FacilityInstance.round_instance_id
				LEFT JOIN Facility ON FacilityInstance.facility_id = Facility.id
				WHERE Game.id = :game_id
				GROUP BY Station.id, RoundInfo2.id
				ORDER BY RoundInfo2.id
			) AS tq LIMIT 0, 1;";
		$args = array('game_id' => $game_id);
		$result = $db->query($query, $args);
		if (mysql_num_rows($result) > 0)
		{
			$data = array();
			$row = mysql_fetch_array($result);
			if ($row['MinTravelerCount'] != NULL) $data[] = round($row['MinTravelerCount']);
			if ($row['MaxTravelerCount'] != NULL) $data[] = round($row['MaxTravelerCount']);
			return $data;
		}
		else
			return array();
	}
}

function LoadPOVNData($game_id, $station_id)
{
	if (isset($game_id) && isset($station_id))
	{
		$db = Database::getDatabase();
		$query = 	"SELECT RoundInstance.POVN FROM RoundInstance
					INNER JOIN StationInstance ON RoundInstance.station_instance_id = StationInstance.id
					INNER JOIN TeamInstance ON StationInstance.team_instance_id = TeamInstance.id
					INNER JOIN Game ON TeamInstance.game_id = Game.id
					INNER JOIN Round ON RoundInstance.round_id = Round.id
					WHERE Game.id = :game_id 
					AND StationInstance.station_id = :station_id
					AND Round.round_info_id <= Game.current_round_id;
				";
		$args = array('game_id' => $game_id, 'station_id' => $station_id);
		$result = $db->query($query, $args);
		return $db->getValues($result);
	}
}

function LoadTravelerData($game_id, $station_id)
{
	if (isset($game_id) && isset($station_id))
	{
		$db = Database::getDatabase();
		$query = "
			SELECT
			ROUND
			(
				(
					(
						(
							(
								(
									(
										(
											(
												Station.area_cultivated_home - 
												(
													SUM(Round.new_transform_area) 
													* 
													(transform_area_cultivated_home / (transform_area_cultivated_home + transform_area_cultivated_work + transform_area_cultivated_mixed + transform_area_undeveloped_urban + transform_area_undeveloped_rural))
												)
											)
											* 
											IFNULL(count_home_total / area_cultivated_home, 0)
										) 
										+ 
										SUM(Program.area_home * TypesHome.area_density)
									) 
									* 
									Constants.average_citizens_per_home
									+
									IFNULL(SUM(Facility.citizens), 0)
								)
								*
								IFNULL(1 + SUM(Facility.citizens_percent) / 100, 1)
							) 
							*
							Constants.average_travelers_per_citizen
						) 
						+
						(
							(
								(
									(
										(
											Station.area_cultivated_work - 
											(
												SUM(Round.new_transform_area) 
												* 
												(transform_area_cultivated_work / (transform_area_cultivated_home + transform_area_cultivated_work + transform_area_cultivated_mixed + transform_area_undeveloped_urban + transform_area_undeveloped_rural))
											)
										)
										* 
										IFNULL(count_worker_total / (area_cultivated_work + area_cultivated_mixed), 0)
									) 
									+ 
									SUM(Program.area_work * TypesWork.people_density)
									+
									IFNULL(SUM(Facility.workers), 0)
								)
								*
								IFNULL(1 + SUM(Facility.workers_percent) / 100, 1)
							) 
							*
							Constants.average_travelers_per_worker
						)
						+
						(
							(
								(
									(
										Station.area_cultivated_mixed - 
										(
											SUM(Round.new_transform_area) 
											* 
											(transform_area_cultivated_mixed / (transform_area_cultivated_home + transform_area_cultivated_work + transform_area_cultivated_mixed + transform_area_undeveloped_urban + transform_area_undeveloped_rural))
										)
									)
									* 
									IFNULL(count_worker_total / (area_cultivated_work + area_cultivated_mixed), 0)
								) 
								+ 
								SUM(Program.area_leisure * TypesLeisure.people_density)
							) 
							*
							Constants.average_travelers_per_worker
						)
						+
						IFNULL(SUM(Facility.travelers), 0)
					)
					*
					IFNULL
					(
						(RoundInstance2.POVN - StationInstance.initial_POVN) 
						/ 
						StationInstance.initial_POVN 
						/
						IF((RoundInstance2.POVN - StationInstance.initial_POVN) / StationInstance.initial_POVN > 5, 20, IF((RoundInstance2.POVN - StationInstance.initial_POVN) / StationInstance.initial_POVN > 1, 15, 10))
						+ 1
						, 1
					)
				) * IFNULL(1 + SUM(Facility.travelers_percent) / 100, 1)
			) AS TravelerCount
			FROM Constants, Station
			INNER JOIN StationInstance ON Station.id = StationInstance.station_id 
			INNER JOIN TeamInstance ON StationInstance.team_instance_id = TeamInstance.id
			INNER JOIN RoundInstance ON StationInstance.id = RoundInstance.station_instance_id
			INNER JOIN Program ON RoundInstance.exec_program_id = Program.id
			INNER JOIN Types AS TypesHome ON Program.type_home = TypesHome.id
			INNER JOIN Types AS TypesWork ON Program.type_work = TypesWork.id
			INNER JOIN Types AS TypesLeisure ON Program.type_leisure = TypesLeisure.id
			INNER JOIN Round ON RoundInstance.round_id = Round.id AND Station.id = Round.station_id
			INNER JOIN RoundInfo ON Round.round_info_id = RoundInfo.id
			INNER JOIN RoundInfo AS RoundInfo2 ON RoundInfo.id < RoundInfo2.id
			INNER JOIN Round AS Round2 ON RoundInfo2.id = Round2.round_info_id AND Station.id = Round2.station_id
			INNER JOIN RoundInstance AS RoundInstance2 ON Round2.id = RoundInstance2.round_id AND StationInstance.id = RoundInstance2.station_instance_id
			INNER JOIN Game ON TeamInstance.game_id = Game.id AND RoundInfo2.id <= current_round_id
			LEFT JOIN FacilityInstance ON RoundInstance.id = FacilityInstance.round_instance_id
			LEFT JOIN Facility ON FacilityInstance.facility_id = Facility.id
			WHERE Game.id = :game_id AND Station.id = :station_id
			GROUP BY Station.id, RoundInfo2.id
			ORDER BY RoundInfo2.id;";
		$args = array('game_id' => $game_id, 'station_id' => $station_id);
		$result = $db->query($query, $args);
		if (mysql_num_rows($result) > 0)
		{
			$data = array();
			while ($row = mysql_fetch_array($result))
				$data[] = round($row['TravelerCount']);
			return $data;
		}
		else
			return array();
	}
}
?>