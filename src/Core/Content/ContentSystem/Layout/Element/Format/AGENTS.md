@README.md

## Constraints

- All classes are `@internal` — no BC promise
- `FormatOption` breakpoint properties are all nullable (`string|bool|float|null`) — null means no override for that breakpoint
- `name()` must be unique across all registered options — enforced by ServiceLocator index
- `valueConstraints()` applied per-breakpoint value during serialization in `ElementFormatFieldSerializer`
- `ElementFormat::toArray()` returns `[]` when no options set — triggers omission from JSON output in `ContentElement::jsonSerialize()`
