<?php
require_once('../../includes/master.inc.php');
require_once('linegraph.php');

// Data
$citizenData = array(0);
$workerData = array(0);
$width = $_REQUEST['width'];
$height = $_REQUEST['height'];

if(!isset($width) || !isset($height))
{
	$width = 240;
	$height = 110; //defaults
}

if (isset($_REQUEST['session']) &&
	$_REQUEST['session'] == session_id() &&
	ClientSession::hasSession($_REQUEST['session']))
{
	$citizenData = LoadCitizenData($_REQUEST['session'], $_REQUEST['station']);
	$workerData = LoadWorkerData($_REQUEST['session'], $_REQUEST['station']);
	$initCitizenCount = GetInitialCitizen($_REQUEST['session'], $_REQUEST['station']);
	

	$citizenData = Shift($citizenData);
	$citizenData[0] = $initCitizenCount;
	

}

// Could not find a simple function in php library for THIS!! :(
function Shift($array)
{
	$count = count($array);
	
	$array[$count] = $array[$count - 1];
	
	for($i = $count -2; $i >= 0; $i--)
	{
		$array[$i+1] = $array[$i];
	}
	
	return $array;
}

function GetInitialCitizen($session_id, $station_id)
{
	if (isset($session_id) && isset($station_id))
	{
		$db = Database::getDatabase();
		$query = "
			SELECT((Station.count_home_total )*(Constants.average_citizens_per_home))
			AS InitialCitizenCount
			FROM Constants, Station
			WHERE Station.id = :station_id;
			";
			
		$args = array('station_id' => $station_id);
		$result = $db->query($query, $args);
	}
	$result = mysql_fetch_array($result);
	$result = round($result['InitialCitizenCount']);
	return $result;
 }

function LoadCitizenData($session_id, $station_id)
{
	if (isset($session_id) && isset($station_id))
	{
		$db = Database::getDatabase();
		$query = "
			SELECT
				(
					(
						(
							(
								Station.area_cultivated_home - 
								(
									(SUM(Program.area_home) + SUM(Program.area_work) + SUM(Program.area_leisure)) 
									* 
									(transform_area_cultivated_home / (transform_area_cultivated_home + transform_area_cultivated_work + transform_area_cultivated_mixed + transform_area_undeveloped_urban + transform_area_undeveloped_mixed))
								)
							)
							* 
							(count_home_total / area_cultivated_home)
						) 
						+ 
						SUM(Program.area_home * Types.density)
					) 
					* Constants.average_citizens_per_home
				) AS CitizenCount
			FROM Constants, Station
			INNER JOIN StationInstance ON Station.id = StationInstance.station_id 
			INNER JOIN TeamInstance ON StationInstance.team_instance_id = TeamInstance.id
			INNER JOIN ClientSession ON TeamInstance.id = ClientSession.team_instance_id
			INNER JOIN RoundInstance ON StationInstance.id = RoundInstance.station_instance_id
			INNER JOIN Program ON RoundInstance.exec_program_id = Program.id
			INNER JOIN Types ON Program.type_home = Types.id
			INNER JOIN Round ON RoundInstance.round_id = Round.id AND Station.id = Round.station_id
			INNER JOIN RoundInfo ON Round.round_info_id = RoundInfo.id
			INNER JOIN RoundInfo AS RoundInfo2 ON RoundInfo.id > RoundInfo2.id
			INNER JOIN Game ON TeamInstance.game_id = Game.id AND RoundInfo.id < current_round_id
			WHERE ClientSession.id = :session_id AND Station.id = :station_id
			GROUP BY Station.id, RoundInfo.id
			ORDER BY RoundInfo.id;";
		$args = array('session_id' => $session_id, 'station_id' => $station_id);
		$result = $db->query($query, $args);
		if (mysql_num_rows($result) > 0)
		{
			$data = array();
			while ($row = mysql_fetch_array($result))
				$data[] = round($row['CitizenCount']);
			return $data;
		}
		else
			return array(0);
	}
}

function LoadWorkerData($session_id, $station_id)
{
	if (isset($session_id) && isset($station_id))
	{
		$db = Database::getDatabase();
		$query = "
			SELECT
				(
					(
						(
							(
								Station.area_cultivated_work - 
								(
									(SUM(Program.area_home) + SUM(Program.area_work) + SUM(Program.area_leisure)) 
									* 
									(transform_area_cultivated_work / (transform_area_cultivated_home + transform_area_cultivated_work + transform_area_cultivated_mixed + transform_area_undeveloped_urban + transform_area_undeveloped_mixed))
								)
							)
							* 
							(count_work_total / area_cultivated_work)
						) 
						+ 
						SUM(Program.area_work * Types.density)
					) 
					* Constants.average_workers_per_bvo
				) AS WorkerCount
			FROM Constants, Station
			INNER JOIN StationInstance ON Station.id = StationInstance.station_id 
			INNER JOIN TeamInstance ON StationInstance.team_instance_id = TeamInstance.id
			INNER JOIN ClientSession ON TeamInstance.id = ClientSession.team_instance_id
			INNER JOIN RoundInstance ON StationInstance.id = RoundInstance.station_instance_id
			INNER JOIN Program ON RoundInstance.exec_program_id = Program.id
			INNER JOIN Types ON Program.type_work = Types.id
			INNER JOIN Round ON RoundInstance.round_id = Round.id AND Station.id = Round.station_id
			INNER JOIN RoundInfo ON Round.round_info_id = RoundInfo.id
			INNER JOIN RoundInfo AS RoundInfo2 ON RoundInfo.id > RoundInfo2.id
			INNER JOIN Game ON TeamInstance.game_id = Game.id AND RoundInfo.id < current_round_id
			WHERE ClientSession.id = :session_id AND Station.id = :station_id
			GROUP BY Station.id, RoundInfo.id
			ORDER BY RoundInfo.id;";
		$args = array('session_id' => $session_id, 'station_id' => $station_id);
		$result = $db->query($query, $args);
		if (mysql_num_rows($result) > 0)
		{
			$data = array();
			while ($row = mysql_fetch_array($result))
				$data[] = round($row['WorkerCount']);
			return $data;
		}
		else
			return array(0);
	}
}

// Construct
// $graph = new LineGraph(480,220);
$graph = new LineGraph($width, $height);

$array1 = array(0,2,4,5,7,8);
$array2 = array(8,7,6,5,3,2);

// Set input
$graph->SetInputArray($citizenData);
$graph->SetInputArray($workerData);



// Get image
$image = $graph->GetImage(); //must fail if there is no width, height or inputArray

// Make .PHP -> .PNG-image
header('Content-type:image/png');

// Display on screen
imagepng($image);

// Destroy garbage
imagedestroy($image);
?>