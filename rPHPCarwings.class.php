<?php
/*

rPHPCarwings.class.php - PHP class for communicating with Nissan Carwings XML-RPC service
Copyright (C) 2014 Jon Tungland // runnane.no

This file is part of rPHPCarwings.

rPHPCarwings is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

rPHPCarwings is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with rPHPCarwings.  If not, see <http://www.gnu.org/licenses/>.
 
*/

if (version_compare(phpversion(), '5.0.0', '<')) {
    die("Please upgrade, you need PHP version 5+");
}
if(!function_exists("simplexml_load_string")){
	die("SimpleXML module not found");	
}


class rPHPCarwings{
	
	private $Username		= "";
	private $Password		= "";
	
	private $Locale			= "US";
	private $AppVersion		= "1.8";
	private $SmartphoneType	= "IPHONE";
	
	private $userService 	= "https://mobileapps.prod.nissan.eu/android-carwings-backend-v2/2.0/carwingsServlet";
	private $userAgent 		= "Dalvik/1.6.0 (Linux; U; Android 4.4.2; HTC One_M8 Build/KOT49H)";
	
	private $sessionId;
	
	private $Vin;
	private $Nickname;
	
	
	
	public function __construct($username,$password){
		$this->Username = $username;
		$this->Password = $password;
	}
	
	public function Login(){
		
		$xml = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
		<ns2:SmartphoneLoginWithAdditionalOperationRequest xmlns:ns2="urn:com:airbiquity:smartphone.userservices:v1" 
		 xmlns:ns3="http://www.nissanusa.com/owners/schemas/api/0" xmlns:ns4="urn:com:hitachi:gdc:type:report:v1" 
		 xmlns:ns5="urn:com:airbiquity:smartphone.reportservice:v1" xmlns:ns6="urn:com:hitachi:gdc:type:vehicle:v1" 
		 xmlns:ns7="urn:com:airbiquity:smartphone.vehicleservice:v1">
		  <SmartphoneLoginInfo>
			<UserLoginInfo>
			  <userId>' . $this->Username . '</userId>
			  <userPassword>'.  $this->Password . '</userPassword>
			</UserLoginInfo>
			<DeviceToken>' . "DUMMY" . microtime(true) . '</DeviceToken>
			<UUID>rPHPCarwings:' . $this->Username . '</UUID>
			<Locale>' . $this->Locale . '</Locale>
			<AppVersion>' . $this->AppVersion . '</AppVersion>
			<SmartphoneType>' . $this->SmartphoneType . '</SmartphoneType>
		  </SmartphoneLoginInfo>
		  <SmartphoneOperationType>SmartphoneGetPreferencesRequest</SmartphoneOperationType>
		  <SmartphoneOperationType>SmartphoneLatestBatteryStatusRequest</SmartphoneOperationType>
		  <SmartphoneOperationType>SmartphoneLatestACStatusRequest</SmartphoneOperationType>
		</ns2:SmartphoneLoginWithAdditionalOperationRequest>';

		$headers = array();
		$headers[] = "Content-Type: text/xml";
		$headers[] = "Accept-Encoding: gzip, deflate";
		$headers[] = "Connection: keep-alive";
		$headers[] = "Expect:";
		
		$response = $this->DoRPCCall($this->userService, $headers, $xml);
		
		preg_match_all('/^Set-Cookie: (.*?)=(.*?)$/sm', $response['header'], $m);
		if(!is_array($m) || !is_array($m[0])){
			throw new Exception("Could not log in, cookies not found in response header");	
		}
		
		for($i = 0; $i < count($m[0]); $i++){
			if($m[1][$i] == "JSESSIONID"){
				$valueparts = explode(";", $m[2][$i]);
				$this->sessionId = trim($valueparts[0]);
			}
		}
		
		if(!$this->sessionId){
			throw new Exception("Could not log in, JSESSION not returned by RPC Server");	
		}

		$xmlResponse = simplexml_load_string($response['content']);
		if($xmlResponse === FALSE){
			throw new Exception("Could not parse XML from RPC server.");	
		}
		
		$vars =  $this->Parse_SmartphoneLatestBatteryStatusResponse($xmlResponse->SmartphoneLatestBatteryStatusResponse);

		$vars['Vin'] = (string)$xmlResponse->SmartphoneUserInfoType->VehicleInfo->Vin;
		$this->Vin = $vars['Vin'];

		$vars['Nickname'] = (string)$xmlResponse->SmartphoneUserInfoType->Nickname;
		$this->Nickname = $vars['Nickname'];

		return $vars;
		
	}
	
	private function Parse_SmartphoneLatestBatteryStatusResponse($xml){
		$vars = array();
		$vars['OperationResult'] = (string)$xml->SmartphoneBatteryStatusResponseType->BatteryStatusRecords->OperationResult;
		$vars['OperationDateAndTime'] = (string)$xml->SmartphoneBatteryStatusResponseType->BatteryStatusRecords->OperationDateAndTime;
		
		$vars['BatteryChargingStatus'] = (string)$xml->SmartphoneBatteryStatusResponseType->BatteryStatusRecords->BatteryStatus->BatteryChargingStatus;
		$vars['BatteryCapacity'] = (int)$xml->SmartphoneBatteryStatusResponseType->BatteryStatusRecords->BatteryStatus->BatteryCapacity;
		$vars['BatteryRemainingAmount'] = (int)$xml->SmartphoneBatteryStatusResponseType->BatteryStatusRecords->BatteryStatus->BatteryRemainingAmount;
		
		$vars['PluginState'] = (string)$xml->SmartphoneBatteryStatusResponseType->BatteryStatusRecords->PluginState;
		$vars['CruisingRangeAcOn'] = (int)$xml->SmartphoneBatteryStatusResponseType->BatteryStatusRecords->CruisingRangeAcOn;
		$vars['CruisingRangeAcOff'] = (int)$xml->SmartphoneBatteryStatusResponseType->BatteryStatusRecords->CruisingRangeAcOff;
		$vars['ChargeMode'] = (string)$xml->SmartphoneBatteryStatusResponseType->BatteryStatusRecords->ChargeMode;
		
		$vars['lastBatteryStatusCheckExecutionTime'] = (string)$xml->SmartphoneBatteryStatusResponseType->lastBatteryStatusCheckExecutionTime;
		return $vars;
	}
	
	public function RequestStatusUpdate(){
		if(!$this->sessionId){
			throw new Exception("Cannot request, not logged in");	
		}
		if(!$this->Vin){
			throw new Exception("Cannot request, vehicle identification number not found");	
		}
		$headers = array();
		$headers[] = "Cookie: JSESSIONID=".$this->sessionId;
		$headers[] = "Content-Type: text/xml";
		$headers[] = "Accept-Encoding: gzip, deflate";
		$headers[] = "Connection: keep-alive";
		$headers[] = "Expect:";
		
		$xml = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
		<ns4:SmartphoneRemoteBatteryStatusCheckRequest 
		 xmlns:ns4="urn:com:airbiquity:smartphone.vehicleservice:v1" 
		 xmlns:ns3="urn:com:hitachi:gdc:type:vehicle:v1" 
		 xmlns:ns2="urn:com:hitachi:gdc:type:portalcommon:v1">
		 <ns3:BatteryStatusCheckRequest>
		  <ns3:VehicleServiceRequestHeader>
		   <ns2:VIN>' . $this->Vin . '</ns2:VIN>
		  </ns3:VehicleServiceRequestHeader>
		 </ns3:BatteryStatusCheckRequest>
		</ns4:SmartphoneRemoteBatteryStatusCheckRequest>';
		
		$response = $this->DoRPCCall($this->userService, $headers, $xml);

		$xmlResponse = simplexml_load_string($response['content']);
		if($xmlResponse === FALSE){
			throw new Exception("Could not parse XML from RPC server.");	
		}
		
		return $xmlResponse;

	}
	
	public function GetVechicleInfo(){
		if(!$this->sessionId){
			throw new Exception("Cannot request, not logged in");	
		}
		if(!$this->Vin){
			throw new Exception("Cannot request, vehicle identification number not found");	
		}
		$headers = array();
		$headers[] = "Cookie: JSESSIONID=".$this->sessionId;
		$headers[] = "Content-Type: text/xml";
		$headers[] = "Accept-Encoding: gzip, deflate";
		$headers[] = "Connection: keep-alive";
		$headers[] = "Expect:";

		$xml = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
		<ns2:SmartphoneGetVehicleInfoRequest 
		 xmlns:ns2="urn:com:airbiquity:smartphone.userservices:v1" 
		 xmlns:ns3="http://www.nissanusa.com/owners/schemas/api/0" 
		 xmlns:ns4="urn:com:hitachi:gdc:type:report:v1" 
		 xmlns:ns5="urn:com:airbiquity:smartphone.reportservice:v1" 
		 xmlns:ns6="urn:com:hitachi:gdc:type:vehicle:v1" 
		 xmlns:ns7="urn:com:airbiquity:smartphone.vehicleservice:v1">
		 <VehicleInfo>
		  <Vin>' . $this->Vin . '</Vin>
		 </VehicleInfo>
		 <SmartphoneOperationType>SmartphoneLatestBatteryStatusRequest</SmartphoneOperationType>
		 <SmartphoneOperationType>SmartphoneLatestACStatusRequest</SmartphoneOperationType>
		 <SmartphoneOperationType>SmartphoneGetPreferencesRequest</SmartphoneOperationType>
		 <changeVehicle>false</changeVehicle>
		</ns2:SmartphoneGetVehicleInfoRequest>';

		$response = $this->DoRPCCall($this->userService, $headers, $xml);

		$xmlResponse = simplexml_load_string($response['content']);
		if($xmlResponse === FALSE){
			throw new Exception("Could not parse XML from RPC server.");	
		}
		return $this->Parse_SmartphoneLatestBatteryStatusResponse($xmlResponse->SmartphoneLatestBatteryStatusResponse);
	}


	private function DoRPCCall($url, $post_headers, $post_fields){
		$ch = curl_init( $url );
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
		curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $post_headers); 
		curl_setopt($ch, CURLOPT_VERBOSE, 0); 
	    $response = curl_exec($ch);   
        curl_close($ch);
		list($header, $content) = explode("\r\n\r\n", $response, 2);
		if(strpos($header, "HTTP/1.1 200 OK") !== 0 ){
			throw new Exception("Unexpected response from RPC server");	
		}
		return array('header' => $header, 'content' => $content);
	}
	
}

?>