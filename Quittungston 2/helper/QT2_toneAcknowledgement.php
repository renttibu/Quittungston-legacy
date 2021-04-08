<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Quittungston/tree/master/Quittungston%202
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait QT2_toneAcknowledgement
{
    public function ExecuteToneAcknowledgement(int $AcousticSignal, bool $UseSwitchingDelay = false): bool
    {
        /*
         * $AcousticSignal
         * 0    = off
         * 1    = low battery
         * 2    = disarmed
         * 3    = internally armed
         * 4    = externally armed
         * 5    = delayed internally armed
         * 6    = delayed externally armed
         * 7    = event
         * 8    = error
         */

        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        $result = false;
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        if ($this->CheckMuteMode()) {
            return $result;
        }
        // Semaphore Enter
        if (!IPS_SemaphoreEnter($this->InstanceID . '.AcousticSignal', 5000)) {
            return $result;
        }
        $id = $this->ReadPropertyInteger('AlarmSiren');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $result = true;
            if ($UseSwitchingDelay) {
                IPS_Sleep($this->ReadPropertyInteger('AlarmSirenSwitchingDelay'));
            }
            $actualAcousticSignal = $this->GetValue('AcousticSignal');
            $this->SetValue('AcousticSignal', $AcousticSignal);
            $acousticSignalList = [0 => 'Aus', 1 => 'Batterie leer', 2 => 'Unscharf', 3 => 'Intern Scharf', 4 => 'Extern Scharf', 5 => 'Intern verzögert Scharf', 6 => 'Extern verzögert Scharf', 7 => 'Ereignis', 8 => 'Fehler'];
            $acousticSignalName = 'Wert nicht vorhanden!';
            if (array_key_exists($AcousticSignal, $acousticSignalList)) {
                $acousticSignalName = $acousticSignalList[$AcousticSignal];
            }
            switch ($AcousticSignal) {
                case 0: # Off
                    $acousticAlarmSelection = 0;
                    break;

                case 1: # Low battery
                    $acousticAlarmSelection = 10;
                    break;

                case 2: # Disarmed
                    $acousticAlarmSelection = 11;
                    break;

                case 3: # Internally armed
                    $acousticAlarmSelection = 12;
                    break;

                case 4: # Externally armed
                    $acousticAlarmSelection = 13;
                    break;

                case 5: # Delayed internally armed
                    $acousticAlarmSelection = 14;
                    break;

                case 6: # Delayed externally armed
                    $acousticAlarmSelection = 15;
                    break;

                case 7: # Event
                    $acousticAlarmSelection = 16;
                    break;

                case 8: # Error
                    $acousticAlarmSelection = 17;
                    break;

                default:
                    return false;
            }
            $this->SendDebug(__FUNCTION__, 'Akustisches Signal: ' . $AcousticSignal . ' - ' . $acousticSignalName, 0);
            $parameter1 = @HM_WriteValueInteger($id, 'DURATION_UNIT', 0);
            $parameter2 = @HM_WriteValueInteger($id, 'DURATION_VALUE', 3);
            $parameter3 = @HM_WriteValueInteger($id, 'OPTICAL_ALARM_SELECTION', 0);
            $parameter4 = @HM_WriteValueInteger($id, 'ACOUSTIC_ALARM_SELECTION', $acousticAlarmSelection);
            if (!$parameter1 || !$parameter2 || !$parameter3 || !$parameter4) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $parameter1 = @HM_WriteValueInteger($id, 'DURATION_UNIT', 0);
                $parameter2 = @HM_WriteValueInteger($id, 'DURATION_VALUE', 3);
                $parameter3 = @HM_WriteValueInteger($id, 'OPTICAL_ALARM_SELECTION', 0);
                $parameter4 = @HM_WriteValueInteger($id, 'ACOUSTIC_ALARM_SELECTION', $AcousticSignal);
                if (!$parameter1 || !$parameter2 || !$parameter3 || !$parameter4) {
                    $result = false;
                    // Revert
                    $this->SetValue('AcousticSignal', $actualAcousticSignal);
                    $errorMessage = 'Quittungston ' . $AcousticSignal . ' - ' . $acousticSignalName . ' konnte nicht ausgegeben werden!';
                    $this->SendDebug(__FUNCTION__, $errorMessage, 0);
                    $errorMessage = 'ID ' . $id . ' , ' . $errorMessage;
                    $this->LogMessage($errorMessage, KL_ERROR);
                }
            }
        }
        // Semaphore leave
        IPS_SemaphoreLeave($this->InstanceID . '.AcousticSignal');
        return $result;
    }

    public function CheckTrigger(int $SenderID, bool $ValueChanged): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        $this->SendDebug(__FUNCTION__, 'Sender: ' . $SenderID . ', Wert hat sich geändert: ' . json_encode($ValueChanged), 0);
        if ($this->CheckMaintenanceMode()) {
            return false;
        }
        if ($this->CheckMuteMode()) {
            return false;
        }
        $vars = json_decode($this->ReadPropertyString('TriggerVariables'));
        if (empty($vars)) {
            return false;
        }
        $result = false;
        foreach ($vars as $var) {
            $execute = false;
            $id = $var->ID;
            if ($id != 0 && @IPS_ObjectExists($id)) {
                if ($var->Use && $SenderID == $id) {
                    $this->SendDebug(__FUNCTION__, 'Variable: ' . $id . ' ist aktiviert', 0);
                    $type = IPS_GetVariable($id)['VariableType'];
                    $value = $var->Value;
                    $acousticSignal = $var->AcousticSignal;
                    switch ($var->Trigger) {
                        case 0: # on change (bool, integer, float, string)
                            $this->SendDebug(__FUNCTION__, 'Bei Änderung (bool, integer, float, string)', 0);
                            if ($ValueChanged) {
                                $execute = true;
                            }
                            break;

                        case 1: # on update (bool, integer, float, string)
                            $this->SendDebug(__FUNCTION__, 'Bei Aktualisierung (bool, integer, float, string)', 0);
                            $execute = true;
                            break;

                        case 2: # on limit drop, once (integer, float)
                            switch ($type) {
                                case 1: # integer
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                                    if ($ValueChanged) {
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if ($value == 'true') {
                                            $value = '1';
                                        }
                                        if (GetValueInteger($SenderID) < intval($value)) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                                case 2: # float
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                                    if ($ValueChanged) {
                                        if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                            }
                            break;

                        case 3: # on limit drop, every time (integer, float)
                            switch ($type) {
                                case 1: # integer
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if ($value == 'true') {
                                        $value = '1';
                                    }
                                    if (GetValueInteger($SenderID) < intval($value)) {
                                        $execute = true;
                                    }
                                    break;

                                case 2: # float
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                                    if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                                        $execute = true;
                                    }
                                    break;

                            }
                            break;

                        case 4: # on limit exceed, once (integer, float)
                            switch ($type) {
                                case 1: # integer
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                                    if ($ValueChanged) {
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if ($value == 'true') {
                                            $value = '1';
                                        }
                                        if (GetValueInteger($SenderID) > intval($value)) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                                case 2: # float
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                                    if ($ValueChanged) {
                                        if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                            }
                            break;

                        case 5: # on limit exceed, every time (integer, float)
                            switch ($type) {
                                case 1: # integer
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if ($value == 'true') {
                                        $value = '1';
                                    }
                                    if (GetValueInteger($SenderID) > intval($value)) {
                                        $execute = true;
                                    }
                                    break;

                                case 2: # float
                                    $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                                    if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                                        $execute = true;
                                    }
                                    break;

                            }
                            break;

                        case 6: # on specific value, once (bool, integer, float, string)
                            switch ($type) {
                                case 0: # bool
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (bool)', 0);
                                    if ($ValueChanged) {
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if (GetValueBoolean($SenderID) == boolval($value)) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                                case 1: # integer
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (integer)', 0);
                                    if ($ValueChanged) {
                                        if ($value == 'false') {
                                            $value = '0';
                                        }
                                        if ($value == 'true') {
                                            $value = '1';
                                        }
                                        if (GetValueInteger($SenderID) == intval($value)) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                                case 2: # float
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (float)', 0);
                                    if ($ValueChanged) {
                                        if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                                case 3: # string
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (string)', 0);
                                    if ($ValueChanged) {
                                        if (GetValueString($SenderID) == (string) $value) {
                                            $execute = true;
                                        }
                                    }
                                    break;

                            }
                            break;

                        case 7: # on specific value, every time (bool, integer, float, string)
                            switch ($type) {
                                case 0: # bool
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (bool)', 0);
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if (GetValueBoolean($SenderID) == boolval($value)) {
                                        $execute = true;
                                    }
                                    break;

                                case 1: # integer
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (integer)', 0);
                                    if ($value == 'false') {
                                        $value = '0';
                                    }
                                    if ($value == 'true') {
                                        $value = '1';
                                    }
                                    if (GetValueInteger($SenderID) == intval($value)) {
                                        $execute = true;
                                    }
                                    break;

                                case 2: # float
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (float)', 0);
                                    if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                                        $execute = true;
                                    }
                                    break;

                                case 3: # string
                                    $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (string)', 0);
                                    if (GetValueString($SenderID) == (string) $value) {
                                        $execute = true;
                                    }
                                    break;

                            }
                            break;

                    }
                    $this->SendDebug(__FUNCTION__, 'Bedingung erfüllt: ' . json_encode($execute), 0);
                    if ($execute) {
                        $result = $this->ExecuteToneAcknowledgement($acousticSignal, true);
                    }
                }
            }
        }
        return $result;
    }
}