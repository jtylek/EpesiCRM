# Base/Box

The basic Smarty-templated container that every page renders into: it reads the active theme's `default.ini`
layout file to decide which containers/modules go where (e.g. main content vs. anonymous/logged-in-only
areas) and packs the corresponding modules accordingly. As the module present on every page, logged in or
not, it is also where the active theme's framework assets get requested.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
