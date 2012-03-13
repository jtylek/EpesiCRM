<?php

define('CID', false);
define('PER_PAGE', 50);

require_once('../../../include.php');
ModuleManager::load_modules();

$user = isset($_GET['user']) ? $_GET['user'] : null;
$order_id = isset($_GET['order_id']) ? $_GET['order_id'] : null;
$value = isset($_GET['value']) ? $_GET['value'] : null;
$curr_code = isset($_GET['curr_code']) ? $_GET['curr_code'] : null;

if ($user === null || $order_id === null || $value === null || $curr_code === null) {
    print 'Not enough parameters';
    return;
}
$description = isset($_GET['description']) ? $_GET['description'] : null;
if (!$description)
    $description = "Order ID $order_id";
$description = htmlspecialchars($description);

Acl::set_user($user);

$payment_url = Base_EssClientCommon::get_payments_url();
$payment_url .= '?' . http_build_query(array('hide_page_banner' => '1'));
$credentials = Base_EpesiStoreCommon::get_payment_credentials();
foreach ($credentials as & $c)
    $c = htmlspecialchars($c);

echo '
<form action="' . $payment_url . '" method="post" name="formPayment">
    <input type="hidden" name="action_url" value="' . $payment_url . '" />
    <input type="hidden" name="first_name" value="' . $credentials['first_name'] . '" />
    <input type="hidden" name="last_name" value="' . $credentials['last_name'] . '" />
    <input type="hidden" name="address_1" value="' . $credentials['address_1'] . '" />
    <input type="hidden" name="address_2" value="' . $credentials['address_2'] . '" />
    <input type="hidden" name="city" value="' . $credentials['city'] . '" />
    <input type="hidden" name="postal_code" value="' . $credentials['postal_code'] . '" />
    <input type="hidden" name="country" value="' . $credentials['country'] . '" />
    <input type="hidden" name="email" value="' . $credentials['email'] . '" />
    <input type="hidden" name="phone" value="' . $credentials['phone'] . '" />
    <input type="hidden" name="record_id" value="' . htmlspecialchars($order_id) . '" />
    <input type="hidden" name="record_type" value="ess_orders" />
    <input type="hidden" name="amount" value="' . htmlspecialchars($value) . '" />
    <input type="hidden" name="currency" value="' . htmlspecialchars($curr_code) . '" />
    <input type="hidden" name="description" value="' . $description . '" />
    <input type="hidden" name="auto_process" value="1" />
</form>
';

print('<script type="text/javascript">document.formPayment.submit();</script>');
?>