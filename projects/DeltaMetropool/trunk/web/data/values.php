<?php
	require_once '../includes/master.inc.php';

	if (isset($_REQUEST['session']) &&
		$_REQUEST['session'] == session_id() &&
		ClientSession::hasSession($_REQUEST['session']))
	{
		if (isset($_REQUEST['data']))
			submitValues($_REQUEST['data']);
		else
			printValues();
	}
	
	function submitValues($xml)
	{
		$db = Database::getDatabase();
		$xml_array = xml2array($xml);
		
		$query = "
			SELECT team_instance_id
			FROM ClientSession 
			WHERE ClientSession.id = :id";
		$args = array('id' => session_id());
		$result = $db->query($query, $args);
		
		$team_instance_id = $db->getValue($result);
		
		foreach ($xml_array['values']['value'] as $value)
		{
			$query = "
				UPDATE `ValueInstance` 
				SET `checked` = :checked 
				WHERE `value_id` = :value_id 
				AND `team_instance_id` = :team_instance_id";
			$args = array(
				'checked' => $value['checked'], 
				'value_id' => $value['id'], 
				'team_instance_id' => $team_instance_id);
			$db->query($query, $args);
		}
		
		$query = "
			UPDATE `TeamInstance` 
			SET `value_description` = :description 
			WHERE id = :id";
		$args = array(
			'description' => $xml_array['values']['description'],
			'id' => $team_instance_id);
		$db->query($query, $args);
		
		$session = new ClientSession($_GET['session']);
	}
	
	function printValues()
	{
		$db = Database::getDatabase();
		
		echo '<values>';
		
		$query = "
			SELECT Value.id, Value.title, Value.description, ValueInstance.checked
			FROM Value 
			INNER JOIN ValueInstance 
			ON ValueInstance.value_id = Value.id 
			INNER JOIN TeamInstance 
			ON TeamInstance.id = ValueInstance.team_instance_id 
			INNER JOIN ClientSession 
			ON ClientSession.team_instance_id = TeamInstance.id 
			WHERE ClientSession.id = :id";
		$args = array('id' => session_id());
		$result = $db->query($query, $args);
		
		while ($row = mysql_fetch_array($result))
		{
			echo '<value>';
			echo '<id>' . $row['id'] . '</id>';
			echo '<title>' . $row['title'] . '</title>';
			echo '<description>' . $row['description'] . '</description>';
			echo '<checked>' . $row['checked'] . '</checked>';
			echo '</value>';
		}
		
		$query = "
			SELECT TeamInstance.value_description
			FROM TeamInstance 
			INNER JOIN ClientSession 
			ON ClientSession.team_instance_id = TeamInstance.id 
			WHERE ClientSession.id = :id";
		$args = array('id' => session_id());
		$result = $db->query($query, $args);
		
		echo '<description>' . $db->getValue($result) . '</description>';
				
		echo '</values>';
	}
?>