# Security Policy

## Supported versions

| Version | Supported          |
|---------|--------------------|
| 3.x     | :white_check_mark: |
| 2.x     | :x:                |
| 1.x     | :x:                |

Automated data releases (weekly blocklist updates) are published as patch
releases of the current 3.x line.

## Reporting a vulnerability

Please **do not** open a public issue for security problems. Email
**info@harungecit.com** with:

- a description of the issue and its impact,
- steps to reproduce (or a proof of concept),
- the affected version(s).

You will receive an acknowledgement within a few days. Once a fix is released,
you will be credited in the release notes unless you prefer otherwise.

## Scope notes

- The list synchronizer (`email-validator sync`) only downloads http(s) URLs
  you configure, never executes downloaded content, rejects HTML responses and
  writes atomically. Point it only at sources you trust.
- SMTP and catch-all verification open outbound connections to the mail
  servers of the validated domains; enable them consciously.
- Disposable-domain detection is a heuristic based on community-maintained
  lists. It reduces abuse; it is not an authentication mechanism.
