# ClockinAPP — Root Cause + Temporal Contract (Phase 2)

## Root cause of +4h offset
The offset was caused by mixed datetime semantics across layers:

1. Backend persisted UTC values as `Y-m-d H:i:s` (no timezone suffix).
2. Some frontend paths interpreted those values as UTC (append `Z`), others treated them as local wall-time (notably `datetime-local` prefill using manual `replace/slice`).
3. Edit modal used raw server string directly in `datetime-local`, which expects local time.

Result: same record displayed as local in detail but as UTC-like local in edit input (+4h for UTC-4 users).

## Global temporal contract

- **DB/backend:** UTC is source of truth.
- **API JSON:** datetime fields are ISO8601 UTC explicit (`...Z`).
- **Frontend:** no ad-hoc `new Date(raw)`, `Date.parse(raw)`, `toISOString()` for server datetimes outside central helpers.
- **`datetime-local` input:** always populated with `UTC -> local` conversion.
- **Saving `datetime-local`:** always convert `local -> UTC ISO` before API payload.

## Central helpers
Implemented in `assets/js/datetime.js`:

- `parseServerUtc(raw)`
- `formatForDisplayLocal(raw, options)`
- `utcToDatetimeLocalValue(raw)`
- `datetimeLocalToUtcIso(value)`
- `durationBetweenUtc(startRaw, endRaw)`

## Applied refactor scope
- Attendance records table rendering
- Attendance detail modal rendering
- Attendance edit modal prefill (`datetime-local`)
- Attendance edit payload datetime serialization
- Duration calculation from entry/exit timestamps
- Legacy records without `Z` are treated as UTC legacy input
- New records with `Z` are treated as explicit UTC

## Validation target case
Input (backend UTC): `2026-05-12 12:49:26`

Expected for UTC-4 user:
- Table/detail: `08:49:26`
- Edit modal: `2026-05-12T08:49`
- Save unchanged -> API receives: `2026-05-12T12:49:26.000Z` (seconds may be `00` if input minute precision applies)

## Remaining risks
- Some non-attendance modules may still have independent date formatting logic and should be migrated to `DateTimeUtils` incrementally.
- `datetime-local` is minute-precision by default in browser UI; preserving seconds requires explicit UX and input step configuration.
