<?php

/** @noinspection PhpUnused */

/*
 * @module      Quittungston 1 (Variable)
 *
 * @prefix      QT1
 *
 * @file        QT1_toneAcknowledgement.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @see         https://github.com/ubittner/Quittungston
 *
 */

declare(strict_types=1);

trait QT1_toneAcknowledgement
{
    /**
     * Toggles the tone acknowledgement off or on.
     *
     * @param bool $State
     * false    = off
     * true     = on
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
    public function ToggleToneAcknowledgement(bool $State, bool $UseSwitchingDelay = false): bool
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
                    //Revert
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
                            $result = $this->ToggleToneAcknowledgement(true, true);
                        }
                    }
                }
            }
        }
        return $result;
    }
}