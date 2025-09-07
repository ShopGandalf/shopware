# ast-grep (sg) Command Reference

## Patterns
```bash
sg -p '$SERVICE->$METHOD($$$)' -l php
sg -p 'new $CLASS($$$)' -l php
sg -p 'class $C extends $BASE' -l php
sg -p '#[Route($$$)]' -l php
sg -p 'dispatch(new $EVENT($$$))' -l php
sg -p '@Component' -l ts src/Administration
sg -p 'public function __construct($$$, $SERVICE $VAR, $$$)' -l php
sg -p 'public static function getSubscribedEvents()' -l php
sg -p 'new $FIELD($$$)' -l php src/Core/*/Definition/
sg -p 'Module("$NAME", {' -l ts src/Administration
sg -p '$EVENT::EVENT_NAME' -l php
sg -p 'interface $I extends $P' -l php
```

## Flags
```bash
-l php|ts|js|yaml|xml  # language
-r 'replacement'       # rewrite
-i                     # interactive
--json                 # JSON output
--report-style short   # condensed
```

## Scan
```bash
sg scan src/Core/                           # all rules
sg scan --rule .sg/rules/find-entity-definitions.yml src/
sg scan --json src/Core/Content/Product/
sg scan --report-style short src/Core/
```

## Rules (14 total)
```bash
# DAL/Entity
find-entity-definitions    # class extending EntityDefinition
find-custom-fields         # entities with CustomFields::class
find-translated-fields     # TranslatedField definitions
find-id-fields            # new IdField(...) definitions
find-json-fields          # new JsonField(...) definitions

# Repository
find-repository-classes    # class extending EntityRepository
find-repository-search     # ->search($criteria, $context) calls

# Events
find-event-subscribers     # implements EventSubscriberInterface
find-event-dispatch        # ->dispatch($event) calls
find-subscribed-events-method # getSubscribedEvents() methods

# Controller
find-route-attributes      # #[Route(...)] attributes

# Testing
find-test-classes         # class extending TestCase

# Migration
find-migrations           # class extending MigrationStep
find-alter-table          # ALTER TABLE statements
```

## When NOT to use sg

Use Grep instead for:
- Comments: TODO, FIXME, notes, docblocks
- String content: Error messages, translations, text in quotes
- Documentation: .md, .txt, README files
- Config as text: .env values, .json, .yml content
- Partial text: Fragments, substrings, case-insensitive search

Use other tools for:
- Cross-file relationships: "Find classes using X AND implementing Y"
- Variable suffixes/prefixes: `$VAR_Repository` → use `sg -p 'class $C' | grep Repository`
- File discovery: Use Glob for finding files by name pattern
- Non-code analysis: Logs, CSV, data files

