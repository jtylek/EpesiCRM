<?php
// Minimal Smarty / plugin stubs for PHPStan (keeps signatures non-strict)
class Smarty {
    public function fetch(string $template = null, mixed $cache_id = null, mixed $compile_id = null, mixed $parent = null): string {}
    public function display(string $template = null): void {}
    public function assign(mixed $var, mixed $value = null): void {}
    public function registerPlugin(string $type, string $name, mixed $callback): void {}
}

/** Internal template placeholder class used by some plugins */
class Smarty_Internal_Template {}

/**
 * Minimal signature for Smarty's eval plugin helper.
 * Real plugin returns evaluated string — keep a permissive signature.
 *
 * @param array<string,mixed> $params
 * @param Smarty_Internal_Template|null $template
 * @return string
 */
function smarty_function_eval(array $params, Smarty_Internal_Template $template = null): string {}
