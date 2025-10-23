<?php

namespace Hyperlab\Dimona\Actions\DimonaPeriod;

use Hyperlab\Dimona\Enums\DimonaDeclarationState;
use Hyperlab\Dimona\Enums\DimonaDeclarationType;
use Hyperlab\Dimona\Enums\DimonaPeriodState;
use Hyperlab\Dimona\Events\DimonaPeriodStateUpdated;
use Hyperlab\Dimona\Models\DimonaDeclaration;
use Hyperlab\Dimona\Models\DimonaPeriod;
use Illuminate\Support\Facades\DB;

use function event;

class UpdateDimonaPeriodState
{
    public static function new(): static
    {
        return app(static::class);
    }

    public function execute(DimonaPeriod $dimonaPeriod): DimonaPeriod
    {
        return DB::transaction(function () use ($dimonaPeriod) {
            $initialState = $dimonaPeriod->state;
            $state = $this->computeState($dimonaPeriod);

            if ($state === null || $state === $initialState) {
                return $dimonaPeriod;
            }

            $dimonaPeriod->update(['state' => $state]);

            event(new DimonaPeriodStateUpdated($dimonaPeriod));

            return $dimonaPeriod;
        });
    }

    private function computeState(DimonaPeriod $dimonaPeriod): ?DimonaPeriodState
    {
        $dimonaDeclarations = $dimonaPeriod->dimona_declarations()->oldest()->get();

        if ($dimonaDeclarations->isEmpty()) {
            return null;
        }

        $lastDeclarationState = $dimonaDeclarations->last()?->state;

        if ($lastDeclarationState === DimonaDeclarationState::Pending) {
            return DimonaPeriodState::Pending;
        }

        if ($lastDeclarationState === DimonaDeclarationState::Waiting) {
            return DimonaPeriodState::Waiting;
        }

        return $dimonaDeclarations->reduce(
            function (?DimonaPeriodState $currentState, DimonaDeclaration $declaration) {
                if ($declaration->type === DimonaDeclarationType::In) {
                    return $this->determineStateForInDeclaration($declaration);
                }

                if ($declaration->type === DimonaDeclarationType::Update) {
                    return $this->determineStateForUpdateDeclaration($declaration);
                }

                if ($declaration->type === DimonaDeclarationType::Cancel) {
                    return $this->determineStateForCancelDeclaration($declaration, $currentState);
                }

                return $currentState;
            }
        );
    }

    private function determineStateForInDeclaration(DimonaDeclaration $declaration): ?DimonaPeriodState
    {
        return match ($declaration->state) {
            DimonaDeclarationState::Accepted => DimonaPeriodState::Accepted,
            DimonaDeclarationState::AcceptedWithWarning => $this->handleAcceptedWithWarning($declaration),
            DimonaDeclarationState::Refused => DimonaPeriodState::Refused,
            DimonaDeclarationState::Waiting => DimonaPeriodState::Waiting,
            DimonaDeclarationState::Failed => DimonaPeriodState::Failed,
            default => null,
        };
    }

    private function determineStateForUpdateDeclaration(DimonaDeclaration $declaration): ?DimonaPeriodState
    {
        return match ($declaration->state) {
            DimonaDeclarationState::Accepted => DimonaPeriodState::Accepted,
            DimonaDeclarationState::AcceptedWithWarning => $this->handleAcceptedWithWarning($declaration),
            DimonaDeclarationState::Refused => DimonaPeriodState::Refused,
            DimonaDeclarationState::Waiting => DimonaPeriodState::Waiting,
            DimonaDeclarationState::Failed => DimonaPeriodState::Failed,
            default => null,
        };
    }

    private function handleAcceptedWithWarning(DimonaDeclaration $declaration): DimonaPeriodState
    {
        if ($declaration->anomalies()->flexiRequirementsAreNotMet()) {
            return DimonaPeriodState::AcceptedWithWarning;
        }

        if ($declaration->anomalies()->studentRequirementsAreNotMet()) {
            return DimonaPeriodState::AcceptedWithWarning;
        }

        return DimonaPeriodState::Accepted;
    }

    private function determineStateForCancelDeclaration(
        DimonaDeclaration $declaration, ?DimonaPeriodState $currentState
    ): ?DimonaPeriodState {
        if ($declaration->state === DimonaDeclarationState::Accepted) {
            return DimonaPeriodState::Cancelled;
        }

        if ($declaration->state === DimonaDeclarationState::Refused && $declaration->anomalies()->isAlreadyCancelled()) {
            return DimonaPeriodState::Cancelled;
        }

        return $currentState;
    }
}
