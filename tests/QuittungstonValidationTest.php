<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class QuittungstonValidationTest extends TestCaseSymconValidation
{
    public function testValidateQuittungston(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateQuittungstonModule(): void
    {
        $this->validateModule(__DIR__ . '/../Quittungston');
    }
}