# liberusoftware/ecommerce-contracts

The boundary between the Liberu ecommerce core and modules distributed on their own. Both sides require this package, so Composer refuses to install a module built for an incompatible core.

It holds interfaces and small value objects only: no Laravel models and no framework dependency.

| Namespace | For |
| --- | --- |
| `Modules` | Declaring a module to stores, reading its per-store state, panel plugins, activation hooks, abilities |
| `Fulfilment` | Sending orders to a supplier and reading tracking |
| `Catalog` | Describing a supplier's products for the core to import |

## Versioning

Semantic versioning, with bare tags (`1.2.0`, not `v1.2.0`).

- **Major:** removing or changing anything here, or adding a method to an interface, because modules implement them.
- **Minor:** a new interface, a new optional value-object argument at the end of a constructor, or a new `Abilities` constant.
- **Patch:** documentation and fixes with no change in signature.

A module requires this package at `^1.0`. New optional behaviour goes in a new interface that a module may also implement, as `ProvidesPanelPlugins` does, so that adding it is never a major version.

See `docs/MODULE_ARCHITECTURE.md` in the core for how to build a module.
