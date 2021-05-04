<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class QuittungstonValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary_Quittungston(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateModule_Quittungston(): void
    {
        $this->validateModule(__DIR__ . '/../Quittungston');
    }

    public function testValidateModule_HMSecSirWM(): void
    {
        $this->validateModule(__DIR__ . '/../HM-Sec-Sir-WM');
    }

    public function testValidateModule_HmIPASIR(): void
    {
        $this->validateModule(__DIR__ . '/../HmIP-ASIR');
    }
}