<?php

/** @noinspection PhpUnused */
/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait QT2_toneAcknowledgement
{
    /**
     * Toggles the tone acknowledgement off or on.
     *
     * @param int $AcousticSignal
     * 0    = off
     * 1    = low battery
     * 2    = disarmed
     * 3    = internally armed
     * 4    = externally armed
     * 5    = delayed internally armed
     * 6    = delayed externally armed
     * 7    = event
     * 8    = error
     *
     * @param bool $UseSwitchingDelay
     * false    = no delay
     * true     = use delay
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function TriggerToneAcknowledgement(int $AcousticSignal, bool $UseSwitchingDelay = false): bool
    {
        $result = false;
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        if ($this->CheckMuteMode()) {
            return $result;
        }
        //Semaphore Enter
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
                case 0: //Off
                    $acousticAlarmSelection = 0;
                    break;

                case 1: //Low battery
                    $acousticAlarmSelection = 10;
                    break;

                case 2: //Disarmed
                    $acousticAlarmSelection = 11;
                    break;

                case 3: //Internally armed
                    $acousticAlarmSelection = 12;
                    break;

                case 4: //Externally armed
                    $acousticAlarmSelection = 13;
                    break;

                case 5: //Delayed internally armed
                    $acousticAlarmSelection = 14;
                    break;

                case 6: //Delayed externally armed
                    $acousticAlarmSelection = 15;
                    break;

                case 7: //Event
                    $acousticAlarmSelection = 16;
                    break;

                case 8: //Error
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
                    //Revert
                    $this->SetValue('AcousticSignal', $actualAcousticSignal);
                    $errorMessage = 'Quittungston ' . $AcousticSignal . ' - ' . $acousticSignalName . ' konnte nicht ausgegeben werden!';
                    $this->SendDebug(__FUNCTION__, $errorMessage, 0);
                    $errorMessage = 'ID ' . $id . ' , ' . $errorMessage;
                    $this->LogMessage($errorMessage, KL_ERROR);
                }
            }
        }
        //Semaphore leave
        IPS_SemaphoreLeave($this->InstanceID . '.AcousticSignal');
        return $result;
    }

    /**
     * Checks a trigger and toggles the tone acknowledgement.
     *
     * @param int $SenderID
     *
     * @return bool
     * false    = an error occurred
     * true     = successful
     *
     * @throws Exception
     */
    public function CheckTrigger(int $SenderID): bool
    {
        $result = false;
        $this->SendDebug(__FUNCTION__, 'Die Methode wird ausgeführt. (' . microtime(true) . ')', 0);
        if ($this->CheckMaintenanceMode()) {
            return $result;
        }
        if ($this->CheckMuteMode()) {
            return $result;
        }
        //Trigger variables
        $triggerVariables = json_decode($this->ReadPropertyString('TriggerVariables'));
        if (!empty($triggerVariables)) {
            foreach ($triggerVariables as $variable) {
                $id = $variable->ID;
                if ($SenderID == $id) {
                    $use = $variable->ID;
                    if ($use) {
                        $actualValue = intval(GetValue($id));
                        $triggerValue = $variable->TriggerValue;
                        if ($actualValue == $triggerValue) {
                            $acousticSignal = $variable->AcousticSignal;
                            $result = $this->TriggerToneAcknowledgement($acousticSignal, true);
                        }
                    }
                }
            }
        }
        return $result;
    }
}