<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

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
 * @see         https://github.com/ubittner/Quittungston
 *
 * @guids       Library
 *              {FC09418F-79AF-F15B-EEF5-D45E9997E0D8}
 *
 *              Quittungston
 *             	{DAC7CF88-0A1E-23C2-9DB2-0C249364A831}
 */

declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Quittungston extends IPSModule
{
    // Helper
    use QTON_backupRestore;
    use QTON_toneAcknowledgement;

    // Constants
    private const DELAY_MILLISECONDS = 250;
    private const QUITTUNGSTON_LIBRARY_GUID = '{FC09418F-79AF-F15B-EEF5-D45E9997E0D8}';
    private const QUITTUNGSTON_MODULE_GUID = '{DAC7CF88-0A1E-23C2-9DB2-0C249364A831}';

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
        $this->DeleteProfiles();
    }

    public function Create()
    {
        // Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->CreateProfiles();
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
        $this->SetOptions();
        $this->CheckMaintenanceMode();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        // Send debug
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $moduleInfo = [];
        $library = IPS_GetLibrary(self::QUITTUNGSTON_LIBRARY_GUID);
        $module = IPS_GetModule(self::QUITTUNGSTON_MODULE_GUID);
        $moduleInfo['name'] = $module['ModuleName'];
        $moduleInfo['version'] = $library['Version'] . '-' . $library['Build'];
        $moduleInfo['date'] = date('d.m.Y', $library['Date']);
        $moduleInfo['time'] = date('H:i', $library['Date']);
        $moduleInfo['developer'] = $library['Author'];
        $formData['elements'][0]['items'][2]['caption'] = "Instanz ID:\t\t" . $this->InstanceID;
        $formData['elements'][0]['items'][3]['caption'] = "Modul:\t\t\t" . $moduleInfo['name'];
        $formData['elements'][0]['items'][4]['caption'] = "Version:\t\t\t" . $moduleInfo['version'];
        $formData['elements'][0]['items'][5]['caption'] = "Datum:\t\t\t" . $moduleInfo['date'];
        $formData['elements'][0]['items'][6]['caption'] = "Uhrzeit:\t\t\t" . $moduleInfo['time'];
        $formData['elements'][0]['items'][7]['caption'] = "Entwickler:\t\t" . $moduleInfo['developer'];
        $formData['elements'][0]['items'][8]['caption'] = "Präfix:\t\t\tQTON";
        return json_encode($formData);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    #################### Request Action

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'ToneAcknowledgement':
                $this->ToggleToneAcknowledgement($Value);
                break;

        }
    }

    #################### Private

    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        $this->RegisterPropertyString('Note', '');
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
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
        $this->RegisterVariableBoolean('ToneAcknowledgement', 'Quittungston', $profile, 10);
        $this->EnableAction('ToneAcknowledgement');
    }

    private function SetOptions(): void
    {
        // Tone acknowledgement
        $id = $this->GetIDForIdent('ToneAcknowledgement');
        $use = $this->ReadPropertyBoolean('EnableToneAcknowledgement');
        IPS_SetHidden($id, !$use);
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = false;
        $status = 102;
        if ($this->ReadPropertyBoolean('MaintenanceMode')) {
            $result = true;
            $status = 104;
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        $this->SetStatus($status);
        IPS_SetDisabled($this->InstanceID, $result);
        return $result;
    }
}
