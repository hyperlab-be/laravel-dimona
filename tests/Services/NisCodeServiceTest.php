<?php

use Hyperlab\Dimona\Enums\EmploymentLocationCountry;
use Hyperlab\Dimona\Services\NisCodeService;

it('returns the correct NIS code for a country', function () {
    $nis = new NisCodeService;

    expect($nis->getNisCodeForCountry(EmploymentLocationCountry::Belgium))->toBe(150);
    expect($nis->getNisCodeForCountry(EmploymentLocationCountry::Netherlands))->toBe(129);
});

it('returns the correct NIS code for a municipality', function () {
    $nis = new NisCodeService;

    // Test a few postal codes
    expect($nis->getNisCodeForMunicipality('1000'))->toBe(21004); // Brussels
    expect($nis->getNisCodeForMunicipality('2000'))->toBe(11002); // Antwerp
    expect($nis->getNisCodeForMunicipality('3000'))->toBe(24062); // Leuven
    expect($nis->getNisCodeForMunicipality('9000'))->toBe(44021); // Ghent
});

it('throws an exception for an unknown postal code', function () {
    $nis = new NisCodeService;

    $nis->getNisCodeForMunicipality('0000');
})->throws(Exception::class, 'Municipality not found for 0000.');
