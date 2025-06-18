<?php

namespace Hyperlab\Dimona\Actions\DimonaPeriod;

use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Facades\DB;

class ComputeDimonaPeriodState
{
    public static function new(): static
    {
        return app(static::class);
    }

    public function execute(DimonaPeriod $dimonaPeriod): DimonaPeriod
    {
        return DB::transaction(function () use ($dimonaPeriod) {
            $state = $this->computeState($dimonaPeriod);

            if ($state === null) {
                return $dimonaPeriod;
            }

            $dimonaPeriod->update(['state' => $state]);

            return $dimonaPeriod;
        });
    }

    private function computeState(DimonaPeriod $dimonaPeriod): ?DimonaPeriodState
    {
        $dimonaDeclarations = $dimonaPeriod->dimona_declarations()->oldest()->get();

        if ($dimonaDeclarations->last()?->state === DimonaDeclarationState::Pending) {
            $state = DimonaPeriodState::Pending;
        } else {
            $state = $dimonaDeclarations->reduce(function (?DimonaPeriodState $state, DimonaDeclaration $dimonaDeclaration) {
                if ($dimonaDeclaration->type === DimonaDeclarationType::In) {
                    if ($dimonaDeclaration->state === DimonaDeclarationState::Accepted) {
                        return DimonaPeriodState::Accepted;
                    }
                    if ($dimonaDeclaration->state === DimonaDeclarationState::AcceptedWithWarning) {
                        return $dimonaDeclaration->anomalies()->flexiRequirementsAreNotMet()
                            ? DimonaPeriodState::AcceptedWithWarning
                            : DimonaPeriodState::Accepted;
                    }
                    if ($dimonaDeclaration->state === DimonaDeclarationState::Refused) {
                        return DimonaPeriodState::Refused;
                    }
                    if ($dimonaDeclaration->state === DimonaDeclarationState::Waiting) {
                        return DimonaPeriodState::Waiting;
                    }
                }

                if ($dimonaDeclaration->type === DimonaDeclarationType::Cancel) {
                    if ($dimonaDeclaration->state === DimonaDeclarationState::Accepted) {
                        return DimonaPeriodState::Cancelled;
                    }
                    if ($dimonaDeclaration->state === DimonaDeclarationState::Refused && $dimonaDeclaration->anomalies()->isAlreadyCancelled()) {
                        return DimonaPeriodState::Cancelled;
                    }
                }

                return $state;
            });
        }

        return $state;
    }
}
