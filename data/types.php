<?php
	require_once '../includes/master.inc.php';

	if (ClientSession::hasSession(session_id()))
	{
		printTypes();
	}
	
	function printTypes()
	{
		$type_fields = array(
			'id', 'name', 'type', 'description', 'color', 'image', 'area_density', 'people_density'
		);
		
		$demand_fields = array(
			'id', 'round_info_id', 'type_id', 'amount'
		);
		
		$db = Database::getDatabase();
		
		$types_result = getTypes();
		
		echo '<types>' . "\n";
		
		while ($type_row = mysql_fetch_array($types_result))
		{
			echo "\t" . '<type>' . "\n";
			foreach ($type_fields as $type_field)
			{
				echo "\t\t" . '<' . $type_field . '>' . $type_row[$type_field] . '</' . $type_field . '>' . "\n";
			}
			
			echo "\t\t" . '<demands>' . "\n";
			$demands_result = getDemands($type_row['id']);
			while ($demand_row = mysql_fetch_array($demands_result))
			{
				echo "\t\t\t" . '<demand>' . "\n";
				foreach ($demand_fields as $demand_field)
				{
					echo "\t\t\t\t". '<' . $demand_field . '>' . $demand_row[$demand_field] . '</' . $demand_field . '>' . "\n";
				}
				echo "\t\t\t" . '</demand>' . "\n";
			}
			echo "\t\t" . '</demands>' . "\n";
			
			echo "\t" . '</type>' . "\n";
		}
		
		echo '</types>';
	}
	
	function getTypes()
	{
		$db = Database::getDatabase();
		$query = "
			SELECT * 
			FROM Types";
		return $db->query($query);
	}
	
	function getDemands($type_id)
	{
		$db = Database::getDatabase();
		$game_id = Game::getGameIdOfSession(session_id());
		$query = "
			SELECT * 
			FROM Demand 
			INNER JOIN Scenario ON Demand.scenario_id = Scenario.id 
			INNER JOIN Game ON Scenario.id = Game.scenario_id 
			WHERE Game.id=:game_id && Demand.type_id=:type_id";
		$args = array('game_id' => $game_id, 'type_id' => $type_id);
		return $db->query($query, $args);
	}
?>
