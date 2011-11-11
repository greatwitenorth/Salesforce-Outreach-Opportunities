<pre>
<?php
/*
Plugin Name: Outreach Opportunities
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: Output how many positions there are available for each outreach. This data is pulled dynamically from salesforce.
Version: 1.0
Author: Nick Verwymeren
Author URI: http://makesomecode.com
License: GPL2
*/

add_action('admin_menu', 'plugin_admin_add_page');
add_action('admin_init', 'plugin_admin_init');
@define('SFDC_PATH', dirname(__FILE__));
require_once('connection.php');

//Set our custom field name and object it belongs to
$searchFieldName 	= "outreach__c";
$searchObject 		= "Opportunity";


function plugin_admin_init() {
	register_setting( 'outreach_options', 'outreach_options' );
	add_settings_section('outreach_main', 'Outreach Settings', 'outreach_section_text', 'outreach_options');
	updateOpportunities();
	$outreaches = get_option('outreach_options');
	output_all_setting_fields($outreaches);

}

function updateOpportunities(){
	$options = get_option('outreach_options');
	$outreaches = outputOpportunities(array("outreach__c"), "Opportunity");
	$positions = outputOpportunities(array("Type_of_Volunteer__c"), "Opportunity");
	
	$values = array_flatten_recursive((array)$options);
	foreach ($outreaches as $outreach){
		//Lets check to see if we need to add any new outreaches to our wordpress cache
		if(!in_array($outreach->value, $values, true)){

			//Build our data model
			$outreach->show = true;
			$outreach->positions = $positions;
			foreach ($outreach->positions as $position){
				$position->target = 0;
				$position->advertise = false;
			}
			//Add the new outreach to our existing data
			$options[] = $outreach;
			//Update our database to add the new outreach from salesforce
			update_option('outreach_options', $options);

		}
	}
	
	foreach ($positions as $position){
		//Lets check to see if we need to add any new positions to our wordpress cache
		if(!in_array($position->value, $values, true)){
			//Build our data model
			foreach ($options as $option){
				$position->target = 0;
				$position->advertise = false;
				$option->positions[] = $position;

			}
			//Add the new position to our existing data
			$options[] = $option;
			echo "<pre>";
			print_r($options);
			echo "</pre>";
			//Update our database to add the new outreach from salesforce
			update_option('outreach_options', $options);

		}
	}
}

function array_flatten_recursive($array) { 
   if (!$array) return false;
   $flat = array();
   $RII = new RecursiveIteratorIterator(new RecursiveArrayIterator($array));
   foreach ($RII as $value) $flat[] = $value;
   return $flat;
}

function plugin_admin_add_page() {
	add_options_page('Outreach Opportunities', 'Outreach Opportunities', 'manage_options', 'sfdc', 'sfdc_options_page');
}

function outreach_options_validate($input) {
	$newinput['text_string'] = trim($input['text_string']);
	if(!preg_match('/^[0-9]{32}$/i', $newinput['text_string'])) {
		$newinput['text_string'] = '';
	}
	return $newinput;
}

function output_all_setting_fields($outreaches){
	/*
	echo "<pre>";
	print_r($outreaches	);
	echo "</pre>";
	*/
	foreach($outreaches as $outreach){
		foreach($outreach->positions as $position){
			$value = '';
			$check_value = '';
			$target = '';
			if(isset($position->advertise)){
				if($position->advertise == true){
					$check_value = "checked=yes";
				}
			}
			if(isset($position->target)){
				$target = $position->target;
			}
			add_settings_field( $outreach->value."-".$position->value, $position->value, 'outreach_setting_field', 'outreach_options',
									'outreach_main', array('label_for' => $outreach->value."-".$position->value, 'id' => array('outreach' => $outreach->value,  
									'position' => $position->value, 'checked' => $check_value, 'target' => $target ) ));
		}

	}
}

function outreach_setting_field($input) {
	$outreach 	= $input['id']['outreach'];
	$position	= $input['id']['position'];
	$value	 	= $input['id']['target'];
	$checked 	= $input['id']['checked'];
		
	echo "<input id='advertise-".$outreach."-".$position."' name='outreach_options[".$outreach."][".$position."][checked]'
			type='checkbox' value='true' $checked />";
	echo "<input id='".$outreach."-".$position."' name='outreach_options[".$outreach."][".$position."][target]' size='5' 
			type='text' value='$value' />";
}

function outreach_section_text() {
echo '<p>Main description of this section here.</p>';
}

function sfdc_options_page(){
	global $wpdb;
	//$table_name = $wpdb->prefix . "mcpd_currency";
	include(SFDC_PATH . '/options.php');
}

function start() {

	
	$client = getConnection();
	$values = $client->describeSObject($searchObject);
	
	//Get the object (or field) in which our field name resides 
	$items 	= searchForField($values->fields, $searchFieldName, "name");
	
	//Check and see if the form has been posted. If so display our photos
	$opp = '';
	if(isset($_POST[$searchObject])){
		$opp = $_POST[$searchObject];
		if ($opp == 'showAll'){
			$client = getConnection();
			$values = $client->describeSObject($searchObject);
			foreach($items->picklistValues as $pickItem){
				getContactPhotosForOpportunity($pickItem->value);
			}
		} else {
			getContactPhotosForOpportunity($opp);
		}
	}
	
	//Display our opportunity drop down menu
	$values = NULL;
	$values = $client->describeSObject($searchObject);
	$outreaches = searchForField($values->fields, "outreach__c", "name");
	$positions	= searchForField($values->fields, "Type_of_Volunteer__c", "name");
	//print_r($outreaches);
	//print_r($positions);
	
	foreach($outreaches->picklistValues as $outreach){
		echo "<h1>" . $outreach->value . "</h1>";
		foreach($positions->picklistValues as $position){
			echo "<h3>" . $position->value . "</h3>";
			//getNeededApplicantsForPosition($outreach->value, $position->value);
		}
	}
}

function searchForField($myObjects, $value, $key){
	$item = null;
	foreach($myObjects as $struct) {
	    if ($value == $struct->$key) {
	        $item = $struct;
			return $item;
	        break;
	    }
	}
	return false;
}

function outputOpportunities($fields, $object){
	
	//Output all available opportunities
	$client = getConnection();
	$values = $client->describeSObject($object);

	$opportunities = array();
	foreach($fields as $field){
		$items 	= searchForField($values->fields, $field, "name");
		foreach($items->picklistValues as $item){
			$opportunities[] = $item;
		}
	}
	return $opportunities;
}

function showOpportunityMenu($selected, $fields, $object){
	echo "<form action='cron.php' method='post'>";
	
	//Output all available opportunities
	$client = getConnection();
	$values = $client->describeSObject($object);

	foreach($fields as $field){
		$items 	= searchForField($values->fields, $field, "name");
		echo "<select name='".$field."'>\n";
		echo "\t<option value=''>---Select a Value---</option>\n";
		foreach($items->picklistValues as $item){
			$selected = ($item->value == $selected ? 'selected' : '');
			echo "\t<option value='" . $item->value . "' ". $selected .">" . $item->label . "</option>\n";
		}
		echo "\t<option value='showAll'>Show all on one page</option>\n";
		echo "</select>\n";
	}
	
	echo "<input type='submit' value='Submit' />\n";
	echo "</form>\n";
}


function getContactPhotosForOpportunity($opp){
	global $searchObject, $searchFieldName;

	$client = getConnection();
	$results = $client->query("SELECT ContactId FROM OpportunityContactRole 
								WHERE OpportunityId in (SELECT Id FROM Opportunity 
								WHERE $searchFieldName = '$opp')");
	
	//Display photos for our contact ID's
	if ($results->size != 0){
		$ids = array();
		foreach($results->records as $record){
			$ids[] = $record->fields->ContactId;
		}
	
		echo "<h1>" . $opp . "</h1>";
		getPhotos($ids);
	} else {
		echo "<h1>" . $opp . "</h1>";
		echo "Sorry looks like no one has been added to " . $opp . " yet.";
	}
}

function getNeededApplicantsForPosition($outreach, $volunteerType){
	
	$client = getConnection();
	$results = $client->query("SELECT ContactId FROM OpportunityContactRole 
								WHERE OpportunityId in (SELECT Id FROM Opportunity 
								WHERE Type_of_Volunteer__c INCLUDES ('$volunteerType') 
								AND outreach__c = '$outreach' AND StageName = 'Approved Applicant')");

	//TODO add logic to get the target value of positions minus actual positions filled
}


function getPhotos($contactids){
	$mySforceConnection = getConnection();
	$query = "SELECT Id, Name, ParentId from Attachment Where (ParentId in (";
	foreach($contactids as $id){
		$query .= "'". $id . "',";
	}
	$query = substr($query , 0, strlen($query) - 1);
	$query .= ") AND Name = 'Contact Picture') ";
	$queryResult = $mySforceConnection->query($query);
	$records = $queryResult->records;
	if(count($records) > 0){
		echo "\n<table cellspacing='5' cellpadding='5'>\n\t<tr>\n";
		foreach($records as $rec){
			$name = $mySforceConnection->retrieve('Name', 'Contact', $rec->fields->ParentId);
			echo "\t\t<td style='text-align:center;font-size:12px;'>\n".
					"\t\t\t<img src='image.php?id=".$rec->Id."' ".
					"style='width:200px;height:200px;border:1px solid #ccc;padding:10px'/> <br/>\n\t\t\t"
					. $name[0]->fields->Name. 
				 "\n\t\t</td>\n";
			 "\t</tr>\n";
		}
		echo "</table>\n";
	}
	return NULL;
}

?>
</pre>