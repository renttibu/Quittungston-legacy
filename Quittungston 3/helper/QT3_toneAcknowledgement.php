<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Quittungston/tree/master/Quittungston%203
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait QT3_toneAcknowledgement
{
    public function ExecuteToneAcknowledgement(int $AcousticSignal, bool $UseSwitchingDelay = false): bool
    {
        /*
         * $AcousticSignal
         * 0    = alarm off
         * 1    = externally armed
         * 2    = internally armed
         * 3    = alarm blocked
         */

        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt.', 0);
        $result = false;
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        if ($this->CheckMuteMode()) {
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
            $acousticSignalList = [0 => 'Alarm Aus', 1 => 'Extern scharf', 2 => 'Intern scharf', 3 => 'Alarm blockiert'];
            $acousticSignalName = 'Wert nicht vorhanden!';
            if (array_key_exists($AcousticSignal, $acousticSignalList)) {
                $acousticSignalName = $acousticSignalList[$AcousticSignal];
            }
            switch ($AcousticSignal) {
                case 0: # Alarm off
                    $armState = 0;
                    break;

                case 1: # Externally armed
                    $armState = 2;
                    break;

                case 2: # Internally armed
                    $armState = 1;
                    break;

                case 3: # Alarm blocked
                    $armState = 3;
                    break;

                default:
                    return false;
            }
            $this->SendDebug(__FUNCTION__, 'Akustisches Signal: ' . $AcousticSignal . ' - ' . $acousticSignalName, 0);
            // Semaphore Enter
            if (!IPS_SemaphoreEnter($this->InstanceID . '.AcousticSignal', 5000)) {
                return $result;
            }
            $execute = @HM_WriteValueInteger($id, 'ARMSTATE', $armState);
            if (!$execute) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $executeAgain = @HM_WriteValueInteger($id, 'ARMSTATE', $armState);
                if (!$executeAgain) {
                    $result = false;
                    //Revert
                    $this->SetValue('AcousticSignal', $actualAcousticSignal);
                    $errorMessage = 'Quittungston ' . $AcousticSignal . ' - ' . $acousticSignalName . ' konnte nicht ausgegeben werden!';
                    $this->SendDebug(__FUNCTION__, $errorMessage, 0);
                    $errorMessage = 'ID ' . $id . ' , ' . $errorMessage;
                    $this->LogMessage($errorMessage, KL_ERROR);
                }
            }
            // Semaphore leave
            IPS_SemaphoreLeave($this->InstanceID . '.AcousticSignal');
        }
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
                if ($var->Use) {
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