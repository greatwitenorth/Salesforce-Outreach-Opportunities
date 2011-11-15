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

global $sf_db_version;
$sf_db_version = "1.0";
register_activation_hook(__FILE__,'outreach_install');
register_activation_hook(__FILE__,'update_data');
add_action('plugins_loaded', 'sf_db_check');

add_action('activated_plugin','save_error');
function save_error(){
    update_option('plugin_error',  ob_get_contents());
}

//Set our custom field name and object it belongs to
$searchFieldName 	= "outreach__c";
$searchObject 		= "Opportunity";

function sf_db_check() {
    global $sf_db_version;
    if (get_site_option('sf_db_version') != $sf_db_version) {
        outreach_install();
    }
}

function outreach_install(){
	global $wpdb;
	global $sf_db_version;
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

	$table_name = $wpdb->prefix . "outreaches";
	$sql = "CREATE TABLE " . $table_name . "  (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	  active tinyint(1) DEFAULT NULL,
	  defaultValue tinyint(1) DEFAULT NULL,
	  label varchar(255) DEFAULT NULL,
	  value varchar(255) DEFAULT NULL,
	  display tinyint(1) DEFAULT NULL,
	  PRIMARY KEY  (id)
	) ;";
	dbDelta($sql);
	
	$table_name = $wpdb->prefix . "outreach_positions";
	$sql = "CREATE TABLE " . $table_name . "  (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	  active tinyint(1) DEFAULT NULL,
	  defaultValue tinyint(1) DEFAULT NULL,
	  label varchar(255) DEFAULT NULL,
	  value varchar(255) DEFAULT NULL,
	  PRIMARY KEY  (id)
	) ;";
	dbDelta($sql);

	$table_name = $wpdb->prefix . "outreach_positions_join";
	$sql = "CREATE TABLE " . $table_name . "  (
	  id mediumint(9) NOT NULL AUTO_INCREMENT,
	  time timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	  outreach_id int(11) DEFAULT NULL,
	  position_id int(11) DEFAULT NULL,
	  target int(11) DEFAULT NULL,
	  approved int(11) DEFAULT NULL,
	  advertise tinyint(1) DEFAULT NULL,
	  PRIMARY KEY  (id)
	) ;";
	dbDelta($sql);
	add_option("sf_db_version", $sf_db_version);
}

function update_data(){
	global $wpdb;
	$options = get_option('outreach_options');
	$outreaches = outputOpportunities(array("outreach__c"), "Opportunity");
	$positions = outputOpportunities(array("Type_of_Volunteer__c"), "Opportunity");
	$outreach_table_name = $wpdb->prefix . "outreaches";
	$position_table_name = $wpdb->prefix . "outreach_positions";
	$join_table_name = $wpdb->prefix . "outreach_positions_join";
	
	foreach ($outreaches as $outreach){
		$query = "SELECT id FROM " . $outreach_table_name . " WHERE label = '" . $outreach->label . "'";
		if($wpdb->query($query) === 0){
			$rows_affected = $wpdb->insert( $outreach_table_name, array( 'active' => $outreach->active, 'defaultValue' => $outreach->defaultValue, 
															'label' => $outreach->label, 'value' => $outreach->value,
															'display' => true));
		}
	}
	
	foreach ($positions as $position){
		$query = "SELECT id FROM " . $position_table_name . " WHERE label = '" . $position->label . "'";
		if($wpdb->query($query) === 0){
			$rows_affected = $wpdb->insert( $position_table_name, array( 'active' => $position->active, 'defaultValue' => $position->defaultValue, 
															'label' => $position->label, 'value' => $position->value));
		}
	}
	
	//Lets get all approved applicant from salesforce and put them in our join table
	foreach ($outreaches as $outreach){
		foreach ($positions as $position){
			$approved = getApprovedApplicantsForPosition($outreach->label, $position->label);
			$oresults = $wpdb->get_row(
				"
				SELECT * from $outreach_table_name
				WHERE label = '$outreach->label'
				"
			);
			
			$presults = $wpdb->get_row(
				"
				SELECT * from $position_table_name
				WHERE label = '$position->label'
				"
			);
			
			$query = $wpdb->get_row(
				"
				SELECT * FROM $join_table_name 
				WHERE outreach_id = $oresults->id 
				AND position_id = $presults->id
				");
			if(!$query){
				$rows_affected = $wpdb->insert( $join_table_name, array( 'advertise' => false, 'approved' => $approved, 
																'target' => 0, 'outreach_id' => $oresults->id,
																'position_id' => $presults->id));
			}
			
			$qapproved = isset($query->approved) ? $query->approved : 0;
			if($approved != $qapproved){
				$rows_affected = $wpdb->update( $join_table_name, array( 'approved' => $approved ),
																  array( 'outreach_id' => $oresults->id,
																  'position_id' => $presults->id));				
			}
		}
	}
}

function plugin_admin_init() {
	register_setting( 'outreach_options', 'outreach_options' );
	output_all_setting_fields();

}

function plugin_admin_add_page() {
	add_options_page('Outreach Opportunities', 'Outreach Opportunities', 'manage_options', 'sfdc', 'sfdc_options_page');
}

function output_all_setting_fields(){
	global $wpdb;
	$outreach_table_name = $wpdb->prefix . "outreaches";
	$position_table_name = $wpdb->prefix . "outreach_positions";
	$join_table_name = $wpdb->prefix . "outreach_positions_join";
	
	$outreaches = $wpdb->get_results( 
		"
		SELECT * 
		FROM $outreach_table_name
		WHERE active = true 
			AND display = true
		"
	);
	
	$positions = $wpdb->get_results( 
		"
		SELECT * 
		FROM $position_table_name
		WHERE active = true 
		"
	);
	
	foreach($outreaches as $key => $outreach){
		add_settings_section($outreach->label, $outreach->label, 'outreach_section_text', 'outreach_options');
		foreach($positions as $position){
			$value = '';
			$check_value = '';
			$target = '';
			$data = $wpdb->get_row( 
				"
				SELECT * FROM $join_table_name a
				INNER JOIN $outreach_table_name b 
				ON b.id = a.outreach_id
				INNER JOIN $position_table_name c
				ON a.position_id = c.id
				WHERE a.outreach_id = $outreach->id
				AND a.position_id = $position->id
				"
			);
			
			if(isset($data->advertise)){
				if($data->advertise == true){
					$check_value = "checked=yes";
				}
			}
			if(isset($data->target)){
				$target = $data->target;
			}
			add_settings_field( $outreach->value."-".$position->value, $position->value, 'outreach_setting_field', 'outreach_options',
									$outreach->label, array('label_for' => $outreach->value."-".$position->value, 'id' => array(
									'outreach' => $outreach->value, 'position' => $position->value, 
									'checked' => $data->advertise, 'target' => $data->target, 'approved' => $data->approved ) ));
		}
	}
}

function outreach_setting_field($input) {
	$outreach 	= $input['id']['outreach'];
	$position	= $input['id']['position'];
	$value	 	= $input['id']['target'];
	$checked 	= $input['id']['checked'];
	$approved 	= $input['id']['approved'];
		
	echo "<input id='advertise-".$outreach."-".$position."' name='outreach_options[".$outreach."][".$position."][checked]'
//			type='checkbox' value='true' $checked />";
	echo "<input id='".$outreach."-".$position."' name='outreach_options[".$position."][target]' size='5' 
			type='text' value='$value' />";
	echo "Approved: ".$approved;
}

function outreach_section_text() {
	echo "<input name='Submit' type='submit' value='Save all changes' />";
}

function sfdc_options_page(){
	global $wpdb;
	include(SFDC_PATH . '/options.php');
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

function getApprovedApplicantsForPosition($outreach, $volunteerType){
	
	$client = getConnection();
	$results = $client->query("SELECT ContactId FROM OpportunityContactRole 
								WHERE OpportunityId in (SELECT Id FROM Opportunity 
								WHERE Type_of_Volunteer__c INCLUDES ('$volunteerType') 
								AND outreach__c = '$outreach' AND StageName = 'Approved Applicant')");

	//TODO add logic to get the target value of positions minus actual positions filled
	return $results->size;
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