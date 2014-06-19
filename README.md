# rPHPCarwings #

### Disclaimer ###
I am not responsible for any problems, crashes, failures or pain this piece of software will cause. Use it on your own risk.

### About ###
PHP class for communicating with Nissan Carwings XML-RPC service
Quickly hacked, with minimal error checking - so keep that in mind. Tested on an norwegian leaf car account. 

### Known bugs/limitations ###

* Unsure on how it will handle server problems, multiple cars on one account, NON-EU accounts
* Minimal errorchecking

### Example code ###

```
#!php
<?php
require_once("rPHPCarwings.class.php");

$cw = new rPHPCarwings("myUsername","myPassword");
$inital_vars = $cw->Login();
print_r($inital_vars);
$cw->RequestStatusUpdate();
$vars = $cw->GetVechicleInfo();

while($vars['OperationResult'] == "PENDING"){
	sleep(10);
	$vars = $cw->GetVechicleInfo();
}

print_r($vars);
?>
```

This should result in something like this:
```
Array
(
    [OperationResult] => OK
    [OperationDateAndTime] => 2014-06-19T12:53:15.0
    [BatteryCapacity] => 12
    [BatteryRemainingAmount] => 12
    [PluginState] => CONNECTED
    [ChargeMode] => 220V
    [BatteryChargingStatus] => true
    [CruisingRangeAcOn] => 144144
    [CruisingRangeAcOff] => 150696
    [lastBatteryStatusCheckExecutionTime] => 2014-06-19T12:53:05.000CEST
    [SecondsSinceUpdate] => 150
    [Vin] => **REMOVED**
    [Nickname] => MyLeaf

)
```

### References/Links ###

* http://electricvehiclewiki.com/Carwings_protocol
* http://www.telerik.com/fiddler
* http://docs.telerik.com/fiddler/configure-fiddler/tasks/configureforandroid