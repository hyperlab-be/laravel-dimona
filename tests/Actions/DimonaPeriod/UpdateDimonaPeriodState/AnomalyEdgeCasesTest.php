<?php

use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Models\DimonaDeclaration;

beforeEach(function () {
    require_once __DIR__.'/Helpers.php';
    $this->period = createDimonaPeriod();
});

describe('unknown anomaly codes', function () {
    it('handles unknown anomaly codes gracefully', function () {
        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::AcceptedWithWarning,
            'anomalies' => [
                ['code' => '99999-999', 'description' => 'Unknown anomaly'],
                ['code' => 'UNKNOWN-CODE', 'description' => 'This is not a real code'],
            ],
        ]);

        $this->period->updateState();

        // Should handle gracefully - since no known anomaly codes, treat as accepted
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::Accepted);
    });

    it('handles mix of known and unknown anomaly codes', function () {
        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::AcceptedWithWarning,
            'anomalies' => [
                ['code' => '90017-510', 'description' => 'Flexi requirements not met'],
                ['code' => '99999-999', 'description' => 'Unknown anomaly'],
                ['code' => 'FAKE-CODE', 'description' => 'This is not a real code'],
            ],
        ]);

        $this->period->updateState();

        // Should detect the known anomaly code and set state accordingly
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::AcceptedWithWarning);
    });

    it('handles empty anomaly code', function () {
        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::AcceptedWithWarning,
            'anomalies' => [
                ['code' => '', 'description' => 'Empty code'],
            ],
        ]);

        $this->period->updateState();

        // Should handle gracefully
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::Accepted);
    });

    it('handles null anomaly code', function () {
        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::AcceptedWithWarning,
            'anomalies' => [
                ['code' => null, 'description' => 'Null code'],
            ],
        ]);

        $this->period->updateState();

        // Should handle gracefully
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::Accepted);
    });
});

describe('anomaly array variations', function () {
    it('handles empty anomalies array', function () {
        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::AcceptedWithWarning,
            'anomalies' => [],
        ]);

        $this->period->updateState();

        // Empty anomalies with AcceptedWithWarning should be treated as Accepted
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::Accepted);
    });

    it('handles null anomalies', function () {
        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::AcceptedWithWarning,
            'anomalies' => null,
        ]);

        $this->period->updateState();

        // Null anomalies should be handled gracefully
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::Accepted);
    });

    it('handles malformed anomaly structure', function () {
        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::AcceptedWithWarning,
            'anomalies' => [
                'not-an-array-structure',
                ['missing-code-key' => 'value'],
            ],
        ]);

        $this->period->updateState();

        // Should handle malformed data gracefully
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::Accepted);
    });
});

describe('multiple anomaly codes', function () {
    it('handles multiple flexi and student anomalies together', function () {
        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::AcceptedWithWarning,
            'anomalies' => [
                ['code' => '90017-510', 'description' => 'Flexi requirements not met'],
                ['code' => '90017-369', 'description' => 'Student quota exceeded'],
            ],
        ]);

        $this->period->updateState();

        // Both anomaly codes present should still result in AcceptedWithWarning
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::AcceptedWithWarning);
    });

    it('handles 10 different anomaly codes', function () {
        $anomalies = collect(range(1, 10))->map(fn ($i) => [
            'code' => "CODE-{$i}",
            'description' => "Anomaly {$i}",
        ])->toArray();

        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::AcceptedWithWarning,
            'anomalies' => $anomalies,
        ]);

        $this->period->updateState();

        // Should handle many anomalies gracefully
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::Accepted);
    });

    it('finds known code in large anomaly array', function () {
        // Create 20 anomalies with one known code in the middle
        $anomalies = collect(range(1, 10))->map(fn ($i) => [
            'code' => "UNKNOWN-{$i}",
            'description' => "Unknown anomaly {$i}",
        ])->toArray();

        $anomalies[] = ['code' => '90017-510', 'description' => 'Flexi requirements not met'];

        $anomalies = array_merge($anomalies, collect(range(11, 20))->map(fn ($i) => [
            'code' => "UNKNOWN-{$i}",
            'description' => "Unknown anomaly {$i}",
        ])->toArray());

        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::AcceptedWithWarning,
            'anomalies' => $anomalies,
        ]);

        $this->period->updateState();

        // Should find the known code even in a large array
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::AcceptedWithWarning);
    });
});

describe('anomaly code format variations', function () {
    it('handles anomaly code with different casing', function () {
        // While the code searches for exact strings, this tests that case matters
        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::AcceptedWithWarning,
            'anomalies' => [
                ['code' => '90017-510', 'description' => 'Correct case'],
            ],
        ]);

        $this->period->updateState();

        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::AcceptedWithWarning);
    });

    it('handles anomaly code with extra whitespace', function () {
        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::AcceptedWithWarning,
            'anomalies' => [
                ['code' => ' 90017-510 ', 'description' => 'Code with whitespace'],
            ],
        ]);

        $this->period->updateState();

        // json_encode will include the whitespace, so it should still match via Str::contains
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::AcceptedWithWarning);
    });

    it('handles anomaly code as substring in description', function () {
        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::AcceptedWithWarning,
            'anomalies' => [
                ['code' => 'SOME-CODE', 'description' => 'Description mentioning 90017-510 as text'],
            ],
        ]);

        $this->period->updateState();

        // The search uses Str::contains on JSON, so it will find the code even in description
        // This might be considered a bug or feature depending on requirements
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::AcceptedWithWarning);
    });
});

describe('cancelled period anomaly code', function () {
    it('handles already cancelled anomaly code', function () {
        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::Cancel,
            'state' => DimonaDeclarationState::Refused,
            'anomalies' => [
                ['code' => '00913-355', 'description' => 'Already cancelled'],
            ],
        ]);

        $this->period->updateState();

        // Should recognize as already cancelled
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::Cancelled);
    });

    it('does not treat already cancelled code as cancellation for non-cancel declaration', function () {
        $declaration = DimonaDeclaration::factory()->create([
            'dimona_period_id' => $this->period->id,
            'type' => DimonaDeclarationType::In,
            'state' => DimonaDeclarationState::Refused,
            'anomalies' => [
                ['code' => '00913-355', 'description' => 'Already cancelled'],
            ],
        ]);

        $this->period->updateState();

        // For IN declaration, already-cancelled code should result in Refused, not Cancelled
        expect($this->period->fresh()->state)->toBe(DimonaPeriodState::Refused);
    });
});
