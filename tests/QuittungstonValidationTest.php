<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class QuittungstonValidationTest extends TestCaseSymconValidation
{
    public function testValidateQuittungston(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateQuittungston1Module(): void
    {
        $this->validateModule(__DIR__ . '/../Quittungston 1');
    }

    public function testValidateQuittungston2Module(): void
    {
        $this->validateModule(__DIR__ . '/../Quittungston 2');
    }

    public function testValidateQuittungston3Module(): void
    {
        $this->validateModule(__DIR__ . '/../Quittungston 3');
    }
}