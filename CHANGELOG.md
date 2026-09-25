# Changelog

All notable changes to `laravel-dimona` will be documented in this file.

## Unreleased

### Added

- `WorkerType::Occasional`, declared as `EXT` per day with start and end hour. Shifts on the same day are declared as a single period.
- More than two consecutive days of occasional work are declared as a single `OTH` period from the first to the last day. The package cancels and redeclares the periods when a series grows past two days or shrinks back.
- `occasional_joint_commissions` config key (default `[]`): in these joint commissions, a flexi or student employment within a worker type exception falls back to `Occasional` instead of `Other`.

### Changed

- An `OTH` period covering consecutive days is declared with its actual end date. Single day `OTH` periods are declared as before.
- An employment is detached from the active period it belonged to when it is linked to another period, so that period gets cancelled.
