<?php
require_once('../../includes/master.inc.php');
require_once('linegraph.php');

// Data
if (ClientSession::hasSession(session_id()))
{
	$povnData = array(0);
	$travelerData = array(0);

	$citizenData = LoadPOVNData($_REQUEST['session'], $_REQUEST['station']);
	$workerData = LoadTravelerData($_REQUEST['session'], $_REQUEST['station']);
	
	// Construct
	$graph = new LineGraph(720,330);
	
	// Set input
	$graph->SetInputArray($povnData);
	$graph->SetInputArray($travelerData);
	$graph->SetToMobilityColors();
	
	// Get image
	$image = $graph->GetImage(); //must fail if there is no width, height or inputArray
	
	// Make .PHP -> .PNG-image
	header('Content-type:image/png');
	
	// Display on screen
	imagepng($image);
	
	// Destroy garbage
	imagedestroy($image);
}

function LoadPOVNData($session_id, $station_id)
{
	return array(0);
}

function LoadTravelerData($session_id, $station_id)
{
	return array(0);
}
?>