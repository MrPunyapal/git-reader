# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

### Added

- Private repository support via HTTP Basic credentials. Pass a `Credentials` value object (`Credentials::make($token)` or `new Credentials($username, $token)`) to the new fluent `RemoteRepository::withCredentials()` method. When a server answers with HTTP 401, the same request is retried once with an `Authorization: Basic ...` header on both ref discovery and `git-upload-pack` calls; a repeated rejection throws the new `AuthenticationFailedException`. Without credentials, the existing failure message now hints that the repository may be private and that credentials can be provided.
