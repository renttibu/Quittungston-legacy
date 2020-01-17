<?php

/*
 * @module      Quittungston
 *
 * @prefix      QTON
 *
 * @file        module.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     4.00-1
 * @date        2020-01-17, 18:00, 1579280400
 * @review      2020-01-17, 18:00, 1579280400
 *
 * @see         https://github.com/ubittner/Quittungston/
 *
 * @guids       Library
 *              {FC09418F-79AF-F15B-EEF5-D45E9997E0D8}
 *
 *              Quittungston
 *             	{DAC7CF88-0A1E-23C2-9DB2-0C249364A831}
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Quittungston extends IPSModule
{
    // Helper
    use QTON_toneAcknowledgement;

    // Constants
    private const DELAY_MILLISECONDS = 250;

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Register properties
        $this->RegisterProperties();

        // Create profiles
        $this->CreateProfiles();

        // Register variables
        $this->RegisterVariables();
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Set options
        $this->SetOptions();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        // Send debug
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

        }
    }

    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();

        // Delete profiles
        $this->DeleteProfiles();
    }

    //#################### Request Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'ToneAcknowledgement':
                $this->ToggleToneAcknowledgement($Value);
                break;

        }
    }

    //#################### Private

    private function RegisterProperties(): void
    {
        // Visibility
        $this->RegisterPropertyBoolean('EnableToneAcknowledgement', true);

        // Alarm sirens
        $this->RegisterPropertyString('AlarmSirens', '[]');

        // Signalling variants
        $this->RegisterPropertyInteger('AcousticSignal', 0);
        $this->RegisterPropertyInteger('OpticalSignal', 0);
    }

    private function CreateProfiles(): void
    {
        $profile = 'QTON.' . $this->InstanceID . '.ToneAcknowledgement';
        if (!IPS_VariableProfileExists($profile)) {
            IPS_CreateVariableProfile($profile, 0);
        }
        IPS_SetVariableProfileAssociation($profile, 0, 'Aus', 'Speaker', -1);
        IPS_SetVariableProfileAssociation($profile, 1, 'Quittungston ausgeben', 'Speaker', 0xFF0000);
    }

    private function DeleteProfiles(): void
    {
        $profiles = ['ToneAcknowledgement'];
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                $profileName = 'QTON.' . $this->InstanceID . '.' . $profile;
                if (IPS_VariableProfileExists($profileName)) {
                    IPS_DeleteVariableProfile($profileName);
                }
            }
        }
    }

    private function RegisterVariables(): void
    {
        // Tone acknowledgement
        $profile = 'QTON.' . $this->InstanceID . '.ToneAcknowledgement';
        $this->RegisterVariableBoolean('ToneAcknowledgement', 'Quittungston', $profile, 1);
        $this->EnableAction('ToneAcknowledgement');
    }

    private function SetOptions(): void
    {
        // Tone acknowledgement
        $id = $this->GetIDForIdent('ToneAcknowledgement');
        $use = $this->ReadPropertyBoolean('EnableToneAcknowledgement');
        IPS_SetHidden($id, !$use);
    }
}
