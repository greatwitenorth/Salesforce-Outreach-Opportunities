<?php
require_once('connection.php');
header('content-type: image/jpeg');

getPhotos($_GET['id']);

function getPhotos($id){
	$mySforceConnection = getConnection();
	$query = "SELECT Id, Name, Body from Attachment Where Id ='" .$id ."'";
	$queryResult = $mySforceConnection->query($query);
	$records = $queryResult->records;	
	print_r(base64_decode($records[0]->fields->Body));
	return $queryResult;
}

?>