# Limits

These are deliberate scope decisions for version 0.1, not permanent ones.

- **Public repositories only.** Smart HTTP authentication is usually just HTTP Basic auth, so private repository support will be a small addition later.
- **Read only.** No push, no working tree, no index, no merge machinery.
- **No history.** Fetches are always depth 1. Walking commit history would require negotiation support that is not implemented.
- **SHA-1 only.** Repositories configured for SHA-256 object IDs are rejected during ref discovery with a clear error.
- **Snapshot sized downloads.** See [how it works](how-it-works.md). Partial clone filters over protocol v2 are the planned fix for very large repositories.
