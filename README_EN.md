# Y Modules

## The "Three Whales" Concept

Y Modules is a new paradigm for web platform development, founded on three fundamental principles:

1. **Not a single line of superfluous code** — elimination of redundant plugins, libraries, and code
2. **Not a single unnecessary request** — optimisation of all interactions with databases, servers, APIs, and browsers
3. **Maximum achievable speed** — attaining perfect 100/100/100/100 PageSpeed scores and lightning-fast UI response

In a world where most CMSs and frameworks accumulate excessive code and suboptimal solutions, Y Modules offers a radically different approach: returning to the essence of web technologies through strict modularity, minimalism, and high performance.

## Why "Y"?

The letter "Y" symbolises:

1. **Y-shaped branching of solutions** — like in the mathematical Y combinator, our approach enables the creation of recursive, self-optimising systems
2. **"Why?"** — continuous critical reassessment of the necessity of each component
3. **"Yes"** — positive outcomes for both developers and users
4. **Way** — a new direction in web development

Similar to the renowned Y Combinator that transformed the startup paradigm, Y Modules aims to revolutionise the approach to web development, making it more rational, performant, and scalable.

## Current Focus: WordPress

We begin with the most widespread, yet most criticised CMS in the world — WordPress. Many consider it outdated, insecure, and slow. We intend to prove otherwise.

### Modules versus Plugins

**Instead of hundreds of plugins — one plugin with modules:**

```
WordPress
├── WooCommerce (if needed)
└── Y Modules
    ├── Classic Editor Module
    ├── Cyr to Lat Module
    ├── SEO Module
    └── ...other modules as required
```

### Advantages of the modular approach:

- **Strict typing** — all components utilise strict typing to prevent errors
- **Adherence to principles** — SOLID, PSR, DRY, KISS, and other best practices
- **Runtime isolation** — modules are maximally separated from WordPress core runtime
- **Absence of frontend dependencies** — ideal: static frontend from cache without unnecessary requests
- **Standardisation and validation** — automatic verification of all modules for compliance with standards

## Technical Stack

### Current technologies:
- **PHP (OOP)** — foundation for WordPress modules with an emphasis on OOP and strict typing
- **TypeScript** — typed client-side development
- **WebAssembly + Go** — for high-performance components

### Planned technologies:
- **Rust** — for performance-critical modules
- **Zig** — experimental high-performance components
- **Python** — for modules related to ML and data analysis

## Promising WebAssembly Applications in WordPress

1. **Pre-compilation of templates to WebAssembly** — rendering acceleration through compilation
2. **Client-side rendering without server requests** — reducing backend load
3. **Real-time media optimisation** — processing images and videos on the client
4. **Intelligent content preloading** — anticipatory loading based on interaction patterns
5. **Isolated UI components** — interface blocks independent of WordPress
6. **Incremental interface loading** — only what the user needs at the moment
7. **Balancing between dynamic and static content** — intelligent caching
8. **Cache invalidation based on data changes** — precise updating of only changed elements
9. **Prioritisation of visible content** — optimisation for "above the fold" content
10. **Offloading heavy calculations to the client** — freeing server resources
11. **Client-side state management system** — Go + WASM for efficient data management
12. **Updating only changed parts of the DOM** — virtual DOM on WASM

## Platform Development

1. **WordPress** — starting point where the need for optimisation is most critical
2. **Custom solutions** — planned support for Slim, Flight, and proprietary developments
3. **Integration with popular platforms** — Symfony, Laravel, Bitrix, ModX, Magento, and others

## For Developers

### What you need to know now:
- WordPress API
- OOP PHP with an emphasis on strict typing
- Modern JavaScript/TypeScript
- WebAssembly fundamentals
- Go for high-performance components

### How to contribute:
1. Study the project documentation and standards
2. Select a module for development or improvement
3. Follow the "Three Whales" principles during development
4. Pass automatic validation before submitting a PR

## Project Future

Our goal is to create an open ecosystem of modules that will change perceptions about the capabilities of WordPress and other CMSs. After accumulating a critical mass of real-world cases, we plan to expand the community of module developers and curators.

With Y Modules, you can create websites that:
- Load instantly (PageSpeed 100/100/100/100)
- Operate securely and stably
- Do not require endless plugin updates
- Scale and modify effortlessly

---

**Y Modules is not merely optimisation; it's a new philosophy of web development.** 