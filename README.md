# rPHPCarwings #

Quickly hacked script to access carwings api. Tested on EU account. 

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

Array
(
    [OperationResult] => OK
    [OperationDateAndTime] => 2014-06-19T10:54:30.0
    [BatteryChargingStatus] => false
    [BatteryCapacity] => 12
    [BatteryRemainingAmount] => 12
    [PluginState] => CONNECTED
    [CruisingRangeAcOn] => 144144
    [CruisingRangeAcOff] => 150696
    [ChargeMode] => not_charging
    [lastBatteryStatusCheckExecutionTime] => 2014-06-19T10:54:28.000Z
)



### Sources/Links ###

* http://electricvehiclewiki.com/Carwings_protocol
* http://www.telerik.com/fiddler
* http://docs.telerik.com/fiddler/configure-fiddler/tasks/configureforandroid