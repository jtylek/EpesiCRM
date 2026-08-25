/**
 * Created by ajb on 16.06.15.
 */

function Utils_QueryBuilder(form_name, form_element_id, builder_id, options_json, rules_json, error_msg) {
    var form_element_selector = '#' + form_element_id;
    var form_element_selector_last = '#' + form_element_id + '_last_valid';
    var builder_selector = '#' + builder_id;
    var events_str = "afterAddGroup.queryBuilder afterDeleteGroup.queryBuilder afterAddRule.queryBuilder afterDeleteRule.queryBuilder afterUpdateRuleValue.queryBuilder afterUpdateRuleFilter.queryBuilder afterUpdateRuleOperator.queryBuilder afterUpdateGroupCondition.queryBuilder afterReset.queryBuilder";
    if (jq(builder_selector).length == 0) return;
    jq(builder_selector).queryBuilder('destroy');
    jq(builder_selector).queryBuilder(options_json).on(events_str, function (event, rule, error, value) {
        var a = JSON.stringify(jq(builder_selector).queryBuilder("getRules"));
        jq(form_element_selector).val(a);
        if (a != '{}') jq(form_element_selector_last).val(JSON.stringify(jq(builder_selector).queryBuilder("getRules")));
    });
    if (jq(form_element_selector_last).val()) {
        jq(builder_selector).queryBuilder('setRules', JSON.parse(jq(form_element_selector_last).val()));
    } else {
        jq(builder_selector).queryBuilder('setRules', rules_json);
    }
}
