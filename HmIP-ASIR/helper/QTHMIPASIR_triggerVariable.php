<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license     CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Quittungston/tree/master/HmIP-ASIR
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait QTHMIPASIR_triggerVariable
{
    public function CheckTriggerVariable(int $SenderID, bool $ValueChanged): bool
    {
        $this->SendDebug(__FUNCTION__, 'Die auslösende Variable wird geprüft.', 0);
        $this->SendDebug(__FUNCTION__, 'ID ' . $SenderID . ', Wert hat sich geändert: ' . json_encode($ValueChanged), 0);
        $triggerVariables = json_decode($this->ReadPropertyString('TriggerVariables'));
        if (empty($triggerVariables)) {
            return false;
        }
        $result = false;
        foreach ($triggerVariables as $triggerVariable) {
            $triggered = false;
            $id = $triggerVariable->ID;
            if ($id == $SenderID && $triggerVariable->Use) {
                $type = IPS_GetVariable($id)['VariableType'];
                $triggerValue = $triggerVariable->TriggerValue;
                $acousticSignal = $triggerVariable->AcousticSignal;
                switch ($triggerVariable->TriggerType) {
                    case 0: # on change (bool, integer, float, string)
                        if ($ValueChanged) {
                            $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei Änderung (bool, integer, float, string)', 0);
                            $triggered = true;
                        }
                        break;

                    case 1: # on update (bool, integer, float, string)
                        $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei Aktualisierung (bool, integer, float, string)', 0);
                        $triggered = true;
                        break;

                    case 2: # on limit drop, once (integer, float)
                        switch ($type) {
                            case 1: # integer
                                if ($ValueChanged) {
                                    if ($triggerValue == 'false') {
                                        $triggerValue = '0';
                                    }
                                    if ($triggerValue == 'true') {
                                        $triggerValue = '1';
                                    }
                                    if (GetValueInteger($SenderID) < intval($triggerValue)) {
                                        $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei Grenzunterschreitung, einmalig (integer)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                            case 2: # float
                                if ($ValueChanged) {
                                    if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $triggerValue))) {
                                        $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei Grenzunterschreitung, einmalig (float)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                        }
                        break;

                    case 3: # on limit drop, every time (integer, float)
                        switch ($type) {
                            case 1: # integer
                                if ($triggerValue == 'false') {
                                    $triggerValue = '0';
                                }
                                if ($triggerValue == 'true') {
                                    $triggerValue = '1';
                                }
                                if (GetValueInteger($SenderID) < intval($triggerValue)) {
                                    $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                                    $triggered = true;
                                }
                                break;

                            case 2: # float
                                if (GetValueFloat($SenderID) < floatval(str_replace(',', '.', $triggerValue))) {
                                    $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei Grenzunterschreitung, mehrmalig (float)', 0);
                                    $triggered = true;
                                }
                                break;

                        }
                        break;

                    case 4: # on limit exceed, once (integer, float)
                        switch ($type) {
                            case 1: # integer
                                if ($ValueChanged) {
                                    if ($triggerValue == 'false') {
                                        $triggerValue = '0';
                                    }
                                    if ($triggerValue == 'true') {
                                        $triggerValue = '1';
                                    }
                                    if (GetValueInteger($SenderID) > intval($triggerValue)) {
                                        $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei Grenzunterschreitung, einmalig (integer)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                            case 2: # float
                                if ($ValueChanged) {
                                    if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $triggerValue))) {
                                        $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei Grenzunterschreitung, einmalig (float)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                        }
                        break;

                    case 5: # on limit exceed, every time (integer, float)
                        switch ($type) {
                            case 1: # integer
                                if ($triggerValue == 'false') {
                                    $triggerValue = '0';
                                }
                                if ($triggerValue == 'true') {
                                    $triggerValue = '1';
                                }
                                if (GetValueInteger($SenderID) > intval($triggerValue)) {
                                    $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei Grenzunterschreitung, mehrmalig (integer)', 0);
                                    $triggered = true;
                                }
                                break;

                            case 2: # float
                                if (GetValueFloat($SenderID) > floatval(str_replace(',', '.', $triggerValue))) {
                                    $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei Grenzunterschreitung, mehrmalig (float)', 0);
                                    $triggered = true;
                                }
                                break;

                        }
                        break;

                    case 6: # on specific value, once (bool, integer, float, string)
                        switch ($type) {
                            case 0: # bool
                                if ($ValueChanged) {
                                    if ($triggerValue == 'false') {
                                        $triggerValue = '0';
                                    }
                                    if (GetValueBoolean($SenderID) == boolval($triggerValue)) {
                                        $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei bestimmten Wert, einmalig (bool)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                            case 1: # integer
                                if ($ValueChanged) {
                                    if ($triggerValue == 'false') {
                                        $triggerValue = '0';
                                    }
                                    if ($triggerValue == 'true') {
                                        $triggerValue = '1';
                                    }
                                    if (GetValueInteger($SenderID) == intval($triggerValue)) {
                                        $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei bestimmten Wert, einmalig (integer)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                            case 2: # float
                                if ($ValueChanged) {
                                    if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $triggerValue))) {
                                        $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei bestimmten Wert, einmalig (float)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                            case 3: # string
                                if ($ValueChanged) {
                                    if (GetValueString($SenderID) == (string) $triggerValue) {
                                        $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei bestimmten Wert, einmalig (string)', 0);
                                        $triggered = true;
                                    }
                                }
                                break;

                        }
                        break;

                    case 7: # on specific value, every time (bool, integer, float, string)
                        switch ($type) {
                            case 0: # bool
                                if ($triggerValue == 'false') {
                                    $triggerValue = '0';
                                }
                                if (GetValueBoolean($SenderID) == boolval($triggerValue)) {
                                    $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei bestimmten Wert, mehrmalig (bool)', 0);
                                    $triggered = true;
                                }
                                break;

                            case 1: # integer
                                if ($triggerValue == 'false') {
                                    $triggerValue = '0';
                                }
                                if ($triggerValue == 'true') {
                                    $triggerValue = '1';
                                }
                                if (GetValueInteger($SenderID) == intval($triggerValue)) {
                                    $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei bestimmten Wert, mehrmalig (integer)', 0);
                                    $triggered = true;
                                }
                                break;

                            case 2: # float
                                $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei bestimmten Wert, mehrmalig (float)', 0);
                                if (GetValueFloat($SenderID) == floatval(str_replace(',', '.', $triggerValue))) {
                                    $triggered = true;
                                }
                                break;

                            case 3: # string
                                if (GetValueString($SenderID) == (string) $triggerValue) {
                                    $this->SendDebug(__FUNCTION__, 'ID ' . $id . ', Bei bestimmten Wert, mehrmalig (string)', 0);
                                    $triggered = true;
                                }
                                break;

                        }
                        break;

                }
                $execute = false;
                if ($triggered) {
                    $secondVariable = $triggerVariable->SecondVariable;
                    if ($secondVariable != 0 && @IPS_ObjectExists($secondVariable)) {
                        $type = IPS_GetVariable($secondVariable)['VariableType'];
                        $value = $triggerVariable->SecondVariableValue;
                        switch ($type) {
                            case 0: # bool
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if (GetValueBoolean($secondVariable) == boolval($value)) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung der weiteren Variable: Bei bestimmten Wert, mehrmalig (bool)', 0);
                                    $execute = true;
                                }
                                break;

                            case 1: # integer
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueInteger($secondVariable) == intval($value)) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung der weiteren Variable: Bei bestimmten Wert, mehrmalig (integer)', 0);
                                    $execute = true;
                                }
                                break;

                            case 2: # float
                                if ($value == 'false') {
                                    $value = '0';
                                }
                                if ($value == 'true') {
                                    $value = '1';
                                }
                                if (GetValueFloat($secondVariable) == floatval(str_replace(',', '.', $value))) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung der weiteren Variable: Bei bestimmten Wert, mehrmalig (float)', 0);
                                    $execute = true;
                                }
                                break;

                            case 3: # string
                                if (GetValueString($secondVariable) == (string) $value) {
                                    $this->SendDebug(__FUNCTION__, 'Auslösebedingung der weiteren Variable: Bei bestimmten Wert, mehrmalig (string)', 0);
                                    $execute = true;
                                }
                                break;

                        }
                    }
                    if ($secondVariable == 0) {
                        $execute = true;
                    }
                }
                if ($execute) {
                    $this->SendDebug(__FUNCTION__, 'Variable löst akkustische Signalisierung aus!', 0);
                    $result = $this->ExecuteToneAcknowledgement($acousticSignal, true);
                }
            }
        }
        return $result;
    }
}