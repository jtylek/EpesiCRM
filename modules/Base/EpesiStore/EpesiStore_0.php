<?php

/**
 * 
 * @author Adam Bukowski <abukowski@telaxus.com>
 * @copyright Copyright &copy; 2011,2012 Janusz Tylek
 * @license MIT
 * @version 20121013
 * @package epesi-Base
 * @subpackage EpesiStore
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Base_EpesiStore extends Module {
    // colors

    const color_success = 'green';
    const color_failure = 'gray';

    // 'action' is the raw server-suggested action string (e.g. "buy") -
    // not a real interactive column, just noise once row actions (the
    // trash-icon remove, the ActionBar's own Buy button) already cover it.
    // 'required_modules'/'needed_modules' dropped per request too - the
    // per-module "repository::name" formatting that used to build
    // 'required_modules' for display is gone with it (dead work otherwise -
    // see AI-private/ESS-checkout.md).
    // 'total_price' dropped, not 'price' (reversed from an earlier pass):
    // for a bundled product (e.g. E-mail Campaign Manager, which requires
    // List Manager) 'price' ($199) and 'total_price' ($248 = $199 + List
    // Manager's own $49) are genuinely different - 'total_price' is the
    // combined cost including required modules. Wrongly assumed identical
    // and used for the "Price" column/cart total, which double-counted a
    // bundled dependency's price: it's both folded into the parent's
    // 'total_price' AND listed again as its own separate cart row (Common's
    // ACTION_ADD_TO_CART adds required modules as their own line items).
    // 'price' is each row's own standalone price - correct for a per-row
    // column, and what the cart total (display_cart_total()) must sum.
    protected $banned_columns_module = array('id', 'owner_id', 'path', 'files', 'active', 'bought', 'paid', 'repository', 'package_file', 'action', 'required_modules', 'needed_modules', 'total_price');
    protected $banned_columns_order = array('id', 'installation_id');

    // Relative column widths for the Buy listing and Cart grids
    // (GB_module()/GB_generic()) - Description is the only field with any
    // real variable-length content, so it gets most of the room; see
    // AI-private/ESS-checkout.md.
    const MODULE_COLUMN_WIDTHS = array('item' => 12, 'description' => 62, 'version' => 15, 'price' => 11);
    // Shortened rather than fighting for more width - "Version" kept
    // truncating to "Ve..." even at a generous share; 'price' already reads
    // fine via the default ucwords() label.
    const MODULE_COLUMN_LABELS = array('version' => 'Ver.');

    public function body() {

    }

    // Base_MainModuleIndicator delegation (MainModuleIndicator_0.php's
    // caption()/icon() calls on the active main module) - without these the
    // navbar's module indicator showed blank whenever Base_EpesiStore itself
    // is the pushed main module (e.g. the Cart screen, reached directly via
    // Base_EpesiStoreCommon::show_cart()), since neither method existed here
    // before.
    public function caption() {
        return __('Epesi Store');
    }

    // 'cart.png' is a synthetic lookup key, not a real file - it only needs
    // to resolve via Base_BootstrapIcons::$by_filename (bootstrap_icons.php),
    // which returns a shopping-cart glyph directly without needing this
    // module's own bootstrap_icon() (Base_EpesiStoreCommon's is bi-box-seam,
    // used for the sidebar/launcher - a generic "store" icon there, not
    // specific enough for "you're looking at your cart" here).
    public function icon() {
        return 'cart.png';
    }

    public function admin() {
        if ($this->is_back()) {
            $this->parent->reset();
            return;
        }
        $this->back_button();
		$this->manage();
	}

    public function manage() {
        if (!Base_EpesiStoreCommon::admin_access())
            return;
        $button_label = Base_EssClientCommon::has_license_key()
                ? __('License Key') : __('Register Epesi!');
		Base_ActionBarCommon::add(
                'license-key',
                $button_label,
                $this->create_callback_href($this->display_registration_form(...)),
                null, 30);

        $invoices_form_name = null;
        print create_html_form($invoices_form_name, Base_EssClientCommon::get_invoices_url(),
                array('key' => Base_EssClientCommon::get_license_key(), 'noheader' => 1), '_blank');
        Base_ActionBarCommon::add('invoices', __('Invoices'),
                'href="javascript:void(0)" onClick="document.' . $invoices_form_name . '.submit();"',
                null, 40);

        $setup = $this->init_module('Base_Setup');
        $setup->set_inline_display();
        if (Base_SetupCommon::is_simple_setup()) {
			if (!$this->isset_module_variable('filter_set')) {
				eval_js('base_setup__last_filter="'.(Base_EssClientCommon::has_license_key() && Base_EpesiStoreCommon::is_update_available()?'updates':'installed').'";');
				$this->set_module_variable('filter_set', true);
			}
            $this->display_module($setup, array(true), 'admin');
            return;
        }

        Base_ActionBarCommon::add('settings', __('Simple view'), $this->create_callback_href($this->switch_simple(...), true));
        // Only shown here (Advanced view), not on the Simple/card Store tab
        // (Setup_0.php::add_store_products() used to add it there too) - see
        // AI-private/ESS-checkout.md.
        $store_visible = Base_SetupCommon::is_store_visible();
        $icon = $store_visible ? 'store-disable' : 'add';
        $text = $store_visible ? __('Disable EPESI Store') : __('Enable EPESI Store');
        $href = $this->create_callback_href(array('Base_SetupCommon', 'set_store_visibility'), array(!$store_visible));
        $desc = $store_visible ? __('Disabling communication with EPESI Store will improve processing speed, but will not update the list of additional modules in the store.') : '';
        Base_ActionBarCommon::add($icon, $text, $href, $desc);
        $tb = $this->init_module('Utils_TabbedBrowser');
        $tb->set_tab('Modules Setup', $this->setup_admin(...), array($setup));
		$tb->set_tab('Epesi Store', $this->form_main_store(...), array());
        $tb->tag();
        $this->display_module($tb);
    }
	
	public function setup_admin($setup) {
		$this->display_module($setup, array(true), 'admin');
	}

    public function switch_simple($value) {
        Base_SetupCommon::set_simple_setup($value);
        location(array());
    }

    private function client_messages() {
        print(Base_EssClientCommon::client_messages_frame());
    }

    private function navigation_buttons() {
        Base_EpesiStoreCommon::navigation_button_cart();
        $this->navigation_button_your_modules();
        $this->navigation_button_orders();
        $this->navigation_button_downloads(false);
    }

    private function navigation_button_downloads($display_empty = false) {
        $count = count(Base_EpesiStoreCommon::get_download_queue());
        if ($display_empty || $count) {
            if ($count == 0)
                $count = __('Empty');
            $download = __('Downloads');
            Base_ActionBarCommon::add('clone', "$download ($count)", $this->href_navigate('form_downloads'));
        }
    }

    private function navigation_button_orders() {
        Base_ActionBarCommon::add('view', __('Orders'), $this->href_navigate('form_orders'), __('Here you can pay for ordered modules'));
    }

    private function navigation_button_your_modules() {
        Base_ActionBarCommon::add('search', __('Your modules'), $this->href_navigate('form_your_modules'), __('Install bought modules, check for updates'));
    }

    public function form_main_store() {
        if (Base_EssClientCommon::has_license_key()) {
            $this->navigation_buttons();
            $this->display_modules();
        } else {
            if (TRIAL_MODE) {
                print('<span class="important_notice">EPESI store is unavailable during the trial.</span>');
            } else {
                $this->display_registration_form();
            }
        }
        $this->client_messages();
    }

    private function display_modules() {
        /* @var $gb Utils_GenericBrowser */
        $gb = $this->init_module(Utils_GenericBrowser::module_name(), null, 'moduleslist');
        // We need total amount of available modules to set GB paging.
        // It's returned with each request for modules list
        // so with first request we use only get_limit to retrieve
        // 'per_page' (numrows) value, then again get_limit to set proper
        // total amount value.
        $total = $this->get_module_variable('modules_total');
        $x = $gb->get_limit($total ?? 500);
        // fetch data
        $ret = Base_EpesiStoreCommon::modules_list($x['offset'], $x['numrows']);
        if ($total === null)
            $x = $gb->get_limit($ret['total']);
        $this->set_module_variable('modules_total', $ret['total']);
        // Already-owned (bought+paid) modules don't belong in a "buy this"
        // listing - see AI-private/ESS-checkout.md. Filtered client-side
        // since modules_list() has no "exclude owned" option of its own;
        // $ret['total']/the pager above are computed server-side before this
        // filter runs, so a catalog large enough to span many pages could in
        // principle under-fill a page after filtering - not a real issue at
        // today's catalog size (one page).
        $modules = array_filter($ret['modules'], function($m) {
            return !(!empty($m['bought']) && !empty($m['paid']));
        });
        if (!$modules)
            print(__('Unfortunately there are no modules available for you.'));
        else {
            $this->compute_dependency_notes($modules);
            $gb = $this->GB_module($gb, $modules, $this->GB_row_additional_actions_store(...), self::MODULE_COLUMN_WIDTHS, self::MODULE_COLUMN_LABELS);
            $this->scope_store_grid($gb);
            $this->display_module($gb);
        }
    }

    /**
     * id => "Requires: X" / "Required by: Y" note, read by
     * GB_row_data_transform_module() (keyed off $data['id']) to fold into
     * Description. A property, not a field added onto each item, precisely
     * so the items passed to GB_module() stay byte-identical to what's
     * actually stored in the cart - GB_row_additional_actions_cart()'s
     * cart_remove_item() does an exact array_search() against the stored
     * cart, which an extra synthetic key on the same array would silently
     * break (the delete button would stop matching any row).
     */
    private $dependency_notes = array();

    /**
     * id => array of names of OTHER items in the same list whose
     * needed_modules names this id - read by GB_row_additional_actions_cart()
     * to disable the delete action for a module something else in the cart
     * still depends on (removing it alone would silently orphan the
     * dependant's bundle - see AI-private/ESS-checkout.md). Same
     * property-not-item-field reasoning as $dependency_notes.
     */
    private $required_by = array();

    /**
     * Populates $dependency_notes/$required_by for this item list - computed
     * here (not in the transform/action callback, which only ever see one
     * row at a time) since "who requires this module" needs visibility
     * across the whole list.
     */
    private function compute_dependency_notes(array $items) {
        $this->dependency_notes = array();
        $this->required_by = array();
        $name_by_id = array();
        foreach ($items as $it) {
            if (isset($it['id'])) $name_by_id[$it['id']] = $it['name'] ?? '';
        }
        foreach ($items as $it) {
            foreach ($it['needed_modules'] ?? array() as $needed_id) {
                $this->required_by[$needed_id][] = $it['name'] ?? '';
            }
        }
        foreach ($items as $it) {
            if (!isset($it['id'])) continue;
            $notes = array();
            $needs = array();
            foreach ($it['needed_modules'] ?? array() as $needed_id) {
                if (isset($name_by_id[$needed_id])) $needs[] = $name_by_id[$needed_id];
            }
            if ($needs)
                $notes[] = __('Requires: %s', array(implode(', ', $needs)));
            if (!empty($this->required_by[$it['id']]))
                $notes[] = __('Required by: %s', array(implode(', ', $this->required_by[$it['id']])));
            if ($notes)
                $this->dependency_notes[$it['id']] = implode(' · ', $notes);
        }
    }

    /**
     * Wraps a Buy-listing/Cart Utils_GenericBrowser instance in
     * .epesi-store-grid and loads this module's own theme CSS, so
     * theme_adminltedark/default.css's larger action-icon/padding overrides
     * apply only here, not to every GenericBrowser table in the app.
     */
    private function scope_store_grid(Utils_GenericBrowser $gb) {
        Base_ThemeCommon::load_css(self::module_name(), 'default', false);
        $gb->set_prefix('<div class="epesi-store-grid">');
        $gb->set_postfix('</div>');
    }

	static $return = true;
    public function reset() {
		self::$return = false;
		location(array());
	}
    public function display_registration_form() {
        $m = $this->init_module(Base_EssClient::module_name());
        $this->display_module($m, array(true), 'admin');
		return self::$return;
    }

    public function form_your_modules() {
        $this->back_button();
        $this->navigation_button_downloads();

        $module_licenses = Base_EssClientCommon::server()->module_licenses_list();
        $this->client_messages();
        $this->display_your_modules($module_licenses);
    }

    private function display_your_modules($module_licenses) {
        if (count($module_licenses) == 0) {
            print(__('You haven\'t bought any modules'));
            return;
        }
        $this->_module_licenses_add_module_versions($module_licenses);
        $to_download = $this->_modules_to_download_and_update($module_licenses);
        if ($to_download)
            Base_ActionBarCommon::add('favorites', __('Download newer'), $this->create_callback_href($this->download_modules(...), array($to_download)));
        $gb = $this->init_module(Utils_GenericBrowser::module_name(), null, 'mymoduleslist');
        $gb = $this->GB_module_licenses($gb, $module_licenses, $this->GB_row_additional_actions_your_modules(...));
        $this->display_module($gb);
    }

    private function _module_licenses_add_module_versions(& $module_licenses) {
        $downloaded = $this->_get_downloaded_modules_versions();
        foreach ($module_licenses as & $ml) {
            $modinfo = Base_EpesiStoreCommon::get_module_info($ml['module']);
            $ml['downloaded_version'] = $downloaded[$ml['module']] ?? '';
            $ml['current_version'] = $modinfo['version'];
        }
    }

    private function _get_downloaded_modules_versions() {
        $downloaded_modules = Base_EpesiStoreCommon::get_downloaded_modules();
        $downloaded = array();
        foreach ($downloaded_modules as $d) {
            $downloaded[$d['module_id']] = $d['version'];
        }
        return $downloaded;
    }

    private function _modules_to_download_and_update($module_licenses) {
        $to_download = array();
        foreach ($module_licenses as $ml) {
            if ($this->_module_license_needs_download_or_update($ml))
                $to_download[] = $ml;
        }
        return $to_download;
    }

    private function _module_license_needs_download_or_update($module_license) {
        return !$module_license['downloaded_version']
                || $this->_version_is_newer($module_license['downloaded_version'], $module_license['current_version']);
    }

    private function _version_is_newer($old_version, $new_version) {
        return Base_EpesiStoreCommon::version_compare($old_version, $new_version) < 0;
    }

    public function form_cart() {
        $this->back_button();

        $items = Base_EpesiStoreCommon::get_cart();

        $this->display_cart($items);
    }

    private function display_cart_items($items) {
        Base_ActionBarCommon::add('delete', __('Clear cart'), $this->create_callback_href(array('Base_EpesiStoreCommon', 'empty_cart')));
        $gb = $this->init_module(Utils_GenericBrowser::module_name(), null, 'cartlist');
        $this->compute_dependency_notes($items);
        $gb = $this->GB_module($gb, $items, $this->GB_row_additional_actions_cart(...), self::MODULE_COLUMN_WIDTHS, self::MODULE_COLUMN_LABELS);
        $this->scope_store_grid($gb);
        $this->display_module($gb);
        $this->display_cart_total($items);
    }

    /**
     * Splits a pre-formatted price string (e.g. "$&nbsp;49.00") into a
     * right-aligned "whole" span (min-width so shorter numbers, e.g. $49,
     * still end at the same x as $199) and a left-aligned ".00"-and-beyond
     * span - the standard decimal-alignment trick. Plain text-align:right
     * alone only lines up the string's right edge, which for "$49.00" vs
     * "$199.00" leaves the decimals themselves offset by the extra digit.
     * Shared by GB_row_data_transform_module()'s Price column and
     * display_cart_total()'s footer total, so both align on the same rule.
     * Returns the original string unsplit if it doesn't look like "...N.NN".
     */
    private static function align_price_decimal($price) {
        if (!is_string($price) || !preg_match('/^(.*?)(\.\d+)$/', trim($price), $m))
            return $price;
        return '<span style="display:inline-block;min-width:3ch;text-align:right;">' . $m[1] . '</span>'
                . '<span style="display:inline-block;text-align:left;">' . $m[2] . '</span>';
    }

    /**
     * Sums each item's own 'price' (same pre-formatted-by-the-server display
     * string the Price column itself shows, e.g. "$&nbsp;49.00" - there's no
     * separate raw numeric field available here) and prints a grand total
     * row directly under the cart grid. Deliberately NOT 'total_price' -
     * that field bundles in a module's own required-module cost (e.g. E-mail
     * Campaign Manager's 'total_price' already includes List Manager's $49),
     * and List Manager is also its own separate cart row (ACTION_ADD_TO_CART
     * adds required modules as their own line items) - summing 'total_price'
     * across all rows double-counts every bundled dependency. Assumes a
     * single currency across the cart (this store only ever shows one) - the
     * total re-uses whichever currency prefix/suffix the priced items
     * themselves used, which may itself be/contain HTML (e.g. "&nbsp;") -
     * printed as-is, not htmlspecialchars()'d, same as every other
     * server-supplied price/description string in this transform.
     */
    private function display_cart_total($items) {
        $sum = 0;
        $prefix = '';
        $suffix = '';
        $matched = false;
        foreach ($items as $item) {
            $price = $item['price'] ?? null;
            if (!is_string($price) || !preg_match('/^(\D*)([\d.,]+)(\D*)$/', trim($price), $m))
                continue;
            $matched = true;
            $prefix = $m[1];
            $suffix = $m[3];
            $sum += (float) str_replace(',', '', $m[2]);
        }
        if (!$matched) return;
        // Styled as a second, immediately-adjacent "card" so it reads as
        // this table's own footer row instead of a disconnected line below
        // it - GenericBrowser's own card (theme_adminltedark/default.tpl's
        // <div class="epesi-gb card mb-3">) has no summary-row hook of its
        // own to extend instead. Column widths mirror MODULE_COLUMN_WIDTHS
        // so the total lines up under the Price column.
        $label_pct = self::MODULE_COLUMN_WIDTHS['item'] + self::MODULE_COLUMN_WIDTHS['description'] + self::MODULE_COLUMN_WIDTHS['version'];
        $price_pct = self::MODULE_COLUMN_WIDTHS['price'];
        print('<div class="epesi-gb card mb-3" style="margin-top:-1rem;border-top-left-radius:0;border-top-right-radius:0;">'
                . '<div class="d-flex align-items-center">'
                . '<div class="fw-bold text-end px-3 py-2" style="flex:' . $label_pct . ' ' . $label_pct . ' 0;">' . __('Total Price') . '</div>'
                . '<div class="fw-bold text-end px-3 py-2" style="flex:' . $price_pct . ' ' . $price_pct . ' 0;font-variant-numeric:tabular-nums;">' . self::align_price_decimal($prefix . number_format($sum, 2) . $suffix) . '</div>'
                . '</div></div>');
    }

    private function display_cart($items) {
        if (count($items) == 0) {
            // Same centered-card pattern as Base_EssClient's registration
            // confirmation (EssClient_0.php ~56-77) - consistent "nice card"
            // look for a standalone status screen, not a data grid.
            if (Base_ThemeCommon::is_adminlte_family()) {
                print('<div class="d-flex justify-content-center py-4">');
                print('<div class="card" style="max-width:600px;width:100%;">');
                print('<div class="card-body text-center">');
                print('<i class="bi bi-cart-x text-muted" style="font-size:3rem;"></i>');
                print('<h3 class="mt-3 mb-3">' . __('Your cart is empty') . '</h3>');
                print('<p class="text-muted mb-3">' . __('Add modules from the Store to buy several at once.') . '</p>');
                print('<a class="btn btn-primary" ' . $this->create_back_href() . '>' . __('Continue shopping') . '</a>');
                print('</div></div></div>');
            } else {
                print('<div class="important_notice">' . __('Your cart is empty') . '</div>');
            }
            return;
        }

        $f = $this->init_module(Libs_QuickForm::module_name());
        $show_cart = true;
        if ($f->validate() && $f->exportValue('submited')) {
            $price_changed = $this->_refresh_cart_prices($items);
            if (!$price_changed) {
                $show_cart = false;
                $this->form_buy_items($items);
            } else {
                $message = __('One or more prices changed on the server since these items were added to your cart. The list below has been refreshed with the current prices - please review it and click Buy again to confirm the purchase.');
                if (Base_ThemeCommon::is_adminlte_family()) {
                    print('<div class="alert alert-warning d-flex align-items-start gap-2 mb-3" role="alert">'
                            . '<i class="bi bi-exclamation-triangle-fill mt-1"></i><div>' . $message . '</div></div>');
                } else {
                    print('<span style="color:red">' . $message . '</span>');
                }
                Base_EpesiStoreCommon::set_cart($items);
            }
        }
        if ($show_cart) {
            $this->display_cart_items($items);
            // Explicit position=1 (every other cart-screen button is the
            // default 0) so this sorts after Back/Clear cart regardless of
            // icon sprite order - see ActionBar_0.php::compare(). Own 'buy'
            // icon key, not 'folder', so the AdminLTE theme can single this
            // one button out for its pill-button treatment (Base_ActionBar/
            // theme_adminltedark/default.tpl) without affecting every other
            // 'folder'-icon action app-wide.
            Base_ActionBarCommon::add('buy', __('Buy'), $f->get_submit_form_href(), null, 1);
            $f->display();
        }
    }

    /**
     * Re-checks each cart item's own 'price' against the server, in place,
     * and reports whether anything actually changed. Deliberately narrower
     * than the old whole-array equality check it replaced (compared every
     * field, not just price) - the items originally stored in the cart came
     * from whichever screen added them (modules_list() for the Buy listing/
     * Simple-view cards, get_module_info() for a bundled dependency added by
     * ACTION_ADD_TO_CART), while this re-check always calls get_module_info()
     * - two different ESS server endpoints, plausibly returning slightly
     * different field sets/values for the same module even when nothing
     * priced actually changed, which made the old check false-positive on
     * effectively every cart. Only price is what a pre-purchase re-check is
     * actually meant to protect against, so only price is compared - and
     * only price is overwritten in place, keeping every other field (name,
     * needed_modules, etc. - some of which get_module_info()'s own response
     * shape may not even carry) exactly as originally stored.
     * @return bool whether any item's price changed
     */
    private function _refresh_cart_prices(array &$items) {
        $ids = array();
        foreach ($items as $r)
            $ids[] = $r['id'];
        $current = Base_EpesiStoreCommon::get_module_info($ids);
        $changed = false;
        foreach ($items as &$item) {
            $new_price = $current[$item['id']]['price'] ?? null;
            if ($new_price !== null && ($item['price'] ?? null) !== $new_price) {
                $item['price'] = $new_price;
                $changed = true;
            }
        }
        unset($item);
        return $changed;
    }

    private function form_buy_items($items) {
        $server_response = $this->_order_submit($items);
        $this->client_messages();
        $this->display_order_submit_response($server_response);
    }

    /**
     * order_submit() returns one order for however many module ids were
     * submitted - {order_id, needs_payment}, the same shape
     * Base_EpesiStoreCommon::handle_module_action()'s single-item ACTION_BUY
     * case already relies on, not a per-item {module_id: true|string} map
     * (this used to assume the latter, a stale contract - the order was
     * placed server-side but the checkout never reached the payment step).
     */
    private function display_order_submit_response($server_response) {
        $ordered = isset($server_response['order_id']) && $server_response['order_id'] !== null;
        if (!$ordered) {
            print('<span style="color: ' . self::color_failure . '">' . __('Order failed.') . '</span><br/>');
            $this->navigation_button_your_modules();
            return;
        }
        if (!empty($server_response['needs_payment'])
                && Base_EpesiStoreCommon::display_payments_for_order($server_response['order_id']) === true) {
            Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href());
            return;
        }
        print('<span style="color: ' . self::color_success . '">' . __('Order placed successfully.') . '</span><br/>');
        $this->navigation_button_your_modules();
    }

    private function _order_submit($items) {
        $modules_ids = array();
        foreach ($items as $r)
            $modules_ids[] = $r['id'];
        Base_EpesiStoreCommon::empty_cart();
        return Base_EssClientCommon::server()->order_submit($modules_ids);
    }

    /**
     * Add module to cart
     * @param array $r modules data
     */
    public function cart_add_item($r) {
        Base_EpesiStoreCommon::cart_add_item($r);
    }

    /**
     * Remove module from cart
     * @param array $r modules data
     */
    public function cart_remove_item($r) {
        Base_EpesiStoreCommon::cart_remove_item($r);
    }

    public function form_orders() {
        $this->back_button();
        $this->navigation_button_your_modules();
        $this->payments_data_button();

        $orders = Base_EssClientCommon::server()->orders_list();
        $this->client_messages();
        $this->display_orders($orders);
    }

    private function display_orders($items) {
        if (count($items) == 0) {
            print(__('You don\'t have any orders'));
        } else {
            $gb = $this->init_module(Utils_GenericBrowser::module_name(), null, 'orderslist');
            $this->GB_order($gb, $items);
            $this->display_module($gb);
        }
    }

    /**
     * Navigate to direct download of specified modules
     * @param array $module_licenses array of module licenses data arrays
     */
    public function download_modules($module_licenses) {
        Base_EpesiStoreCommon::empty_download_queue();
        foreach ($module_licenses as $m) {
            $this->download_queue_item($m);
        }
        $this->process_downloading();
    }

    /**
     * @param array $r order data
     */
    public function download_queue_item($r) {
        $q = Base_EpesiStoreCommon::get_download_queue();
        // use order id to compare is it in queue already
        if (!isset($r['id']))
            return;
        foreach ($q as $x) {
            if (isset($x['id']) && $x['id'] == $r['id'])
                return;
        }
        $q[] = $r;
        Base_EpesiStoreCommon::set_download_queue($q);
    }

    /**
     * @param array $r order data
     */
    public function download_dequeue_item($r) {
        $q = Base_EpesiStoreCommon::get_download_queue();
        $k = array_search($r, $q);
        if ($k !== false) {
            unset($q[$k]);
            Base_EpesiStoreCommon::set_download_queue($q);
        }
    }

    public function form_downloads() {
        $this->back_button();
        $downloads = Base_EpesiStoreCommon::get_download_queue();
        if (count($downloads)) {
            $this->navigation_button_process_downloading();
        }
        $this->display_downloads($downloads);
    }

    private function navigation_button_process_downloading() {
        Base_ActionBarCommon::add('clone', __('Proceed with download'), $this->create_callback_href($this->process_downloading(...)));
    }

    private function display_downloads($download_items) {
        if (count($download_items) == 0) {
            print(__('No items'));
            return;
        }
        Base_ActionBarCommon::add('delete', __('Clear list'), $this->create_callback_href(array('Base_EpesiStoreCommon', 'empty_download_queue')));
        $gb = $this->init_module(Utils_GenericBrowser::module_name(), null, 'downloadslist');
        $gb = $this->GB_module_licenses($gb, $download_items, $this->GB_row_additional_actions_downloads(...));
        $this->display_module($gb);
    }

    public function download_as_zip($module_license) {
        $hash_or_url = Base_EssClientCommon::server()->download_prepare($module_license['id']);
        $this->client_messages();
        if (!$hash_or_url)
            return;
        $post_data = Base_EssClientCommon::server()->get_module_as_file_post_data_array($hash_or_url);
        $str = '<form method="post" id = "' . $hash_or_url . '" action="' . Base_EssClientCommon::get_server_url() . '">';
        foreach ($post_data as $key => $value) {
            $key = htmlspecialchars($key);
            $value = htmlspecialchars($value);
            $str .= '<input type="hidden" name="' . $key . '" value="' . $value . '"/>';
        }
        print($str);
        eval_js('document.getElementById("' . $hash_or_url . '").submit();');
    }

    public function process_downloading() {
        $module_licenses = Base_EpesiStoreCommon::get_download_queue();
        $status = $this->_download_modules($module_licenses);
        foreach ($module_licenses as $ml) {
            if ($status[$ml['id']] === true) {
                $this->download_dequeue_item($ml);
            }
        }
        Base_SetupCommon::refresh_available_modules();
        $this->navigate('form_downloaded_status', array($module_licenses, $status));
    }

    private function _download_modules($module_licenses) {
        if (!count($module_licenses))
            return array();

        $status = array();
        foreach ($module_licenses as $ml) {
            $status[$ml['id']] = Base_EpesiStoreCommon::download_module($ml);
        }
        return $status;
    }

    public function form_downloaded_status($module_licenses, $status) {
        $this->client_messages();
        $times_back = count(Base_EpesiStoreCommon::get_download_queue()) == 0 ? 2 : 1;
        $this->back_button($times_back);
        foreach ($module_licenses as $ml) {
            $this->display_download_status_info($ml, $status[$ml['id']]);
        }
    }

    private function display_download_status_info($module_license, $status_info) {
        $module_info = Base_EpesiStoreCommon::get_module_info($module_license['module']);
        if ($status_info === true)
            $status_info = __('Success!');
        print("<b>{$module_info['name']}</b> - $status_info<br/>");
        $all_files = $module_info['files'];
        $modules = $this->_extract_modules_names($all_files);
        $this->_print_module_list($modules);
        $this->_print_other_files_list($all_files);
        Base_EpesiStoreCommon::post_install_refresh_by_ajax();
    }

    private function _extract_modules_names(& $all_files) {
        $modules = array();
        $module_prefix = 'modules/';
        $str_length = strlen($module_prefix);
        foreach ($all_files as $f) {
            if (is_dir($f) && substr_compare($f, $module_prefix, 0, $str_length) == 0) {
                $module_dir = substr($f, $str_length);
                // module path with slashes Test/Module
                $module_path = trim($module_dir, DIRECTORY_SEPARATOR);
                if (ModuleManager::exists(str_replace(DIRECTORY_SEPARATOR, '_', $module_path))) {
                    $modules[] = $module_path;
                }
            }
        }
        // remove each file under module path
        foreach ($modules as $mod) {
            $modxx = $module_prefix . $mod;
            foreach ($all_files as $k => $v) {
                if (str_starts_with($v, $modxx)) {
                    unset($all_files[$k]);
                }
            }
        }
        return $modules;
    }

    private function _print_module_list($modules) {
        if (!count($modules))
            return;

        print(__('Modules') . ':<br/>');
        foreach ($modules as $mod) {
            $this->display_module_entry($mod);
        }
    }

    private function display_module_entry($module) {
        $installed = (ModuleManager::is_installed($module) >= 0);
        $install_href = $installed ? '' : $this->create_callback_href($this->_install_module(...), array($module));
        $install_link = " - " . ($install_href ? "<a $install_href>" . __('Install module') . "</a>" : 'Module already installed');
        print(htmlspecialchars($module) . "$install_link<br/>");
    }

    public function _install_module($module) {
        $module = str_replace('/', '_', $module);
        ModuleManager::install($module);
    }

    private function _print_other_files_list($other_files) {
        if (!count($other_files))
            return;

        print(__('Other files:') . '<br/>');
        foreach ($other_files as $file) {
            print(htmlspecialchars($file) . '<br/>');
        }
    }

    private function payments_data_button() {
        $href = $this->create_callback_href($this->navigate(...), array('payments_show_user_settings'));
        Base_ActionBarCommon::add('settings', __('Payment data'), $href, __('Here you can edit your default credentials used to payments'));
    }

    public function payments_show_user_settings() {
        $this->back_button();
        $module_to_show = $this->init_module(Base_User_Settings::module_name());
        $this->display_module($module_to_show, array(__('EPESI Store')));
    }

    public function form_payment_frame($order_id, $value, $curr_code, $modules = null) {
        $this->back_button();
        $this->payments_data_button();

        $payment_url = Base_EssClientCommon::get_payments_url();
        $description = $modules ? "Payment for: $modules" : "Order id: $order_id";
        
        $data = array(
            'action_url' => $payment_url,
            'record_id' => $order_id,
            'record_type' => 'ess_orders',
            'amount' => $value,
            'currency' => $curr_code,
            'description' => $description,
            'auto_process' => '1',
            'lang' => Base_LangCommon::get_lang_code(),
            'hide_page_banner' => '1'
        );

        $credentials = Base_EpesiStoreCommon::get_payment_credentials();
        foreach (array('first_name', 'last_name', 'address_1', 'address_2',
            'city', 'postal_code', 'country', 'email', 'phone') as $key) {
            if (isset($credentials[$key]))
                $data[$key] = $credentials[$key];
        }
        
        $form_name = null;
        print create_html_form($form_name, $payment_url, $data, '_blank');
        print('<div style="text-align:center;padding:2em;">'
                . '<button type="button" class="btn btn-primary" onclick="document.' . $form_name . '.submit();">'
                . __('Continue to payment') . '</button></div>');
    }

    protected function GB_module(Utils_GenericBrowser $gb, array $items, $row_additional_actions_callback, $column_widths = array(), $column_labels = array()) {
        return $this->GB_generic($gb, $items, $this->banned_columns_module, $this->GB_row_data_transform_module(...), $row_additional_actions_callback, $column_widths, $column_labels);
    }

    protected function GB_order(Utils_GenericBrowser $gb, array $items, $row_additional_actions_callback = null) {
        return $this->GB_generic($gb, $items, $this->banned_columns_order, $this->GB_row_data_transform_order(...), $row_additional_actions_callback);
    }

    protected function GB_module_licenses(Utils_GenericBrowser $gb, array $items, $row_additional_actions_callback) {
        return $this->GB_generic($gb, $items, array('installation_id', 'id'), $this->GB_row_data_transform_module_licenses(...), $row_additional_actions_callback);
    }

    protected function GB_row_data_transform_order(array $data) {
        static $module_licenses = null;
        if ($module_licenses === null) {
            $module_licenses = Base_EssClientCommon::server()->module_licenses_list();
        }
        // change module ids to names
        foreach ($data['modules'] as & $m) { // $m is module_license
            $mod_id = isset($module_licenses[$m]) ? $module_licenses[$m]['module'] : null;
            $m = __('[license not found]');
            if($mod_id !== null) {
                $mi = Base_EpesiStoreCommon::get_module_info($mod_id);
                $m = $mi['name'];
            }
        }
        $data['modules'] = implode(', ', $data['modules']);
        // handle prices
        $total = array();
        $to_pay = array();
        foreach ($data['price'] as $curr_code => $amount) {
            $total[] = $amount['display_total'];
            if ($amount['to_pay']) {
                $href = $this->href_navigate('form_payment_frame', $data['id'], $amount['to_pay'], $curr_code, $data['modules']);
                $pay_button = "<button $href>Pay {$amount['display_to_pay']}</button>";
                $to_pay[] = $pay_button;
            } else {
                $to_pay[] = __('Paid');
            }
        }
        unset($data['price']);
        $data['total_price'] = implode('<br/>', $total);
        $data['to_pay'] = implode('<br/>', $to_pay);
        return $data;
    }

    protected function GB_row_data_transform_module(array $data) {
        if (isset($data['active']))
            unset($data['active']);
        if (isset($data['icon_url']) && $data['icon_url'])
            // Icon first, then the name, inline - the old float:right put the
            // icon at the cell's right edge instead of leading the name.
            $data['name'] = "<img style=\"max-height: 30px; vertical-align: middle; margin-right: 6px;\" src=\"{$data['icon_url']}\" alt=\"{$data['name']} icon\"/>" . $data['name'];
        unset($data['icon_url']);

        // "Readme..." button leading the description text, not the
        // description itself as a link - full-size (per request), not
        // shrunk to match the Store tab's own smaller card button.
        if (isset($data['description_url']) && $data['description_url'])
            $data['description'] = '<a class="btn btn-sm btn-primary rounded-pill px-3 me-2" target="_blank" href="'
                    . $data['description_url'] . '">' . __('Readme...') . '</a>' . $data['description'];
        unset($data['description_url']);
        // Requires/Required by note (computed for the whole item list by
        // compute_dependency_notes() before this row ever reaches
        // GB_generic(), keyed by id in $this->dependency_notes since this
        // one row alone doesn't know about its siblings) - folded into
        // Description rather than kept as its own column, for clarity on
        // why a bundled dependency (e.g. List Manager) shows up alongside
        // the module that needs it.
        if (isset($data['id']) && !empty($this->dependency_notes[$data['id']]))
            $data['description'] = ($data['description'] !== '' ? $data['description'] . '<br>' : '')
                    . '<small class="text-muted">' . htmlspecialchars($this->dependency_notes[$data['id']]) . '</small>';
        // overflow_box=>false: wraps the cell in place (white-space:normal)
        // instead of Utils_GenericBrowser's default truncate-with-hover-
        // preview, which read as "description is too long" on this column -
        // see AI-private/ESS-checkout.md.
        $data['description'] = array('value' => $data['description'], 'overflow_box' => false);

        // Right-aligned (inline style, not the .epesi-store-grid CSS's own
        // :last-child rule - that one covers the header th, but a data cell
        // value here is the one place PHP can guarantee the style lands
        // regardless of any CSS specificity/load-order surprise) with the
        // decimal point itself aligned across rows: splitting into a
        // right-aligned "whole" span (min-width so shorter numbers, e.g.
        // $49, still end at the same x as $199) and a left-aligned
        // ".00"-and-beyond span is the standard trick - plain
        // text-align:right alone only lines up the string's right edge,
        // which for "$49.00" vs "$199.00" leaves the decimals themselves
        // offset by the extra digit.
        if (isset($data['price']))
            $data['price'] = array(
                'value' => self::align_price_decimal($data['price']),
                'style' => 'text-align:right;font-variant-numeric:tabular-nums;',
            );

        // "Item" reads better than "Name" as a column header here - renamed
        // in place, and the whole row reordered into a fixed column order
        // (item, description, version, price, then anything left over) -
        // GB_generic() derives both the header row (from the first item)
        // and each data row's own cell order from this same array's key
        // order, and different items don't necessarily carry the exact same
        // set of optional fields (e.g. description_url present on some,
        // absent on others) - left to natural key order, that silently
        // shifted a row's cells out of alignment with the header AND with
        // other rows (reported as "Price"/"Version" values landing in each
        // other's columns depending on the item).
        $data = array_combine(
            array_map(fn($k) => $k === 'name' ? 'item' : $k, array_keys($data)),
            array_values($data)
        );
        $ordered = array();
        foreach (array('item', 'description', 'version', 'price') as $k) {
            if (array_key_exists($k, $data)) {
                $ordered[$k] = $data[$k];
                unset($data[$k]);
            }
        }
        return $ordered + $data;
    }

    private function module_info_tooltip($module_id) {
        $mi = Base_EpesiStoreCommon::get_module_info($module_id);
        $tooltip = Utils_TooltipCommon::ajax_open_tag_attrs(array('Base_EpesiStoreCommon', 'module_format_info'), array($mi));
        return "<a $tooltip>{$mi['name']}</a>";
    }

    private function _module_is_active($module_id) {
        $mi = Base_EpesiStoreCommon::get_module_info($module_id);
        return $mi['active'];
    }

    protected function GB_row_data_transform_module_licenses(array $data) {
        // module name
        if (isset($data['module']))
            $data['module'] = $this->module_info_tooltip($data['module']);
        // paid
        if (isset($data['paid'])) {
            if (!$data['paid']) {
                $this->navigation_button_orders();
            }
            $data['paid'] = $data['paid'] ? __('Yes') : __('No - Go to orders to pay');
        }
        // active
        if (isset($data['active'])) {
            $text = $data['active'] ? __('Yes') : __('No');
            $tip = $data['active'] ? __('You can download newer version if it\'s available') : __('You cannot download newer version');
            $data['active'] = "<a " . Utils_TooltipCommon::open_tag_attrs($tip) . ">$text</a>";
        }
        return $data;
    }

    protected function GB_row_additional_actions_store($row, $data) {
        $row->add_action($this->create_callback_href($this->cart_add_item(...), array($data)), '+', __('Add to cart'));
    }

    protected function GB_row_additional_actions_cart($row, $data) {
        // Removing a module something else in the cart still requires
        // (e.g. List Manager while E-mail Campaign Manager is also in the
        // cart) used to silently leave the dependant behind with a missing
        // dependency - disabled instead (dimmed icon, off=true, no removal
        // href) until the dependant itself is removed first. Requires
        // $this->required_by, populated by compute_dependency_notes() for
        // the whole cart before this callback runs per-row.
        if (isset($data['id']) && !empty($this->required_by[$data['id']])) {
            $message = __('Required by %s - remove that module from the cart first.', array(implode(', ', $this->required_by[$data['id']])));
            $row->add_action(Utils_TooltipCommon::open_tag_attrs($message, false), __('Remove from cart'), $message, 'delete', 0, true);
            return;
        }
        $row->add_action($this->create_callback_href($this->cart_remove_item(...), array($data)), 'delete', __('Remove from cart'));
    }

    protected function GB_row_additional_actions_your_modules($row, $data) {
        if ($data['paid'] && $data['active'] && $this->_module_is_active($data['module'])
                && $this->_module_license_needs_download_or_update($data))
            $row->add_action($this->create_callback_href($this->download_queue_item(...), array($data)), '+', __('Queue download'));
    }

    protected function GB_row_additional_actions_downloads($row, $data) {
        $row->add_action($this->create_callback_href($this->download_dequeue_item(...), array($data)), 'delete');
        $row->add_action($this->create_callback_href($this->download_as_zip(...), array($data)), 'append data', 'Download as zip file');
    }

    protected function GB_generic(Utils_GenericBrowser $gb, array $items, $banned_columns = array(), $row_data_transform_callback = null, $row_additional_actions_callback = null, $column_widths = array(), $column_labels = array()) {
        if (count($items)) {
            // add column headers
            $first_el = reset($items);
            if ($row_data_transform_callback != null && is_callable($row_data_transform_callback))
                $first_el = call_user_func($row_data_transform_callback, $first_el);
            $columns = array();
            foreach ($first_el as $k => $v) {
                if (in_array($k, $banned_columns))
                    continue;
                $col = array('name' => isset($column_labels[$k]) ? __($column_labels[$k]) : ucwords(str_replace('_', ' ', $k)));
                if (isset($column_widths[$k])) $col['width'] = $column_widths[$k];
                $columns[] = $col;
            }
            $gb->set_table_columns($columns);
            // add elements
            foreach ($items as $r) {
                $v = array();
                $r_modified = $r;
                if ($row_data_transform_callback != null && is_callable($row_data_transform_callback))
                    $r_modified = call_user_func($row_data_transform_callback, $r);
                foreach ($r_modified as $k => $x) {
                    if (in_array($k, $banned_columns))
                        continue;
                    $v[] = $x;
                }
                /* @var $row Utils_GenericBrowser_RowObject */
                $row = $gb->get_new_row();
                $row->add_data_array($v);
                if ($row_additional_actions_callback != null && is_callable($row_additional_actions_callback))
                    call_user_func($row_additional_actions_callback, $row, $r);
            }
        }
        return $gb;
    }

    private function href_navigate($func) {
        $args = func_get_args();
        $func = array_shift($args);
        if (!$func)
            throw new ErrorException("Function to navigate not defined.");
        return $this->create_callback_href($this->navigate(...), array($func, $args));
    }

    public function navigate($func, $params = array()) {
        return Base_BoxCommon::push_module($this->get_type(), $func, $params);
    }

    public function pop_main($i = 1) {
        $x = ModuleManager::get_instance('/Base_Box|0');
        if (!$x)
            trigger_error('There is no base box module instance', E_USER_ERROR);
        $x->pop_main($i);
    }

    public function back_button($i = 1) {
        $x = 0;
        while ($this->is_back())
            $x++;
        if ($x > 0)
            return $this->pop_main($x);
        Base_ActionBarCommon::add('back', __('Back'), $this->create_back_href($i));
    }

}

?>