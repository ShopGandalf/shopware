# Format

Responsive per-breakpoint format options for content elements. Each option provides layout/styling hints keyed by breakpoint (`xs`, `sm`, `md`, `lg`, `xl`, `xxl`).

## Key Classes

- `ElementFormat` - Immutable container holding `FormatOption` instances keyed by `name()`
- `FormatOption` - Abstract base class; subclasses implement `name()` and `valueConstraints()`
- `FormatOptionRegistry` - DI-backed registry via `ServiceLocator<FormatOption>`; `all()` returns registered options
- `Breakpoint` - Enum defining 6 breakpoint keys (`xs`, `sm`, `md`, `lg`, `xl`, `xxl`)

## Built-in Options

| Class         | Name           | Value Type | Constraints                   |
|---------------|----------------|------------|-------------------------------|
| `Display`     | `display`      | bool       | Type(bool)                    |
| `AlignSelf`   | `align-self`   | string     | Type(string), NotBlank        |
| `JustifySelf` | `justify-self` | string     | Type(string), NotBlank        |
| `ColSpan`     | `col-span`     | integer    | Type(integer), GreaterThan(0) |
| `RowSpan`     | `row-span`     | integer    | Type(integer), GreaterThan(0) |
| `Padding`     | `padding`      | string     | Type(string), NotBlank        |
| `Margin`      | `margin`       | string     | Type(string), NotBlank        |

## Extension

Add custom options by extending `FormatOption` and registering with the `content_system.format_option` service tag. The registry indexes by `name()`.
