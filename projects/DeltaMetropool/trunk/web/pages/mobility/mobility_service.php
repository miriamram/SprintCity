<?php
require_once (__DIR__ . '/../../includes/master.inc.php');

$tempTablesExist = false;

// Get post variables that load.js gives
if (isset($_REQUEST['get'])) {
	if ($_REQUEST['get'] == 'all') {
		$result = array();
		$result['stations'] = getMobilityDataStations();
		$result['trains'] = getMobilityDataTrains();
		echo json_encode($result);
	}
}

if (isset($_REQUEST['trainId']) && isset($_REQUEST['stationStops'])) {
	$game_id = Game::getGameIdOfSession(session_id());
	$round_info_instance_id = RoundInfoInstance::getCurrentRoundInfoInstanceIdBySessionId(session_id());

	$train_table_id = TrainTable::GetTrainTableIdOfGame($game_id);

	$trainId = $_REQUEST['trainId'];
	$stationStops = $_REQUEST['stationStops'];
	$stationIds = getCurrentStations($game_id, $train_table_id);

	$db = Database::getDatabase();
	foreach ($stationStops as $index => $frequency) {
		$query = "
			INSERT INTO TrainTableEntryInstance 
				(round_info_instance_id, train_id, station_id, frequency) 
			VALUES 
				(:round_info_instance_id, :train_id, :station_id, :frequency)
			ON DUPLICATE KEY UPDATE frequency = :frequency;";
		$args = array('round_info_instance_id' => $round_info_instance_id, 'train_id' => $trainId, 'station_id' => $stationIds[$index], 'frequency' => $frequency);
		$db -> query($query, $args);
	}
}

function getCurrentStations($game_id, $train_table_id) {
	$db = Database::getDatabase();
	$query = "
		SELECT TrainTableStation.id AS station_id
		FROM Station
		INNER JOIN StationInstance ON Station.id = StationInstance.station_id
		INNER JOIN TeamInstance ON StationInstance.team_instance_id = TeamInstance.id
		INNER JOIN Game ON Game.id = TeamInstance.game_id
		INNER JOIN ScenarioStation ON ScenarioStation.scenario_id = Game.scenario_id AND ScenarioStation.station_id = Station.id
		INNER JOIN TrainTableStation ON TrainTableStation.code = Station.code
		WHERE TeamInstance.game_id = :game_id
		AND train_table_id = :train_table_id
		ORDER BY ScenarioStation.order;";
	$args = array('game_id' => $game_id, 'train_table_id' => $train_table_id);
	$result = $db -> query($query, $args);

	while ($row = mysql_fetch_array($result)) {
		$stations[] = $row['station_id'];
	}
	return $stations;
}

function getMobilityDataStations() {
	$db = Database::getDatabase();
	$db -> query("START TRANSACTION");

	$game_id = Game::getGameIdOfSession(session_id());
	$round_info_instance_id = RoundInfoInstance::getCurrentRoundInfoInstanceIdBySessionId(session_id());
	
	createTempTables($game_id, $round_info_instance_id);

	$train_table_id = TrainTable::GetTrainTableIdOfGame($game_id);

	$query = "
		SELECT Station.code, 
			   Station.name, 
			   tempNetworkValues.networkValue + tempNetworkValues.chainValue AS networkValue, 
			   IFNULL(B.previousTravelers, 0) AS previousTravelers, 
			   IFNULL(A.currentTravelers, 0) AS currentTravelers, 
			   IFNULL(A.cap100, 0) AS cap100, 
			   IFNULL(A.capOver, 0) AS capOver, 
			   IFNULL(A.capUnder, 0) AS capUnder,
			   tempTravelers.travelers AS totalTravelers
		FROM Station
		INNER JOIN StationInstance ON Station.id = StationInstance.station_id
		INNER JOIN TeamInstance ON StationInstance.team_instance_id = TeamInstance.id
		INNER JOIN Game ON Game.id = TeamInstance.game_id
		INNER JOIN ScenarioStation ON ScenarioStation.scenario_id = Game.scenario_id AND ScenarioStation.station_id = Station.id
		LEFT JOIN (
			SELECT Station.id AS station_id, 
				   current_avg_travelers_per_stop AS currentTravelers, 
				   initial_avg_travelers_per_stop AS cap100, 
				   initial_avg_travelers_per_stop * 1.1 AS capOver, 
				   initial_avg_travelers_per_stop * 0.9 AS capUnder
			FROM Station 
			INNER JOIN TrainTableStation ON Station.code = TrainTableStation.code
			INNER JOIN (SELECT station_id, AVG(travelersPerStop) AS current_avg_travelers_per_stop
				FROM tempTravelersPerStop
				GROUP BY station_id) AS CurrentAvgTravelersPerStop
			ON TrainTableStation.id = CurrentAvgTravelersPerStop.station_id
			INNER JOIN (SELECT station_id, AVG(travelersPerStop) AS initial_avg_travelers_per_stop
				FROM InitialTravelersPerStop
				WHERE game_id = :game_id
				GROUP BY station_id) AS InitialAvgTravelersPerStop 
			ON CurrentAvgTravelersPerStop.station_id = InitialAvgTravelersPerStop.station_id
			WHERE train_table_id = :train_table_id
		) AS A ON A.station_id = Station.id
		LEFT JOIN TrainTableStation ON Station.code = TrainTableStation.code
		LEFT JOIN (
			SELECT TravelerHistory.station_id, travelers_per_stop AS previousTravelers
			FROM TravelerHistory
			WHERE TravelerHistory.round_info_instance_id = :round_info_instance_id - 1
		) AS B ON B.station_id = TrainTableStation.id
		LEFT JOIN tempNetworkValues ON tempNetworkValues.station_id = TrainTableStation.id
		LEFT JOIN tempTravelers ON tempTravelers.station_id = TrainTableStation.id
		WHERE TeamInstance.game_id = :game_id
		AND train_table_id = :train_table_id
		ORDER BY ScenarioStation.order;";
	$args = array('game_id' => $game_id,
				  'round_info_instance_id' => $round_info_instance_id,
				  'train_table_id' => $train_table_id);
	$result = $db -> query($query, $args);
	$db -> query("COMMIT");

	$sum = 0;
	$count = 0;
	while ($row = mysql_fetch_array($result)) {
		$stations[] = array("code" => $row['code'], 
							"name" => $row['name'], 
							"networkValue" => round($row['networkValue']), 
							"prevIU" => round($row['previousTravelers']), 
							"currentIU" => round($row['currentTravelers']), 
							"progIU" => 0, 
							"cap100" => round($row['cap100']), 
							"capOver" => round($row['capOver']), 
							"capUnder" => round($row['capUnder']),
							"totalTravelers" => round($row['totalTravelers']));
		if (round($row['cap100']) > 0) {
			$sum += round($row['cap100']);
			$count++;
		}
	}
	
	for ($i = 0; $i < count($stations); ++$i) {
		if ($stations[$i]['cap100'] == 0) {
			
			$stations[$i]['cap100'] = $sum / $count;
			$stations[$i]['capOver'] = ($sum / $count) * 1.1;
			$stations[$i]['capUnder'] = ($sum / $count) * 0.9;
		}
	}

	return $stations;
}

function getMobilityDataTrains() {
	$db = Database::getDatabase();
	$db -> query("START TRANSACTION");

	$game_id = Game::getGameIdOfSession(session_id());
	$round_info_instance_id = RoundInfoInstance::getCurrentRoundInfoInstanceIdBySessionId(session_id());
	createTempTables($game_id, $round_info_instance_id);

	$train_table_id = TrainTable::GetTrainTableIdOfGame($game_id);

	$query = "
		SELECT A.train_id, 
			   TrainTableTrain.name, 
			   TrainTableTrain.type, 
			   A.station_order, 
			   IFNULL(frequency,0) AS frequency, 
			   initialAvgTravelersPerStop.avgTravelers AS initialAvgTravelers, 
			   avgTravelersPerStop.avgTravelers AS currentAvgTravelers 
		FROM (
			SELECT * 
			FROM (
				SELECT train_id
				FROM Station 
				INNER JOIN StationInstance ON Station.id = StationInstance.station_id
				INNER JOIN TeamInstance ON StationInstance.team_instance_id = TeamInstance.id
				INNER JOIN TrainTableStation ON Station.code = TrainTableStation.code
				INNER JOIN traintableentry ON TrainTableStation.id = traintableentry.station_id
				WHERE TeamInstance.game_id = :game_id
				AND train_table_id = :train_table_id
				GROUP BY train_id
				HAVING COUNT(*) > 1
			) AS A,
			(
				SELECT ScenarioStation.order AS station_order, 
					   TrainTableStation.id AS station_id
				FROM Station 
				INNER JOIN StationInstance ON Station.id = StationInstance.station_id
				INNER JOIN TeamInstance ON StationInstance.team_instance_id = TeamInstance.id
				INNER JOIN TrainTableStation ON Station.code = TrainTableStation.code
				INNER JOIN Game ON Game.id = TeamInstance.game_id
				INNER JOIN ScenarioStation ON ScenarioStation.station_id = Station.id AND ScenarioStation.scenario_id = Game.scenario_id
				WHERE TeamInstance.game_id = :game_id
				AND train_table_id = :train_table_id
			) AS B
		) AS A 
		LEFT JOIN tempEntries ON A.train_id = tempEntries.train_id AND A.station_id = tempEntries.station_id
		INNER JOIN (
			SELECT train_id, 
				   AVG(travelersPerStop) AS avgTravelers
			FROM InitialTravelersPerStop
			WHERE game_id = :game_id
			GROUP BY train_id
		) AS initialAvgTravelersPerStop ON initialAvgTravelersPerStop.train_id = A.train_id
		INNER JOIN (
			SELECT train_id, AVG(travelersPerStop) AS avgTravelers
			FROM tempTravelersPerStop
			GROUP BY train_id
		) AS avgTravelersPerStop ON avgTravelersPerStop.train_id = A.train_id
		INNER JOIN TrainTableTrain ON A.train_id = TrainTableTrain.id
		ORDER BY train_id, station_order";

	$args = array('game_id' => $game_id, 'train_table_id' => $train_table_id);
	$result = $db -> query($query, $args);
	$db -> query("COMMIT");
	
	$trainId = -1;
	while ($row = mysql_fetch_array($result)) {
		if ($row['train_id'] != $trainId && $trainId != -1) {
			$train['stationStops'] = $stationStops;
			$trains[] = $train;
			$stationStops = array();
		}

		$train = array("id" => $row['train_id'], "name" => $row['type'], "route" => $row['name'], "currentAvgIU" => round($row['currentAvgTravelers']), "minAvgIU" => round($row['initialAvgTravelers'] * 0.9), "maxAvgIU" => round($row['initialAvgTravelers'] * 1.1));
		$stationStops[] = $row['frequency'];
		$trainId = $train['id'];
	}
	$train['stationStops'] = $stationStops;
	$trains[] = $train;

	return $trains;
}

function writeTravelersHistory($game_id, $round_info_instance_id) {
	$db = Database::getDatabase();
	$db -> query("START TRANSACTION");

	createTempTables($game_id, $round_info_instance_id);
	
	$train_table_id = TrainTable::GetTrainTableIdOfGame($game_id);
	
	$query = "
		INSERT INTO TravelerHistory (round_info_instance_id, station_id, travelers_per_stop)
		SELECT *
		FROM (
			SELECT :round_info_instance_id, ScenarioStations.id, AVG(tempTravelersPerStop.travelersPerStop) AS travelers
			FROM tempTravelersPerStop
			INNER JOIN (
				SELECT TrainTableStation.id
				FROM Station 
				INNER JOIN StationInstance ON Station.id = StationInstance.station_id
				INNER JOIN TeamInstance ON StationInstance.team_instance_id = TeamInstance.id
				INNER JOIN TrainTableStation ON Station.code = TrainTableStation.code
				WHERE TeamInstance.game_id = :game_id
				AND train_table_id = :train_table_id
			) AS ScenarioStations ON ScenarioStations.id = tempTravelersPerStop.station_id
			GROUP BY ScenarioStations.id
		) AS A
		ON DUPLICATE KEY UPDATE travelers_per_stop = A.travelers;";
	$args = array('game_id' => $game_id, 
				  'round_info_instance_id' => $round_info_instance_id,
				  'train_table_id' => $train_table_id);
	$db -> query($query, $args);
	
	$db -> query("COMMIT");
}

function updateNetworkValues($game_id, $current_round_id, $next_round_id) {
	$db = Database::getDatabase();
	$db -> query("START TRANSACTION");

	//createTempTables($game_id, $round_info_instance_id);
	
	$train_table_id = TrainTable::GetTrainTableIdOfGame($game_id);
	
	$query = "
		SELECT ScenarioStations.station_id, tempNetworkValues.networkValue
		FROM (
			SELECT Station.id AS station_id, TrainTableStation.id AS train_table_station_id
			FROM Station 
			INNER JOIN StationInstance ON Station.id = StationInstance.station_id
			INNER JOIN TeamInstance ON StationInstance.team_instance_id = TeamInstance.id
			INNER JOIN TrainTableStation ON Station.code = TrainTableStation.code
			WHERE TeamInstance.game_id = :game_id
			AND train_table_id = :train_table_id
		) AS ScenarioStations
		INNER JOIN tempNetworkValues ON tempNetworkValues.station_id = ScenarioStations.train_table_station_id;";
	$args = array(
		'train_table_id' => $train_table_id,
		'game_id' => $game_id);
	$result = $db->query($query, $args);
	
	// fill in new povn values in next round's round instance
	while ($row = mysql_fetch_array($result)) {
		$query = "
			UPDATE RoundInstance
			INNER JOIN Round ON RoundInstance.round_id = Round.id
			INNER JOIN StationInstance ON RoundInstance.station_instance_id = StationInstance.id
			INNER JOIN TeamInstance ON StationInstance.team_instance_id = TeamInstance.id
			SET RoundInstance.POVN = :new_povn
			WHERE Round.round_info_id = :current_round_id
				AND StationInstance.station_id = :station_id
				AND TeamInstance.game_id = :game_id;";
		$args = array(
			'new_povn' => $row['networkValue'],
			'current_round_id' => $current_round_id,
			'station_id' => $row['station_id'],
			'game_id' => $game_id);
		$db->query($query, $args);
	}
	
	$db -> query("COMMIT");
}

function createInitialTables($game_id) {
	$db = Database::getDatabase();
	$db -> query("START TRANSACTION");

	$train_table_id = TrainTable::GetTrainTableIdOfGame($game_id);

	$queries = array(	"CREATE TEMPORARY TABLE tempInitialEntries (train_id INT, station_id INT, frequency INT);", 
						"CREATE TEMPORARY TABLE tempInitialEntries2 LIKE tempInitialEntries;",
						"CREATE TEMPORARY TABLE tempInitialEntries3 LIKE tempInitialEntries;",
						"CREATE TEMPORARY TABLE tempInitialNetworkValues (station_id INT, networkValue DOUBLE, chainvalue INT);", 
						"CREATE TEMPORARY TABLE tempInitialTravelers (station_id INT, travelers INT);", 
						"CREATE TEMPORARY TABLE tempInitialTravelersPerStop (train_id INT, station_id INT, travelersPerStop INT);");
	foreach ($queries as $query) {
		$db -> query($query, array());
	}
	createInitialEntriesTable("tempInitialEntries", $train_table_id);
	createNetworkValueTable("tempInitialNetworkValues", "tempInitialEntries", $train_table_id);

	$db -> query("INSERT INTO InitialNetworkValues SELECT station_id, networkValue, chainValue, " . $game_id . " FROM tempInitialNetworkValues;", array());
	
	createTravelersTable("tempInitialTravelers", "InitialNetworkValues", "tempInitialNetworkValues", $game_id, $train_table_id);
	createTravelersPerStopTable("tempInitialTravelersPerStop", "tempInitialEntries", "tempInitialNetworkValues", "tempInitialTravelers");
	
	$db -> query("INSERT INTO InitialTravelersPerStop SELECT train_id, station_id, travelersPerStop, " . $game_id . " FROM tempInitialTravelersPerStop;", array());
	
	$db -> query("COMMIT");
}

function createTempTables($game_id, $round_info_instance_id) {
	if ($GLOBALS['tempTablesExist']) {
		return;
	}
	$GLOBALS['tempTablesExist'] = true;
	
	$db = Database::getDatabase();

	$train_table_id = TrainTable::GetTrainTableIdOfGame($game_id);

	$queries = array(	"CREATE TEMPORARY TABLE tempEntries (train_id INT, station_id INT, frequency INT);", 
						"CREATE TEMPORARY TABLE tempEntries2 LIKE tempEntries;",
						"CREATE TEMPORARY TABLE tempEntries3 LIKE tempEntries;",
						"CREATE TEMPORARY TABLE tempNetworkValues (station_id INT, networkValue DOUBLE, chainvalue INT);", 
						"CREATE TEMPORARY TABLE tempTravelers (station_id INT, travelers INT);",
						"CREATE TEMPORARY TABLE tempTravelersPerStop (train_id INT, station_id INT, travelersPerStop INT);");
	foreach ($queries as $query) {
		$db -> query($query, array());
	}
	
	createCurrentEntriesTable("tempEntries", $train_table_id, $round_info_instance_id);
	createNetworkValueTable("tempNetworkValues", "tempEntries", $train_table_id);
	createTravelersTable("tempTravelers", "InitialNetworkValues", "tempNetworkValues", $game_id, $train_table_id);
	createTravelersPerStopTable("tempTravelersPerStop", "tempEntries", "tempNetworkValues", "tempTravelers");
}

function createInitialEntriesTable($table_name, $train_table_id) {
	$db = Database::getDatabase();
	$query = "
	INSERT INTO " . $table_name . "
	SELECT train_id, station_id, frequency 
	FROM traintableentry
	INNER JOIN traintabletrain ON train_id = traintabletrain.id 
	WHERE train_table_id = :train_table_id;";
	$args = array('train_table_id' => $train_table_id);
	$db -> query($query, $args);
	$db -> query("INSERT INTO " . $table_name . "2 SELECT * FROM " . $table_name . ";", array());
	$db -> query("INSERT INTO " . $table_name . "3 SELECT * FROM " . $table_name . ";", array());
}

function createCurrentEntriesTable($table_name, $train_table_id, $round_info_instance_id) {
	$db = Database::getDatabase();
	$query = "
	INSERT INTO " . $table_name . "
	SELECT train_id, station_id, frequency
	FROM (
		SELECT * 
		FROM (
			SELECT * 
			FROM (
				SELECT train_id, station_id, frequency
				FROM traintableentryinstance 
				WHERE round_info_instance_id = :round_info_instance_id 
				UNION
				SELECT train_id, station_id, frequency 
				FROM traintableentry
			) AS A 
			GROUP BY train_id, station_id
		) AS A 
		WHERE frequency != 0
	) AS A
	INNER JOIN traintabletrain ON train_id = traintabletrain.id 
	WHERE train_table_id = :train_table_id;";
	$args = array('train_table_id' => $train_table_id, 'round_info_instance_id' => $round_info_instance_id);
	$db -> query($query, $args);
	$db -> query("INSERT INTO " . $table_name . "2 SELECT * FROM " . $table_name . ";", array());
	$db -> query("INSERT INTO " . $table_name . "3 SELECT * FROM " . $table_name . ";", array());
}

function createNetworkValueTable($table_name, $entries_table, $train_table_id) {
	$db = Database::getDatabase();
	$query = "
	INSERT INTO " . $table_name . "
	SELECT traintablestation.id AS station_id, IFNULL(SUM(trainvalue * frequency),0) AS networkvalue, chain AS chainvalue
	FROM traintablestation 
	LEFT JOIN " . $entries_table . " ON traintablestation.id = " . $entries_table . ".station_id
	LEFT JOIN (
		SELECT train_id, SUM(stopvalue) / COUNT(stopvalue) AS trainvalue 
		FROM (
			SELECT station_id, SUM(frequency) AS stopvalue FROM " . $entries_table . "2
			GROUP BY station_id
		) AS stopvalues
		INNER JOIN " . $entries_table . "3 ON stopvalues.station_id = " . $entries_table . "3.station_id
		GROUP BY train_id
	) AS trainvalues ON trainvalues.train_id = " . $entries_table . ".train_id
	WHERE train_table_id = :train_table_id
	GROUP BY traintablestation.id;";
	$args = array('train_table_id' => $train_table_id);
	$db -> query($query, $args);
}

/*function createInitialTravelersTable($table_name, $game_id, $train_table_id) {
	$db = Database::getDatabase();
	$query = "
	INSERT INTO " . $table_name . "
	SELECT id AS station_id, A.travelers 
	FROM (
		SELECT * 
		FROM (
			SELECT Station.code, ROUND
			(
				Station.area_cultivated_mixed * Constants.average_travelers_per_ha_leisure 
				+
				Station.count_home_total * Constants.average_citizens_per_home * Constants.average_travelers_per_citizen
				+
				Station.count_work_total * Constants.average_workers_per_bvo * Constants.average_travelers_per_worker
			) AS travelers
			FROM Constants, Station		
		) AS A 
		UNION
		SELECT code, travelers
		FROM TrainTableStation 
		WHERE train_table_id = :train_table_id
	) AS A 
	INNER JOIN TrainTableStation ON A.code = TrainTableStation.code
	WHERE train_table_id = :train_table_id
	GROUP BY A.code;";
	$args = array('train_table_id' => $train_table_id);
	$db -> query($query, $args);
}*/

function createTravelersTable($table_name, $nwval_table_initial, $nwval_table_current, $game_id, $train_table_id) {
	$db = Database::getDatabase();
	$query = "
	INSERT INTO " . $table_name . "
	SELECT id AS station_id, A.travelers 
	FROM (
		SELECT * 
		FROM (
			SELECT A.code, A.travelers * 
				IFNULL(
					(current.networkValue - initial.networkValue) 
					/ 
					initial.networkValue 
					/
					IF((current.networkValue - initial.networkValue) / initial.networkValue > 5, 20, IF((current.networkValue - initial.networkValue) / initial.networkValue > 1, 15, 10))
					+ 1
					, 1
				) AS travelers 
			FROM (
				SELECT Station.code, ROUND
				(
					(
						(
							(
								(
									(
										(
											Station.area_cultivated_home - 
											(
												(SUM(Program.area_home) + SUM(Program.area_work) + SUM(Program.area_leisure)) 
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
							* Constants.average_travelers_per_citizen
						) 
						+
						(
							(
								(
									(
										Station.area_cultivated_work - 
										(
											(SUM(Program.area_home) + SUM(Program.area_work) + SUM(Program.area_leisure)) 
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
							Constants.average_travelers_per_worker
						)
						+
						(
							(
								(
									(
										Station.area_cultivated_mixed - 
										(
											(SUM(Program.area_home) + SUM(Program.area_work) + SUM(Program.area_leisure)) 
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
				) AS travelers
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
				INNER JOIN RoundInfo AS RoundInfo2 ON RoundInfo.id <= RoundInfo2.id
				INNER JOIN Round AS Round2 ON RoundInfo2.id = Round2.round_info_id AND Station.id = Round2.station_id
				INNER JOIN RoundInstance AS RoundInstance2 ON Round2.id = RoundInstance2.round_id AND StationInstance.id = RoundInstance2.station_instance_id
				INNER JOIN Game ON TeamInstance.game_id = Game.id AND RoundInfo2.id < current_round_id
				LEFT JOIN FacilityInstance ON RoundInstance.id = FacilityInstance.round_instance_id
				LEFT JOIN Facility ON FacilityInstance.facility_id = Facility.id
				WHERE Game.id = :game_id
				GROUP BY Station.id, RoundInfo2.id
				ORDER BY RoundInfo2.id
			) AS A
			INNER JOIN traintablestation ON A.code = traintablestation.code
			INNER JOIN " . $nwval_table_initial . " AS initial ON initial.station_id = traintablestation.id
			INNER JOIN " . $nwval_table_current . " AS current ON current.station_id = traintablestation.id
			WHERE traintablestation.train_table_id = :train_table_id
			AND game_id = :game_id
		) AS A 
		UNION
		SELECT code, travelers
		FROM TrainTableStation 
		WHERE train_table_id = :train_table_id
	) AS A 
	INNER JOIN TrainTableStation ON A.code = TrainTableStation.code
	WHERE train_table_id = :train_table_id
	GROUP BY A.code;";
	$args = array('game_id' => $game_id, 'train_table_id' => $train_table_id);
	$db -> query($query, $args);
}

function createTravelersPerStopTable($table_name, $entries_table, $nwval_table, $travelers_table) {
	$db = Database::getDatabase();
	$query = "
	INSERT INTO " . $table_name . "
	SELECT " . $entries_table . ".train_id, " . $entries_table . ".station_id, (trainvalue * frequency) / networkValue * " . $travelers_table . ".travelers / frequency AS travelersPerStop
	FROM (
		SELECT train_id, SUM(stopvalue) / COUNT(stopvalue) AS trainvalue 
		FROM (
			SELECT station_id, SUM(frequency) AS stopvalue 
			FROM " . $entries_table . "2
	        GROUP BY station_id
		) AS stopvalues
	    INNER JOIN " . $entries_table . "3 ON stopvalues.station_id = " . $entries_table . "3.station_id
	    GROUP BY train_id
	) AS trainvalues
	INNER JOIN " . $entries_table . " ON trainvalues.train_id = " . $entries_table . ".train_id
	INNER JOIN traintablestation ON traintablestation.id = " . $entries_table . ".station_id
	INNER JOIN " . $nwval_table . " ON traintablestation.id = " . $nwval_table . ".station_id
	INNER JOIN " . $travelers_table . " ON traintablestation.id = " . $travelers_table . ".station_id;";
	$db -> query($query, array());
}
?>
