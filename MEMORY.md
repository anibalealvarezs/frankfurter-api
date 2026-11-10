# Frankfurter API Local Memory

## Architecture & Decisions
- Standalone PHP SDK wrapping Frankfurter API (`api.frankfurter.dev`).
- Extends `anibalealvarezs/api-client-skeleton`.
- Provides exchange rates published by the European Central Bank (ECB).
- Supports single-date historical lookups, latest rates, range lookups for batch operations, and currency listings.
