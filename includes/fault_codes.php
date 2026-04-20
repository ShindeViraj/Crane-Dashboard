<?php
/**
 * Fault Code Mapping Array
 * Maps the decimal fault codes received from the PLC to their human-readable Yaskawa descriptions.
 */

global $faultMap;
$faultMap = [
    2  => 'Uv1 [DC Bus Undervoltage]',
    3  => 'Uv2 [Control Power Undervoltage]',
    4  => 'Uv3 [Soft Charge Answerback Fault]',
    5  => 'SC [Short Circuit/IGBT Failure]',
    6  => 'GF [Ground Fault]',
    7  => 'oC [Overcurrent]',
    8  => 'ov [Overvoltage]',
    9  => 'oH [Heatsink Overheat]',
    10 => 'oH1 [Heatsink Overheat]',
    11 => 'oL1 [Motor Overload]',
    12 => 'oL2 [Drive Overload]',
    13 => 'oL3 [Overtorque Detection 1]',
    14 => 'oL4 [Overtorque Detection 2]',
    15 => 'rr [Dynamic Braking Transistor Fault]',
    16 => 'rH [Braking Resistor Overheat]',
    17 => 'EF3 [External Fault (Terminal S3)]',
    18 => 'EF4 [External Fault (Terminal S4)]',
    19 => 'EF5 [External Fault (Terminal S5)]',
    20 => 'EF6 [External Fault (Terminal S6)]',
    21 => 'EF7 [External Fault (Terminal S7)]',
    22 => 'EF8 [External Fault (Terminal S8)]',
    23 => 'FAn [Internal Fan Fault]',
    24 => 'oS [Overspeed]',
    25 => 'dEv [Speed Deviation]',
    26 => 'PGo [Encoder (PG) Feedback Loss]',
    27 => 'PF [Input Phase Loss]',
    28 => 'LF [Output Phase Loss]',
    29 => 'oH3 [Motor Overheat (PTC Input)]',
    30 => 'oPr [Keypad Connection Fault]',
    31 => 'Err [EEPROM Write Error]',
    32 => 'oH4 [Motor Overheat Fault (PTC Input)]',
    33 => 'CE [Modbus Communication Error]',
    34 => 'bUS [Option Communication Error]',
    37 => 'CF [Control Fault]',
    38 => 'SvE [Zero Servo Fault]',
    39 => 'EF0 [Option Card External Fault]',
    40 => 'FbL [PID Feedback Loss]',
    41 => 'UL3 [Undertorque Detection 1]',
    42 => 'UL4 [Undertorque Detection 2]',
    43 => 'oL7 [High Slip Braking Overload]',
    48 => 'Includes oFx Fault [Hardware Fault]',
    50 => 'dv1 [Z Pulse Fault]',
    51 => 'dv2 [Z Pulse Noise Fault Detection]',
    52 => 'dv3 [Inversion Detection]',
    53 => 'dv4 [Inversion Prevention Detection]',
    54 => 'LF2 [Output Current Imbalance]',
    55 => 'STPo [Motor Step-Out Detected]',
    56 => 'PGoH [Encoder (PG) Hardware Fault]',
    57 => 'E5 [MECHATROLINK Watchdog Timer Err]',
    59 => 'SEr [Speed Search Retries Exceeded]',
];

function getFaultCodeDescription($decimalCode) {
    global $faultMap;
    if (!$decimalCode || $decimalCode == 0) return 'No Fault';

    if (isset($faultMap[$decimalCode])) {
        return $faultMap[$decimalCode];
    }
    
    return "Unknown Fault [Code: $decimalCode]";
}
?>
