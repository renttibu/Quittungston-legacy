<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Quittungston/tree/master/Quittungston%201
 */

/** @noinspection PhpUnused */

declare(strict_types=1);

trait QT1_toneAcknowledgement
{
    public function ToggleToneAcknowledgement(bool $State, bool $UseSwitchingDelay = false): bool
    {
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
        $id = $this->ReadPropertyInteger('ToneAcknowledgementVariable');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $result = true;
            if ($UseSwitchingDelay) {
                IPS_Sleep($this->ReadPropertyInteger('ToneAcknowledgementVariableSwitchingDelay'));
            }
            $actualValue = $this->GetValue('AcousticSignal');
            $this->SetValue('AcousticSignal', $State);
            $this->SendDebug(__FUNCTION__, 'Akustisches Signal: ' . json_encode($State), 0);
            $toggle = @RequestAction($id, $State);
            if (!$toggle) {
                IPS_Sleep(self::DELAY_MILLISECONDS);
                $toggleAgain = @RequestAction($id, $State);
                if (!$toggleAgain) {
                    $result = false;
                    // Revert
                    $this->SetValue('AcousticSignal', $actualValue);
                    $stateName = 'aus';
                    if ($State) {
                        $stateName = 'ein';
                    }
                    $errorMessage = 'Quittungston konnte nicht ' . $stateName . 'geschaltet werden!';
                    $this->SendDebug(__FUNCTION__, $errorMessage, 0);
                    $errorMessage = 'ID ' . $id . ' , ' . $errorMessage;
                    $this->LogMessage($errorMessage, KL_ERROR);
                }
            }
            if ($State) {
                $impulseDuration = $this->ReadPropertyInteger('ImpulseDuration');
                if ($impulseDuration > 0) {
                    IPS_Sleep($impulseDuration);
                    $actualValue = $this->GetValue('AcousticSignal');
                    $this->SetValue('AcousticSignal', false);
                    $this->SendDebug(__FUNCTION__, 'Akustisches Signal: ' . json_encode(false), 0);
                    $toggle = @RequestAction($id, false);
                    if (!$toggle) {
                        IPS_Sleep(self::DELAY_MILLISECONDS);
                        $toggleAgain = @RequestAction($id, false);
                        if (!$toggleAgain) {
                            $result = false;
                            //Revert
                            $this->SetValue('AcousticSignal', $actualValue);
                            $errorMessage = 'Quittungston konnte nicht ausgeschaltet werden!';
                            $this->SendDebug(__FUNCTION__, $errorMessage, 0);
                            $errorMessage = 'ID ' . $id . ' , ' . $errorMessage;
                            $this->LogMessage($errorMessage, KL_ERROR);
                        }
                    }
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
        $vars = json_decode($this->ReadPropertyString('TriggerVariables'), true);
        if (empty($vars)) {
            return false;
        }
        $key = array_search($SenderID, array_column($vars, 'ID'));
        if (!is_int($key)) {
            return false;
        }
        if (!$vars[$key]['Use']) {
            return false;
        }
        $execute = false;
        $state = false;
        $type = IPS_GetVariable($SenderID)['VariableType'];
        $value = $vars[$key]['Value'];
        switch ($vars[$key]['Trigger']) {
            case 0: # on change (bool, integer, float, string)
                $this->SendDebug(__FUNCTION__, 'Bei Änderung (bool, integer, float, string)', 0);
                if ($ValueChanged) {
                    $execute = true;
                    $state = true;
                }
                break;

            case 1: # on update (bool, integer, float, string)
                $this->SendDebug(__FUNCTION__, 'Bei Aktualisierung (bool, integer, float, string)', 0);
                $execute = true;
                $state = true;
                break;

            case 2: # on limit drop, once (integer, float)
                switch ($type) {
                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if ($value == 'true') {
                                $value = '1';
                            }
                            if (GetValueInteger($SenderID) < intval($value)) {
                                $state = true;
                            }
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                                $state = true;
                            }
                        }
                        break;

                }
                break;

            case 3: # on limit drop, every time (integer, float)
                switch ($type) {
                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if ($value == 'true') {
                            $value = '1';
                        }
                        if (GetValueInteger($SenderID) < intval($value)) {
                            $state = true;
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                        $execute = true;
                        if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $value))) {
                            $state = true;
                        }
                        break;

                }
                break;

            case 4: # on limit exceed, once (integer, float)
                switch ($type) {
                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (integer)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if ($value == 'true') {
                                $value = '1';
                            }
                            if (GetValueInteger($SenderID) > intval($value)) {
                                $state = true;
                            }
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, einmalig (float)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                                $state = true;
                            }
                        }
                        break;

                }
                break;

            case 5: # on limit exceed, every time (integer, float)
                switch ($type) {
                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if ($value == 'true') {
                            $value = '1';
                        }
                        if (GetValueInteger($SenderID) > intval($value)) {
                            $state = true;
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei Grenzunterschreitung, mehrmalig (float)', 0);
                        $execute = true;
                        if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $value))) {
                            $state = true;
                        }
                        break;

                }
                break;

            case 6: # on specific value, once (bool, integer, float, string)
                switch ($type) {
                    case 0: # bool
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (bool)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if (GetValueBoolean($SenderID) == boolval($value)) {
                                $state = true;
                            }
                        }
                        break;

                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (integer)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if ($value == 'false') {
                                $value = '0';
                            }
                            if ($value == 'true') {
                                $value = '1';
                            }
                            if (GetValueInteger($SenderID) == intval($value)) {
                                $state = true;
                            }
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (float)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                                $state = true;
                            }
                        }
                        break;

                    case 3: # string
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, einmalig (string)', 0);
                        if ($ValueChanged) {
                            $execute = true;
                            if (GetValueString($SenderID) == (string) $value) {
                                $state = true;
                            }
                        }
                        break;

                }
                break;

            case 7: # on specific value, every time (bool, integer, float, string)
                switch ($type) {
                    case 0: # bool
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (bool)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if (GetValueBoolean($SenderID) == boolval($value)) {
                            $state = true;
                        }
                        break;

                    case 1: # integer
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (integer)', 0);
                        $execute = true;
                        if ($value == 'false') {
                            $value = '0';
                        }
                        if ($value == 'true') {
                            $value = '1';
                        }
                        if (GetValueInteger($SenderID) == intval($value)) {
                            $state = true;
                        }
                        break;

                    case 2: # float
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (float)', 0);
                        $execute = true;
                        if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $value))) {
                            $state = true;
                        }
                        break;

                    case 3: # string
                        $this->SendDebug(__FUNCTION__, 'Bei bestimmten Wert, mehrmalig (string)', 0);
                        $execute = true;
                        if (GetValueString($SenderID) == (string) $value) {
                            $state = true;
                        }
                        break;

                }
                break;

        }
        $this->SendDebug(__FUNCTION__, 'Bedingung erfüllt: ' . json_encode($execute), 0);
        $result = false;
        if ($execute) {
            $result = $this->ToggleToneAcknowledgement($state, true);
        }
        return $result;
    }
}