# CLAUDE.md

## Project Overview

This is a PHP library. Target PHP 8.2+ with strict typing.

-----

## Development Workflow

### 1. Test-Driven Development (TDD) - MANDATORY

**This project follows strict TDD. All features and bug fixes MUST follow the Red-Green-Refactor cycle.**

#### TDD Rules

1. **RED Phase** - Write failing tests FIRST
- (MUST) Write test(s) that define the expected behavior BEFORE any implementation
- (MUST) Run tests and confirm they FAIL - this validates the tests are correct
- (MUST) Do NOT write implementation code during this phase
- (MUST) Do NOT commit yet - tests are failing
1. **GREEN Phase** - Write minimal implementation
- (MUST) Write the MINIMUM code necessary to make tests pass
- (MUST) Do NOT modify the tests - if tests are wrong, go back to RED phase
- (MUST) Run tests after each change to verify progress
- (MUST) Only commit once all tests pass
1. **REFACTOR Phase** - Improve code quality
- (SHOULD) Clean up implementation while keeping tests green
- (MUST) Run tests after each refactoring change
- (SHOULD) Commit refactoring separately: `refactor(scope): <description>`

#### TDD Prompting Guide

When implementing a feature, use this explicit workflow:

```
Step 1: "Write failing tests for [feature]. Do NOT write any implementation code yet."
Step 2: "Run the tests and confirm they fail."
Step 3: "Now implement the minimum code to make the tests pass. Do NOT modify the tests."
Step 4: "Run tests to verify they pass."
Step 5: "Commit the tests and implementation together."
Step 6: "Refactor if needed, keeping tests green, and commit separately."
```

-----

### 2. Git Workflow - Atomic Conventional Commits

**All commits MUST follow Conventional Commits specification with atomic changes.**

#### Commit Message Format

```
<type>(<scope>): <description>

[optional body]

[optional footer(s)]
```

#### Commit Types

|Type      |Description                                            |
|----------|-------------------------------------------------------|
|`feat`    |A new feature                                          |
|`fix`     |A bug fix                                              |
|`test`    |Adding or updating tests (no production code change)   |
|`refactor`|Code change that neither fixes a bug nor adds a feature|
|`docs`    |Documentation only changes                             |
|`style`   |Code style changes (formatting, no logic change)       |
|`chore`   |Build process, dependencies, tooling changes           |
|`perf`    |Performance improvement                                |
|`ci`      |CI/CD configuration changes                            |

#### Commit Rules

- (MUST) Use imperative mood: “add feature” not “added feature”
- (MUST) First line under 72 characters
- (MUST) One logical change per commit (atomic)
- (MUST) Codebase must be in a working state after each commit (tests pass)
- (MUST) Split unrelated changes into separate commits
- (SHOULD) Include scope when applicable: `feat(parser): add JSON support`
- (SHOULD) Reference issues in footer: `Closes #123`

#### Breaking Changes

For breaking changes, add `!` after type/scope and explain in footer:

```
feat(api)!: change return type of parse method

BREAKING CHANGE: parse() now returns Result instead of array
```

-----

### 3. Before Writing Code

- (MUST) Understand the requirements fully before starting
- (SHOULD) If requirements are unclear, ask clarifying questions
- (SHOULD) For complex features, outline the approach first
- (MUST) Check existing code patterns and follow them

### 4. Code Quality

- (MUST) All code must pass static analysis (PHPStan level max)
- (MUST) All code must follow PSR-12 coding style
- (MUST) All public methods must have PHPDoc with `@param` and `@return`
- (SHOULD) Prefer composition over inheritance
- (SHOULD) Keep methods small and focused (single responsibility)
- (MUST) Use strict types: `declare(strict_types=1);`

-----

## Quick Reference

### Typical TDD Commit Sequence

```bash
# RED + GREEN: Tests and implementation together (after tests pass)
git commit -m "feat(feature): implement user validation"

# REFACTOR: Clean up (optional, separate commit)
git commit -m "refactor(feature): extract validation rules to separate class"
```

### Common Commands

```bash
# Run tests
composer test

# Run static analysis
composer phpstan

# Run code style fixer
composer cs-fix
```
