# Canonical Database Schema

This directory is reserved for the verified PostgreSQL schema artifact used as the canonical fresh-database baseline.

**Current status: baseline not yet generated.**

Do not create a hand-written schema dump here from memory or from partial migration inspection.

The artifact must be generated from a disposable PostgreSQL database produced by the accepted migration chain, then replay-tested independently. The resulting schema must preserve the current canonical constraints, indexes, functions, triggers, temporal protections, financial protections, academic protections, access protections and provenance rules.

Once a verified schema artifact exists, Laravel's supported schema-dump/baseline mechanism should be used rather than inventing a custom migration runner.
