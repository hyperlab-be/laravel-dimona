<?php

use Hyperlab\Dimona\Actions\DimonaPeriod\ComputeExpectedDimonaPeriods;
use Illuminate\Support\Collection;

const EMPLOYER_ENTERPRISE_NUMBER = '0123456789';
const WORKER_SSN = '12345678901';

function computeExpectedDimonaPeriods(Collection $employments)
{
    return ComputeExpectedDimonaPeriods::new()->execute(EMPLOYER_ENTERPRISE_NUMBER, WORKER_SSN, $employments);
}
