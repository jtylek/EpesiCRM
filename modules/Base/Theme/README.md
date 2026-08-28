# Base/Theme

The core theming module: on install it sets the `default_theme` variable (currently
`adminltedark`) that determines which theme every other module's `theme_*` templates and CSS are
rendered under. Other modules declare a dependency on it before installing their own default
theme via `Base_ThemeCommon`.

Part of the **Epesi Core** package on the admin Setup screen.

## License

Released under the [MIT License](https://opensource.org/licenses/MIT).

Copyright © 2006-2026 by Janusz Tylek and Karina Tylek
