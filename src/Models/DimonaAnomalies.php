<?php

namespace Hyperlab\Dimona\Models;

use Illuminate\Support\Str;

class DimonaAnomalies
{
    public function __construct(
        private array $anomalies = [],
    ) {}

    public function isAlreadyCancelled(): bool
    {
        $anomaliesJson = json_encode($this->anomalies);

        // Reeds geannuleerde Dimonaperiode of dagelijkse registratie
        return Str::contains($anomaliesJson, '00913-355');
    }

    public function flexiRequirementsAreNotMet(): bool
    {
        $anomaliesJson = json_encode($this->anomalies);

        // Toegangsvoorwaarden voor flexi-jobs niet gerespecteerd
        return Str::contains($anomaliesJson, '90017-510');
    }

    public function studentRequirementsAreNotMet(): bool
    {
        $anomaliesJson = json_encode($this->anomalies);

        // Overschrijding van het contingent
        return Str::contains($anomaliesJson, '90017-369');
    }
}
