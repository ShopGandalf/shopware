# Twig UX components

Guidelines to write uniform Twig UX components that follow our best practices and keep extensibility in mind.

## Anonymous components

* We recommend anonymous components without a PHP class that are declared in twig.
* Components with a PHP class can only be used in on-prem (plugin system) environments.
* The app system only supports anonymous components.

## Naming and directory structure

* Each component name must be unique.
* Default storefront components are in the `Sw` namespace. `<twig:Sw:Button>`
* Component names are written in uppercase.
* 3rd party components bring their own namespace e.g. `<twig:Acency:Button>`.
* Template, (S)CSS and JavaScript are in the same directory.

```
components/Button/
    Button.html.twig
    Button.js
    Button.css
```

✅ Do:
```html
<twig:Sw:Button>My button</twig:Sw:Button>
```

❌ Don't
```html
<twig:sw-button>My button</twig:sw-button>
```

### Class naming

* Each component must have a unique root class that is used throughout the component markup and CSS.
* The root class also uses the `sw-` prefix to be independent of existing/old components.
* Child elements must "follow" the root class naming.
* The BEM naming pattern is used.

✅ Do:
```html
<div class="sw-product-card card">
    <div class="sw-product-card__body card-body">
        <h2 class="sw-product-card__title">Card title</h2>
    </div>
    <div class="sw-product-card__footer card-footer">
    </div>
</div>
```

```css
.sw-product-card {
    /* Styling */
}

.sw-product-card__body {
    /* Styling */
}

.sw-product-card__title {
    /* Styling */
}
```

❌ Don't
```html
<div class="sw-product-card card">
    <div class="sw-card-inner card-body">
        <h2>Card title</h2>
    </div>
    <div class="sw-card-bottom card-footer">
    </div>
</div>
```

```css
.sw-product-card {
    .sw-card-inner {
        h2 {
            /* Styling */
        }
    }
}
```

## Props

* Required props must not be used to prevent hard template errors.
* Provide a fallback value for props in case a prop is not given.
* When a component is able to work with an object/entity like a product, it should also be possible to use the component without the product object and set the props individually.

```twig
{% props
    product = null,
    name: product ? product.translated.name : 'Example product'
    mediaHeight = 240,
    defaultRoute = '#',
%}

{# When I just render the component without any props, I get a renderd component with some demo content. #}
<twig:Sw:ProductCard />
```

## Slots/Blocks

* A larger component should bring blocks (slots) for each logical section of the component to allow customization.
* Not all components must have blocks. For example when there is no inner HTML element that would make sense to customize.
* A simple (one tag) component like a button must bring a default content block `{% block content %}`
* A component block must not be prefixed with the components name since it is automatically namespaced to the component.

## Data independence and global state access

* A component should be as independent as possible.
* A component must not rely on its parent component in order to function correctly.
* A component must not rely on global variables or state internally in order to function correctly.
* If a global setting is needed e.g. `config('core.listing.allowBuyInListing')` it should be able to be passed as a prop from outside.
* A component can use symfony translation internally.

```twig
{# ProductCard.html.twig #}
{% props
    name = 'Example product',
    allowsBuyAction = config('core.listing.allowBuyInListing')
%}

{# Usage: #}
<twig:Sw:ProductCard
  name="{{ product.name }}"
  allowsBuyAction="false"
/>
```

## Attributes and CVA

* A component should make attributes extendable using the attributes feature of symfony UX.
* Attributes must not be hardcoded in the HTML elements.
* Use nested attributes for child-elements.
* A component should also use CVA to make the CSS-classes configurable.
* The CVA must bring the `base` variant by default.
* A `defaultVariants` prop should be available to allow extending the CVA variants.
* A `defaultBaseClasses` prop should be available to allow changing of the base classes of the component.
* A `defaultAttributes` prop should be to available allow changing of the default attributes of the component.

✅ Do:
```twig
{% props
    defaultBaseClasses = 'sw-product-card card',
    defaultVariants = {},
%}

{% set rootVariants = defaultVariants|merge({
    base: defaultBaseClasses,
}) %}

{% set rootCVA = cva(rootVariants) %}

<div {{ attributes.defaults({ role: 'article' }) }} class="{{ rootCVA.apply({}, attributes.render('class')) }}">
    ...
</div>
```

❌ Don't
```twig
<div class="sw-product-card card" role="article">
    ...
</div>
```

## Sub components

* It is allowed to create sub components that are part of a larger component.
* The sub components must live under the same namespace.
* If a component should be used in other areas as well it should be an independent component instead.

```
components/ProductCard
    ProductCard.html.twig
    Actions.html.twig
```

```twig
<twig:Sw:ProductCard:ProductCard></twig:Sw:ProductCard:ProductCard>
<twig:Sw:ProductCard:Actions></twig:Sw:ProductCard:Actions>
```

## CSS

* Prefer native CSS and custom properties over SCSS

