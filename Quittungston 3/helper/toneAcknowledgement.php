<?php

/** @noinspection PhpUnused */
/** @noinspection DuplicatedCode */

declare(strict_types=1);

trait QT3_toneAcknowledgement
{
    /**
     * Toggles the tone acknowledgement off or on.
     *
     * @param int $AcousticSignal
     * 0    = off
     * 1    = externally armed
     * 2    = internally armed
     * 3    = alarm blocked
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
            $acousticSignalList = [0 => 'Alarm Aus', 1 => 'Extern scharf', 2 => 'Intern scharf', 3 => 'Alarm blockiert'];
            $acousticSignalName = 'Wert nicht vorhanden!';
            if (array_key_exists($AcousticSignal, $acousticSignalList)) {
                $acousticSignalName = $acousticSignalList[$AcousticSignal];
            }
            switch ($AcousticSignal) {
                case 0: //Off
                    $armState = 0;
                    break;

                case 1: //Externally armed
                    $armState = 2;
                    break;

                case 2: //Internally armed
                    $armState = 1;
                    break;

                case 3: //Alarm blocked
                    $armState = 3;
                    break;

                default:
                    return false;
            }
            $this->SendDebug(__FUNCTION__, 'Akustisches Signal: ' . $AcousticSignal . ' - ' . $acousticSignalName, 0);
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