<?php

use Hyperlab\Dimona\Enums\Country;
use Hyperlab\Dimona\Services\Nis;

it('returns the correct NIS code for a country', function () {
    $nis = new Nis;

    expect($nis->getNisCodeForCountry(Country::Belgium))->toBe(150);
    expect($nis->getNisCodeForCountry(Country::Netherlands))->toBe(129);
});

it('returns the correct NIS code for a municipality', function () {
    $nis = new Nis;

    // Test a few postal codes
    expect($nis->getNisCodeForMunicipality('1000'))->toBe(21004); // Brussels
    expect($nis->getNisCodeForMunicipality('2000'))->toBe(11002); // Antwerp
    expect($nis->getNisCodeForMunicipality('3000'))->toBe(24062); // Leuven
    expect($nis->getNisCodeForMunicipality('9000'))->toBe(44021); // Ghent
});

it('throws an exception for an unknown postal code', function () {
    $nis = new Nis;

    $nis->getNisCodeForMunicipality('0000');
})->throws(Exception::class, 'Municipality not found for 0000.');
