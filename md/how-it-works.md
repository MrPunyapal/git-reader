# How it works

The library speaks the Git smart HTTP protocol, the same one `git clone` uses over HTTP. No provider API is involved, so the host does not matter.

## The request cycle

1. **Ref discovery.** A GET to `/info/refs?service=git-upload-pack` returns a pkt-line stream listing every ref and its commit. This is also how the library resolves `main`, `v1.2`, or a full SHA.
2. **Fetch.** A POST to `/git-upload-pack` asks for the wanted commit with `deepen 1`. Depth 1 means the server sends only objects reachable from that single commit: no history.
3. **Packfile parsing.** The reply is a packfile. Objects may be stored as deltas against earlier objects in the same pack. The parser inflates entries one at a time, applies copy and insert instructions for deltas, verifies the SHA-1 trailer, and writes each object into a temporary directory.
4. **Tree walking.** Commits point to root trees. Trees contain named entries with modes and object ids. Walking them by path finds subdirectories; blobs hold file contents.

## What this costs

A depth 1 fetch downloads every blob of the requested commit, not just the directory you want. For most documentation directories this is fine. On a very large monorepo it can mean tens of megabytes.

Protocol v2 partial clone filters (for example `blob:none`) would reduce this to trees plus the blobs actually requested. That is the planned improvement for large repositories.

## Why not shell out to git

Running `git clone --depth 1` would work, but it requires the binary on every machine and CI image, behaves differently across git versions, and leaves a working tree behind. Implementing the narrow read-only path is about a thousand lines of PHP with no dependencies, which keeps installs predictable.
