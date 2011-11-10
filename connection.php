<?php
// SOAP_CLIENT_BASEDIR - folder that contains the PHP Toolkit and your WSDL
// $USERNAME - variable that contains your Salesforce.com username (must be in the form of an email)
// $PASSWORD - variable that contains your Salesforce.ocm password

@define('SOAP_CLIENT_BASEDIR', dirname(__FILE__));
require_once (SOAP_CLIENT_BASEDIR.'/soapclient/SforcePartnerClient.php');
require_once (SOAP_CLIENT_BASEDIR.'/soapclient/SforceHeaderOptions.php');

function getConnection(){
	$USERNAME='';
	$PASSWORD='';


	$mySforceConnection = new SforcePartnerClient();
	$mySforceConnection->createConnection(SOAP_CLIENT_BASEDIR.'/soapclient/partner.wsdl.xml');
	$mySforceConnection->login($USERNAME, $PASSWORD);
	return $mySforceConnection;
}

?>